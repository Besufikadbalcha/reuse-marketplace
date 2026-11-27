<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

test('guests can view a public seller profile', function () {
    $seller = User::factory()->create(['name' => 'Public Seller']);

    $this->get(route('sellers.show', $seller))
        ->assertOk()
        ->assertSee('Public Seller')
        ->assertSee(__('Seller profile'))
        ->assertSee(__('No reviews yet.'));
});

test('seller profile shows only active approved available listings', function () {
    $seller = User::factory()->create(['name' => 'Listing Seller']);
    $category = Category::factory()->create();

    $visible = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create(['title' => 'Visible Seller Listing']);
    Product::factory()->for($seller, 'seller')->for($category, 'category')->create(['title' => 'Pending Seller Listing']);
    Product::factory()->for($seller, 'seller')->for($category, 'category')->rejected()->create(['title' => 'Rejected Seller Listing']);

    $reserved = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create(['title' => 'Reserved Seller Listing']);
    Product::query()->whereKey($reserved->id)->update(['reservation_token' => 'RM-SELLER1']);

    $response = $this->get(route('sellers.show', $seller));

    $response->assertOk()
        ->assertSee('Visible Seller Listing')
        ->assertDontSee('Pending Seller Listing')
        ->assertDontSee('Rejected Seller Listing')
        ->assertDontSee('Reserved Seller Listing');

    expect($response->viewData('listings')->pluck('id')->all())->toBe([$visible->id]);
});

test('seller profile completed trades count is accurate and does not double count items', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $category = Category::factory()->create();

    $completedProductA = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create();
    $completedProductB = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create();
    $pendingProduct = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create();

    $completedOrder = Order::factory()->for($buyer, 'buyer')->create(['status' => Order::STATUS_COMPLETED]);
    OrderItem::factory()->for($completedOrder)->for($completedProductA)->completedForSeller($seller)->create();
    OrderItem::factory()->for($completedOrder)->for($completedProductB)->completedForSeller($seller)->create();

    $pendingOrder = Order::factory()->for($buyer, 'buyer')->create(['status' => Order::STATUS_CONFIRMED]);
    OrderItem::factory()->for($pendingOrder)->for($pendingProduct)->confirmedForSeller($seller)->create();

    $cancelledOrder = Order::factory()->for($buyer, 'buyer')->create(['status' => Order::STATUS_CANCELLED]);
    $cancelledProduct = Product::factory()->for($seller, 'seller')->for($category, 'category')->approved()->create();
    OrderItem::factory()->for($cancelledOrder)->for($cancelledProduct)->completedForSeller($seller)->create();

    $response = $this->get(route('sellers.show', $seller));

    $response->assertOk();
    expect($response->viewData('completedTradesCount'))->toBe(2);
});

test('seller profile returns not found for archived or admin users', function () {
    $archivedSeller = User::factory()->create(['archived_at' => now()]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->get(route('sellers.show', $archivedSeller))->assertNotFound();
    $this->get(route('sellers.show', $admin))->assertNotFound();
});

test('product detail shows seller name once in seller section with profile link', function () {
    $seller = User::factory()->create(['name' => 'Unique Seller Name']);
    $category = Category::factory()->create();

    $product = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Seller Link Product']);

    $response = $this->get(route('products.show', $product->slug));

    $response->assertOk();

    $sellerUrl = route('sellers.show', $seller);
    $content = $response->getContent();
    $sellerSection = str($content)->after(__('Seller'))->before(__('Buy now'))->toString();

    expect(substr_count($sellerSection, 'Unique Seller Name'))->toBe(1);
    expect($sellerSection)->toContain($sellerUrl);
});

test('product card shows seller name once with profile link', function () {
    $seller = User::factory()->create(['name' => 'Card Seller Name']);
    $category = Category::factory()->create(['is_active' => true]);

    Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Card Seller Product']);

    $response = $this->get(route('home'));

    $response->assertOk();

    $sellerUrl = route('sellers.show', $seller);
    $content = $response->getContent();
    $card = str($content)->after('Card Seller Product')->before('Card Seller Product' === '' ? '' : '</article>')->toString();

    expect(substr_count($card, 'Card Seller Name'))->toBe(1);
    expect($card)->toContain($sellerUrl);
});
