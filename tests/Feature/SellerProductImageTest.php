<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

const SELLER_IMAGE_TINY_JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDIzM//AABEIAAEAAQMBIgACEQEDEQH/xAAfAAABBQEBAQEBAQAAAAAAAAAAAQIDBAUGBwgJCgv/xAC1EAACAQMDAgQDBQUEBAAAAX0BAgMABBEFEiExQQYTUWEHInEUMoGRoQgjQrHBFVLR8CRjZLKCkpsNLXLy8vExg9Nzc4OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/aAAwDAQACEQMRAD8A/v4ooooA/9k=';
const SELLER_IMAGE_TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=';
const SELLER_IMAGE_TINY_WEBP = 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA';

function sellerImageTestFakeImage(string $name): UploadedFile
{
    $contents = match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'png' => base64_decode(SELLER_PRODUCT_TINY_PNG, true),
        'webp' => base64_decode(SELLER_PRODUCT_TINY_WEBP, true),
        default => base64_decode(SELLER_PRODUCT_TINY_JPEG, true),
    };

    return UploadedFile::fake()->createWithContent($name, $contents);
}

function sellerImageTestLocationId(): int
{
    return (int) Location::query()->firstOrCreate(['name' => 'Test Location'])->id;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerImageTestPayload(int $categoryId, array $overrides = []): array
{
    return array_merge([
        'title' => 'Image Test Listing',
        'description' => 'A listing used to exercise image handling.',
        'price' => '99.50',
        'category_id' => $categoryId,
        'location_id' => sellerImageTestLocationId(),
        'condition' => Product::CONDITION_LIKE_NEW,
        'images' => [sellerImageTestFakeImage('photo.jpg')],
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sellerImageTestUpdatePayload(Product $product, array $overrides = []): array
{
    return array_merge([
        'title' => $product->title,
        'description' => $product->description,
        'price' => number_format((int) $product->price_minor / 100, 2, '.', ''),
        'category_id' => $product->category_id,
        'location_id' => $product->location_id ?? sellerImageTestLocationId(),
        'condition' => $product->condition,
    ], $overrides);
}

test('guests are redirected away from private image previews', function () {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product, 'product')->create();

    $this->get(route('seller.products.images.show', [$product, $image]))
        ->assertRedirect(route('login'));
});

test('jpeg png and webp uploads are accepted', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerImageTestPayload($category->id, [
            'images' => [
                sellerImageTestFakeImage('photo.jpg'),
                sellerImageTestFakeImage('photo.png'),
                sellerImageTestFakeImage('photo.webp'),
            ],
        ])
    )->assertRedirect();

    $product = Product::query()->where('title', 'Image Test Listing')->firstOrFail();
    $extensions = $product->images->map(
        fn (ProductImage $image): string => pathinfo($image->path, PATHINFO_EXTENSION)
    )->all();

    expect($product->images)->toHaveCount(3)
        ->and($extensions)->toContain('jpg')
        ->and($extensions)->toContain('png')
        ->and($extensions)->toContain('webp');

    Storage::disk('local')->assertCount('seller-products/'.$seller->id, 3);
});

test('the create form allows selecting multiple product images', function () {
    $seller = User::factory()->create();
    Category::factory()->create();
    Location::query()->create(['name' => 'Multi Image Location']);

    $this->actingAs($seller)
        ->get(route('seller.products.create'))
        ->assertOk()
        ->assertSee('name="images[]"', false)
        ->assertSee('multiple', false)
        ->assertSee('Upload 1–5 JPEG, PNG, or WebP images.', false);
});

test('a seller can add multiple images while editing and keep existing ones', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Multi Edit Item',
    ]);
    $existingPath = 'seller-products/'.$seller->id.'/existing.jpg';
    ProductImage::factory()->for($product, 'product')->create([
        'path' => $existingPath,
        'sort_order' => 0,
    ]);
    Storage::disk('local')->put($existingPath, 'existing-image');

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, [
            'images' => [
                sellerImageTestFakeImage('front.jpg'),
                sellerImageTestFakeImage('back.png'),
            ],
        ])
    )->assertRedirect(route('seller.products.show', $product));

    expect($product->images()->count())->toBe(3)
        ->and($product->images()->where('path', $existingPath)->exists())->toBeTrue();

    Storage::disk('local')->assertCount('seller-products/'.$seller->id, 3);
    Storage::disk('local')->assertExists($existingPath);
});

test('newly uploaded images stay private and never receive a public url', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();

    $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerImageTestPayload(Category::factory()->create()->id)
    )->assertRedirect();

    $product = Product::query()->where('title', 'Image Test Listing')->firstOrFail();
    $image = $product->images->firstOrFail();

    expect($image->isPrivate())->toBeTrue()
        ->and($image->isPrivateForSeller($seller->id))->toBeTrue()
        ->and(str_contains($image->url(), '/products/'.$product->id.'/images/'.$image->id))->toBeTrue()
        ->and(str_contains($image->url(), '/storage/products/'))->toBeFalse()
        ->and(str_contains(
            $image->previewUrl(),
            '/seller/products/'.$product->id.'/images/'.$image->id
        ))->toBeTrue();

    Storage::disk('local')->assertExists($image->path);
    Storage::disk('public')->assertEmpty();
});

test('the owner can preview a private image with private no store headers', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $product = Product::factory()->for($owner, 'seller')->create();
    $path = 'seller-products/'.$owner->id.'/photo.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'private-image-contents');

    $response = $this->actingAs($owner)
        ->get(route('seller.products.images.show', [$product, $image]));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->baseResponse->getFile()->getPathname())
        ->toBe(Storage::disk('local')->path($path));
});

test('another seller cannot preview a private image', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($owner, 'seller')->create();
    $path = 'seller-products/'.$owner->id.'/photo.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'private-image-contents');

    $this->actingAs($otherSeller)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertNotFound();
});

test('a private image with a missing file returns not found', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $product = Product::factory()->for($owner, 'seller')->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$owner->id.'/missing.jpg',
    ]);

    $this->actingAs($owner)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertNotFound();
});

test('a public image path cannot be served through the private preview route', function () {
    Storage::fake('local');
    Storage::fake('public');

    $owner = User::factory()->create();
    $product = Product::factory()->for($owner, 'seller')->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/public.jpg',
    ]);
    Storage::disk('public')->put($image->path, 'public-image-contents');

    $this->actingAs($owner)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertNotFound();
});

test('updating image order works and resubmits an approved listing', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Ordered Item',
    ]);

    $first = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/one.jpg',
        'sort_order' => 0,
    ]);
    $second = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/two.jpg',
        'sort_order' => 1,
    ]);
    $third = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/three.jpg',
        'sort_order' => 2,
    ]);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, [
            'image_order' => [
                $third->id => 0,
                $first->id => 1,
                $second->id => 2,
            ],
        ])
    )->assertRedirect(route('seller.products.show', $product))
        ->assertSessionHas('status', 'Listing updated and submitted for review.');

    expect($product->images()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$third->id, $first->id, $second->id]);

    expect($product->refresh()->status)->toBe(Product::STATUS_PENDING);
    $this->get(route('products.show', $product->slug))->assertNotFound();
});

test('removing an image deletes its file and resubmits an approved listing', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Partially Removed Item',
    ]);

    $removedPath = 'seller-products/'.$seller->id.'/remove.jpg';
    $keptPath = 'seller-products/'.$seller->id.'/keep.jpg';
    $removed = ProductImage::factory()->for($product, 'product')->create([
        'path' => $removedPath,
        'sort_order' => 0,
    ]);
    ProductImage::factory()->for($product, 'product')->create([
        'path' => $keptPath,
        'sort_order' => 1,
    ]);
    Storage::disk('local')->put($removedPath, 'remove-me');
    Storage::disk('local')->put($keptPath, 'keep-me');

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, ['remove_images' => [$removed->id]])
    )->assertRedirect();

    expect($product->images()->pluck('id')->all())->not->toContain($removed->id);
    expect($product->images()->count())->toBe(1);
    expect($product->refresh()->status)->toBe(Product::STATUS_PENDING);

    Storage::disk('local')->assertMissing($removedPath);
    Storage::disk('local')->assertExists($keptPath);
});

test('the last image cannot be removed', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $path = 'seller-products/'.$seller->id.'/only.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'only-image');

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, ['remove_images' => [$image->id]])
    )->assertRedirect()->assertSessionHasErrors('images');

    expect($product->images()->count())->toBe(1);
    expect($product->refresh()->status)->toBe(Product::STATUS_APPROVED);
    Storage::disk('local')->assertExists($path);
});

test('a listing cannot be updated beyond five images', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create();

    foreach (range(1, 5) as $index) {
        $path = 'seller-products/'.$seller->id.'/image-'.$index.'.jpg';
        ProductImage::factory()->for($product, 'product')->create([
            'path' => $path,
            'sort_order' => $index - 1,
        ]);
        Storage::disk('local')->put($path, 'image-'.$index);
    }

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, [
            'images' => [sellerImageTestFakeImage('sixth.jpg')],
        ])
    )->assertRedirect()->assertSessionHasErrors('images');

    expect($product->images()->count())->toBe(5);
    Storage::disk('local')->assertCount('seller-products/'.$seller->id, 5);
});

test('foreign and duplicate image ids cannot be removed or reordered', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $otherSeller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create();

    $first = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/one.jpg',
        'sort_order' => 0,
    ]);
    $second = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/two.jpg',
        'sort_order' => 1,
    ]);
    $otherProduct = Product::factory()->for($otherSeller, 'seller')->create();
    $foreign = ProductImage::factory()->for($otherProduct, 'product')->create([
        'path' => 'seller-products/'.$otherSeller->id.'/foreign.jpg',
    ]);

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, ['remove_images' => [$foreign->id]])
    )->assertRedirect()->assertSessionHasErrors('remove_images.0');

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, ['image_order' => [$foreign->id, $first->id]])
    )->assertRedirect()->assertSessionHasErrors('image_order.0');

    $this->actingAs($seller)->put(
        route('seller.products.update', $product),
        sellerImageTestUpdatePayload($product, ['image_order' => [$first->id, $first->id]])
    )->assertRedirect()->assertSessionHasErrors('image_order.0');

    expect($product->images()->count())->toBe(2)
        ->and($product->images()->pluck('id')->all())->toContain($first->id, $second->id);
});

test('failed image uploads leave no orphaned files or records', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();

    $this->actingAs($seller)->post(
        route('seller.products.store'),
        sellerImageTestPayload(Category::factory()->create()->id, [
            'images' => [
                sellerImageTestFakeImage('valid.jpg'),
                UploadedFile::fake()->createWithContent('invalid.jpg', 'not an image')->mimeType('text/plain'),
            ],
        ])
    )->assertRedirect()->assertSessionHasErrors('images.1');

    expect(Product::query()->exists())->toBeFalse();
    expect(ProductImage::query()->exists())->toBeFalse();
    Storage::disk('local')->assertEmpty();
    Storage::disk('public')->assertEmpty();
});
