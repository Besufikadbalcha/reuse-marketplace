<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\PromotionPaymentSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('payment_proofs');
});

function archivedAccessTestImage(Product $product, User $seller): ProductImage
{
    $path = 'seller-products/'.$seller->id.'/'.Str::uuid().'.jpg';
    Storage::disk('local')->put($path, 'private-image-contents');

    return ProductImage::factory()->for($product, 'product')->create(['path' => $path]);
}

function archivedAccessTestPendingPromotion(User $seller, Product $product): array
{
    $package = PromotionPackage::factory()->basic()->create([
        'price_minor' => 1500,
        'duration_days' => 3,
    ]);

    $promotion = ProductPromotion::factory()
        ->paymentPending()
        ->for($seller, 'seller')
        ->for($product)
        ->create(['package_id' => $package->id]);

    $contents = 'archived access payment proof';
    $path = sprintf(
        'promotion-payment-proofs/%d/%d/attempt-1/%s.jpg',
        (int) $seller->id,
        (int) $promotion->id,
        (string) Str::uuid(),
    );
    Storage::disk('payment_proofs')->put($path, $contents);

    $submission = PromotionPaymentSubmission::factory()
        ->telebirr()
        ->for($promotion, 'promotion')
        ->create([
            'attempt' => 1,
            'proof_path' => $path,
            'proof_checksum' => hash('sha256', $contents),
            'transaction_reference' => 'ARCHIVE-001',
            'transaction_details' => 'Payment details.',
        ]);

    return [$promotion, $submission];
}

test('an archived account cannot log in', function () {
    $seller = User::factory()->archived()->create();

    $this->post(route('login.store'), [
        'email' => $seller->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email', __('auth.archived'));

    $this->assertGuest();
});

test('the archived login message follows the active locale', function (string $locale, string $message) {
    $seller = User::factory()->archived()->create();

    $this->withSession(['locale' => $locale])
        ->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email', $message);

    $this->assertGuest();
})->with([
    'Amharic' => ['am', 'ይህ መለያ ተደብቋል።'],
    'Afaan Oromoo' => ['om', 'Akkaunti kun dhokateera.'],
]);

test('an existing session is logged out when the account is archived', function () {
    $seller = User::factory()->archived()->create();

    $this->actingAs($seller)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email', __('auth.archived'));

    $this->assertGuest();
});

test('archived sellers cannot use authenticated seller routes', function (string $routeName) {
    $seller = User::factory()->archived()->create();
    $product = Product::factory()->for($seller, 'seller')->create();

    $this->actingAs($seller)
        ->get(route($routeName, $product))
        ->assertRedirect(route('login'));

    $this->assertGuest();
})->with([
    'seller listing detail' => 'seller.products.show',
    'seller listing edit' => 'seller.products.edit',
    'seller promotion request' => 'seller.promotions.create',
]);

test('archived sellers cannot submit listings or promotion resubmissions', function () {
    $seller = User::factory()->archived()->create();
    $product = Product::factory()->for($seller, 'seller')->create();

    [$promotion] = archivedAccessTestPendingPromotion($seller, $product);

    $this->actingAs($seller)
        ->post(route('seller.promotions.resubmit.store', $promotion), [])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('listings belonging to an archived seller are hidden from every public surface', function () {
    $seller = User::factory()->archived()->create(['name' => 'Archived Surface Seller']);
    $category = Category::factory()->create(['name' => 'Archived Surface Category']);

    $hidden = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Archived Surface Listing',
            'slug' => 'archived-surface-listing',
        ]);
    $image = archivedAccessTestImage($hidden, $seller);

    $visible = Product::factory()
        ->for(User::factory()->create(), 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Active Surface Listing']);

    foreach ([
        route('home'),
        route('categories.show', $category),
        route('home', ['q' => 'Archived Surface']),
    ] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertDontSee('Archived Surface Listing')
            ->assertSee('Active Surface Listing');
    }

    $this->get(route('products.show', $hidden->slug))->assertNotFound();
    $this->get(route('product-images.show', [$hidden, $image]))->assertNotFound();

    $buyer = User::factory()->create();

    $this->actingAs($buyer)
        ->get(route('checkout.create', $hidden))
        ->assertNotFound();

    $this->actingAs($buyer)
        ->post(route('checkout.store', $hidden), [
            'idempotency_key' => (string) Str::uuid(),
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        ])
        ->assertNotFound();

    expect(Order::query()->count())->toBe(0);
});

test('admin promotion approval is blocked for a listing hidden by seller archive', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->archived()->create();
    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->create();
    [$promotion, $submission] = archivedAccessTestPendingPromotion($seller, $product);

    $this->actingAs($admin)
        ->post(route('admin.promotions.payments.approve', [$promotion, $submission]))
        ->assertSessionHas('error', __('This promotion cannot be activated because its listing is no longer approved.'));

    expect($promotion->fresh()->status)->toBe(ProductPromotion::STATUS_PAYMENT_PENDING)
        ->and($submission->fresh()->status)->toBe(PromotionPaymentSubmission::STATUS_PENDING);
});

test('individually archived listings stay hidden after their seller is restored', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->archived()->create();

    $product = Product::factory()
        ->for($seller, 'seller')
        ->approved()
        ->sellerArchived()
        ->create([
            'title' => 'Individual Archive After Restore',
            'slug' => 'individual-archive-after-restore',
            'archived_at' => now(),
        ]);

    $this->actingAs($admin)
        ->post(route('admin.sellers.restore', $seller))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), ['products' => [$product->id]])
        ->assertStatus(409);

    $this->get(route('products.show', $product->slug))->assertNotFound();

    expect($product->fresh()->archived_at)->not->toBeNull()
        ->and($product->fresh()->seller_archived_at)->not->toBeNull();
});

test('eligible listings become publicly visible again after selective restoration', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->create();
    $category = Category::factory()->create(['name' => 'Restored Category']);

    $product = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->sellerArchived()
        ->create([
            'title' => 'Restored Surface Listing',
            'slug' => 'restored-surface-listing',
        ]);

    $this->get(route('products.show', $product->slug))->assertNotFound();

    $this->actingAs($admin)
        ->post(route('admin.sellers.listings.restore', $seller), ['products' => [$product->id]])
        ->assertRedirect();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Restored Surface Listing');

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Restored Surface Listing');
});
