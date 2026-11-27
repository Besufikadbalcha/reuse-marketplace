<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

/**
 * @param  array<string, mixed>  $orderAttributes
 * @param  array<string, mixed>  $itemAttributes
 * @return array{order: Order, item: OrderItem, product: Product}
 */
function sellerOrderFixture(User $seller, ?User $buyer = null, array $orderAttributes = [], array $itemAttributes = []): array
{
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create([
            'title' => $itemAttributes['product_title'] ?? 'Seller Item',
            'price_minor' => $itemAttributes['price_minor'] ?? 12500,
        ]);

    $order = Order::factory()
        ->for($buyer ?? User::factory(), 'buyer')
        ->create($orderAttributes);

    $item = OrderItem::factory()
        ->for($order)
        ->for($product, 'product')
        ->create(array_merge([
            'product_title' => 'Seller Item',
            'price_minor' => 12500,
            'quantity' => 1,
            'seller_status' => OrderItem::SELLER_STATUS_PENDING,
        ], $itemAttributes));

    return ['order' => $order, 'item' => $item, 'product' => $product];
}

test('new order items use pending seller fulfillment status', function () {
    $item = OrderItem::factory()->create();

    expect($item->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING)
        ->and($item->canBeConfirmedBySeller())->toBeTrue()
        ->and($item->canBeCompletedBySeller())->toBeFalse();

    $this->assertDatabaseHas('order_items', [
        'id' => $item->id,
        'seller_status' => OrderItem::SELLER_STATUS_PENDING,
    ]);
});

test('guests cannot access seller order routes', function (string $method, string $routeName, bool $withItem) {
    $order = Order::factory()->create();
    $item = OrderItem::factory()->for($order)->create();

    $this->{$method}(route($routeName, $withItem ? [$order, $item] : []))
        ->assertRedirect(route('login'));
})->with([
    'index' => ['get', 'seller.orders.index', false],
    'show' => ['get', 'seller.orders.show', true],
    'confirm' => ['post', 'seller.orders.confirm', true],
    'complete' => ['post', 'seller.orders.complete', true],
]);

test('a seller sees only their own orders filtered by seller fulfillment status', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();

    $pending = sellerOrderFixture($seller, null, [
        'order_number' => 'RM-SELLERPEND',
    ]);
    $confirmed = sellerOrderFixture($seller, null, [
        'order_number' => 'RM-SELLERCONF',
    ], [
        'product_title' => 'Confirmed Seller Item',
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
    ]);
    $other = sellerOrderFixture($otherSeller, null, [
        'order_number' => 'RM-OTHERSELL',
    ], [
        'product_title' => 'Other Seller Item',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.orders.index'))
        ->assertOk()
        ->assertViewIs('seller.orders.index')
        ->assertSee('RM-SELLERPEND')
        ->assertSee('RM-SELLERCONF')
        ->assertDontSee('RM-OTHERSELL')
        ->assertSee('Seller orders');

    $this->actingAs($seller)
        ->get(route('seller.orders.index', ['status' => OrderItem::SELLER_STATUS_CONFIRMED]))
        ->assertOk()
        ->assertSee('RM-SELLERCONF')
        ->assertDontSee('RM-SELLERPEND')
        ->assertDontSee('RM-OTHERSELL');

    $this->actingAs($seller)
        ->get(route('seller.orders.index', ['status' => 'unknown']))
        ->assertOk()
        ->assertSee('RM-SELLERPEND')
        ->assertSee('RM-SELLERCONF');
});

test('seller order detail hides another sellers items and denies other users', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create([
        'order_number' => 'RM-MULTI0001',
    ]);

    $ownProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'My Sold Item',
    ]);
    $ownItem = OrderItem::factory()->for($order)->for($ownProduct, 'product')->create([
        'product_title' => 'My Sold Item',
    ]);

    $otherProduct = Product::factory()->for($otherSeller, 'seller')->approved()->create([
        'title' => 'Other Sold Item',
    ]);
    $otherItem = OrderItem::factory()->for($order)->for($otherProduct, 'product')->create([
        'product_title' => 'Other Sold Item',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.orders.show', $order))
        ->assertOk()
        ->assertViewIs('seller.orders.show')
        ->assertSee('RM-MULTI0001')
        ->assertSee('My Sold Item')
        ->assertDontSee('Other Sold Item')
        ->assertSee('Pending');

    $unrelatedSeller = User::factory()->create();

    $this->actingAs($otherSeller)
        ->get(route('seller.orders.show', $order))
        ->assertOk()
        ->assertSee('Other Sold Item')
        ->assertDontSee('My Sold Item');

    $this->actingAs($unrelatedSeller)
        ->get(route('seller.orders.show', $order))
        ->assertNotFound();

    $this->actingAs($buyer)
        ->get(route('seller.orders.show', $order))
        ->assertNotFound();

    $this->actingAs($otherSeller)
        ->post(route('seller.orders.confirm', [$order, $ownItem]))
        ->assertNotFound();

    expect($ownItem->fresh()->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING)
        ->and($otherItem->fresh()->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING);
});

test('a seller can confirm a pending item without changing the buyer order', function () {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller);

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $item]))
        ->assertRedirect(route('seller.orders.show', $order))
        ->assertSessionHas('status', 'Item confirmed.');

    expect($item->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_CONFIRMED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('a seller cannot confirm an item that is not pending', function (string $sellerStatus) {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, [], [
        'seller_status' => $sellerStatus,
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $item]))
        ->assertConflict();

    expect($item->fresh()->seller_status)->toBe($sellerStatus)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING);
})->with([
    'confirmed' => OrderItem::SELLER_STATUS_CONFIRMED,
    'completed' => OrderItem::SELLER_STATUS_COMPLETED,
]);

test('a seller can complete an item after cash and buyer confirmation', function () {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, [], [
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        'fulfillment_status' => OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION,
        'seller_cash_received_at' => now()->subMinute(),
        'buyer_confirmed_received_at' => now(),
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertRedirect(route('seller.orders.show', $order))
        ->assertSessionHas('status', 'Item marked complete.');

    expect($item->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_COMPLETED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_COMPLETED);
});

test('a seller cannot complete an item that is not confirmed', function (string $sellerStatus) {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, [], [
        'seller_status' => $sellerStatus,
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertConflict();

    expect($item->fresh()->seller_status)->toBe($sellerStatus)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING);
})->with([
    'pending' => OrderItem::SELLER_STATUS_PENDING,
    'completed' => OrderItem::SELLER_STATUS_COMPLETED,
]);

test('seller status transitions leave the product reservation intact', function () {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item, 'product' => $product] = sellerOrderFixture($seller);
    Product::query()->whereKey($product->id)->update(['reservation_token' => 'RM-RESERVE01']);

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $item]));

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]));

    expect($product->fresh()->reservation_token)->toBe('RM-RESERVE01')
        ->and(Product::query()->availableForPurchase()->whereKey($product->id)->exists())->toBeFalse()
        ->and($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('buyer cancellation leaves the seller fulfillment status untouched', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item, 'product' => $product] = sellerOrderFixture($seller, $buyer);
    Product::query()->whereKey($product->id)->update(['reservation_token' => $order->order_number]);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order));

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($item->fresh()->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING)
        ->and($product->fresh()->reservation_token)->toBeNull();
});

test('seller order detail includes cash on delivery contact details', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, $buyer, [
        'fulfillment_method' => Order::FULFILLMENT_CASH_ON_DELIVERY,
        'contact_name' => 'Delivery Contact',
        'contact_phone' => '0912345678',
        'delivery_address' => '12 Market Street',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.orders.show', $order))
        ->assertOk()
        ->assertSee('Cash on delivery')
        ->assertSee('Delivery Contact')
        ->assertSee('0912345678')
        ->assertSee('12 Market Street')
        ->assertSee('Confirm item');

    expect($item->fresh()->seller_status)->toBe(OrderItem::SELLER_STATUS_PENDING);
});

test('cancelled order views do not expose seller transition forms', function (string $routeName, bool $passOrder) {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, [
        'status' => Order::STATUS_CANCELLED,
    ]);

    $url = $passOrder ? route($routeName, $order) : route($routeName);

    $this->actingAs($seller)
        ->get($url)
        ->assertOk()
        ->assertDontSee(route('seller.orders.confirm', [$order, $item]), false)
        ->assertDontSee(route('seller.orders.complete', [$order, $item]), false);
})->with([
    'index' => ['seller.orders.index', false],
    'show' => ['seller.orders.show', true],
]);

test('a seller cannot transition an item on a cancelled order', function (string $routeName, string $sellerStatus, string $reservationToken) {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item, 'product' => $product] = sellerOrderFixture($seller, null, [
        'status' => Order::STATUS_CANCELLED,
    ], [
        'seller_status' => $sellerStatus,
    ]);
    Product::query()->whereKey($product->id)->update(['reservation_token' => $reservationToken]);

    $this->actingAs($seller)
        ->post(route($routeName, [$order, $item]))
        ->assertConflict();

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($item->fresh()->seller_status)->toBe($sellerStatus)
        ->and($product->fresh()->reservation_token)->toBe($reservationToken);
})->with([
    'confirm' => ['seller.orders.confirm', OrderItem::SELLER_STATUS_PENDING, 'RM-CANCEL01'],
    'complete' => ['seller.orders.complete', OrderItem::SELLER_STATUS_CONFIRMED, 'RM-CANCEL02'],
]);

test('a seller transition leaves another sellers item in the same order unchanged', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create([
        'order_number' => 'RM-MULTI0002',
    ]);

    $ownProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Own Multi Item',
    ]);
    $ownItem = OrderItem::factory()->for($order)->for($ownProduct, 'product')->create([
        'product_title' => 'Own Multi Item',
    ]);

    $otherProduct = Product::factory()->for($otherSeller, 'seller')->approved()->create([
        'title' => 'Other Multi Item',
    ]);
    $otherItem = OrderItem::factory()->for($order)->for($otherProduct, 'product')->confirmedForSeller()->create([
        'product_title' => 'Other Multi Item',
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $ownItem]))
        ->assertRedirect(route('seller.orders.show', $order));

    expect($ownItem->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_CONFIRMED)
        ->and($otherItem->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_CONFIRMED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('the complete endpoint denies another seller', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, ['status' => Order::STATUS_CONFIRMED], [
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        'fulfillment_status' => OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION,
        'seller_cash_received_at' => now()->subMinute(),
        'buyer_confirmed_received_at' => now(),
    ]);

    $this->actingAs($otherSeller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertNotFound();

    $item->refresh();
    expect($item->fulfillment_status)->toBe(OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION)
        ->and($item->seller_status)->toBe(OrderItem::SELLER_STATUS_CONFIRMED)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('a mismatched parent order cannot be used to transition an item', function (string $routeName, string $sellerStatus) {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, [], [
        'seller_status' => $sellerStatus,
    ]);
    $unrelatedOrder = Order::factory()->create();

    $this->actingAs($seller)
        ->post(route($routeName, [$unrelatedOrder, $item]))
        ->assertNotFound();

    expect($item->fresh()->seller_status)->toBe($sellerStatus)
        ->and($order->fresh()->status)->toBe(Order::STATUS_PENDING)
        ->and($unrelatedOrder->fresh()->status)->toBe(Order::STATUS_PENDING);
})->with([
    'confirm' => ['seller.orders.confirm', OrderItem::SELLER_STATUS_PENDING],
    'complete' => ['seller.orders.complete', OrderItem::SELLER_STATUS_CONFIRMED],
]);

test('seller order views show quantity separately from the line total', function (string $routeName, bool $passOrder) {
    $seller = User::factory()->create();
    ['order' => $order] = sellerOrderFixture($seller, null, [], [
        'product_title' => 'Quantity Item',
        'price_minor' => 15000,
        'quantity' => 2,
    ]);

    $url = $passOrder ? route($routeName, $order) : route($routeName);

    $this->actingAs($seller)
        ->get($url)
        ->assertOk()
        ->assertSee('Quantity: 2 | Line total: ETB 300.00', false)
        ->assertDontSee('2 &times; ETB 300.00', false)
        ->assertDontSee('ETB 600.00', false);
})->with([
    'index' => ['seller.orders.index', false],
    'show' => ['seller.orders.show', true],
]);

test('seller cannot complete item before buyer confirms receipt', function () {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, ['status' => Order::STATUS_CONFIRMED], [
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        'fulfillment_status' => OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION,
        'seller_cash_received_at' => now()->subMinute(),
        'buyer_confirmed_received_at' => null,
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertConflict();

    expect($item->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION)
        ->and($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED);
});

test('seller cannot complete disputed item', function () {
    $seller = User::factory()->create();
    ['order' => $order, 'item' => $item] = sellerOrderFixture($seller, null, ['status' => Order::STATUS_CONFIRMED], [
        'seller_status' => OrderItem::SELLER_STATUS_CONFIRMED,
        'fulfillment_status' => OrderItem::FULFILLMENT_DISPUTED,
        'seller_cash_received_at' => now()->subMinute(),
        'buyer_confirmed_received_at' => now(),
        'buyer_disputed_at' => now(),
        'buyer_dispute_reason' => 'Issue',
    ]);

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertConflict();

    expect($item->fresh()->fulfillmentStatus())->toBe(OrderItem::FULFILLMENT_DISPUTED);
});
