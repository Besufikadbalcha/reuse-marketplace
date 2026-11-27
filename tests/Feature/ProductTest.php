<?php

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;

test('product belongs to its seller and category', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();

    $product = Product::factory()
        ->for($seller, 'seller')
        ->for($category)
        ->create();

    expect($product->seller->is($seller))->toBeTrue()
        ->and($product->category->is($category))->toBeTrue();
});

test('product has images ordered by sort order and related order items', function () {
    $product = Product::factory()->create();

    ProductImage::factory()->for($product)->create(['sort_order' => 2]);
    ProductImage::factory()->for($product)->create(['sort_order' => 1]);
    OrderItem::factory()->count(2)->for($product, 'product')->create();

    expect($product->images()->orderBy('sort_order')->pluck('sort_order')->all())->toBe([1, 2])
        ->and($product->orderItems()->count())->toBe(2);
});

test('listing status scopes return only their status', function (string $scope, string $status) {
    Product::factory()->approved()->create();
    Product::factory()->create();
    Product::factory()->rejected()->create();

    $ids = Product::$scope()->pluck('id')->all();

    expect($ids)->toHaveCount(1);
    expect(Product::findOrFail($ids[0])->status)->toBe($status);
})->with([
    'approved' => ['approved', Product::STATUS_APPROVED],
    'pending' => ['pending', Product::STATUS_PENDING],
    'rejected' => ['rejected', Product::STATUS_REJECTED],
]);

test('public visibility scopes exclude archived approved products', function () {
    $visible = Product::factory()->approved()->create(['title' => 'Visible Lamp']);
    $archived = Product::factory()->approved()->archived()->create(['title' => 'Archived Lamp']);

    expect(Product::publiclyVisible()->pluck('id')->all())->toBe([$visible->id])
        ->and(Product::availableForPurchase()->pluck('id')->all())->toBe([$visible->id])
        ->and($archived->isArchived())->toBeTrue()
        ->and($visible->isArchived())->toBeFalse();
});
