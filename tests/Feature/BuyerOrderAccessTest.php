<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

function buyerOrderProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->create(), 'category')
        ->approved()
        ->create($attributes);
}

test('buyers can view their own order history and order detail', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['title' => 'History Item', 'price_minor' => 12000]);
    $order = Order::factory()->for($buyer, 'buyer')->create([
        'order_number' => 'RM-HISTORY01',
        'total_amount_minor' => 12000,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_title' => 'History Item',
        'price_minor' => 12000,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertViewIs('orders.index')
        ->assertSee('RM-HISTORY01')
        ->assertSee('History Item')
        ->assertSee('ETB 120.00');

    $this->actingAs($buyer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertViewIs('orders.show')
        ->assertSee('RM-HISTORY01')
        ->assertSee('History Item')
        ->assertSee('Pickup');
});

test('buyers cannot view or cancel another buyers order', function () {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    $order = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-PRIVATE01']);

    $this->actingAs($otherBuyer)->get(route('orders.show', $order))->assertNotFound();
    $this->actingAs($otherBuyer)->post(route('orders.cancel', $order))->assertNotFound();
    $this->actingAs($otherBuyer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertDontSee('RM-PRIVATE01');

    expect($order->fresh()->status)->toBe(Order::STATUS_PENDING);
});

test('guests cannot access buyer order pages', function () {
    $order = Order::factory()->create();

    $this->get(route('orders.index'))->assertRedirect(route('login'));
    $this->get(route('orders.show', $order))->assertRedirect(route('login'));
    $this->post(route('orders.cancel', $order))->assertRedirect(route('login'));
});

test('legacy orders remain readable and keep their products unavailable', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['title' => 'Legacy Item', 'price_minor' => 650000]);
    $order = Order::factory()->for($buyer, 'buyer')->cashOnDelivery()->create([
        'order_number' => 'RM-DEMO0001',
        'status' => Order::STATUS_CONFIRMED,
        'total_amount_minor' => 650000,
        'idempotency_key' => null,
        'contact_name' => 'Legacy Buyer',
        'contact_phone' => '0911111111',
        'delivery_address' => 'Legacy address',
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'product_title' => 'Legacy Item',
        'price_minor' => 650000,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('RM-DEMO0001')
        ->assertSee('Legacy Buyer')
        ->assertSee('0911111111')
        ->assertSee('Legacy address');

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Legacy Item')
        ->assertSee('This item is no longer available.');

    expect($order->fresh()->idempotency_key)->toBeNull()
        ->and($product->fresh()->reservation_token)->toBeNull();
});

test('cancelling a pending order releases its product reservation', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['title' => 'Cancellable Item', 'price_minor' => 15000]);

    $this->actingAs($buyer)->post(route('checkout.store', $product), [
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ]);

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    expect($product->fresh()->reservation_token)->toBe($order->order_number);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order cancelled.');

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($product->fresh()->reservation_token)->toBeNull()
        ->and(OrderItem::query()->where('order_id', $order->id)->count())->toBe(1);

    $otherBuyer = User::factory()->create();

    $this->actingAs($otherBuyer)->post(route('checkout.store', $product), [
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ])->assertRedirect();

    expect(Order::count())->toBe(2);
});

test('orders that are no longer pending cannot be cancelled', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['price_minor' => 15000]);
    $order = Order::factory()->for($buyer, 'buyer')->confirmed()->create(['idempotency_key' => null]);

    Product::query()->whereKey($product->id)->update(['reservation_token' => $order->order_number]);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertStatus(409);

    expect($order->fresh()->status)->toBe(Order::STATUS_CONFIRMED)
        ->and($product->fresh()->reservation_token)->toBe($order->order_number);
});

test('the my orders link is available only to authenticated buyers', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('My Orders');

    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('My Orders');
});

test('private buyer delivery details are not exposed on public pages', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['title' => 'Public Item', 'price_minor' => 22000]);
    $order = Order::factory()->for($buyer, 'buyer')->cashOnDelivery()->create([
        'contact_name' => 'Private Buyer',
        'contact_phone' => '0912345678',
        'delivery_address' => 'Secret address',
    ]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Public Item')
        ->assertDontSee('Private Buyer')
        ->assertDontSee('0912345678')
        ->assertDontSee('Secret address');
});

test('checkout throttling does not consume the cancellation quota', function () {
    $buyer = User::factory()->create();
    $product = buyerOrderProduct(['title' => 'Independent Item', 'price_minor' => 15000]);

    $order = Order::factory()->for($buyer, 'buyer')->create([
        'order_number' => 'RM-CHK-001',
        'status' => Order::STATUS_PENDING,
    ]);

    Product::query()->whereKey($product->id)->update(['reservation_token' => $order->order_number]);

    foreach (range(1, 5) as $attempt) {
        $this->actingAs($buyer)->post(route('checkout.store', $product), [
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        ])->assertSessionHasErrors('idempotency_key');
    }

    $this->actingAs($buyer)->post(route('checkout.store', $product), [
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ])->assertStatus(429);

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $order))
        ->assertRedirect(route('orders.show', $order))
        ->assertSessionHas('status', 'Order cancelled.');

    expect($order->fresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($product->fresh()->reservation_token)->toBeNull();
});

test('order cancellation is rate limited after five attempts', function () {
    $buyer = User::factory()->create();
    $orders = collect(range(1, 6))->map(fn (int $attempt): Order => Order::factory()
        ->for($buyer, 'buyer')
        ->create([
            'order_number' => 'RM-CXL-'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT),
            'status' => Order::STATUS_PENDING,
        ]));

    foreach ($orders->take(5) as $order) {
        $this->actingAs($buyer)
            ->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status', 'Order cancelled.');
    }

    $this->actingAs($buyer)
        ->post(route('orders.cancel', $orders[5]))
        ->assertStatus(429);

    expect($orders[5]->fresh()->status)->toBe(Order::STATUS_PENDING);
});
