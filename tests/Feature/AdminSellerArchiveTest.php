<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerArchiveEvent;
use App\Models\User;

function sellerArchiveTestProduct(User $seller, array $attributes = []): Product
{
    return Product::factory()->for($seller, 'seller')->create($attributes);
}

function sellerArchiveTestSoldProduct(Product $product, string $orderStatus = Order::STATUS_CONFIRMED): OrderItem
{
    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create(['status' => $orderStatus]);

    return OrderItem::factory()->for($order)->for($product, 'product')->create();
}

test('guests and non admins cannot reach seller archive management', function () {
    $seller = User::factory()->create();

    $routes = [
        ['get', route('admin.sellers.index')],
        ['get', route('admin.sellers.show', $seller)],
        ['post', route('admin.sellers.archive', $seller)],
        ['post', route('admin.sellers.restore', $seller)],
        ['post', route('admin.sellers.listings.restore', $seller)],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertRedirect(route('login'));
    }

    foreach ($routes as [$method, $url]) {
        $this->actingAs($seller)
            ->{$method}($url, ['products' => [], 'reason' => 'A valid reason.'])
            ->assertForbidden();
    }
});

test('the admin navigation exposes seller management to moderators', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.sellers.index'))
        ->assertOk()
        ->assertViewIs('admin.sellers.index')
        ->assertSee('Sellers')
        ->assertSee(route('admin.sellers.index'), false)
        ->assertSee('Search sellers')
        ->assertSee('All')
        ->assertSee('Active')
        ->assertSee('Archived');
});

test('the seller directory filters by status and preserves the search query', function () {
    $admin = User::factory()->admin()->create();
    $archivedSeller = User::factory()->archived()->create(['name' => 'Archive Directory Seller']);
    $activeSeller = User::factory()->create(['name' => 'Active Directory Seller']);

    sellerArchiveTestProduct($archivedSeller, [
        'title' => 'Archive Directory Listing',
        'seller_archived_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.sellers.index', ['status' => 'archived', 'q' => 'Archive Directory']))
        ->assertOk()
        ->assertSee('Archive Directory Seller')
        ->assertSee('1 total')
        ->assertSee('1 hidden')
        ->assertDontSee('Active Directory Seller')
        ->assertSee('status=all', false)
        ->assertSee('status=active', false)
        ->assertSee('q=Archive%20Directory', false);

    $this->actingAs($admin)
        ->get(route('admin.sellers.index', ['status' => 'active']))
        ->assertOk()
        ->assertSee('Active Directory Seller')
        ->assertDontSee('Archive Directory Seller');
});

test('archiving a seller hides eligible listings preserves history and records an audit event', function () {
    $admin = User::factory()->admin()->create(['name' => 'Archive Actor']);
    $seller = User::factory()->create(['name' => 'Archive Target']);

    $visibleListing = sellerArchiveTestProduct($seller, ['title' => 'Archive Visible Listing']);
    $individuallyArchived = sellerArchiveTestProduct($seller, [
        'title' => 'Archive Individual Listing',
        'archived_at' => now()->subDay(),
    ]);
    $soldListing = sellerArchiveTestProduct($seller, ['title' => 'Archive Sold Listing']);
    $soldItem = sellerArchiveTestSoldProduct($soldListing);

    $this->actingAs($admin)
        ->post(route('admin.sellers.archive', $seller), ['reason' => 'Policy review.'])
        ->assertRedirect(route('admin.sellers.show', $seller))
        ->assertSessionHas('status', __('Seller archived. :count listings hidden.', ['count' => 2]))
        ->assertSessionHas('status_key', 'seller_archived');

    expect($seller->fresh()->isArchived())->toBeTrue()
        ->and($visibleListing->fresh()->seller_archived_at)->not->toBeNull()
        ->and($individuallyArchived->fresh()->seller_archived_at)->toBeNull()
        ->and($individuallyArchived->fresh()->archived_at)->not->toBeNull()
        ->and($soldListing->fresh()->seller_archived_at)->not->toBeNull()
        ->and(OrderItem::query()->whereKey($soldItem->id)->exists())->toBeTrue();

    $event = SellerArchiveEvent::query()->where('seller_id', $seller->id)->firstOrFail();

    expect($event->action)->toBe(SellerArchiveEvent::ACTION_ARCHIVE)
        ->and($event->actor_id)->toBe($admin->id)
        ->and($event->reason)->toBe('Policy review.')
        ->and($event->listings_affected)->toBe(2)
        ->and($event->listings_restored)->toBe(0)
        ->and($event->metadata)->toBe(['scope' => 'account']);
});

test('archiving rejects self archive admin targets and repeated archives', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $archivedSeller = User::factory()->archived()->create();

    $this->actingAs($admin)
        ->post(route('admin.sellers.archive', $admin))
        ->assertSessionHas('error', __('You cannot archive your own account.'));

    $this->actingAs($admin)
        ->post(route('admin.sellers.archive', $otherAdmin))
        ->assertSessionHas('error', __('Administrator accounts cannot be archived.'));

    $this->actingAs($admin)
        ->post(route('admin.sellers.archive', $archivedSeller))
        ->assertSessionHas('error', __('This seller is already archived.'));

    expect($admin->fresh()->archived_at)->toBeNull()
        ->and($otherAdmin->fresh()->archived_at)->toBeNull()
        ->and(SellerArchiveEvent::query()->count())->toBe(0);
});

test('restoring a seller account leaves every listing hidden until separately restored', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->archived()->create();
    $firstListing = Product::factory()->for($seller, 'seller')->sellerArchived()->create();
    $secondListing = Product::factory()->for($seller, 'seller')->sellerArchived()->create();

    $this->actingAs($admin)
        ->post(route('admin.sellers.restore', $seller))
        ->assertRedirect(route('admin.sellers.show', $seller))
        ->assertSessionHas('status', __('Seller restored. Listings remain hidden until selected for restoration.'))
        ->assertSessionHas('status_key', 'seller_restored');

    expect($seller->fresh()->archived_at)->toBeNull()
        ->and($firstListing->fresh()->seller_archived_at)->not->toBeNull()
        ->and($secondListing->fresh()->seller_archived_at)->not->toBeNull();

    $event = SellerArchiveEvent::query()->where('seller_id', $seller->id)->firstOrFail();

    expect($event->action)->toBe(SellerArchiveEvent::ACTION_RESTORE)
        ->and($event->listings_affected)->toBe(0)
        ->and($event->listings_restored)->toBe(0)
        ->and($event->metadata)->toBe(['scope' => 'account']);
});

test('restoring an active seller is rejected', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.sellers.restore', $seller))
        ->assertSessionHas('error', __('Only archived sellers can be restored.'));

    expect(SellerArchiveEvent::query()->count())->toBe(0);
});

test('selective restoration only restores the chosen eligible listings', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    $selected = Product::factory()->for($seller, 'seller')->approved()->sellerArchived()->create([
        'title' => 'Selected Restore Listing',
    ]);
    $notSelected = Product::factory()->for($seller, 'seller')->approved()->sellerArchived()->create([
        'title' => 'Unselected Restore Listing',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), [
            'products' => [$selected->id],
            'reason' => 'Verified with seller.',
        ])
        ->assertRedirect(route('admin.sellers.show', $seller))
        ->assertSessionHas('status', __(':count listings restored.', ['count' => 1]))
        ->assertSessionHas('status_key', 'seller_listings_restored');

    expect($selected->fresh()->seller_archived_at)->toBeNull()
        ->and($notSelected->fresh()->seller_archived_at)->not->toBeNull();

    $event = SellerArchiveEvent::query()->where('seller_id', $seller->id)->firstOrFail();

    expect($event->action)->toBe(SellerArchiveEvent::ACTION_RESTORE)
        ->and($event->actor_id)->toBe($admin->id)
        ->and($event->reason)->toBe('Verified with seller.')
        ->and($event->listings_affected)->toBe(0)
        ->and($event->listings_restored)->toBe(1)
        ->and($event->metadata)->toBe(['scope' => 'listings', 'product_ids' => [$selected->id]]);
});

test('selective restoration rejects individually archived rejected reserved sold and foreign listings', function (callable $prepare, int $expectedStatus) {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->sellerArchived()->create();
    $product = $prepare($product, $otherSeller);

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), ['products' => [$product->id]])
        ->assertStatus($expectedStatus);

    expect($product->fresh()->seller_archived_at)->not->toBeNull()
        ->and(SellerArchiveEvent::query()->count())->toBe(0);
})->with([
    'individually archived' => [
        fn (Product $product): Product => tap($product, fn (Product $item): bool => (bool) $item->forceFill(['archived_at' => now()])->save()),
        409,
    ],
    'rejected' => [
        fn (Product $product): Product => tap($product, fn (Product $item): bool => (bool) $item->forceFill([
            'status' => Product::STATUS_REJECTED,
            'rejection_reason' => 'Not suitable.',
        ])->save()),
        409,
    ],
    'reserved' => [
        fn (Product $product): Product => tap($product, fn (Product $item): bool => (bool) $item->forceFill(['reservation_token' => 'RM-TEST-001'])->save()),
        409,
    ],
    'sold' => [
        function (Product $product): Product {
            sellerArchiveTestSoldProduct($product);

            return $product;
        },
        409,
    ],
    'foreign seller' => [
        fn (Product $product, User $otherSeller): Product => Product::factory()
            ->for($otherSeller, 'seller')
            ->approved()
            ->sellerArchived()
            ->create(),
        409,
    ],
]);

test('listings cannot be restored until the seller account is restored', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->archived()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->sellerArchived()->create();

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), ['products' => [$product->id]])
        ->assertSessionHas('error', __('Restore the seller account before restoring listings.'));

    expect($product->fresh()->seller_archived_at)->not->toBeNull();
});

test('selective restoration requires at least one existing product', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), [])
        ->assertSessionHasErrors('products');

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), ['products' => [987654]])
        ->assertSessionHasErrors();

    expect(SellerArchiveEvent::query()->count())->toBe(0);
});

test('the seller detail page shows archive history and only eligible restoration controls', function () {
    $admin = User::factory()->admin()->create(['name' => 'Detail Actor']);
    $seller = User::factory()->create(['name' => 'Detail Seller']);

    $eligible = Product::factory()->for($seller, 'seller')->approved()->sellerArchived()->create([
        'title' => 'Eligible Detail Listing',
    ]);
    $blocked = Product::factory()->for($seller, 'seller')->sellerArchived()->create([
        'title' => 'Blocked Detail Listing',
        'archived_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.sellers.archive', $seller), ['reason' => 'Audit reason.'])
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.sellers.show', $seller))
        ->assertOk()
        ->assertSee('Detail Seller')
        ->assertSee('Restore seller account')
        ->assertSee('Archive history')
        ->assertSee('Audit reason.')
        ->assertSee('Detail Actor')
        ->assertDontSee('Restore selected listings');

    $this->actingAs($admin)
        ->post(route('admin.sellers.restore', $seller))
        ->assertRedirect();

    $this->actingAs($admin)
        ->get(route('admin.sellers.show', $seller))
        ->assertOk()
        ->assertSee('Restore selected listings')
        ->assertSee('Eligible Detail Listing')
        ->assertSee('Listings unavailable for restoration')
        ->assertSee('Blocked Detail Listing')
        ->assertSee('Individually archived')
        ->assertSee(route('admin.sellers.listings.restore', $seller), false)
        ->assertSee('value="'.$eligible->id.'"', false);
});
