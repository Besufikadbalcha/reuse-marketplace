<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPaymentSubmission;
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
        ['delete', route('admin.products.destroy', $product)],
        ['post', route('admin.products.restore', $product)],
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
        ['delete', route('admin.products.destroy', $product)],
        ['post', route('admin.products.restore', $product)],
        ['get', route('admin.products.images.show', [$product, $image])],
    ];

    foreach ($routes as [$method, $url]) {
        $this->actingAs($seller)
            ->{$method}($url, ['rejection_reason' => 'A valid reviewer note.'])
            ->assertForbidden();
    }
});

test('an admin sees every moderation status and the listing date in the queue', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    Product::factory()->for($seller, 'seller')->create([
        'title' => 'Pending Moderation Listing',
        'status' => Product::STATUS_PENDING,
        'created_at' => '2026-11-20 10:30:00',
    ]);
    Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Approved Moderation Listing',
    ]);
    Product::factory()->for($seller, 'seller')->rejected()->create([
        'title' => 'Rejected Moderation Listing',
    ]);
    $soldProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Sold Moderation Listing',
    ]);
    OrderItem::factory()
        ->for(Order::factory()->create(['status' => Order::STATUS_CONFIRMED]))
        ->for($soldProduct, 'product')
        ->create();

    $this->actingAs($admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee('Moderation queue')
        ->assertSee('Listing date')
        ->assertSee('2026-11-20 10:30')
        ->assertSee('Pending Moderation Listing')
        ->assertSee('Approved Moderation Listing')
        ->assertSee('Rejected Moderation Listing')
        ->assertSee('Sold Moderation Listing')
        ->assertSee('Sold')
        ->assertSee($seller->name)
        ->assertSee('Moderation');
});

test('the admin listing detail shows the listing date and archive wording', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->for($admin, 'seller')->create([
        'title' => 'Detail Date Listing',
        'created_at' => '2026-11-20 10:30:00',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertOk()
        ->assertSee('Listing date')
        ->assertSee('2026-11-20 10:30')
        ->assertSee('Archive this listing?')
        ->assertSee('Archive');
});

test('admin moderation status tabs preserve the search query', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create([
        'title' => 'Searchable Queue Item',
        'status' => Product::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.products.index', ['q' => 'Searchable']))
        ->assertOk()
        ->assertSee('All')
        ->assertSee('Pending')
        ->assertSee('Approved')
        ->assertSee('Rejected')
        ->assertSee('Sold')
        ->assertSee('status=all', false)
        ->assertSee('status=sold', false)
        ->assertSee('q=Searchable', false);
});

test('admin moderation filters listings by status and sales history', function (string $status, array $expected, array $unexpected) {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();

    $pendingProduct = Product::factory()->for($seller, 'seller')->create([
        'title' => 'Queue Pending',
        'status' => Product::STATUS_PENDING,
    ]);
    $approvedProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Queue Approved',
    ]);
    $rejectedProduct = Product::factory()->for($seller, 'seller')->rejected()->create([
        'title' => 'Queue Rejected',
    ]);
    $soldProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Queue Sold',
    ]);
    $cancelledProduct = Product::factory()->for($seller, 'seller')->approved()->create([
        'title' => 'Queue Cancelled Only',
    ]);

    OrderItem::factory()
        ->for(Order::factory()->create(['status' => Order::STATUS_CONFIRMED]))
        ->for($soldProduct, 'product')
        ->create();
    OrderItem::factory()
        ->for(Order::factory()->create(['status' => Order::STATUS_CANCELLED]))
        ->for($cancelledProduct, 'product')
        ->create();

    $response = $this->actingAs($admin)
        ->get(route('admin.products.index', ['status' => $status]))
        ->assertOk();

    foreach ($expected as $title) {
        $response->assertSee($title);
    }

    foreach ($unexpected as $title) {
        $response->assertDontSee($title);
    }
})->with([
    'all' => ['all', ['Queue Pending', 'Queue Approved', 'Queue Rejected', 'Queue Sold', 'Queue Cancelled Only'], []],
    'pending' => [Product::STATUS_PENDING, ['Queue Pending'], ['Queue Approved', 'Queue Rejected', 'Queue Sold', 'Queue Cancelled Only']],
    'approved' => [Product::STATUS_APPROVED, ['Queue Approved', 'Queue Sold', 'Queue Cancelled Only'], ['Queue Pending', 'Queue Rejected']],
    'rejected' => [Product::STATUS_REJECTED, ['Queue Rejected'], ['Queue Pending', 'Queue Approved', 'Queue Sold', 'Queue Cancelled Only']],
    'sold' => ['sold', ['Queue Sold'], ['Queue Pending', 'Queue Approved', 'Queue Rejected', 'Queue Cancelled Only']],
]);

test('admin moderation search finds listings by title seller or category', function (string $search, string $expected, string $unexpected) {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create(['name' => 'Queue Seller']);
    $category = Category::factory()->create(['name' => 'Queue Category']);

    Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->create(['title' => 'Target Queue Item']);
    Product::factory()->create(['title' => 'Other Queue Item']);

    $this->actingAs($admin)
        ->get(route('admin.products.index', ['q' => $search]))
        ->assertOk()
        ->assertSee($expected)
        ->assertDontSee($unexpected);
})->with([
    'title' => ['Target', 'Target Queue Item', 'Other Queue Item'],
    'seller' => ['Queue Seller', 'Target Queue Item', 'Other Queue Item'],
    'category' => ['Queue Category', 'Target Queue Item', 'Other Queue Item'],
]);

test('admin moderation search treats wildcard characters as literal text', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->create(['title' => '100% Cotton Item']);
    Product::factory()->create(['title' => '100X Cotton Item']);

    $this->actingAs($admin)
        ->get(route('admin.products.index', ['q' => '100%']))
        ->assertOk()
        ->assertSee('100% Cotton Item')
        ->assertDontSee('100X Cotton Item');
});

test('admin moderation pagination preserves the active filters', function () {
    $admin = User::factory()->admin()->create();
    Product::factory()->count(13)->create([
        'title' => 'Paginated Queue Item',
        'status' => Product::STATUS_PENDING,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.products.index', ['status' => Product::STATUS_PENDING, 'q' => 'Paginated']))
        ->assertOk();

    expect($response->getContent())
        ->toContain('page=2')
        ->toContain('status=pending')
        ->toContain('q=Paginated');
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

test('an admin can archive pending and approved listings', function (string $status) {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->create(['title' => 'Archive Candidate', 'status' => $status]);

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('status', 'Listing Archive Candidate deleted.');

    $product->refresh();

    expect($product->status)->toBe($status)
        ->and($product->archived_at)->not->toBeNull();
})->with([
    'pending' => [Product::STATUS_PENDING],
    'approved' => [Product::STATUS_APPROVED],
]);

test('archiving a listing preserves order payment promotion and image history', function () {
    Storage::fake('local');
    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $buyer = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create(['title' => 'History Keeping Lamp']);
    [$image, $privatePath] = adminModerationTestPrivateImage($product, $seller);
    $promotion = ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->active()
        ->create();
    $submission = PromotionPaymentSubmission::factory()
        ->for($promotion, 'promotion')
        ->approved()
        ->create();
    $order = Order::factory()->for($buyer, 'buyer')->completed()->create();
    $orderItem = OrderItem::factory()->for($order)->for($product, 'product')->create();

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    $product->refresh();

    expect($product->archived_at)->not->toBeNull()
        ->and($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_ACTIVE)
        ->and($submission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_APPROVED)
        ->and($order->fresh())->not->toBeNull()
        ->and($orderItem->fresh())->not->toBeNull()
        ->and($image->fresh()->isPrivate())->toBeTrue();

    Storage::disk('local')->assertExists($privatePath);
    Storage::disk('public')->assertEmpty();
});

test('an admin cannot archive rejected or already archived listings', function (string $status, bool $archived, string $error) {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->create([
            'title' => 'Archive Guard',
            'status' => $status,
            'archived_at' => $archived ? now() : null,
        ]);

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertSessionHas('error', $error);

    if ($archived) {
        expect($product->fresh()->archived_at)->not->toBeNull();
    } else {
        expect($product->fresh()->archived_at)->toBeNull();
    }
})->with([
    'rejected' => [Product::STATUS_REJECTED, false, 'Only pending or approved listings can be deleted.'],
    'already archived' => [Product::STATUS_APPROVED, true, 'This listing is already archived.'],
]);

test('an admin can restore an archived listing without resuming its promotion', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->archived()
        ->create(['title' => 'Restore Candidate']);
    $promotion = ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->paused()
        ->create();

    $this->actingAs($admin)
        ->post(route('admin.products.restore', $product))
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('status', 'Listing Restore Candidate restored.');

    $product->refresh();

    expect($product->archived_at)->toBeNull()
        ->and($product->status)->toBe(Product::STATUS_APPROVED)
        ->and($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAUSED)
        ->and($promotion->fresh()->paused_at)->not->toBeNull();
});

test('an admin cannot restore a listing that is not archived', function () {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()->approved()->create(['title' => 'Active Candidate']);

    $this->actingAs($admin)
        ->post(route('admin.products.restore', $product))
        ->assertSessionHas('error', 'Only archived listings can be restored.');

    expect($product->fresh()->archived_at)->toBeNull();
});

test('an admin cannot review an archived listing until it is restored', function (string $action) {
    $admin = User::factory()->admin()->create();
    $product = Product::factory()
        ->create(['title' => 'Archived Review', 'status' => Product::STATUS_PENDING, 'archived_at' => now()]);

    $this->actingAs($admin)
        ->post(route('admin.products.'.$action, $product), [
            'rejection_reason' => 'A valid reviewer note.',
        ])
        ->assertSessionHas('error', 'Restore this listing before reviewing it.');

    expect($product->fresh()->status)->toBe(Product::STATUS_PENDING)
        ->and($product->fresh()->archived_at)->not->toBeNull();
})->with(['approve', 'reject']);

test('archived approved listings disappear from public surfaces but remain in the admin queue', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->archived()
        ->create(['title' => 'Zamzag Archive Cabinet']);
    [$image] = adminModerationTestPrivateImage($product, $seller);
    ProductPromotion::factory()
        ->for($seller, 'seller')
        ->for($product, 'product')
        ->active()
        ->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee($product->title);
    $this->get(route('categories.show', $product->category))
        ->assertOk()
        ->assertDontSee($product->title);
    $this->get(route('products.show', $product->slug))
        ->assertNotFound();
    $this->get(route('product-images.show', [$product, $image]))
        ->assertNotFound();
    $this->getJson(route('search.suggestions', ['q' => 'Zamzag']))
        ->assertOk()
        ->assertExactJson(['data' => []]);

    $this->actingAs($admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertSee($product->title)
        ->assertSee('Archived')
        ->assertSee(route('admin.products.restore', $product), false);

    $this->actingAs($admin)
        ->get(route('admin.products.show', $product))
        ->assertOk()
        ->assertSee('This listing is archived and hidden from the public catalog.')
        ->assertDontSee('View public listing');
});
