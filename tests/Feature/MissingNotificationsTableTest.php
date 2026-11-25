<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function pretendNotificationsTableIsAbsent(): void
{
    DB::table('users')->count();

    Schema::shouldReceive('hasTable')
        ->andReturnUsing(fn (string $table): bool => $table !== 'notifications');
    Schema::shouldReceive('hasColumn')
        ->andReturn(true);
}

/**
 * @return array<string, mixed>
 */
function missingTableCheckoutPayload(): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'fulfillment_method' => Order::FULFILLMENT_PICKUP,
    ];
}

test('public pages render without checking notifications', function (string $routeName): void {
    pretendNotificationsTableIsAbsent();

    $this->get(route($routeName))
        ->assertOk()
        ->assertDontSee('aria-label="Notifications"');

    Schema::shouldNotHaveReceived('hasTable', ['notifications']);
})->with([
    'home' => 'home',
    'login' => 'login',
    'privacy policy' => 'privacy-policy',
]);

test('buyer pages render with an empty unread badge and notification list', function (): void {
    $buyer = User::factory()->create();
    $product = Product::factory()->approved()->create();

    pretendNotificationsTableIsAbsent();

    foreach ([route('dashboard'), route('orders.index'), route('checkout.create', $product)] as $url) {
        $this->actingAs($buyer)
            ->get($url)
            ->assertOk()
            ->assertDontSee('min-w-5', false);
    }

    $this->actingAs($buyer)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('No notifications yet.')
        ->assertDontSee('Mark all as read')
        ->assertDontSee('min-w-5', false);

    Schema::shouldHaveReceived('hasTable')->with('notifications');
});

test('seller pages render with an empty unread badge', function (): void {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $order = Order::factory()->create();

    OrderItem::factory()
        ->for($order)
        ->for($product, 'product')
        ->create();

    pretendNotificationsTableIsAbsent();

    foreach ([
        route('seller.orders.index'),
        route('seller.orders.show', $order),
        route('seller.products.index'),
        route('seller.products.show', $product),
    ] as $url) {
        $this->actingAs($seller)
            ->get($url)
            ->assertOk()
            ->assertDontSee('min-w-5', false);
    }

    Schema::shouldHaveReceived('hasTable')->with('notifications');
});

test('admin pages render with an empty unread badge', function (): void {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create();

    pretendNotificationsTableIsAbsent();

    foreach ([route('admin.products.index'), route('admin.products.show', $product)] as $url) {
        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertDontSee('min-w-5', false);
    }

    Schema::shouldHaveReceived('hasTable')->with('notifications');
});

test('notification actions are harmless when the table is absent', function (): void {
    $user = User::factory()->create();

    pretendNotificationsTableIsAbsent();

    $this->actingAs($user)
        ->patch(route('notifications.readAll'))
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('notifications.read', 'missing-notification'))
        ->assertRedirect();

    Schema::shouldHaveReceived('hasTable')->with('notifications');
});

test('checkout succeeds without writing notifications when the table is absent', function (): void {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'Tableless Item']);

    pretendNotificationsTableIsAbsent();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $product), missingTableCheckoutPayload())
        ->assertRedirect();

    $this->assertDatabaseHas('orders', ['buyer_id' => $buyer->id]);

    Schema::shouldHaveReceived('hasTable')->with('notifications');
});
