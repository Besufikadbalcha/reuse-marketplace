<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Storage;

test('seeded products reference real private image files and reseeding is idempotent', function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed(ProductSeeder::class);

    $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();
    $products = Product::query()->whereIn('slug', ['city-bicycle', 'smart-phone', 'wooden-desk'])->get();

    expect($products)->toHaveCount(3);

    foreach ($products as $product) {
        expect($product->images)->toHaveCount(2);

        foreach ($product->images as $image) {
            expect($image->isPrivate())->toBeTrue()
                ->and($image->isPrivateForSeller($seller->id))->toBeTrue()
                ->and($image->path)->toBe('seller-products/'.$seller->id.'/'.$product->slug.'-'.($image->sort_order + 1).'.png')
                ->and($image->isServable())->toBeTrue();

            Storage::disk('local')->assertExists($image->path);
            Storage::disk('public')->assertMissing($image->path);
        }
    }

    Storage::disk('local')->assertCount('seller-products/'.$seller->id, 6);
    expect(ProductImage::query()->count())->toBe(6);

    $this->seed(ProductSeeder::class);

    Storage::disk('local')->assertCount('seller-products/'.$seller->id, 6);
    expect(ProductImage::query()->count())->toBe(6);
});

test('seeded images render through protected public urls', function () {
    Storage::fake('local');

    $this->seed(ProductSeeder::class);

    $product = Product::query()->where('slug', 'city-bicycle')->firstOrFail();
    $image = $product->images()->orderBy('sort_order')->firstOrFail();

    expect(str_starts_with($image->url(), 'data:image/svg+xml,'))->toBeFalse()
        ->and(str_contains($image->url(), route('product-images.show', [$product, $image])))->toBeTrue();

    $this->get($image->url())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee(route('product-images.show', [$product, $image]), false)
        ->assertDontSee($image->path, false);
});

test('sellers can preview seeded images', function () {
    Storage::fake('local');

    $this->seed(ProductSeeder::class);

    $seller = User::query()->where('email', 'seller@example.com')->firstOrFail();
    $product = Product::query()->where('slug', 'city-bicycle')->firstOrFail();
    $image = $product->images()->orderBy('sort_order')->firstOrFail();

    expect(str_starts_with($image->previewUrl(), 'data:image/svg+xml,'))->toBeFalse()
        ->and(str_contains($image->previewUrl(), route('seller.products.images.show', [$product, $image])))->toBeTrue();

    $this->actingAs($seller)
        ->get($image->previewUrl())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('admins can preview seeded images', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();

    $this->seed(ProductSeeder::class);

    $product = Product::query()->where('slug', 'city-bicycle')->firstOrFail();
    $image = $product->images()->orderBy('sort_order')->firstOrFail();

    expect(str_starts_with($image->adminUrl(), 'data:image/svg+xml,'))->toBeFalse()
        ->and(str_contains($image->adminUrl(), route('admin.products.images.show', [$product, $image])))->toBeTrue();

    $this->actingAs($admin)
        ->get($image->adminUrl())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('seeding converts legacy seeded image records to private paths', function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed(CategorySeeder::class);

    $seller = User::factory()->create(['email' => 'seller@example.com']);
    $product = Product::factory()->for($seller, 'seller')->create([
        'slug' => 'city-bicycle',
        'title' => 'City Bicycle',
    ]);
    $firstImage = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/legacy-first.jpg',
        'sort_order' => 0,
    ]);
    $secondImage = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'products/legacy-second.jpg',
        'sort_order' => 1,
    ]);

    $this->seed(ProductSeeder::class);

    expect($product->images()->count())->toBe(2)
        ->and($firstImage->fresh()->path)->toBe('seller-products/'.$seller->id.'/city-bicycle-1.png')
        ->and($secondImage->fresh()->path)->toBe('seller-products/'.$seller->id.'/city-bicycle-2.png');

    Storage::disk('local')->assertExists([
        $firstImage->fresh()->path,
        $secondImage->fresh()->path,
    ]);
    Storage::disk('public')->assertEmpty();
});

test('seeded products appear in the public catalog', function () {
    Storage::fake('local');

    $this->seed(ProductSeeder::class);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('City Bicycle')
        ->assertSee('Smart Phone')
        ->assertSee('Wooden Desk');
});
