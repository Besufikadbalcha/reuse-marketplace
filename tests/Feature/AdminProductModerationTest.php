<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: ProductImage, 1: string}
 */
function adminModerationTestPrivateImage(Product $product, User $seller, string $contents = 'private-image-contents'): array
{
    $path = 'seller-products/'.$seller->id.'/'.fake()->uuid().'.jpg';
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, $contents);

    return [$image, $path];
}

test('users are not administrators by default and the admin factory state grants moderation', function () {
    $user = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeFalse()
        ->and($admin->isAdmin())->toBeTrue()
        ->and(Gate::forUser($user)->allows('moderateProducts'))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('moderateProducts'))->toBeTrue();
});

test('guests are redirected from admin moderation routes', function () {
    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$product->seller_id.'/photo.jpg',
    ]);

    $routes = [
        ['get', route('admin.products.index')],
        ['get', route('admin.products.show', $product)],
        ['post', route('admin.products.approve', $product)],
        ['post', route('admin.products.reject', $product)],
        ['get', route('admin.products.images.show', [$product, $image])],
    ];

    foreach ($routes as [$method, $url]) {
        $this->{$method}($url)->assertRedirect(route('login'));
    }
});

test('non admin users receive a forbidden response from admin moderation routes', function () {
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create();
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/photo.jpg',
    ]);

    $routes = [
        ['get', route('admin.products.index')],
        ['get', route('admin.products.show', $product)],
        ['post', route('admin.products.approve', $product)],
        ['post', route('admin.products.reject', $product)],
        ['get', route('admin.products.images.show', [$product, $image])],
    ];

    foreach ($routes as [$method, $url]) {
        $this->actingAs($seller)
            ->{$method}($url, ['rejection_reason' => 'A valid reviewer note.'])
            ->assertForbidden();
    }
});

test('an admin sees only pending listings in the moderation queue', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    Product::factory()->for($seller, 'seller')->create([
        'title' => 'Pending Moderation Listing',
        'status' => Product::STATUS_PENDING,
    ]);
    Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Approved Moderation Listing',
    ]);
    Product::factory()->for($seller, 'seller')->rejected()->create([
        'title' => 'Rejected Moderation Listing',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee('Moderation queue')
        ->assertSee('Pending Moderation Listing')
        ->assertSee($seller->name)
        ->assertDontSee('Approved Moderation Listing')
        ->assertDontSee('Rejected Moderation Listing')
        ->assertSee('Moderation');
});

test('non admin navigation does not show the moderation link', function () {
    $seller = User::factory()->create();

    $this->actingAs($seller)
        ->get(route('seller.products.index'))
        ->assertOk()
        ->assertDontSee('Moderation');
});

test('moderation navigation is active only on admin routes', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($admin, 'seller')->approved()->create();
    $moderationUrl = route('admin.products.index');

    $moderationLinkIsActive = function (string $html, string $url): bool {
        $pattern = '/<a\b[^>]*\bhref=["\']'.preg_quote($url, '/').'["\'][^>]*>/i';

        if (preg_match($pattern, $html, $matches) !== 1) {
            return false;
        }

        return preg_match('/\baria-current=["\']page["\']/i', $matches[0]) === 1;
    };

    $adminResponse = $this->actingAs($admin)
        ->get($moderationUrl)
        ->assertOk()
        ->assertSee($moderationUrl, false);

    expect($moderationLinkIsActive($adminResponse->getContent(), $moderationUrl))->toBeTrue();

    $publicResponse = $this->actingAs($admin)
        ->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee($moderationUrl, false);

    expect($moderationLinkIsActive($publicResponse->getContent(), $moderationUrl))->toBeFalse();
});

test('an admin can review a listing and preview its private image safely', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Private Review Listing',
    ]);
    [$image, $path] = adminModerationTestPrivateImage($product, $seller);
    $otherProduct = Product::factory()->create();
    $otherImage = ProductImage::factory()->for($otherProduct, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/other.jpg',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertOk()
        ->assertSee('Private Review Listing')
        ->assertSee($seller->name)
        ->assertSee(route('admin.products.images.show', [$product, $image]), false);

    $response = $this->actingAs($admin)
        ->get(route('admin.products.images.show', [$product, $image]));

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->baseResponse->getFile()->getPathname())
        ->toBe(Storage::disk('local')->path($path));

    $this->actingAs($admin)
        ->get(route('admin.products.images.show', [$product, $otherImage]))
        ->assertNotFound();
});

test('an admin can approve a pending listing without publishing its private images', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
        'rejection_reason' => 'Old reviewer note.',
    ]);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);
    [$secondImage, $secondPrivatePath] = adminModerationTestPrivateImage($product, $seller, 'second-private-image-contents');

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect(route('admin.products.index'));

    $product->refresh();
    $image->refresh();
    $secondImage->refresh();

    expect($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($product->rejection_reason)->toBeNull()
        ->and($image->isPrivate())->toBeTrue()
        ->and($secondImage->isPrivate())->toBeTrue()
        ->and($image->path)->toBe($privatePath)
        ->and($secondImage->path)->toBe($secondPrivatePath)
        ->and(str_contains($image->url(), '/products/'.$product->id.'/images/'.$image->id))->toBeTrue()
        ->and(str_contains($secondImage->url(), '/products/'.$product->id.'/images/'.$secondImage->id))->toBeTrue();

    Storage::disk('local')->assertExists([$privatePath, $secondPrivatePath]);
    Storage::disk('public')->assertEmpty();

    expect(Storage::disk('local')->get($privatePath))->toBe('private-image-contents')
        ->and(Storage::disk('local')->get($secondPrivatePath))->toBe('second-private-image-contents');

    $this->get('/')->assertSee($product->title);
    $this->get(route('products.show', $product->slug))->assertOk();
});

test('approval does not write to the public disk', function () {
    Storage::fake('local');
    $publicDisk = Storage::fake('public');
    $publicDisk->put('products/unrelated.jpg', 'unrelated-public-contents');

    $guardedDisk = Mockery::mock($publicDisk)->makePartial();
    $guardedDisk->shouldNotReceive('writeStream');
    $guardedDisk->shouldNotReceive('put');
    $guardedDisk->shouldNotReceive('delete');

    Storage::set('public', $guardedDisk);

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    [$firstImage, $firstPrivatePath] = adminModerationTestPrivateImage($product, $seller, 'first-private-contents');
    [$secondImage, $secondPrivatePath] = adminModerationTestPrivateImage($product, $seller, 'second-private-contents');

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->status)->toBe(Product::STATUS_APPROVED)
        ->and($firstImage->fresh()->path)->toBe($firstPrivatePath)
        ->and($secondImage->fresh()->path)->toBe($secondPrivatePath)
        ->and(Storage::disk('local')->get($firstPrivatePath))->toBe('first-private-contents')
        ->and(Storage::disk('local')->get($secondPrivatePath))->toBe('second-private-contents')
        ->and(Storage::disk('public')->files('products'))->toBe(['products/unrelated.jpg']);

    $this->get(route('products.show', $product->slug))->assertOk();
});

test('approval does not depend on public disk checksums', function () {
    Storage::fake('local');
    $publicDisk = Storage::fake('public');
    $publicDisk->put('products/unrelated.jpg', 'unrelated-public-contents');

    $guardedDisk = Mockery::mock($publicDisk)->makePartial();
    $guardedDisk->shouldNotReceive('checksum');

    Storage::set('public', $guardedDisk);

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->status)->toBe(Product::STATUS_APPROVED)
        ->and($image->fresh()->path)->toBe($privatePath)
        ->and(Storage::disk('local')->get($privatePath))->toBe('private-image-contents')
        ->and(Storage::disk('public')->files('products'))->toBe(['products/unrelated.jpg']);
});

test('a repeated approval request cannot modify an already approved listing', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertRedirect(route('admin.products.index'));

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertSessionHas('error', 'Only pending listings can be approved.');

    expect($product->fresh()->status)->toBe(Product::STATUS_APPROVED)
        ->and($image->fresh()->path)->toBe($privatePath);

    Storage::disk('local')->assertExists($privatePath);
    Storage::disk('public')->assertEmpty();
});

test('an admin can reject a pending listing with a required reason', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), [
            'rejection_reason' => '  Images are unclear.  ',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product->refresh();

    expect($product->status)->toBe(Product::STATUS_REJECTED)
        ->and($product->rejection_reason)->toBe('Images are unclear.');

    Storage::disk('local')->assertExists($privatePath);
    Storage::disk('public')->assertEmpty();

    $this->actingAs($seller)
        ->get(route('seller.products.show', $product))
        ->assertOk()
        ->assertSee('Images are unclear.');

    auth()->logout();

    $this->get('/')->assertDontSee($product->title);
});

test('an admin cannot reject a pending listing without a reason', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->create([
        'status' => Product::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.reject', $product), [
            'rejection_reason' => '   ',
        ])
        ->assertSessionHasErrors('rejection_reason');

    expect($product->fresh()->status)->toBe(Product::STATUS_PENDING)
        ->and($product->fresh()->rejection_reason)->toBeNull();
});

test('an admin cannot approve or reject listings that are no longer pending', function (string $status, string $action, string $error) {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create(['status' => $status]);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);

    $this->actingAs($admin)
        ->post(route('admin.products.'.$action, $product), [
            'rejection_reason' => 'A valid reviewer note.',
        ])
        ->assertSessionHas('error', $error);

    expect($product->fresh()->status)->toBe($status);

    Storage::disk('local')->assertExists($privatePath);
    Storage::disk('public')->assertEmpty();
})->with([
    'approved approval' => [Product::STATUS_APPROVED, 'approve', 'Only pending listings can be approved.'],
    'rejected approval' => [Product::STATUS_REJECTED, 'approve', 'Only pending listings can be approved.'],
    'approved rejection' => [Product::STATUS_APPROVED, 'reject', 'Only pending listings can be rejected.'],
    'rejected rejection' => [Product::STATUS_REJECTED, 'reject', 'Only pending listings can be rejected.'],
]);

test('approval does not publish a listing when a private image file is missing', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    $image = ProductImage::factory()->for($product, 'product')->create([
        'path' => 'seller-products/'.$seller->id.'/missing.jpg',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertStatus(409);

    expect($product->fresh()->status)->toBe(Product::STATUS_PENDING)
        ->and($image->fresh()->isPrivate())->toBeTrue();

    Storage::disk('public')->assertEmpty();
});

test('approval rejects invalid private image paths', function (string $pathTemplate) {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()->for($seller, 'seller')->create([
        'status' => Product::STATUS_PENDING,
    ]);
    $path = str_replace('{seller}', (string) $seller->id, $pathTemplate);
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
    Storage::disk('local')->put($path, 'private-image-contents');

    $this->actingAs($admin)
        ->post(route('admin.products.approve', $product))
        ->assertStatus(409);

    expect($product->fresh()->status)->toBe(Product::STATUS_PENDING)
        ->and($image->fresh()->isPrivate())->toBeTrue();

    Storage::disk('public')->assertEmpty();
})->with([
    'wrong seller' => ['seller-products/9999/photo.jpg'],
    'missing seller segment' => ['seller-products/photo.jpg'],
    'nested directory' => ['seller-products/{seller}/nested/photo.jpg'],
    'unsupported extension' => ['seller-products/{seller}/photo.txt'],
]);

test('the admin seeder promotes only the configured existing email', function () {
    $email = 'configured-admin@example.test';
    $target = User::factory()->create(['email' => $email]);
    $other = User::factory()->create();

    config()->set('marketplace.admin_email', $email);

    $this->seed(AdminUserSeeder::class);

    expect($target->fresh()->isAdmin())->toBeTrue()
        ->and($other->fresh()->isAdmin())->toBeFalse();
});

test('the admin seeder does nothing when no admin email is configured', function () {
    $user = User::factory()->create();

    config()->set('marketplace.admin_email', null);

    $this->seed(AdminUserSeeder::class);

    expect($user->fresh()->isAdmin())->toBeFalse();
});
