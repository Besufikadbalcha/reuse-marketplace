<?php

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use App\Notifications\PromotionPaymentApprovedNotification;
use App\Notifications\PromotionPaymentRejectedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('payment_proofs');
});

function adminPromotionTestPackage(): PromotionPackage
{
    return PromotionPackage::factory()->basic()->create([
        'name' => 'Basic Promotion',
        'price_minor' => 1500,
        'duration_days' => 3,
    ]);
}

function adminPromotionTestApprovedProduct(User $seller, string $title = 'Admin Review Listing'): Product
{
    return Product::factory()->approved()->for($seller, 'seller')->create(['title' => $title]);
}

function adminPromotionTestPendingPromotion(User $seller, Product $product, PromotionPackage $package): ProductPromotion
{
    return ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($product)
        ->create(['package_id' => $package->id]);
}

function adminPromotionTestProof(ProductPromotion $promotion, int $attempt, string $contents = 'payment proof contents'): string
{
    $path = sprintf(
        'promotion-payment-proofs/%d/%d/attempt-%d/%s.jpg',
        (int) $promotion->seller_id,
        (int) $promotion->id,
        $attempt,
        (string) Str::uuid(),
    );

    Storage::disk('payment_proofs')->put($path, $contents);

    return $path;
}

/**
 * @param  array<string, mixed>  $attributes
 * @return array{0: PromotionPaymentSubmission, 1: string}
 */
function adminPromotionTestSubmission(ProductPromotion $promotion, int $attempt, string $status = PromotionPaymentSubmission::STATUS_PENDING, array $attributes = []): array
{
    $contents = $attributes['contents'] ?? 'payment proof contents';
    unset($attributes['contents']);

    $path = adminPromotionTestProof($promotion, $attempt, $contents);
    $factory = PromotionPaymentSubmission::factory()->telebirr()->for($promotion, 'promotion');
    $factory = match ($status) {
        PromotionPaymentSubmission::STATUS_REJECTED => $factory->rejected(),
        PromotionPaymentSubmission::STATUS_APPROVED => $factory->approved(),
        PromotionPaymentSubmission::STATUS_SUPERSEDED => $factory->superseded(),
        default => $factory,
    };

    $submission = $factory->create(array_merge([
        'attempt' => $attempt,
        'proof_path' => $path,
        'proof_checksum' => hash('sha256', $contents),
        'transaction_reference' => 'ADMIN-'.$attempt,
        'transaction_details' => 'Payment details for attempt '.$attempt.'.',
    ], $attributes));

    return [$submission, $path];
}

/**
 * @return array{admin: User, seller: User, product: Product, package: PromotionPackage, promotion: ProductPromotion, submission: PromotionPaymentSubmission, path: string}
 */
function adminPromotionTestFixture(?User $admin = null, string $title = 'Admin Review Listing'): array
{
    $admin ??= User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = adminPromotionTestApprovedProduct($seller, $title);
    $package = adminPromotionTestPackage();
    $promotion = adminPromotionTestPendingPromotion($seller, $product, $package);
    [$submission, $path] = adminPromotionTestSubmission($promotion, 1);

    return compact('admin', 'seller', 'product', 'package', 'promotion', 'submission', 'path');
}

test('guests and non admins cannot reach admin promotion review routes', function () {
    ['seller' => $seller, 'promotion' => $promotion, 'submission' => $submission] = adminPromotionTestFixture();

    $routes = [
        ['get', route('admin.promotions.index')],
        ['get', route('admin.promotions.show', $promotion)],
        ['get', route('admin.promotions.proof', [$promotion, $submission])],
        ['post', route('admin.promotions.payments.approve', [$promotion, $submission])],
        ['post', route('admin.promotions.payments.reject', [$promotion, $submission])],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertRedirect(route('login'));
    }

    foreach ($routes as [$method, $url]) {
        $this->actingAs($seller)
            ->{$method}($url, ['rejection_reason' => 'A valid reviewer note.'])
            ->assertForbidden();
    }
});

test('the admin queue only lists pending payments with a proof submission', function () {
    ['admin' => $admin, 'seller' => $seller] = $fixture = adminPromotionTestFixture(null, 'Queued Admin Payment');

    $reviewedProduct = adminPromotionTestApprovedProduct($seller, 'Reviewed Admin Payment');
    $reviewedPromotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($reviewedProduct)
        ->create(['package_id' => $fixture['package']->id]);
    adminPromotionTestSubmission($reviewedPromotion, 1, PromotionPaymentSubmission::STATUS_REJECTED, [
        'rejection_reason' => 'The receipt was unreadable.',
    ]);

    $missingProofProduct = adminPromotionTestApprovedProduct($seller, 'Missing Proof Admin Payment');
    adminPromotionTestPendingPromotion($seller, $missingProofProduct, $fixture['package']);

    $this->actingAs($admin)
        ->get(route('admin.promotions.index'))
        ->assertOk()
        ->assertSee('Promotion payments')
        ->assertSee('Queued Admin Payment')
        ->assertSee($seller->name)
        ->assertDontSee('Reviewed Admin Payment')
        ->assertDontSee('Missing Proof Admin Payment')
        ->assertSee(route('admin.promotions.index'), false);
});

test('non admin navigation does not show the promotion payments link', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertDontSee('Promotion payments');
});

test('an admin sees every payment attempt with its reviewer note and proof links', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = adminPromotionTestApprovedProduct($seller, 'Detailed Admin Payment');
    $package = adminPromotionTestPackage();
    $promotion = adminPromotionTestPendingPromotion($seller, $product, $package);
    [$original, $originalPath] = adminPromotionTestSubmission($promotion, 1, PromotionPaymentSubmission::STATUS_SUPERSEDED);

    [$superseded, $supersededPath] = adminPromotionTestSubmission($promotion, 2, PromotionPaymentSubmission::STATUS_SUPERSEDED, [
        'transaction_reference' => 'SUPERSEDED-2',
    ]);
    [$rejected, $rejectedPath] = adminPromotionTestSubmission($promotion, 3, PromotionPaymentSubmission::STATUS_REJECTED, [
        'transaction_reference' => 'REJECTED-3',
        'rejection_reason' => 'The receipt was unreadable.',
    ]);
    [$pending, $pendingPath] = adminPromotionTestSubmission($promotion, 4, PromotionPaymentSubmission::STATUS_PENDING, [
        'transaction_reference' => 'PENDING-4',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.promotions.show', $promotion))
        ->assertOk()
        ->assertSee('Detailed Admin Payment')
        ->assertSee($seller->name)
        ->assertSee($package->name)
        ->assertSee('Payment history')
        ->assertSee('Attempt 1')
        ->assertSee('Attempt 2')
        ->assertSee('Attempt 3')
        ->assertSee('Attempt 4')
        ->assertSee('SUPERSEDED-2')
        ->assertSee('REJECTED-3')
        ->assertSee('PENDING-4')
        ->assertSee('The receipt was unreadable.')
        ->assertSee(route('admin.promotions.proof', [$promotion, $original]), false)
        ->assertSee(route('admin.promotions.proof', [$promotion, $superseded]), false)
        ->assertSee(route('admin.promotions.proof', [$promotion, $rejected]), false)
        ->assertSee(route('admin.promotions.proof', [$promotion, $pending]), false)
        ->assertSee(route('admin.promotions.payments.approve', [$promotion, $pending]), false)
        ->assertSee(route('admin.promotions.payments.reject', [$promotion, $pending]), false);

    Storage::disk('payment_proofs')->assertExists([$originalPath, $supersededPath, $rejectedPath, $pendingPath]);
});

test('a reviewed promotion has no decision actions', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = adminPromotionTestApprovedProduct($seller, 'Reviewed Admin Payment');
    $promotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($product)
        ->create(['package_id' => adminPromotionTestPackage()->id]);
    [$submission] = adminPromotionTestSubmission($promotion, 1, PromotionPaymentSubmission::STATUS_REJECTED, [
        'rejection_reason' => 'The receipt was unreadable.',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.promotions.show', $promotion))
        ->assertOk()
        ->assertSee('Payment rejected')
        ->assertSee('The receipt was unreadable.')
        ->assertSee('This payment has already been reviewed.')
        ->assertDontSee(route('admin.promotions.payments.approve', [$promotion, $submission]), false)
        ->assertDontSee(route('admin.promotions.payments.reject', [$promotion, $submission]), false);
});

test('an admin can download a payment proof as a private attachment', function () {
    ['admin' => $admin, 'promotion' => $promotion, 'submission' => $submission, 'path' => $path] = adminPromotionTestFixture();

    $response = $this->actingAs($admin)
        ->get(route('admin.promotions.proof', [$promotion, $submission]));

    $response->assertOk()
        ->assertDownload('payment-proof-attempt-1.jpg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->baseResponse->getFile()->getPathname())
        ->toBe(Storage::disk('payment_proofs')->path($path));
});

test('admin payment proof downloads reject mismatched tampered and missing files', function () {
    ['admin' => $admin, 'promotion' => $promotion, 'submission' => $submission, 'path' => $path] = adminPromotionTestFixture();

    $otherProduct = adminPromotionTestApprovedProduct($submission->promotion->seller, 'Other Admin Payment');
    $otherPromotion = adminPromotionTestPendingPromotion($submission->promotion->seller, $otherProduct, $promotion->package);

    $this->actingAs($admin)
        ->get(route('admin.promotions.proof', [$otherPromotion, $submission]))
        ->assertNotFound();

    $submission->update(['proof_checksum' => hash('sha256', 'tampered contents')]);

    $this->actingAs($admin)
        ->get(route('admin.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();

    $submission->update([
        'proof_checksum' => hash('sha256', 'payment proof contents'),
        'proof_path' => '../private-secret.jpg',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();

    $submission->update(['proof_path' => $path]);
    Storage::disk('payment_proofs')->delete($path);

    $this->actingAs($admin)
        ->get(route('admin.promotions.proof', [$promotion, $submission]))
        ->assertNotFound();
});

test('an admin can approve a payment and activate the promotion for exactly its snapshot duration', function () {
    ['admin' => $admin, 'seller' => $seller, 'product' => $product, 'promotion' => $promotion, 'submission' => $submission, 'path' => $path] = $fixture = adminPromotionTestFixture();

    $promotion->update([
        'paused_at' => now()->subDay(),
        'remaining_seconds' => 1234,
        'stopped_at' => now()->subHours(6),
    ]);

    $approvedAt = Carbon::parse('2026-11-25 12:00:00');
    Carbon::setTestNow($approvedAt);

    try {
        Notification::fake();

        $this->actingAs($admin)
            ->post(route('admin.promotions.payments.approve', [$promotion, $submission]))
            ->assertRedirect(route('admin.promotions.index'))
            ->assertSessionHas('status', __('Payment approved and promotion activated for :title.', ['title' => $product->title]))
            ->assertSessionHas('status_key', 'promotion_payment_approved');
    } finally {
        Carbon::setTestNow();
    }

    $promotion->refresh();
    $submission->refresh();

    $durationSeconds = $promotion->duration_days_snapshot * 86400;

    expect($promotion->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($promotion->starts_at->timestamp)->toBe($approvedAt->timestamp)
        ->and($promotion->ends_at->timestamp)->toBe($approvedAt->copy()->addSeconds($durationSeconds)->timestamp)
        ->and($promotion->paused_at)->toBeNull()
        ->and($promotion->remaining_seconds)->toBeNull()
        ->and($promotion->stopped_at)->toBeNull()
        ->and($promotion->open_product_id)->toBe($product->id)
        ->and($submission->status)->toBe(PromotionPaymentSubmission::STATUS_APPROVED)
        ->and($submission->pending_promotion_id)->toBeNull()
        ->and($submission->approved_promotion_id)->toBe($promotion->id)
        ->and($submission->rejection_reason)->toBeNull()
        ->and($submission->reviewed_by)->toBe($admin->id)
        ->and($submission->reviewed_at?->timestamp)->toBe($approvedAt->timestamp);

    Storage::disk('payment_proofs')->assertExists($path);

    Notification::assertSentTo($seller, PromotionPaymentApprovedNotification::class, function (PromotionPaymentApprovedNotification $notification) use ($product, $promotion, $submission): bool {
        return $notification->type === 'promotion_payment_approved'
            && $notification->translationKey === 'Promotion payment approved for :title.'
            && $notification->params['title'] === $product->title
            && $notification->params['attempt'] === $submission->attempt
            && $notification->url === route('seller.promotions.show', $promotion);
    });
});

test('an admin can reject a payment with a trimmed reason while preserving history', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = adminPromotionTestApprovedProduct($seller, 'Reject Admin Payment');
    $promotion = adminPromotionTestPendingPromotion($seller, $product, adminPromotionTestPackage());
    [$oldSubmission, $oldPath] = adminPromotionTestSubmission($promotion, 1, PromotionPaymentSubmission::STATUS_REJECTED, [
        'rejection_reason' => 'The receipt was unreadable.',
    ]);
    [$pending, $pendingPath] = adminPromotionTestSubmission($promotion, 2, PromotionPaymentSubmission::STATUS_PENDING, [
        'transaction_reference' => 'REJECT-ME',
    ]);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.reject', [$promotion, $pending]), [
            'rejection_reason' => '  The transaction reference does not match.  ',
        ])
        ->assertRedirect(route('admin.promotions.index'))
        ->assertSessionHas('status', __('Payment rejected for :title.', ['title' => $product->title]))
        ->assertSessionHas('status_key', 'promotion_payment_rejected');

    $promotion->refresh();
    $pending->refresh();
    $oldSubmission->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_PAYMENT_REJECTED)
        ->and($promotion->starts_at)->toBeNull()
        ->and($promotion->ends_at)->toBeNull()
        ->and($promotion->open_product_id)->toBe($product->id)
        ->and($pending->status)->toBe(PromotionPaymentSubmission::STATUS_REJECTED)
        ->and($pending->pending_promotion_id)->toBeNull()
        ->and($pending->approved_promotion_id)->toBeNull()
        ->and($pending->rejection_reason)->toBe('The transaction reference does not match.')
        ->and($pending->reviewed_by)->toBe($admin->id)
        ->and($pending->reviewed_at)->not->toBeNull()
        ->and($oldSubmission->status)->toBe(PromotionPaymentSubmission::STATUS_REJECTED)
        ->and($oldSubmission->rejection_reason)->toBe('The receipt was unreadable.');

    Storage::disk('payment_proofs')->assertExists([$oldPath, $pendingPath]);

    Notification::assertSentTo($seller, PromotionPaymentRejectedNotification::class, function (PromotionPaymentRejectedNotification $notification) use ($pending): bool {
        return $notification->type === 'promotion_payment_rejected'
            && $notification->translationKey === 'Promotion payment rejected for :title.'
            && $notification->params['reason'] === 'The transaction reference does not match.'
            && $notification->params['attempt'] === $pending->attempt;
    });
});

test('a rejection reason is required and limited to 1000 characters', function () {
    ['admin' => $admin, 'seller' => $seller, 'promotion' => $promotion, 'submission' => $submission] = adminPromotionTestFixture();

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.reject', [$promotion, $submission]), [
            'rejection_reason' => '   ',
        ])
        ->assertSessionHasErrors('rejection_reason');

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.reject', [$promotion, $submission]), [
            'rejection_reason' => str_repeat('a', 1001),
        ])
        ->assertSessionHasErrors('rejection_reason');

    expect($submission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING)
        ->and($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING);

    Notification::assertNotSentTo($seller, PromotionPaymentRejectedNotification::class);
});

test('reviewed payments cannot be approved or rejected again', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $package = adminPromotionTestPackage();

    $rejectedProduct = adminPromotionTestApprovedProduct($seller, 'Already Rejected Admin Payment');
    $rejectedPromotion = ProductPromotion::factory()
        ->paymentRejected()
        ->for($seller, 'seller')
        ->for($rejectedProduct)
        ->create(['package_id' => $package->id]);
    [$rejectedSubmission] = adminPromotionTestSubmission($rejectedPromotion, 1, PromotionPaymentSubmission::STATUS_REJECTED, [
        'rejection_reason' => 'The receipt was unreadable.',
    ]);

    $activeProduct = adminPromotionTestApprovedProduct($seller, 'Already Approved Admin Payment');
    $activePromotion = ProductPromotion::factory()
        ->active()
        ->for($seller, 'seller')
        ->for($activeProduct)
        ->create(['package_id' => $package->id]);
    [$approvedSubmission] = adminPromotionTestSubmission($activePromotion, 1, PromotionPaymentSubmission::STATUS_APPROVED);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.approve', [$rejectedPromotion, $rejectedSubmission]))
        ->assertSessionHas('error', __('Only pending payments can be approved.'));

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.reject', [$activePromotion, $approvedSubmission]), [
            'rejection_reason' => 'A valid reviewer note.',
        ])
        ->assertSessionHas('error', __('Only pending payments can be rejected.'));

    expect($rejectedPromotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAYMENT_REJECTED)
        ->and($rejectedSubmission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_REJECTED)
        ->and($activePromotion->fresh()->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($approvedSubmission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_APPROVED);

    Notification::assertNotSentTo($seller, PromotionPaymentApprovedNotification::class);
    Notification::assertNotSentTo($seller, PromotionPaymentRejectedNotification::class);
});

test('an unapproved listing blocks payment approval', function () {
    ['admin' => $admin, 'seller' => $seller, 'product' => $product, 'promotion' => $promotion, 'submission' => $submission] = adminPromotionTestFixture();

    $product->update(['status' => Product::STATUS_PENDING]);

    Notification::fake();

    $this->actingAs($admin)
        ->get(route('admin.promotions.show', $promotion))
        ->assertOk()
        ->assertSee('This listing is no longer approved. Payment approval is blocked until the listing is approved again.');

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.approve', [$promotion, $submission]))
        ->assertSessionHas('error', __('This promotion cannot be activated because its listing is no longer approved.'));

    expect($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING)
        ->and($submission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING);

    Notification::assertNotSentTo($seller, PromotionPaymentApprovedNotification::class);
});

test('a conflicting unique payment state does not notify the seller', function () {
    ['admin' => $admin, 'seller' => $seller, 'promotion' => $promotion, 'submission' => $submission] = adminPromotionTestFixture();

    adminPromotionTestSubmission($promotion, 2, PromotionPaymentSubmission::STATUS_APPROVED);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.approve', [$promotion, $submission]))
        ->assertSessionHas('error', __('This payment submission was updated elsewhere. Please review it and try again.'));

    expect($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING)
        ->and($submission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING);

    Notification::assertNotSentTo($seller, PromotionPaymentApprovedNotification::class);
});

test('the payment submission factory stores canonical proof paths', function () {
    $seller = User::factory()->create();
    $product = adminPromotionTestApprovedProduct($seller, 'Factory Proof Path');
    $promotion = adminPromotionTestPendingPromotion($seller, $product, adminPromotionTestPackage());

    $submission = PromotionPaymentSubmission::factory()
        ->superseded()
        ->for($promotion, 'promotion')
        ->create(['attempt' => 7]);

    expect(preg_match(
        sprintf(
            '/^promotion-payment-proofs\/%d\/%d\/attempt-7\/[0-9a-f-]{36}\.jpg$/',
            (int) $seller->id,
            (int) $promotion->id,
        ),
        (string) $submission->proof_path,
    ))->toBe(1);
});

test('admin promotion pages use the active locale', function () {
    ['admin' => $admin] = adminPromotionTestFixture();

    $this->actingAs($admin)
        ->withSession(['locale' => 'am'])
        ->get(route('admin.promotions.index'))
        ->assertOk()
        ->assertSee('የማስተዋወቂያ ክፍያዎች');
});
