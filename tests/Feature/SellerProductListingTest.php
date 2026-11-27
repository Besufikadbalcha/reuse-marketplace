<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const SELLER_PRODUCT_TINY_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDIzM//AABEIAAEAAQMBIgACEQEDEQH/xAAfAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgv/xAC1EAACAQMDAgQDBQUEBAAAAX0BAgMABBEFEiExQQYTUWEHInEUMoGRoQgjQrHBFVLR8CRjZLKCkpsNLXLy8vExg9Nzc4OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/aAAwDAQACEQMRAD8A/v4ooooA/9k=';
const SELLER_PRODUCT_TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';
const SELLER_PRODUCT_TINY_WEBP = 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA';

function sellerProductTestFakeImage(string $name): UploadedFile
{
    $contents = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'png' => base64_decode(SELLER_PRODUCT_TINY_PNG, true),
        'webp' => base64_decode(SELLER_PRODUCT_TINY_WEBP, true),
        default => base64_decode(SELLER_PRODUCT_TINY_JPEG, true),
    };

    return UploadedFile::fake()->createWithContent($name, $contents);
}

function sellerProductTestLocationId(): int
{
    return (int) Location::query()->firstOrCreate(['name' => 'Test Location'])->id;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerProductTestPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Test Listing',
        'description' => 'A carefully described second-hand item.',
        'price' => '1250.00',
        'category_id' => Category::factory()->create()->id,
        'location_id' => sellerProductTestLocationId(),
        'condition' => Product::CONDITION_GOOD,
        'images' => [sellerProductTestFakeImage('photo.jpg')],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerProductTestUpdatePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'title' => $product->title,
        'description' => $product->description,
        'price' => number_format((int) $product->price_minor / 100, 2, '.', ''),
        'category_id' => $product->category_id,
        'location_id' => $product->location_id ?? sellerProductTestLocationId(),
        'condition' => $product->condition,
    ], $overrides);
}

function sellerProductTestGiveImage(Product $product, User $seller): ProductImage
{
    return ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/'.fake()->uuid.'.jpg',
    ]);
}

test('guests cannot access seller listing routes', function (string $method, string $routeName, bool $withProduct) {
    $product = $withProduct ? Product::factory()->create() : null;

    $this->{$method}(route($routeName, $product ?: []))
        ->assertRedirect(route('login'));
})->with([
    'index' => ['get', 'seller.products.index', false],
    'create' => ['get', 'seller.products.create', false],
    'store' => ['post', 'seller.products.store', false],
    'show' => ['get', 'seller.products.show', true],
    'edit' => ['get', 'seller.products.edit', true],
    'update' => ['put', 'seller.products.update', true],
    'destroy' => ['delete', 'seller.products.destroy', true],
]);

test('a seller sees their own listings filtered by status and not other sellers listings', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();

    Product::factory()->for($seller, 'seller')->create([
        'title' => 'My Pending Listing',
        'status' => Product::STATUS_PENDING,
    ]);
    Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'My Approved Listing',
    ]);
    Product::factory()->for($seller, 'seller')->rejected()->create([
        'title' => 'My Rejected Listing',
    ]);
    Product::factory()->for($otherSeller, 'seller')->approved()->create([
        'title' => 'Other Seller Listing',
    ]);

    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertSee('My Pending Listing')
        ->assertSee('My Approved Listing')
        ->assertSee('My Rejected Listing')
        ->assertDontSee('Other Seller Listing');

    $this->actingAs($seller)
        ->get(route('seller.products.index', ['status' => Product::STATUS_REJECTED]))
        ->assertOk()
        ->assertSee('My Rejected Listing')
        ->assertDontSee('My Approved Listing');

    $this->actingAs($seller)
        ->get(route('seller.products.index', ['status' => 'unknown']))
        ->assertOk()
        ->assertSee('My Pending Listing');
});

test('the create page renders active categories conditions and locations and defaults condition to like new', function () {
    $category = Category::factory()->create(['name' => 'Active Category']);
    Category::factory()->inactive()->create(['name' => 'Inactive Category']);
    $activeLocation = Location::query()->create(['name' => 'Active Location']);
    Location::query()->create(['name' => 'Inactive Location', 'is_active' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route('seller.products.create'))
        ->assertOk()
        ->assertSee('Active Category')
        ->assertDontSee('Inactive Category')
        ->assertSee('Active Location')
        ->assertSee('value="'.$activeLocation->id.'"', false)
        ->assertDontSee('Inactive Location')
        ->assertSee('Like New')
        ->assertSee('value="'.Product::CONDITION_LIKE_NEW.'" selected', false);
});

test('a seller can select and save a seeded location', function () {
    Storage::fake('local');

    $this->seed(LocationSeeder::class);
    $location = Location::query()->where('name', 'Addis Ababa')->firstOrFail();
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertOk()
        ->assertSee('Addis Ababa')
        ->assertSee('value="'.$location->id.'"', false);

    $response = $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerProductTestPayload([
            'location_id' => $location->id,
        ])
    );

    $product = Product::query()->where('title', 'Test Listing')->firstOrFail();

    $response->assertRedirect(route('seller.products.index'));

    expect($product->location_id)->toBe($location->id);
});

test('a seller can create a pending listing with privately stored images', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Create Location']);

    $response = $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerProductTestPayload([
            'category_id' => $category->id,
            'location_id' => $location->id,
            'images' => [
                sellerProductTestFakeImage('front.jpg'),
                sellerProductTestFakeImage('back.png'),
            ],
        ])
    );

    $product = Product::query()->where('title', 'Test Listing')->firstOrFail();

    $response->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('status', 'Listing submitted for review.')
        ->assertSessionHas('status_key', 'listing_submitted');

    expect($product->seller_id)->toBe($seller->id)
        ->and($product->category_id)->toBe($category->id)
        ->and($product->location_id)->toBe($location->id)
        ->and($product->status)->toBe(Product::STATUS_PENDING)
        ->and($product->price_minor)->toBe(125000)
        ->and($product->condition)->toBe(Product::CONDITION_GOOD)
        ->and($product->slug)->toBe('test-listing');

    expect($product->images)->toHaveCount(2);

    foreach ($product->images as $image) {
        expect(str_starts_with($image->path, 'seller-products/'.$seller->id.'/'))->toBeTrue();
        Storage::disk('local')->assertExists($image->path);
    }

    $this->get(route('seller.products.index'))
        ->assertOk()
        ->assertSee('Test Listing');

    Storage::disk('public')->assertEmpty();
});

test('a new seller listing defaults to like new when condition is omitted', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Default Condition Location']);

    $payload = sellerProductTestPayload([
        'category_id' => $category->id,
        'location_id' => $location->id,
    ]);
    unset($payload['condition']);

    $this->actingAs($seller)
        ->post(route('seller.products.store'), $payload)
        ->assertRedirect(route('seller.products.index'));

    $product = Product::query()->where('seller_id', $seller->id)->latest('id')->firstOrFail();

    expect($product->condition)->toBe(Product::CONDITION_LIKE_NEW);
});

test('seller id status and rejection reason from the request are ignored on create', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();

    $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerProductTestPayload([
            'seller_id' => $otherSeller->id,
            'status' => Product::STATUS_APPROVED,
            'rejection_reason' => 'Injected reason',
        ])
    )->assertRedirect();

    $product = Product::query()->where('title', 'Test Listing')->firstOrFail();

    expect($product->seller_id)->toBe($seller->id)
        ->and($product->status)->toBe(Product::STATUS_PENDING)
        ->and($product->rejection_reason)->toBeNull();
});

test('create validation rejects invalid listing data', function (callable $overrides, string $errorKey) {
    Storage::fake('local');

    $this->actingAs(User::factory()->create())
        ->post(route('seller.products.store'), sellerProductTestPayload($overrides()))
        ->assertRedirect()
        ->assertSessionHasErrors($errorKey);

    expect(Product::query()->where('title', 'Test Listing')->exists())->toBeFalse();
    expect(ProductImage::query()->exists())->toBeFalse();
    Storage::disk('local')->assertEmpty();
})->with([
    'missing title' => [fn () => ['title' => ''], 'title'],
    'missing description' => [fn () => ['description' => ''], 'description'],
    'missing images' => [fn () => ['images' => []], 'images'],
    'too many images' => [fn () => ['images' => array_fill(0, 6, sellerProductTestFakeImage('photo.jpg'))], 'images'],
    'invalid image mime' => [fn () => ['images' => [UploadedFile::fake()->createWithContent('fake.jpg', 'not an image')->mimeType('text/plain')]], 'images.0'],
    'oversized image' => [fn () => ['images' => [UploadedFile::fake()->createWithContent('large.jpg', base64_decode(SELLER_PRODUCT_TINY_JPEG, true).str_repeat("\0", 5121 * 1024))]], 'images.0'],
    'inactive category' => [fn () => ['category_id' => Category::factory()->inactive()->create()->id], 'category_id'],
    'unknown category' => [fn () => ['category_id' => 999999], 'category_id'],
    'missing location' => [fn () => ['location_id' => ''], 'location_id'],
    'inactive location' => [fn () => ['location_id' => Location::query()->create(['name' => 'Inactive Location', 'is_active' => false])->id], 'location_id'],
    'unknown location' => [fn () => ['location_id' => 999999], 'location_id'],
    'unknown condition' => [fn () => ['condition' => 'unknown'], 'condition'],
    'zero price' => [fn () => ['price' => '0'], 'price'],
    'negative price' => [fn () => ['price' => '-1'], 'price'],
    'too many decimals' => [fn () => ['price' => '10.123'], 'price'],
    'price too high' => [fn () => ['price' => '1000001'], 'price'],
    'non numeric price' => [fn () => ['price' => 'free'], 'price'],
]);

test('the edit form preselects the listings current location', function () {
    $seller = User::factory()->create();
    $location = Location::query()->create(['name' => 'Edit Location']);
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Located Listing',
        'location_id' => $location->id,
    ]);

    $this->actingAs($seller)
        ->get(route('seller.products.edit', $product))
        ->assertOk()
        ->assertSee('value="'.$location->id.'" selected>', false);
});

test('a seller can update an unlocated listing without choosing a new location', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Legacy Listing',
        'location_id' => null,
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, [
            'title' => 'Updated Legacy Listing',
            'location_id' => '',
        ])
    )->assertRedirect(route('seller.products.show', $product))
        ->assertSessionHas('status', 'Listing updated.');

    expect($product->refresh()->title)->toBe('Updated Legacy Listing')
        ->and($product->location_id)->toBeNull();
});

test('seller product detail displays the assigned location', function () {
    $seller = User::factory()->create();
    $location = Location::query()->create(['name' => 'Seller Detail Location']);
    $product = Product::factory()->for($seller, 'seller')->create([
        'location_id' => $location->id,
    ]);

    $response = $this->actingAs($seller)->get(route('seller.products.show', $product));

    $response->assertOk()->assertSee('Seller Detail Location');

    expect($response->viewData('product')->relationLoaded('location'))->toBeTrue();
});

test('sellers cannot view edit update or delete another sellers listing', function (string $method, string $routeName, bool $withPayload) {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($otherSeller, 'seller')->create([
        'title' => 'Other Listing',
    ]);
    sellerProductTestGiveImage($product, $otherSeller);

    $payload = $withPayload
        ? sellerProductTestUpdatePayload($product, ['title' => 'Hijacked Listing'])
        : [];

    $this->actingAs($seller)->{$method}(route($routeName, $product), $payload)
        ->assertNotFound();

    expect(Product::query()->findOrFail($product->id)->title)->toBe('Other Listing');
})->with([
    'show' => ['get', 'seller.products.show', false],
    'edit' => ['get', 'seller.products.edit', false],
    'update' => ['put', 'seller.products.update', true],
    'destroy' => ['delete', 'seller.products.destroy', false],
]);

test('a seller can still update their own product', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();
    $originalLocation = Location::query()->create(['name' => 'Original Location']);
    $updatedLocation = Location::query()->create(['name' => 'Updated Location']);
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Original Listing',
        'description' => 'Original description.',
        'price_minor' => 10000,
        'category_id' => $category->id,
        'location_id' => $originalLocation->id,
        'condition' => Product::CONDITION_GOOD,
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, [
            'title' => 'Updated Listing',
            'description' => 'Revised description.',
            'price' => '150.00',
            'location_id' => $updatedLocation->id,
            'condition' => Product::CONDITION_LIKE_NEW,
        ])
    )->assertRedirect(route('seller.products.show', $product))
        ->assertSessionHas('status', 'Listing updated.');

    $product->refresh();

    expect($product->title)->toBe('Updated Listing')
        ->and($product->description)->toBe('Revised description.')
        ->and($product->price_minor)->toBe(15000)
        ->and($product->category_id)->toBe($category->id)
        ->and($product->location_id)->toBe($updatedLocation->id)
        ->and($product->condition)->toBe(Product::CONDITION_LIKE_NEW)
        ->and($product->status)->toBe(Product::STATUS_PENDING);

    expect($product->images()->count())->toBe(1);
});

test('editing an existing listing retains its existing condition when condition is omitted', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create();
    $location = Location::query()->create(['name' => 'Keep Condition Location']);
    $product = Product::factory()->for($seller, 'seller')->for($category)->create([
        'location_id' => $location->id,
        'condition' => Product::CONDITION_FAIR,
        'status' => Product::STATUS_APPROVED,
    ]);
    sellerProductTestGiveImage($product, $seller);

    $payload = sellerProductTestUpdatePayload($product, [
        'title' => 'Condition Kept',
    ]);
    unset($payload['condition']);

    $this->actingAs($seller)
        ->put(route('seller.products.update', $product), $payload)
        ->assertRedirect(route('seller.products.show', $product));

    expect($product->refresh()->condition)->toBe(Product::CONDITION_FAIR);
});

test('another seller gets not found and cannot change the product or its images', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($otherSeller, 'seller')->approved()->create([
        'title' => 'Other Listing',
        'description' => 'Other description.',
        'price_minor' => 12345,
        'rejection_reason' => 'Original note',
    ]);
    $image = sellerProductTestGiveImage($product, $otherSeller);
    $originalPath = $image->path;
    $originalSortOrder = $image->sort_order;
    $originalSlug = $product->slug;

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, [
            'title' => 'Hijacked Listing',
            'description' => 'Hijacked description.',
            'price' => '1.00',
            'status' => Product::STATUS_REJECTED,
            'rejection_reason' => 'Hijacked note',
        ])
    )->assertNotFound();

    $product->refresh();

    expect($product->title)->toBe('Other Listing')
        ->and($product->description)->toBe('Other description.')
        ->and($product->price_minor)->toBe(12345)
        ->and($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($product->rejection_reason)->toBe('Original note')
        ->and($product->slug)->toBe($originalSlug)
        ->and($product->seller_id)->toBe($otherSeller->id);

    $image->refresh();

    expect($image->product_id)->toBe($product->id)
        ->and($image->path)->toBe($originalPath)
        ->and($image->sort_order)->toBe($originalSortOrder)
        ->and(ProductImage::query()->count())->toBe(1);
});

test('authorization runs before product specific validation on updates', function () {
    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($otherSeller, 'seller')->create();
    sellerProductTestGiveImage($product, $otherSeller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, [
            'title' => '',
            'remove_images' => [999999],
            'image_order' => [999998],
        ])
    )->assertNotFound()
        ->assertSessionHasNoErrors();
});

test('editing an approved listing with changed content returns it to pending and hides it publicly', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Approved Item',
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, ['title' => 'Updated Item'])
    )->assertRedirect(route('seller.products.show', $product))
        ->assertSessionHas('status', 'Listing updated and submitted for review.');

    $product->refresh();

    expect($product->title)->toBe('Updated Item')
        ->and($product->status)->toBe(Product::STATUS_PENDING)
        ->and($product->rejection_reason)->toBeNull();

    $this->get(route('products.show', $product->slug))->assertNotFound();
    $this->get(route('home'))->assertDontSee('Updated Item');
});

test('editing a rejected listing returns it to pending and clears the rejection reason', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->rejected()->create([
        'title' => 'Rejected Item',
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, ['description' => 'Revised description after review.'])
    )->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(Product::STATUS_PENDING)
        ->and($product->rejection_reason)->toBeNull();
});

test('an unchanged update does not reset an approved listing', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Unchanged Item',
        'location_id' => sellerProductTestLocationId(),
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product)
    )->assertRedirect()->assertSessionHas('status', 'No changes detected.');

    expect($product->refresh()->status)->toBe(Product::STATUS_APPROVED);
});

test('a seller cannot approve their own pending listing through an update', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Pending Item',
        'status' => Product::STATUS_PENDING,
    ]);
    sellerProductTestGiveImage($product, $seller);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerProductTestUpdatePayload($product, [
            'status' => Product::STATUS_APPROVED,
            'rejection_reason' => 'Should not be set',
        ])
    )->assertRedirect();

    $product->refresh();

    expect($product->status)->toBe(Product::STATUS_PENDING)
        ->and($product->rejection_reason)->toBeNull();
});

test('a seller can archive a listing and its stored images remain available', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Archivable Item',
    ]);

    $paths = [
        'seller-products/'.$seller->id.'/one.jpg',
        'seller-products/'.$seller->id.'/two.jpg',
    ];

    foreach ($paths as $path) {
        ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
        Storage::disk('local')->put($path, 'image-contents');
    }

    $this->actingAs($seller)->delete(route('seller.products.destroy', $product))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('status', 'Listing archived.')
        ->assertSessionHas('status_key', 'listing_archived');

    $product->refresh();

    expect($product->archived_at)->not->toBeNull()
        ->and($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($product->images()->whereIn('path', $paths)->count())->toBe(2);

    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
    }

    Storage::disk('public')->assertEmpty();

    $this->get(route('products.show', $product->slug))->assertNotFound();
});

test('a seller can archive a listing with order history and the order remains intact', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Sold Item',
    ]);

    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    $orderItem = OrderItem::factory()
        ->for($order, 'order')
        ->for($product, 'product')
        ->create();

    $this->actingAs($seller)->delete(route('seller.products.destroy', $product))
        ->assertRedirect(route('seller.products.index'))
        ->assertSessionHas('status', 'Listing archived.')
        ->assertSessionHas('status_key', 'listing_archived');

    $product->refresh();

    expect($product->archived_at)->not->toBeNull()
        ->and($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($orderItem->fresh()->product_id)->toBe($product->id);
});

test('a seller cannot archive the same listing twice', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->archived()->create([
        'title' => 'Already Archived Item',
    ]);

    $this->actingAs($seller)->delete(route('seller.products.destroy', $product))
        ->assertRedirect()
        ->assertSessionHas('error', 'This listing is already archived.');

    expect($product->fresh()->archived_at)->not->toBeNull();
});
