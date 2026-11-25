<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;

test('order belongs to its buyer and has items', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create();

    OrderItem::factory()->count(2)->for($order)->create();

    expect($order->buyer->is($buyer))->toBeTrue()
        ->and($order->items()->count())->toBe(2)
        ->and($order->total_amount_minor)->toBeInt();
});

test('cash on delivery orders carry contact and address details', function () {
    $order = Order::factory()->cashOnDelivery()->create();

    expect($order->fulfillment_method)->toBe(Order::FULFILLMENT_CASH_ON_DELIVERY)
        ->and($order->contact_name)->not->toBeNull()
        ->and($order->contact_phone)->not->toBeNull()
        ->and($order->delivery_address)->not->toBeNull();
});

test('pickup orders leave delivery details empty', function () {
    $order = Order::factory()->create();

    expect($order->fulfillment_method)->toBe(Order::FULFILLMENT_PICKUP)
        ->and($order->contact_name)->toBeNull()
        ->and($order->contact_phone)->toBeNull()
        ->and($order->delivery_address)->toBeNull();
});
