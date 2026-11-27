<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('admin can view orders and filter disputed orders', function () {
    $admin = User::factory()->admin()->create();
    $buyer = User::factory()->create();
    $seller = User::factory()->create();

    $product = Product::factory()->for($seller, 'seller')->approved()->create(['title' => 'Disputed Item']);
    $order = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-DISPUTE01', 'status' => Order::STATUS_CONFIRMED]);
    OrderItem::factory()->for($order)->for($product)->create([
        'product_title' => 'Disputed Item',
        'fulfillment_status' => OrderItem::FULFILLMENT_DISPUTED,
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        'buyer_disputed_at' => now(),
        'buyer_dispute_reason' => 'Item damaged',
    ]);

    $normalOrder = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-NORMAL01']);
    OrderItem::factory()->for($normalOrder)->create();

    $this->actingAs($admin)
        ->get(route('admin.orders.index', ['disputed' => '1']))
        ->assertOk()
        ->assertSee('RM-DISPUTE01')
        ->assertDontSee('RM-NORMAL01');

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee('Disputed Item')
        ->assertSee('Item damaged')
        ->assertSee('Status history');
});

test('non admin cannot access admin order oversight', function () {
    $user = User::factory()->create();
    $order = Order::factory()->create();

    $this->actingAs($user)->get(route('admin.orders.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.orders.show', $order))->assertForbidden();
});
