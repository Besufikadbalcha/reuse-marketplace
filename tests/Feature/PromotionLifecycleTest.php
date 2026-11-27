<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\User;
use App\Notifications\PromotionPausedNotification;
use App\Notifications\PromotionResumedNotification;
use App\Notifications\PromotionStoppedAfterOrderNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $attributes
 */
function lifecycleProduct(User $seller, array $attributes = []): Product
{
    return Product::factory()
        ->for($seller, 'seller')
        ->for(Category::factory()->create(), 'category')
        ->approved()
        ->create($attributes);
}

function lifecycleImage(Product $product): void
{
    $path = 'seller-products/'.$product->seller_id.'/photo.jpg';

    ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'lifecycle-test-image');
}

/**
 * @return array<string, mixed>
 */
function lifecycleUpdatePayload(Product $product, string $title): array
{
    return [
        'title' => $title,
        'description' => $product->description,
        'price' => number_format($product->price_minor / 100, 2, '.', ''),
        'category_id' => $product->category_id,
        'location_id' => $product->location_id ?? (int) Location::query()->firstOrCreate(['name' => 'Test Location'])->id,
        'condition' => $product->condition,
    ];
}

/**
 * @return array<string, mixed>
 */
function lifecycleCheckoutPayload(?string $idempotencyKey = null): array
{
    return [
        'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ];
}

function lifecyclePackage(): PromotionPackage
{
    return PromotionPackage::query()->first() ?? PromotionPackage::factory()->create();
}

function lifecycleActivePromotion(User $seller, Product $product, string $endsAt): ProductPromotion
{
    return ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->for(lifecyclePackage(), 'package')
        ->active()
        ->create([
            'starts_at' => now()->subMinute(),
            'ends_at' => Carbon::parse($endsAt),
        ]);
}

function lifecyclePausedPromotion(User $seller, Product $product, int $remainingSeconds): ProductPromotion
{
    return ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->for(lifecyclePackage(), 'package')
        ->paused()
        ->create([
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addSeconds($remainingSeconds),
            'paused_at' => now(),
            'remaining_seconds' => $remainingSeconds,
        ]);
}

function lifecycleStoppedPromotion(User $seller, Product $product, Order $order, int $remainingSeconds): ProductPromotion
{
    return ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->for(lifecyclePackage(), 'package')
        ->stoppedByOrder($order)
        ->create([
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addSeconds($remainingSeconds),
            'stopped_at' => now(),
            'remaining_seconds' => $remainingSeconds,
        ]);
}

test('seller edit pauses an active promotion and preserves exact remaining seconds', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Original Lamp', 'price_minor' => 10000]);
    lifecycleImage($product);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:02:00');

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), lifecycleUpdatePayload($product, 'Edited Lamp'))
        ->assertRedirect(route('seller.products.show', $product));

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_PAUSED)
        ->and($promotion->remaining_seconds)->toBe(120)
        ->and($promotion->paused_at->toDateTimeString())->toBe('2026-11-25 12:00:00')
        ->and($promotion->starts_at->toDateTimeString())->toBe('2026-11-25 11:59:00')
        ->and($promotion->ends_at->toDateTimeString())->toBe('2026-11-25 12:02:00')
        ->and($promotion->open_product_id)->toBe($product->id);

    Notification::assertSentToTimes($seller, PromotionPausedNotification::class, 1);
});

test('repeated seller edits while paused do not overwrite remaining seconds or notify twice', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Original Chair', 'price_minor' => 15000]);
    lifecycleImage($product);
    $promotion = lifecyclePausedPromotion($seller, $product, 45);
    $originalPausedAt = $promotion->paused_at->toDateTimeString();

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), lifecycleUpdatePayload($product, 'First Edit'))
        ->assertRedirect();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product->fresh()), lifecycleUpdatePayload($product->fresh(), 'Second Edit'))
        ->assertRedirect();

    $promotion->refresh();

    expect($promotion->remaining_seconds)->toBe(45)
        ->and($promotion->paused_at->toDateTimeString())->toBe($originalPausedAt)
        ->and($promotion->status)->toBe(ProductPromotion::STATUS_PAUSED);

    Notification::assertNotSentTo($seller, PromotionPausedNotification::class);
});

test('admin approval resumes a paused promotion with exact remaining seconds and rejects stale repeats', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->for(Category::factory()->create(), 'category')
        ->create(['title' => 'Pending Promotion Item', 'price_minor' => 10000]);
    lifecycleImage($product);
    $promotion = lifecyclePausedPromotion($seller, $product, 90);
    $originalStartsAt = $promotion->starts_at->toDateTimeString();

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect(route('admin.products.index'));

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($promotion->starts_at->toDateTimeString())->toBe($originalStartsAt)
        ->and($promotion->ends_at->toDateTimeString())->toBe('2026-11-25 12:01:30')
        ->and($promotion->paused_at)->toBeNull()
        ->and($promotion->remaining_seconds)->toBeNull()
        ->and($promotion->open_product_id)->toBe($product->id);

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertSessionHas('error');

    Notification::assertSentToTimes($seller, PromotionResumedNotification::class, 1);
});

test('admin rejection leaves a paused promotion paused', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->for(Category::factory()->create(), 'category')
        ->create(['title' => 'Rejected Promotion Item']);
    lifecycleImage($product);
    $promotion = lifecyclePausedPromotion($seller, $product, 90);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), ['rejection_reason' => 'Needs more detail.'])
        ->assertRedirect(route('admin.products.index'));

    $promotion->refresh();

    expect($product->fresh()->status)->toBe(Product::STATUS_REJECTED)
        ->and($promotion->status)->toBe(ProductPromotion::STATUS_PAUSED)
        ->and($promotion->remaining_seconds)->toBe(90)
        ->and($promotion->ends_at->toDateTimeString())->toBe('2026-11-25 12:01:30');

    Notification::assertNotSentTo($seller, PromotionResumedNotification::class);
});

test('checkout stops the active promotion and links it to the created order', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Sold Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), lifecycleCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();
    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($promotion->remaining_seconds)->toBe(300)
        ->and($promotion->stopped_at->toDateTimeString())->toBe('2026-11-25 12:00:00')
        ->and($promotion->stopped_by_order_id)->toBe($order->id)
        ->and($promotion->open_product_id)->toBeNull();

    Notification::assertSentToTimes($seller, PromotionStoppedAfterOrderNotification::class, 1);
});

test('replayed checkout does not stop or notify a promotion twice', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Replay Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');
    $payload = lifecycleCheckoutPayload((string) Str::uuid());

    Notification::fake();

    $this->actingAs($buyer)->post(route('checkout.store', $product), $payload)->assertRedirect();
    $this->actingAs($buyer)->post(route('checkout.store', $product), $payload)->assertRedirect();

    $promotion->refresh();

    expect(Order::count())->toBe(1)
        ->and($promotion->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($promotion->remaining_seconds)->toBe(300);

    Notification::assertSentToTimes($seller, PromotionStoppedAfterOrderNotification::class, 1);
});

test('cancelling the pending order restores the stopped promotion from exact remaining seconds', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Restored Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');
    $originalStartsAt = $promotion->starts_at->toDateTimeString();

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), lifecycleCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $this->travelTo('2026-11-25 12:01:00');

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order));

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($promotion->starts_at->toDateTimeString())->toBe($originalStartsAt)
        ->and($promotion->ends_at->toDateTimeString())->toBe('2026-11-25 12:06:00')
        ->and($promotion->stopped_at)->toBeNull()
        ->and($promotion->stopped_by_order_id)->toBeNull()
        ->and($promotion->remaining_seconds)->toBeNull()
        ->and($promotion->open_product_id)->toBe($product->id);

    Notification::assertSentToTimes($seller, PromotionResumedNotification::class, 1);
});

test('cancellation does not restore a promotion while the product remains unavailable', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Still Sold Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), lifecycleCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();
    $otherOrder = Order::factory()->for(User::factory(), 'buyer')->confirmed()->create();

    OrderItem::factory()->for($otherOrder)->for($product, 'product')->create([
        'product_title' => $product->title,
        'price_minor' => $product->price_minor,
    ]);

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect();

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($promotion->stopped_by_order_id)->toBe($order->id);

    Notification::assertNotSentTo($seller, PromotionResumedNotification::class);
});

test('cancellation does not restore a promotion while the product is archived', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Archived Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), lifecycleCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $product->update(['archived_at' => now()]);

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect();

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($promotion->stopped_by_order_id)->toBe($order->id);

    Notification::assertNotSentTo($seller, PromotionResumedNotification::class);
});

test('cancellation does not restore a promotion when another open promotion conflicts', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Conflict Lamp', 'price_minor' => 20000]);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:05:00');

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), lifecycleCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();
    $replacement = lifecycleActivePromotion($seller, $product, '2026-11-25 12:10:00');

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect();

    expect($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($replacement->fresh()->status)->toBe(ProductPromotion::STATUS_ACTIVE);

    Notification::assertNotSentTo($seller, PromotionResumedNotification::class);
});

test('a non pending order cannot restore its stopped promotion', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Confirmed Lamp', 'price_minor' => 20000]);
    $order = Order::factory()
        ->for($buyer, 'buyer')
        ->confirmed()
        ->create(['order_number' => 'RM-LOCKED01']);

    Product::query()->whereKey($product->id)->update(['reservation_token' => $order->order_number]);
    $promotion = lifecycleStoppedPromotion($seller, $product, $order, 300);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertConflict();

    $promotion->refresh();

    expect($promotion->status)->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($promotion->remaining_seconds)->toBe(300);
});

test('a failed promotion save rolls back the seller edit and pause', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $product = lifecycleProduct($seller, ['title' => 'Rollback Lamp', 'price_minor' => 10000]);
    lifecycleImage($product);
    $promotion = lifecycleActivePromotion($seller, $product, '2026-11-25 12:02:00');

    ProductPromotion::saving(function (): void {
        throw new RuntimeException('Forced promotion save failure.');
    });

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), lifecycleUpdatePayload($product, 'Should Roll Back'))
        ->assertStatus(500);

    expect($product->fresh()->title)->toBe('Rollback Lamp')
        ->and($product->fresh()->status)->toBe(Product::STATUS_APPROVED)
        ->and($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($promotion->fresh()->remaining_seconds)->toBeNull();
});

test('lifecycle notification messages are available in all supported locales', function (
    string $locale,
    string $paused,
    string $resumed,
    string $expired,
    string $stopped,
) {
    app()->setLocale($locale);

    expect(__('Promotion paused for :title.', ['title' => 'Old Lamp']))->toBe($paused)
        ->and(__('Promotion resumed for :title.', ['title' => 'Old Lamp']))->toBe($resumed)
        ->and(__('Promotion expired for :title.', ['title' => 'Old Lamp']))->toBe($expired)
        ->and(__('Promotion stopped after order :order for :title.', [
            'order' => 'RM-12345678',
            'title' => 'Old Lamp',
        ]))->toBe($stopped);
})->with([
    'English' => [
        'en',
        'Promotion paused for Old Lamp.',
        'Promotion resumed for Old Lamp.',
        'Promotion expired for Old Lamp.',
        'Promotion stopped after order RM-12345678 for Old Lamp.',
    ],
    'Amharic' => [
        'am',
        'ለOld Lamp ማስተዋወቂያው ለጊዜው ተቆም።',
        'ለOld Lamp ማስተዋወቂያው እንደገና ተጀመረ።',
        'ለOld Lamp የማስተዋወቂያ ጊዜ አልፏል።',
        'ለOld Lamp ማስተዋወቂያው ትዕዛዝ RM-12345678 ከተመዘገበ በኋላ ተቆም።',
    ],
    'Afaan Oromoo' => [
        'om',
        'Tamsa\'iin Old Lampf yeroof dhaabbate.',
        'Tamsa\'iin Old Lampf irra deebi\'ee hojjeettii uume.',
        'Yeroon tamsa\'ii Old Lampf darbee.',
        'Tamsa\'iin Old Lampf bittaa RM-12345678 booda dhaabbate.',
    ],
]);
