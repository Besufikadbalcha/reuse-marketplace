<?php

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('payment_proofs');
});

function promotionProofFixture(): array
{
    $seller = User::factory()->create();
    $product = Product::factory()->approved()->for($seller, 'seller')->create();
    $promotion = ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($product)
        ->create();

    $path = sprintf(
        'promotion-payment-proofs/%d/%d/attempt-1/%s.jpg',
        $seller->id,
        $promotion->id,
        (string) Str::uuid()
    );
    Storage::disk('payment_proofs')->put($path, 'payment proof contents');

    $submission = PromotionPaymentSubmission::factory()
        ->telebirr()
        ->for($promotion, 'promotion')
        ->create([
            'attempt' => 1,
            'proof_path' => $path,
            'proof_checksum' => hash('sha256', 'payment proof contents'),
            'transaction_reference' => 'PROOF-123',
            'transaction_details' => 'Payment made with Telebirr.',
        ]);

    return compact('seller', 'product', 'promotion', 'submission', 'path');
}

test('guests are redirected away from payment proofs', function () {
    ['promotion' => $promotion, 'submission' => $submission] = promotionProofFixture();

    $this->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertRedirect(route('login'));
});

test('the owner can download a payment proof as a private attachment', function () {
    ['seller' => $seller, 'promotion' => $promotion, 'submission' => $submission, 'path' => $path] = promotionProofFixture();

    $response = $this->actingAs($seller)
        ->get(route('seller.promotions.proof', [$promotion, $submission]));

    $response->assertOk()
        ->assertDownload('payment-proof-attempt-1.jpg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->baseResponse->getFile()->getPathname())
        ->toBe(Storage::disk('payment_proofs')->path($path));
});

test('another seller cannot download a payment proof', function () {
    ['promotion' => $promotion, 'submission' => $submission] = promotionProofFixture();

    $this->actingAs(User::factory()->create())
        ->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});

test('a submission from another promotion cannot be downloaded', function () {
    ['seller' => $seller, 'promotion' => $promotion] = promotionProofFixture();
    $otherPromotion = ProductPromotion::factory()->paymentPending()->create([
        'package_id' => $promotion->package_id,
    ]);
    $submission = PromotionPaymentSubmission::factory()
        ->for($otherPromotion, 'promotion')
        ->create(['attempt' => 1]);

    $this->actingAs($seller)
        ->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});

test('a tampered payment proof is not served', function () {
    ['seller' => $seller, 'promotion' => $promotion, 'submission' => $submission] = promotionProofFixture();

    $submission->update(['proof_checksum' => hash('sha256', 'changed contents')]);

    $this->actingAs($seller)
        ->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});

test('an unexpected proof path is not served', function () {
    ['seller' => $seller, 'promotion' => $promotion, 'submission' => $submission] = promotionProofFixture();

    $submission->update(['proof_path' => '../private-secret.jpg']);

    $this->actingAs($seller)
        ->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});

test('a missing payment proof file is not served', function () {
    ['seller' => $seller, 'promotion' => $promotion, 'submission' => $submission, 'path' => $path] = promotionProofFixture();

    Storage::disk('payment_proofs')->delete($path);

    $this->actingAs($seller)
        ->get(route('seller.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});
