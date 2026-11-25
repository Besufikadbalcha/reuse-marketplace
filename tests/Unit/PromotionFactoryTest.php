<?php

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use Database\Factories\ProductPromotionFactory;
use Database\Factories\PromotionCreditFactory;
use Database\Factories\PromotionCreditUsageFactory;
use Database\Factories\PromotionPaymentSubmissionFactory;
use Database\Seeders\PromotionPackageSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\TestCase;

uses(TestCase::class);

test('promotion package prices are configured as positive minor-unit placeholders', function () {
    $prices = config('marketplace.promotion_package_prices_minor');

    expect($prices)->toBeArray()
        ->toHaveKeys(['basic', 'standard', 'premium']);

    foreach ($prices as $price) {
        expect($price)->toBeInt()->toBeGreaterThan(0);
    }
});

test('promotion package seeder rejects missing or invalid price configuration', function () {
    $seeder = new PromotionPackageSeeder;
    $resolvePriceMinor = new ReflectionMethod($seeder, 'resolvePriceMinor');

    config()->set('marketplace.promotion_package_prices_minor.basic', null);

    expect(fn () => $resolvePriceMinor->invoke($seeder, PromotionPackage::CODE_BASIC))
        ->toThrow(RuntimeException::class);
});

test('promotion package factory cycles through valid unique codes and matching constraints', function () {
    $packages = PromotionPackage::factory()->count(3)->make();

    expect($packages->pluck('code')->all())->toBe([
        PromotionPackage::CODE_BASIC,
        PromotionPackage::CODE_STANDARD,
        PromotionPackage::CODE_PREMIUM,
    ]);

    foreach ($packages as $package) {
        expect($package->priority)->toBe(PromotionPackage::priorityFor($package->code))
            ->and($package->duration_days)->toBeGreaterThan(0)
            ->and($package->price_minor)->toBeGreaterThan(0)
            ->and($package->sort_order)->toBeGreaterThan(0);
    }
});

test('promotion package factory states produce the expected package codes', function () {
    expect(PromotionPackage::factory()->basic()->make()->code)->toBe(PromotionPackage::CODE_BASIC)
        ->and(PromotionPackage::factory()->standard()->make()->code)->toBe(PromotionPackage::CODE_STANDARD)
        ->and(PromotionPackage::factory()->premium()->make()->code)->toBe(PromotionPackage::CODE_PREMIUM);
});

test('promotion credit factory defaults to an early sale owned by the credit seller', function () {
    $definition = (new PromotionCreditFactory)->definition();

    expect($definition)->toHaveKeys([
        'seller_id',
        'source_promotion_id',
        'source_order_item_id',
        'seconds_granted',
        'seconds_remaining',
    ])
        ->and($definition['source_promotion_id'])->toBeInstanceOf(Closure::class)
        ->and($definition['source_order_item_id'])->toBeInstanceOf(Closure::class)
        ->and($definition['seconds_granted'])->toBeGreaterThan(0)
        ->and($definition['seconds_remaining'])->toBe($definition['seconds_granted']);

    $promotionAttributes = $definition['source_promotion_id'](['seller_id' => 42])->withoutParents()->raw();
    $orderItemAttributes = $definition['source_order_item_id'](['seller_id' => 42])->withoutParents()->raw();

    expect($promotionAttributes['seller_id'])->toBe(42)
        ->and($promotionAttributes['status'])->toBe(ProductPromotion::STATUS_STOPPED_SOLD)
        ->and($orderItemAttributes['seller_status'])->toBe(OrderItem::SELLER_STATUS_COMPLETED);
});

test('product promotion factory assigns the promoted product to the promotion seller', function () {
    $definition = (new ProductPromotionFactory)->definition();
    $productAttributes = $definition['product_id'](['seller_id' => 77])->withoutParents()->raw();

    expect($productAttributes['seller_id'])->toBe(77)
        ->and($productAttributes['status'])->toBe(Product::STATUS_APPROVED)
        ->and($definition['stopped_by_order_id'])->toBeNull();
});

test('promotion credit usage factory defaults to an active promotion and a credit', function () {
    $definition = (new PromotionCreditUsageFactory)->definition();

    expect($definition['credit_id'])->toBeInstanceOf(Factory::class)
        ->and($definition['promotion_id'])->toBeInstanceOf(Factory::class);

    $promotionAttributes = $definition['promotion_id']->withoutParents()->raw();

    expect($promotionAttributes['status'])->toBe(ProductPromotion::STATUS_ACTIVE);
});

test('promotion payment submission factory emits database-valid pending defaults', function () {
    $definition = (new PromotionPaymentSubmissionFactory)->definition();

    expect(strlen($definition['proof_checksum']))->toBe(64)
        ->and($definition['status'])->toBe(PromotionPaymentSubmission::STATUS_PENDING)
        ->and($definition['attempt'])->toBeGreaterThan(0);
});
