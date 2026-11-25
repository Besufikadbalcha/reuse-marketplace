<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\SellerItemCompletedNotification;
use App\Notifications\SellerItemConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $itemAttributes
 * @return array{order: Order, item: OrderItem, product: Product}
 */
function notificationSellerOrderFixture(User $seller, ?User $buyer = null, array $itemAttributes = []): array
{
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Notified Seller Item']);

    $order = Order::factory()
        ->for($buyer ?? User::factory(), 'buyer')
        ->create();

    $item = OrderItem::factory()
        ->for($order)
        ->for($product, 'product')
        ->create(array_merge([
            'product_title' => 'Notified Seller Item',
            'seller_status' => OrderItem::SELLER_STATUS_PENDING,
        ], $itemAttributes));

    return ['order' => $order, 'item' => $item, 'product' => $product];
}

test('checkout notifies the buyer and the seller', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Notified Item']);

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), notificationCheckoutPayload())
        ->assertRedirect();

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    Notification::assertSentTo($buyer, OrderPlacedNotification::class, function (OrderPlacedNotification $notification) use ($order): bool {
        return $notification->params['order'] === $order->order_number;
    });

    Notification::assertSentTo($seller, NewOrderNotification::class, function (NewOrderNotification $notification) use ($order, $product): bool {
        return $notification->params['order'] === $order->order_number
            && $notification->params['item'] === $product->title;
    });
});

test('a replayed checkout does not send notifications again', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $payload = notificationCheckoutPayload();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), $payload)
        ->assertRedirect();

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), $payload)
        ->assertRedirect();

    Notification::assertNotSentTo($buyer, OrderPlacedNotification::class);
    Notification::assertNotSentTo($seller, NewOrderNotification::class);
});

test('cancelling an order notifies the buyer once', function () {
    $buyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create();

    Notification::fake();

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect();

    Notification::assertSentToTimes($buyer, OrderCancelledNotification::class, 1);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertConflict();

    Notification::assertSentToTimes($buyer, OrderCancelledNotification::class, 1);
});

test('seller confirm and complete transitions notify the buyer', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    ['order' => $order, 'item' => $item] = notificationSellerOrderFixture($seller, $buyer);

    Notification::fake();

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $item]))
        ->assertRedirect();

    Notification::assertSentTo($buyer, SellerItemConfirmedNotification::class, function (SellerItemConfirmedNotification $notification) use ($order, $item): bool {
        return $notification->params['order'] === $order->order_number
            && $notification->params['item'] === $item->product_title;
    });

    $this->actingAs($seller)
        ->post(route('seller.orders.complete', [$order, $item]))
        ->assertRedirect();

    Notification::assertSentTo($buyer, SellerItemCompletedNotification::class, function (SellerItemCompletedNotification $notification) use ($order, $item): bool {
        return $notification->params['order'] === $order->order_number
            && $notification->params['item'] === $item->product_title;
    });
});

test('a rejected seller transition does not notify the buyer', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    ['order' => $order, 'item' => $item] = notificationSellerOrderFixture($seller, $buyer, [
        'seller_status' => OrderItem::SELLER_STATUS_COMPLETED,
    ]);

    Notification::fake();

    $this->actingAs($seller)
        ->post(route('seller.orders.confirm', [$order, $item]))
        ->assertConflict();

    Notification::assertNotSentTo($buyer, SellerItemConfirmedNotification::class);
});

test('approving a listing notifies the seller', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->create(['title' => 'Approved Notification Item']);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect();

    Notification::assertSentTo($seller, ListingApprovedNotification::class, function (ListingApprovedNotification $notification) use ($product): bool {
        return $notification->params['title'] === $product->title;
    });
});

test('rejecting a listing notifies the seller with the reason', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->create(['title' => 'Rejected Notification Item']);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), [
            'rejection_reason' => 'Images are unclear.',
        ])
        ->assertRedirect();

    Notification::assertSentTo($seller, ListingRejectedNotification::class, function (ListingRejectedNotification $notification) use ($product): bool {
        return $notification->params['title'] === $product->title
            && $notification->params['reason'] === 'Images are unclear.';
    });
});

test('a repeated approval does not notify the seller again', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect();

    Notification::assertNotSentTo($seller, ListingApprovedNotification::class);
});

test('a repeated rejection does not notify the seller again', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->create(['title' => 'Rejected Notification Item']);

    Notification::fake();

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), [
            'rejection_reason' => 'Images are unclear.',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), [
            'rejection_reason' => 'Images are still unclear.',
        ])
        ->assertRedirect();

    Notification::assertSentToTimes($seller, ListingRejectedNotification::class, 1);
});
