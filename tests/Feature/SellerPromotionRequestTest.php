<?php

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

const PROMOTION_TEST_TINY_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDIzM//AABEIAAEAAQMBIgACEQEDEQH/xAAfAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgv/xAC1EAACAQMDAgQDBQUEBAAAAX0BAgMABBEFEiExQQYTUWEHInEUMoGRoQgjQrHBFVLR8CRjZLKCkpsNLXLy8vExg9Nzc4OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/aAAwDAQACEQMRAD8A/v4ooooA/9k=';

beforeEach(function () {
    Storage::fake('payment_proofs');
});

function promotionTestProof(string $name = 'proof.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, (string) base64_decode(PROMOTION_TEST_TINY_JPEG, true));
}

function promotionTestApprovedProduct(User $seller): Product
{
    return Product::factory()->approved()->for($seller, 'seller')->create();
}

function promotionTestPackage(array $attributes = []): PromotionPackage
{
    return PromotionPackage::factory()->basic()->create(array_merge([
        'price_minor' => 1500,
        'duration_days' => 3,
        'name' => 'Basic Promotion',
    ], $attributes));
}

function promotionTestPayload(PromotionPackage $package, array $overrides = []): array
{
    return array_merge([
        'package_id' => $package->id,
        'method' => PromotionPaymentSubmission::METHOD_TELEBIRR,
        'transaction_reference' => 'TXN-12345',
        'transaction_details' => 'Paid with Telebirr on 25 November 2026.',
        'proof' => promotionTestProof(),
    ], $overrides);
}

test('guests are redirected away from promotion pages', function () {
    $product = promotionTestApprovedProduct(User::factory()->create());

    $this->get(route('seller.promotions.create', $product))
        ->assertRedirect(route('login'));

    $this->get(route('seller.promotions.index'))
        ->assertRedirect(route('login'));
});

test('a seller can submit payment proof for an approved listing', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $package = promotionTestPackage();

    $this->actingAs($seller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload($package))
        ->assertSessionHas('status', __('Payment proof submitted for review.'));

    $promotion = ProductPromotion::query()->where('product_id', $product->id)->firstOrFail();
    $submission = $promotion->paymentSubmissions()->firstOrFail();

    expect($promotion->seller_id)->toBe($seller->id)
        ->and($promotion->package_id)->toBe($package->id)
        ->and($promotion->funding_type)->toBe(ProductPromotion::FUNDING_PAYMENT)
        ->and($promotion->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING)
        ->and($promotion->open_product_id)->toBe($product->id)
        ->and($promotion->package_code_snapshot)->toBe(PromotionPackage::CODE_BASIC)
        ->and($promotion->price_minor_snapshot)->toBe(1500)
        ->and($promotion->duration_days_snapshot)->toBe(3)
        ->and($promotion->priority_snapshot)->toBe(PromotionPackage::PRIORITY_BASIC)
        ->and($promotion->starts_at)->toBeNull()
        ->and($promotion->ends_at)->toBeNull();

    expect($submission->promotion_id)->toBe($promotion->id)
        ->and($submission->attempt)->toBe(1)
        ->and($submission->method)->toBe(PromotionPaymentSubmission::METHOD_TELEBIRR)
        ->and($submission->transaction_reference)->toBe('TXN-12345')
        ->and($submission->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING)
        ->and($submission->pending_promotion_id)->toBe($promotion->id)
        ->and($submission->submitted_at)->not->toBeNull()
        ->and($submission->proof_checksum)->toBe(hash('sha256', Storage::disk('payment_proofs')->get($submission->proof_path)))
        ->and(str_starts_with($submission->proof_path, 'promotion-payment-proofs/'.$seller->id.'/'.$promotion->id.'/attempt-1/'))->toBeTrue();

    Storage::disk('payment_proofs')->assertExists($submission->proof_path);

    $this->actingAs($seller)
        ->get(route('seller.promotions.show', $promotion))
        ->assertOk()
        ->assertSee('Payment history')
        ->assertSee('Attempt 1');
});

test('payment proof is validated for package method and file type', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $package = promotionTestPackage();
    $inactivePackage = promotionTestPackage(['code' => PromotionPackage::CODE_STANDARD, 'is_active' => false]);

    $this->actingAs($seller)->post(
        route('seller.promotions.store', $product),
        promotionTestPayload($package, ['package_id' => $inactivePackage->id])
    )->assertSessionHasErrors('package_id');

    $this->actingAs($seller)->post(
        route('seller.promotions.store', $product),
        promotionTestPayload($package, ['method' => 'cash'])
    )->assertSessionHasErrors('method');

    $this->actingAs($seller)->post(
        route('seller.promotions.store', $product),
        promotionTestPayload($package, ['proof' => UploadedFile::fake()->createWithContent('receipt.txt', 'not a receipt')])
    )->assertSessionHasErrors('proof');

    $this->actingAs($seller)->post(
        route('seller.promotions.store', $product),
        promotionTestPayload($package, ['proof' => UploadedFile::fake()->create('receipt.jpg', 5121, 'image/jpeg')])
    )->assertSessionHasErrors('proof');

    expect(ProductPromotion::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('valid payment proof content with a disallowed extension is rejected', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $package = promotionTestPackage();

    $this->actingAs($seller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload($package, [
            'proof' => promotionTestProof('receipt.txt'),
        ]))
        ->assertSessionHasErrors([
            'proof' => __('Payment proof must be a JPEG, PNG, WebP, or PDF file.'),
        ]);

    expect(ProductPromotion::query()->where('product_id', $product->id)->exists())->toBeFalse();
});

test('uploaded proof is removed when the database transaction fails', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $package = promotionTestPackage();

    PromotionPaymentSubmission::creating(function (PromotionPaymentSubmission $submission): void {
        throw new RuntimeException('Forced payment submission failure.');
    });

    $this->actingAs($seller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload($package))
        ->assertInternalServerError();

    expect(ProductPromotion::query()->where('product_id', $product->id)->exists())->toBeFalse()
        ->and(PromotionPaymentSubmission::query()->count())->toBe(0)
        ->and(Storage::disk('payment_proofs')->allFiles())->toBe([]);
});

test('a seller cannot promote another seller listing or read another seller promotion', function () {
    $owner = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = promotionTestApprovedProduct($owner);
    $promotion = ProductPromotion::factory()
        ->paymentPending()
        ->for($owner, 'seller')
        ->for($product)
        ->create();

    $this->actingAs($otherSeller)
        ->get(route('seller.promotions.create', $product))
        ->assertNotFound();

    $this->actingAs($otherSeller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload($promotion->package))
        ->assertNotFound();

    $this->actingAs($otherSeller)
        ->get(route('seller.promotions.show', $promotion))
        ->assertNotFound();
});

test('a pending listing cannot be promoted', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create();

    $this->actingAs($seller)
        ->get(route('seller.promotions.create', $product))
        ->assertNotFound();

    $this->actingAs($seller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload(promotionTestPackage()))
        ->assertNotFound();
});

test('an approved listing with an open promotion cannot be submitted again', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);

    $promotion = ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    $this->actingAs($seller)
        ->get(route('seller.promotions.create', $product))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('error', __('This listing already has an open promotion request.'));

    $this->actingAs($seller)
        ->post(route('seller.promotions.store', $product), promotionTestPayload($promotion->package))
        ->assertSessionHasErrors('product');

    expect(ProductPromotion::query()->where('product_id', $product->id)->count())->toBe(1);
});

test('my listings only offers promotion for approved listings without an open promotion', function () {
    $seller = User::factory()->create();
    $promotable = promotionTestApprovedProduct($seller);
    $promoted = promotionTestApprovedProduct($seller);
    $pending = Product::factory()->for($seller, 'seller')->create();

    ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($promoted)
        ->create();

    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertSee(route('seller.promotions.create', $promotable))
        ->assertDontSee(route('seller.promotions.create', $promoted))
        ->assertDontSee(route('seller.promotions.create', $pending));
});

test('a rejected payment is resubmitted on the same promotion with a new attempt', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $promotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    $oldPath = sprintf(
        'promotion-payment-proofs/%d/%d/attempt-1/%s.jpg',
        $seller->id,
        $promotion->id,
        (string) Str::uuid()
    );
    Storage::disk('payment_proofs')->put($oldPath, 'old proof');

    $rejectedSubmission = PromotionPaymentSubmission::factory()
        ->telebirr()
        ->rejected()
        ->for($promotion, 'promotion')
        ->create([
            'attempt' => 1,
            'proof_path' => $oldPath,
            'proof_checksum' => hash('sha256', 'old proof'),
            'transaction_reference' => 'OLD-123',
            'transaction_details' => 'Old payment details.',
            'rejection_reason' => 'The receipt was unreadable.',
        ]);

    $originalSnapshots = [
        $promotion->package_code_snapshot,
        $promotion->price_minor_snapshot,
        $promotion->duration_days_snapshot,
        $promotion->priority_snapshot,
    ];

    $this->actingAs($seller)
        ->post(route('seller.promotions.resubmit.store', $promotion), [
            'method' => PromotionPaymentSubmission::METHOD_BANK_TRANSFER,
            'transaction_reference' => 'NEW-456',
            'transaction_details' => 'Corrected bank transfer details.',
            'proof' => promotionTestProof('corrected-proof.jpg'),
        ])
        ->assertRedirect(route('seller.promotions.show', $promotion))
        ->assertSessionHas('status', __('Corrected payment proof submitted for review.'));

    $promotion->refresh();
    $rejectedSubmission->refresh();
    $newSubmission = PromotionPaymentSubmission::query()
        ->forPromotion((int) $promotion->id)
        ->where('attempt', 2)
        ->firstOrFail();

    expect(PromotionPaymentSubmission::query()->forPromotion((int) $promotion->id)->count())->toBe(2)
        ->and($promotion->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING)
        ->and($promotion->open_product_id)->toBe($product->id)
        ->and([
            $promotion->package_code_snapshot,
            $promotion->price_minor_snapshot,
            $promotion->duration_days_snapshot,
            $promotion->priority_snapshot,
        ])->toBe($originalSnapshots)
        ->and($rejectedSubmission->status)->toBe(PromotionPaymentSubmission::STATUS_REJECTED)
        ->and($rejectedSubmission->rejection_reason)->toBe('The receipt was unreadable.')
        ->and($rejectedSubmission->proof_path)->toBe($oldPath)
        ->and($newSubmission->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING)
        ->and($newSubmission->pending_promotion_id)->toBe($promotion->id)
        ->and($newSubmission->method)->toBe(PromotionPaymentSubmission::METHOD_BANK_TRANSFER)
        ->and($newSubmission->proof_checksum)->toBe(hash('sha256', Storage::disk('payment_proofs')->get($newSubmission->proof_path)))
        ->and(str_starts_with($newSubmission->proof_path, 'promotion-payment-proofs/'.$seller->id.'/'.$promotion->id.'/attempt-2/'))->toBeTrue();

    Storage::disk('payment_proofs')->assertExists($oldPath);
    Storage::disk('payment_proofs')->assertExists($newSubmission->proof_path);
});

test('a pending payment cannot be resubmitted', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $promotion = ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    PromotionPaymentSubmission::factory()->for($promotion, 'promotion')->create(['attempt' => 1]);

    $this->actingAs($seller)
        ->get(route('seller.promotions.resubmit', $promotion))
        ->assertRedirect(route('seller.promotions.show', $promotion))
        ->assertSessionHas('error', __('This payment submission can no longer be resubmitted.'));

    $this->actingAs($seller)
        ->post(route('seller.promotions.resubmit.store', $promotion), [
            'method' => PromotionPaymentSubmission::METHOD_TELEBIRR,
            'transaction_details' => 'Should not be accepted.',
            'proof' => promotionTestProof(),
        ])
        ->assertNotFound();
});

test('a rejected payment cannot be resubmitted after the listing leaves approved status', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $promotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    PromotionPaymentSubmission::factory()
        ->rejected()
        ->for($promotion, 'promotion')
        ->create(['attempt' => 1]);

    $product->update(['status' => Product::STATUS_PENDING]);

    $this->actingAs($seller)
        ->get(route('seller.promotions.show', $promotion))
        ->assertOk()
        ->assertDontSee(route('seller.promotions.resubmit', $promotion));

    $this->actingAs($seller)
        ->get(route('seller.promotions.resubmit', $promotion))
        ->assertRedirect(route('seller.promotions.show', $promotion))
        ->assertSessionHas('error', __('This payment submission can no longer be resubmitted.'));

    $this->actingAs($seller)
        ->post(route('seller.promotions.resubmit.store', $promotion), [
            'method' => PromotionPaymentSubmission::METHOD_TELEBIRR,
            'transaction_details' => 'Should not be accepted.',
            'proof' => promotionTestProof(),
        ])
        ->assertNotFound();
});

test('an eligible seller sees the promotion submission form', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    promotionTestPackage(['name' => 'Basic Promotion']);

    $this->actingAs($seller)
        ->get(route('seller.promotions.create', $product))
        ->assertOk()
        ->assertSee('Promotion package')
        ->assertSee('Basic Promotion')
        ->assertSee('Submit payment proof');
});

test('a seller sees the resubmission form with the reviewer note', function () {
    $seller = User::factory()->create();
    $product = promotionTestApprovedProduct($seller);
    $promotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    PromotionPaymentSubmission::factory()
        ->rejected()
        ->for($promotion, 'promotion')
        ->create([
            'attempt' => 1,
            'rejection_reason' => 'The receipt was unreadable.',
            'transaction_details' => 'Original payment details.',
        ]);

    $this->actingAs($seller)
        ->get(route('seller.promotions.resubmit', $promotion))
        ->assertOk()
        ->assertSee('The receipt was unreadable.')
        ->assertSee('Unchanged package')
        ->assertSee('Original payment details.');
});
