<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

test('order item preserves the product title and price when the listing changes', function () {
    $order = Order::factory()->create();
    $product = Product::factory()->approved()->create([
        'title' => 'Original title',
        'price_minor' => 12500,
    ]);

    $item = OrderItem::factory()
        ->for($order)
        ->for($product, 'product')
        ->create([
            'product_title' => 'Original title',
            'price_minor' => 12500,
            'quantity' => 2,
        ]);

    $product->update([
        'title' => 'Updated title',
        'price_minor' => 20000,
    ]);

    $item->refresh();

    expect($item->order->is($order))->toBeTrue()
        ->and($item->product->is($product))->toBeTrue()
        ->and($item->product_title)->toBe('Original title')
        ->and($item->price_minor)->toBe(12500)
        ->and($item->product->title)->toBe('Updated title');
});

test('order item casts price and quantity to integers', function () {
    $item = OrderItem::factory()->create([
        'price_minor' => '12500',
        'quantity' => '3',
    ]);

    expect($item->price_minor)->toBe(12500)
        ->and($item->quantity)->toBe(3);
});
