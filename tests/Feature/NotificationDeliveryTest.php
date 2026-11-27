<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Notifications\ListingApprovedNotification;
use App\Notifications\ListingRejectedNotification;
use App\Notifications\ListingResubmittedForReviewNotification;
use App\Notifications\ListingSubmittedForReviewNotification;
use App\Notifications\NewOrderNotification;
use App\Notifications\OrderCancelledNotification;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\SellerItemCompletedNotification;
use App\Notifications\SellerItemConfirmedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationListingPayload(Category $category, Location $location, array $overrides = []): array
{
    return array_merge([
        'title' => 'Notification Listing',
        'description' => 'A listing awaiting moderation review.',
        'price' => '125.00',
        'category_id' => $category->id,
        'location_id' => $location->id,
        'condition' => Product::CONDITION_GOOD,
        'images' => [UploadedFile::fake()->create('listing.jpg', 200, 'image/jpeg')],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function notificationListingUpdatePayload(Product $product, array $overrides = []): array
{
    $product->loadMissing('images');

    return array_merge([
        'title' => $product->title,
        'description' => $product->description,
        'price' => number_format($product->price_minor / 100, 2, '.', ''),
        'category_id' => $product->category_id,
        'location_id' => $product->location_id,
        'condition' => $product->condition,
        'remove_images' => [],
        'image_order' => $product->images->pluck('id')->all(),
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

    $item->forceFill([
        'fulfillment_status' => OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION,
        'seller_cash_received_at' => now()->subMinute(),
        'buyer_confirmed_received_at' => now(),
    ])->save();
    $order->forceFill(['status' => Order::STATUS_CONFIRMED])->save();

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

test('new listing submission notifies active admins with a moderation link', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $adminA = User::factory()->admin()->create();
    $adminB = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Notification Location '.Str::random(5), 'is_active' => true]);

    Notification::fake();

    $this->actingAs($seller)
        ->post(route('seller.products.store'), notificationListingPayload($category, $location, ['title' => 'Admin Review Item']))
        ->assertRedirect(route('seller.products.index'));

    $product = Product::query()->where('title', 'Admin Review Item')->firstOrFail();

    Notification::assertSentTo($adminA, ListingSubmittedForReviewNotification::class, function (ListingSubmittedForReviewNotification $notification) use ($product): bool {
        return $notification->params['title'] === $product->title
            && $notification->params['product_id'] === $product->id
            && $notification->url === route('admin.products.show', $product);
    });

    Notification::assertSentTo($adminB, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($seller, ListingSubmittedForReviewNotification::class);
});

test('archived admins do not receive new listing submission notifications', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $activeAdmin = User::factory()->admin()->create();
    $archivedAdmin = User::factory()->admin()->create(['archived_at' => now()]);
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Notification Location '.Str::random(5), 'is_active' => true]);

    Notification::fake();

    $this->actingAs($seller)
        ->post(route('seller.products.store'), notificationListingPayload($category, $location))
        ->assertRedirect(route('seller.products.index'));

    Notification::assertSentTo($activeAdmin, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($archivedAdmin, ListingSubmittedForReviewNotification::class);
});

test('a resubmitted listing notifies active admins', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create(['title' => 'Resubmitted Item']);
    ProductImage::factory()->for($product, 'product')->create();

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), notificationListingUpdatePayload($product, [
            'title' => 'Resubmitted Item Updated',
        ]))
        ->assertRedirect(route('seller.products.show', $product));

    $product->refresh();

    Notification::assertSentTo($admin, ListingResubmittedForReviewNotification::class, function (ListingResubmittedForReviewNotification $notification) use ($product): bool {
        return $notification->params['product_id'] === $product->id
            && $notification->url === route('admin.products.show', $product);
    });
});

test('an ordinary pending listing edit does not notify admins again', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
        'title' => 'Pending Item',
    ]);
    ProductImage::factory()->for($product, 'product')->create();

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), notificationListingUpdatePayload($product, [
            'title' => 'Pending Item Tweaked',
        ]))
        ->assertRedirect(route('seller.products.show', $product));

    Notification::assertNotSentTo($admin, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($admin, ListingResubmittedForReviewNotification::class);
});

test('an admin-seller listing submission does not notify that same admin', function () {
    Storage::fake('local');
    Storage::fake('public');

    $adminSeller = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Notification Location '.Str::random(5), 'is_active' => true]);

    Notification::fake();

    $this->actingAs($adminSeller)
        ->post(route('seller.products.store'), notificationListingPayload($category, $location, ['title' => 'Admin Seller Listing']))
        ->assertRedirect(route('seller.products.index'));

    Notification::assertSentTo($otherAdmin, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($adminSeller, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($adminSeller, ListingResubmittedForReviewNotification::class);
});

test('duplicate pending submission notifications are not created while an unread notification exists', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Notification Location '.Str::random(5), 'is_active' => true]);

    $this->actingAs($seller)
        ->post(route('seller.products.store'), notificationListingPayload($category, $location, ['title' => 'Duplicate Pending Item']))
        ->assertRedirect(route('seller.products.index'));

    $product = Product::query()->where('title', 'Duplicate Pending Item')->firstOrFail();
    expect($admin->unreadNotifications()
        ->where('type', ListingSubmittedForReviewNotification::class)
        ->where('data->params->product_id', $product->id)
        ->count())->toBe(1);

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), notificationListingUpdatePayload($product, [
            'title' => 'Duplicate Pending Item Still Pending',
        ]))
        ->assertRedirect(route('seller.products.show', $product));

    expect($admin->unreadNotifications()
        ->whereIn('type', [ListingSubmittedForReviewNotification::class, ListingResubmittedForReviewNotification::class])
        ->where('data->params->product_id', $product->id)
        ->count())->toBe(1);
});

test('a later legitimate resubmission can notify again after the previous notification is read', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create(['title' => 'Rereview Item']);
    $imagePath = 'seller-products/'.$seller->id.'/rereview.jpg';
    Storage::disk('local')->put($imagePath, 'image-content');
    ProductImage::factory()->for($product, 'product')->create(['path' => $imagePath, 'sort_order' => 0]);

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), notificationListingUpdatePayload($product, [
            'title' => 'Rereview Item First Edit',
        ]))
        ->assertRedirect(route('seller.products.show', $product));

    $notification = $admin->unreadNotifications()
        ->where('type', ListingResubmittedForReviewNotification::class)
        ->where('data->params->product_id', $product->id)
        ->firstOrFail();
    $notification->markAsRead();

    $approver = User::factory()->admin()->create();
    $fresh = $product->fresh()->loadMissing(['images', 'seller']);
    $this->actingAs($approver)
        ->post(route('admin.products.approve', $fresh))
        ->assertRedirect(route('admin.products.index'));

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product->fresh()), notificationListingUpdatePayload($product->fresh(), [
            'title' => 'Rereview Item Second Edit',
        ]))
        ->assertRedirect(route('seller.products.show', $product));

    Notification::assertSentTo($admin, ListingResubmittedForReviewNotification::class);
});

test('admins are not notified when seller updates do not submit a listing for moderation', function () {
    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $approvedProduct = Product::factory()->for($seller, 'seller')->approved()->create(['title' => 'Approved Stable']);
    $pendingProduct = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
        'title' => 'Pending Stable',
    ]);
    ProductImage::factory()->for($approvedProduct, 'product')->create();
    ProductImage::factory()->for($pendingProduct, 'product')->create();

    Notification::fake();

    $this->actingAs($seller)
        ->put(route('seller.products.update', $approvedProduct), notificationListingUpdatePayload($approvedProduct))
        ->assertRedirect()
        ->assertSessionHas('status', 'No changes detected.');

    $this->actingAs($seller)
        ->put(route('seller.products.update', $pendingProduct), notificationListingUpdatePayload($pendingProduct, [
            'title' => 'Pending Stable Changed',
        ]))
        ->assertRedirect(route('seller.products.show', $pendingProduct));

    Notification::assertNotSentTo($admin, ListingSubmittedForReviewNotification::class);
    Notification::assertNotSentTo($admin, ListingResubmittedForReviewNotification::class);
});
