<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: ProductImage, 1: string}
 */
function productImageTestPrivateImage(Product $product, string $contents = 'private-image-contents'): array
{
    $path = 'seller-products/'.$product->seller_id.'/'.fake()->uuid().'.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, $contents);

    return [$image, $path];
}

test('the public image endpoint serves private approved images with private no store headers', function () {
    Storage::fake('local');

    $product = Product::factory()->approved()->create();
    [$image, $path] = productImageTestPrivateImage($product);

    $response = $this->get(route('product-images.show', [$product, $image]));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=0')
        ->and($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('local')->path($path));
});

test('the public image endpoint rejects products that are not publicly visible', function () {
    Storage::fake('local');

    $pendingProduct = Product::factory()->create();
    [$pendingImage] = productImageTestPrivateImage($pendingProduct);

    $this->get(route('product-images.show', [$pendingProduct, $pendingImage]))
        ->assertNotFound();

    $inactiveProduct = Product::factory()
        ->for(Category::factory()->inactive(), 'category')
        ->approved()
        ->create();
    [$inactiveImage] = productImageTestPrivateImage($inactiveProduct);

    $this->get(route('product-images.show', [$inactiveProduct, $inactiveImage]))
        ->assertNotFound();
});

test('the public image endpoint rejects products that have been rejected', function () {
    Storage::fake('local');

    $product = Product::factory()->rejected()->create();
    [$image] = productImageTestPrivateImage($product);

    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();
});

test('the public image endpoint rejects foreign invalid and missing image paths', function () {
    Storage::fake('local');

    $product = Product::factory()->approved()->create();
    [$image, $path] = productImageTestPrivateImage($product);
    $otherProduct = Product::factory()->approved()->create();

    $this->get(route('product-images.show', [$otherProduct, $image]))
        ->assertNotFound();

    $image->update(['path' => 'seller-products/9999/photo.jpg']);
    Storage::disk('local')->put('seller-products/9999/photo.jpg', 'wrong-seller-contents');

    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();

    $image->update(['path' => $path]);
    Storage::disk('local')->delete($path);

    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();
});

test('invalid private image paths cannot be served from any image endpoint', function (string $pathTemplate) {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $path = str_replace('{seller}', (string) $seller->id, $pathTemplate);
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'private-image-contents');

    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();

    $this->actingAs($seller)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertNotFound();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.products.images.show', [$product, $image]))
        ->assertNotFound();
})->with([
    'wrong seller' => ['seller-products/9999/photo.jpg'],
    'missing seller segment' => ['seller-products/photo.jpg'],
    'nested directory' => ['seller-products/{seller}/nested/photo.jpg'],
    'unsupported extension' => ['seller-products/{seller}/photo.txt'],
]);

test('legacy public images are unavailable unless the transitional flag is enabled', function () {
    Storage::fake('local');
    Storage::fake('public');

    $seller = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($seller, 'seller')->approved()->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/legacy.jpg',
    ]);
    Storage::disk('public')->put($image->path, 'legacy-image-contents');

    expect(str_starts_with($image->url(), 'data:image/svg+xml,'))->toBeTrue();

    $this->get(route('product-images.show', [$product, $image]))->assertNotFound();
    $this->actingAs($seller)->get(route('seller.products.images.show', [$product, $image]))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.products.images.show', [$product, $image]))->assertNotFound();

    config()->set('marketplace.allow_legacy_public_images', true);

    expect(str_contains($image->url(), '/products/'.$product->id.'/images/'.$image->id))->toBeTrue();

    $response = $this->get(route('product-images.show', [$product, $image]));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('private')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=0')
        ->and($response->baseResponse->getFile()->getPathname())->toBe(Storage::disk('public')->path($image->path));

    $this->actingAs($seller)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($admin)
        ->get(route('admin.products.images.show', [$product, $image]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('invalid legacy public paths cannot be served even with the transitional flag enabled', function () {
    Storage::fake('public');

    config()->set('marketplace.allow_legacy_public_images', true);

    $product = Product::factory()->approved()->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/legacy.txt',
    ]);
    Storage::disk('public')->put($image->path, 'legacy-text-contents');

    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();
});

test('each missing legacy image falls back to the shared placeholder without exposing its path', function (string $path) {
    Storage::fake('local');
    Storage::fake('public');

    $product = Product::factory()->approved()->create();
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);

    $url = $image->url();

    expect(str_starts_with($url, 'data:image/svg+xml,'))->toBeTrue()
        ->and(str_contains(rawurldecode($url), 'Image coming soon'))->toBeTrue()
        ->and(str_contains($url, $path))->toBeFalse();
})->with([
    'bicycle first' => ['products/a4f27a5f-d004-3c95-a8e0-22d54ea53bc9.jpg'],
    'bicycle second' => ['products/01aaf9e9-6aa1-389a-9ac6-a1f0a1153490.jpg'],
    'phone first' => ['products/655873b0-99fd-3644-868a-a12a17a29297.jpg'],
    'phone second' => ['products/d3ce7453-e1e3-3a00-9bb1-662aaf025e7e.jpg'],
    'desk first' => ['products/9c5f367a-9cb1-3f72-9ba6-b2d4429bfcb2.jpg'],
    'desk second' => ['products/896222f9-96b8-3649-9fe4-fba58f21b719.jpg'],
]);

test('the catalog renders a placeholder for a missing image without exposing its path', function () {
    Storage::fake('local');
    Storage::fake('public');

    $product = Product::factory()->approved()->create(['title' => 'Missing Image Listing']);
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/missing.jpg',
        'sort_order' => 0,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Missing Image Listing')
        ->assertSee($image->url(), false)
        ->assertDontSee($image->path, false);
});

test('the product detail page renders a placeholder for a missing file alongside real images', function () {
    Storage::fake('local');
    Storage::fake('public');

    $product = Product::factory()->approved()->create(['title' => 'Mixed Detail Listing']);
    $realPath = 'seller-products/'.$product->seller_id.'/real.jpg';
    $realImage = ProductImage::factory()->for($product, 'product')->create([
        'path' => $realPath,
        'sort_order' => 0,
    ]);
    Storage::disk('local')->put($realPath, 'real-image-contents');

    $missingImage = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/missing.jpg',
        'sort_order' => 1,
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Mixed Detail Listing')
        ->assertSee($realImage->url(), false)
        ->assertSee($missingImage->url(), false)
        ->assertDontSee($realPath, false)
        ->assertDontSee($missingImage->path, false);
});

test('seller and admin previews fall back to the placeholder until the private file exists', function () {
    Storage::fake('local');

    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/missing.jpg',
    ]);

    $previewUrl = $image->previewUrl();
    $adminUrl = $image->adminUrl();

    expect(str_starts_with($previewUrl, 'data:image/svg+xml,'))->toBeTrue()
        ->and(str_starts_with($adminUrl, 'data:image/svg+xml,'))->toBeTrue()
        ->and(str_contains($previewUrl, $image->path))->toBeFalse();

    expect($previewUrl)->toBe($adminUrl);

    $this->actingAs($seller)
        ->get(route('seller.products.images.show', [$product, $image]))
        ->assertNotFound();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.products.images.show', [$product, $image]))
        ->assertNotFound();

    Storage::disk('local')->put($image->path, 'real-image-contents');

    expect(str_contains($image->previewUrl(), route('seller.products.images.show', [$product, $image])))->toBeTrue()
        ->and(str_contains($image->adminUrl(), route('admin.products.images.show', [$product, $image])))->toBeTrue();
});
