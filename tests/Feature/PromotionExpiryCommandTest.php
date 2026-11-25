<?php

use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\User;
use App\Notifications\PromotionExpiredNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

function expiryPackage(): PromotionPackage
{
    return PromotionPackage::query()->first() ?? PromotionPackage::factory()->create();
}

function expiryPromotion(User $seller, Product $product, string $endsAt): ProductPromotion
{
    return ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->for(expiryPackage(), 'package')
        ->active()
        ->create([
            'starts_at' => now()->subMinute(),
            'ends_at' => Carbon::parse($endsAt),
        ]);
}

function expiryProduct(User $seller, string $title): Product
{
    return Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => $title,
        'price_minor' => 10000,
    ]);
}

test('promotions expire at the exact boundary and display eligibility ends before the database update', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $due = expiryPromotion($seller, expiryProduct($seller, 'Due Promotion'), '2026-11-25 12:00:00');
    $future = expiryPromotion($seller, expiryProduct($seller, 'Future Promotion'), '2026-11-25 12:00:01');

    Notification::fake();

    expect(ProductPromotion::query()->displayable()->whereKey($due->id)->exists())->toBeFalse()
        ->and(ProductPromotion::query()->currentlyActive()->whereKey($due->id)->exists())->toBeFalse()
        ->and(ProductPromotion::query()->displayable()->whereKey($future->id)->exists())->toBeTrue();

    $this->artisan('promotions:expire')->assertSuccessful();

    expect($due->fresh()->status)->toBe(ProductPromotion::STATUS_EXPIRED)
        ->and($due->fresh()->open_product_id)->toBeNull()
        ->and($future->fresh()->status)->toBe(ProductPromotion::STATUS_ACTIVE);

    Notification::assertSentToTimes($seller, PromotionExpiredNotification::class, 1);
});

test('expiring promotions is idempotent and does not notify twice', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $promotion = expiryPromotion($seller, expiryProduct($seller, 'Idempotent Promotion'), '2026-11-25 11:59:59');

    Notification::fake();

    $this->artisan('promotions:expire')->assertSuccessful();
    $this->artisan('promotions:expire')->assertSuccessful();

    expect($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_EXPIRED)
        ->and($promotion->fresh()->remaining_seconds)->toBeNull();

    Notification::assertSentToTimes($seller, PromotionExpiredNotification::class, 1);
});

test('the expiry command processes all due promotions in bounded chunks', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create();
    $first = expiryPromotion($seller, expiryProduct($seller, 'Chunk One'), '2026-11-25 11:59:58');
    $second = expiryPromotion($seller, expiryProduct($seller, 'Chunk Two'), '2026-11-25 11:59:57');

    Notification::fake();

    $this->artisan('promotions:expire', ['--chunk' => 1])->assertSuccessful();

    expect($first->fresh()->status)->toBe(ProductPromotion::STATUS_EXPIRED)
        ->and($second->fresh()->status)->toBe(ProductPromotion::STATUS_EXPIRED);

    Notification::assertSentToTimes($seller, PromotionExpiredNotification::class, 2);
});

test('the expiry command is scheduled every minute with overlap and single server protection', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (object $scheduledEvent): bool => str_contains($scheduledEvent->command, 'promotions:expire'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');

    $withoutOverlapping = new ReflectionProperty($event, 'withoutOverlapping');
    $onOneServer = new ReflectionProperty($event, 'onOneServer');

    expect($withoutOverlapping->getValue($event))->toBeTrue()
        ->and($onOneServer->getValue($event))->toBeTrue();
});
