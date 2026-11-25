<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

function normalizeMyAccountMarkup(string $html): string
{
    return preg_replace('/\s+/', ' ', $html) ?? $html;
}

test('guests cannot open My Account or its tabs', function (string $routeName): void {
    $this->get(route($routeName))
        ->assertRedirect(route('login'));
})->with([
    'my account' => 'my-account',
    'my orders' => 'orders.index',
    'my sales' => 'seller.orders.index',
    'my listings' => 'seller.products.index',
]);

test('My Account redirects authenticated users to the My Orders tab', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('my-account'))
        ->assertRedirect(route('orders.index'));
});

test('account pages use the wider responsive marketplace container', function (string $routeName): void {
    $user = User::factory()->create();
    $content = normalizeMyAccountMarkup((string) $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->getContent());

    expect($content)->toContain('w-full max-w-[96rem] min-w-0')
        ->toContain('px-4 py-5 sm:px-6 sm:py-3 lg:px-8');
})->with([
    'my orders' => 'orders.index',
    'my listings' => 'seller.products.index',
    'notifications' => 'notifications.index',
]);

test('dashboard uses the wider responsive account container', function (): void {
    $user = User::factory()->create();
    $content = normalizeMyAccountMarkup((string) $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent());

    expect($content)->toContain('max-w-5xl sm:max-w-7xl')
        ->toContain('px-4 py-10 sm:px-6 sm:py-14 lg:px-8');
});

/**
 * @param  array<int, string>  $otherRouteNames
 */
test('each account tab page marks its own link as current', function (string $routeName, string $selectedRouteName, array $otherRouteNames): void {
    $user = User::factory()->create();

    $content = normalizeMyAccountMarkup((string) $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('aria-label="'.__('Account sections').'"', false)
        ->getContent());

    expect($content)->toContain('aria-current="page" href="'.route($selectedRouteName).'"')
        ->toContain(route('orders.index'))
        ->toContain(route('seller.orders.index'))
        ->toContain(route('seller.products.index'));

    foreach ($otherRouteNames as $otherRouteName) {
        expect($content)->not->toContain('aria-current="page" href="'.route($otherRouteName).'"');
    }
})->with([
    'my orders' => ['orders.index', 'orders.index', ['seller.orders.index', 'seller.products.index']],
    'my sales' => ['seller.orders.index', 'seller.orders.index', ['orders.index', 'seller.products.index']],
    'my listings' => ['seller.products.index', 'seller.products.index', ['orders.index', 'seller.orders.index']],
]);

test('authenticated layouts show one My Account link per navigation instead of separate account links', function (string $routeName): void {
    $user = User::factory()->create();

    $content = (string) $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('My Account')
        ->getContent();

    expect(substr_count($content, 'href="'.route('my-account').'"'))->toBe(2)
        ->and(substr_count($content, 'href="'.route('orders.index').'"'))->toBe(0)
        ->and(substr_count($content, 'href="'.route('seller.orders.index').'"'))->toBe(0)
        ->and(substr_count($content, 'href="'.route('seller.products.index').'"'))->toBe(0);
})->with([
    'marketplace layout' => 'home',
    'guest layout' => 'privacy-policy',
]);

test('the dashboard auth header shows one My Account link per navigation', function (): void {
    $user = User::factory()->create();

    $content = (string) $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('My Account')
        ->getContent();

    expect(substr_count($content, 'href="'.route('my-account').'"'))->toBe(2);
});

test('My Orders keeps buyer ownership and pagination on the original URL', function (): void {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();

    Order::factory()->for($otherBuyer, 'buyer')->create(['order_number' => 'RM-OTHERBUY']);

    $orderNumbers = collect(range(1, 12))
        ->map(fn (int $number): string => 'RM-PAGE-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT));

    foreach ($orderNumbers as $orderNumber) {
        Order::factory()->for($buyer, 'buyer')->create(['order_number' => $orderNumber]);
    }

    $this->actingAs($buyer)
        ->get(route('orders.index'))
        ->assertOk()
        ->assertDontSee('RM-OTHERBUY')
        ->assertDontSee('RM-PAGE-01')
        ->assertSee('page=2', false);

    $this->actingAs($buyer)
        ->get(route('orders.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('RM-PAGE-01')
        ->assertDontSee('RM-PAGE-12');
});

test('My Sales keeps seller ownership and status filtering on the original URL', function (): void {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $buyer = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $otherProduct = Product::factory()->for($otherSeller, 'seller')->approved()->create();

    $pendingOrder = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-SALEPEND']);
    OrderItem::factory()->for($pendingOrder)->for($product, 'product')->create();

    $confirmedOrder = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-SALECONF']);
    OrderItem::factory()->for($confirmedOrder)->for($product, 'product')->confirmedForSeller()->create();

    $otherOrder = Order::factory()->for($buyer, 'buyer')->create(['order_number' => 'RM-OTHERSALE']);
    OrderItem::factory()->for($otherOrder)->for($otherProduct, 'product')->create();

    $this->actingAs($seller)
        ->get(route('seller.orders.index', ['status' => OrderItem::SELLER_STATUS_CONFIRMED]))
        ->assertOk()
        ->assertSee('RM-SALECONF')
        ->assertDontSee('RM-SALEPEND')
        ->assertDontSee('RM-OTHERSALE');
});

test('My Listings keeps seller ownership filtering and pagination on the original URL', function (): void {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();

    $titles = collect(range(1, 13))
        ->map(fn (int $number): string => 'Listing '.str_pad((string) $number, 2, '0', STR_PAD_LEFT));

    foreach ($titles as $title) {
        Product::factory()->for($seller, 'seller')->approved()->create(['title' => $title]);
    }

    Product::factory()->for($seller, 'seller')->rejected()->create(['title' => 'Hidden Rejected Listing']);
    Product::factory()->for($otherSeller, 'seller')->approved()->create(['title' => 'Other Seller Listing']);

    $this->actingAs($seller)
        ->get(route('seller.products.index', ['status' => Product::STATUS_APPROVED]))
        ->assertOk()
        ->assertDontSee('Hidden Rejected Listing')
        ->assertDontSee('Other Seller Listing')
        ->assertDontSee('Listing 01')
        ->assertSee('page=2', false);

    $this->actingAs($seller)
        ->get(route('seller.products.index', ['status' => Product::STATUS_APPROVED, 'page' => 2]))
        ->assertOk()
        ->assertSee('Listing 01')
        ->assertDontSee('Listing 13');
});

test('My Account label is translated in the authenticated navigation', function (string $locale, string $label): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee($label);
})->with([
    'English' => ['en', 'My Account'],
    'Amharic' => ['am', 'የኔ መለያ'],
    'Afaan Oromoo' => ['om', 'Akauntii koo'],
]);

/**
 * @param  array<int, string>  $labels
 */
test('account tabs reuse the existing tab translations', function (string $locale, array $labels): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['locale' => $locale])
        ->get(route('orders.index'))
        ->assertOk();

    foreach ($labels as $label) {
        $response->assertSee($label);
    }
})->with([
    'English' => ['en', ['My Orders', 'My Sales', 'My Listings']],
    'Amharic' => ['am', ['የኔ ትዕዛዞች', 'የኔ ሽያጦች', 'የኔ ማስታወቂያዎች']],
    'Afaan Oromoo' => ['om', ['Tartiib koo', 'Gurgurtaa koo', 'Dubbisa koo']],
]);

test('admin moderation remains separate from the My Account link', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('My Account')
        ->assertSee('Moderation');

    $this->actingAs(User::factory()->create())
        ->get(route('orders.index'))
        ->assertOk()
        ->assertSee('My Account')
        ->assertDontSee('Moderation');
});
