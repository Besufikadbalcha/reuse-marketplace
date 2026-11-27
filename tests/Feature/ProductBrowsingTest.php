<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductPromotion;
use App\Models\PromotionPackage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('guests can browse the public catalog', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('products.index')
        ->assertViewHas('products');
});

test('homepage product cards show the listing creation date', function () {
    $this->travelTo('2026-11-25 12:00:00');

    $seller = User::factory()->create(['name' => 'Date Seller']);
    $category = Category::factory()->create(['name' => 'Date Category', 'is_active' => true]);
    $product = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Dated Listing',
            'created_at' => '2026-11-20 08:30:00',
            'updated_at' => '2026-11-20 08:30:00',
        ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Dated Listing')
        ->assertSee(__('Listed: :date', ['date' => Product::formatDate($product->created_at)]), false);
});

test('catalog only lists approved products in active categories', function () {
    $seller = User::factory()->create(['name' => 'Bicycle Seller']);
    $activeCategory = Category::factory()->create(['name' => 'Bicycles']);
    $inactiveCategory = Category::factory()->inactive()->create();

    Product::factory()
        ->for($seller, 'seller')
        ->for($activeCategory, 'category')
        ->approved()
        ->create([
            'title' => 'Visible Bicycle',
            'price_minor' => 890000,
            'condition' => Product::CONDITION_GOOD,
        ]);

    Product::factory()->for($activeCategory, 'category')->create(['title' => 'Pending Bicycle']);
    Product::factory()->for($activeCategory, 'category')->rejected()->create(['title' => 'Rejected Bicycle']);
    Product::factory()->for($inactiveCategory, 'category')->approved()->create(['title' => 'Inactive Bicycle']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Visible Bicycle')
        ->assertSee('Bicycle Seller')
        ->assertSee('Bicycles')
        ->assertSee('Good')
        ->assertSee('ETB 8,900.00')
        ->assertDontSee('Pending Bicycle')
        ->assertDontSee('Rejected Bicycle')
        ->assertDontSee('Inactive Bicycle');
});

test('public queries exclude unavailable products from catalog category search and similar adverts', function () {
    $category = Category::factory()->create(['name' => 'Availability Category']);
    $seller = User::factory()->create(['name' => 'Availability Seller']);

    $available = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Availability Available Item']);

    $viewed = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Availability Viewed Item']);

    $reserved = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Availability Reserved Item']);
    Product::query()->whereKey($reserved->id)->update(['reservation_token' => 'RM-AVAIL001']);

    $sold = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Availability Sold Item']);
    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    OrderItem::factory()->for($order)->create(['product_id' => $sold->id]);

    $pending = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->create(['title' => 'Availability Pending Item']);

    $rejected = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->rejected()
        ->create(['title' => 'Availability Rejected Item']);

    $listingResponses = [
        $this->get(route('home')),
        $this->get(route('categories.show', $category)),
        $this->get(route('home', ['q' => 'Availability'])),
    ];

    foreach ($listingResponses as $response) {
        $response->assertOk()
            ->assertSee('Availability Available Item')
            ->assertDontSee('Availability Reserved Item')
            ->assertDontSee('Availability Sold Item')
            ->assertDontSee('Availability Pending Item')
            ->assertDontSee('Availability Rejected Item');
    }

    $this->get(route('products.show', $viewed->slug))
        ->assertOk()
        ->assertSee('Similar adverts')
        ->assertSee('Availability Available Item')
        ->assertDontSee('Availability Reserved Item')
        ->assertDontSee('Availability Sold Item')
        ->assertDontSee('Availability Pending Item')
        ->assertDontSee('Availability Rejected Item');

    foreach ([$reserved, $sold] as $product) {
        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee($product->title)
            ->assertSee('This item is no longer available.')
            ->assertDontSee('Buy now');
    }

    foreach ([$pending, $rejected] as $product) {
        $this->get(route('products.show', $product->slug))->assertNotFound();
    }

    $buyer = User::factory()->create();

    foreach ([$reserved, $sold] as $product) {
        $this->actingAs($buyer)
            ->post(route('checkout.store', $product), [
                'idempotency_key' => (string) Str::uuid(),
                'fulfillment_method' => Order::FULFILLMENT_PICKUP,
            ])
            ->assertRedirect(route('products.show', $product->slug))
            ->assertSessionHas('error', 'This item is no longer available.');
    }

    foreach ([$pending, $rejected] as $product) {
        $this->actingAs($buyer)
            ->get(route('checkout.create', $product))
            ->assertNotFound();
    }

    expect(Order::query()->where('buyer_id', $buyer->id)->count())->toBe(0)
        ->and($available->fresh()->status)->toBe(Product::STATUS_APPROVED);
});

test('catalog filters keep every field available in a spacious single row', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('flex min-w-0 max-w-full flex-wrap items-end gap-x-3 gap-y-3', false)
        ->assertSee('p-3 shadow-soft', false)
        ->assertSee('sm:p-3.5', false)
        ->assertSee('lg:grid lg:grid-cols-[minmax(8rem,1.5fr)_minmax(7rem,1.2fr)_minmax(6rem,1fr)_minmax(6rem,1fr)_minmax(4rem,0.7fr)_minmax(4rem,0.7fr)_minmax(7rem,1fr)_auto_auto]', false)
        ->assertSee('w-full min-w-0 sm:w-auto sm:flex-1', false)
        ->assertSee('lg:col-span-2', false)
        ->assertSee('w-full min-w-max whitespace-nowrap px-5 py-3 sm:w-auto', false)
        ->assertSee('w-full min-w-max whitespace-nowrap px-4 py-3 text-sm sm:w-auto', false)
        ->assertSee('name="q"', false)
        ->assertSee('name="category"', false)
        ->assertSee('name="location"', false)
        ->assertSee('data-location-filter', false)
        ->assertSee('aria-controls="filter-location-options"', false)
        ->assertSee('id="filter-location-options"', false)
        ->assertSee('max-h-[9.25rem] overflow-y-auto', false)
        ->assertSee('data-location-option', false)
        ->assertSee('name="condition"', false)
        ->assertSee('name="min_price"', false)
        ->assertSee('name="max_price"', false)
        ->assertSee('name="sort"', false)
        ->assertSee('aria-controls="filter-product-suggestions"', false)
        ->assertSee('id="filter-product-suggestions"', false)
        ->assertSee('grid-cols-2 gap-2', false)
        ->assertSee('px-3 py-3 text-sm text-forest-900', false)
        ->assertSee('py-3 text-sm text-forest-900', false)
        ->assertSee('px-4 py-2 text-sm', false)
        ->assertSee('aria-label="'.__('Price range').' (ETB)"', false)
        ->assertDontSee('<span class="sr-only">'.__('Price range').' (ETB)</span>', false)
        ->assertSee('<label class="sr-only" for="q">'.__('Search').'</label>', false)
        ->assertSee('<label class="sr-only" for="location">'.__('Location').'</label>', false)
        ->assertSee('<label class="sr-only" for="filter-category">'.__('Category').'</label>', false)
        ->assertSee('<label class="sr-only" for="filter-condition">'.__('Condition').'</label>', false)
        ->assertSee('<label class="sr-only" for="min_price">'.__('Min price').'</label>', false)
        ->assertSee('<label class="sr-only" for="max_price">'.__('Max price').'</label>', false)
        ->assertSee('<label class="sr-only" for="filter-sort">'.__('Sort by').'</label>', false)
        ->assertSee(__('No suggestions'))
        ->assertDontSee('<label for="q" class="block', false)
        ->assertDontSee('<label for="location" class="block', false)
        ->assertDontSee('<label for="filter-category" class="block', false)
        ->assertDontSee('<label for="filter-condition" class="block', false)
        ->assertSee('placeholder="'.__('Search products...').'"', false)
        ->assertSee('placeholder="'.__('Location').'"', false)
        ->assertSee('placeholder="'.__('Min price').'"', false)
        ->assertSee('placeholder="'.__('Max price').'"', false)
        ->assertSee('<option value="">'.__('All categories').'</option>', false)
        ->assertSee('<option value="">'.__('All conditions').'</option>', false)
        ->assertDontSee('id="all-products-heading"', false)
        ->assertDontSee('Filter approved listings by category, condition, price, or search term.');
});

test('filter controls stay in one ordered row with both buttons beside each other', function () {
    $previousInternalErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML((string) $this->get(route('home'))->assertOk()->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousInternalErrors);

    $form = collect(iterator_to_array($document->getElementsByTagName('form')))
        ->first(fn (DOMElement $candidate): bool => $candidate->getAttribute('method') === 'GET');

    expect($form)->not->toBeNull();

    $descriptors = collect(iterator_to_array($form->childNodes))
        ->filter(fn (DOMNode $node): bool => $node instanceof DOMElement)
        ->map(function (DOMElement $node): string {
            $inputs = $node->getElementsByTagName('input');

            if ($inputs->length > 1) {
                return 'min_price,max_price';
            }

            $control = $inputs->item(0) ?? $node->getElementsByTagName('select')->item(0);

            if ($control instanceof DOMElement) {
                return $control->getAttribute('name');
            }

            return trim($node->textContent);
        })
        ->values()
        ->all();

    expect($descriptors)->toBe([
        'q',
        'location',
        'category',
        'condition',
        'min_price,max_price',
        'sort',
        'Search',
        'Clear filters',
    ]);
});

test('location dropdown stacks above product cards without clipped parents', function () {
    $response = $this->get(route('home'))->assertOk();

    $response
        ->assertSee('class="relative z-20 flex min-w-0 max-w-full flex-wrap items-end gap-x-3 gap-y-3 rounded-2xl border border-white/70 bg-white/65 p-3 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 lg:grid lg:grid-cols-[minmax(8rem,1.5fr)_minmax(7rem,1.2fr)_minmax(6rem,1fr)_minmax(6rem,1fr)_minmax(4rem,0.7fr)_minmax(4rem,0.7fr)_minmax(7rem,1fr)_auto_auto] sm:p-3.5"', false)
        ->assertSee('class="absolute inset-x-0 top-full z-50 mt-1 max-h-[9.25rem] overflow-y-auto overscroll-contain rounded-xl border border-sage-200 bg-white/95 p-1 shadow-soft backdrop-blur-xl dark:border-white/15 dark:bg-forest-900/95"', false)
        ->assertSee('<div class="mt-1">', false);
});

test('catalog cards expose separate product and category links', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $product = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'City Bicycle']);

    $response = $this->get(route('home'));

    $response->assertOk();

    $previousInternalErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousInternalErrors);

    $anchors = iterator_to_array($document->getElementsByTagName('a'));
    $hrefs = array_map(fn (DOMElement $anchor): string => $anchor->getAttribute('href'), $anchors);
    $nestedAnchors = array_filter($anchors, fn (DOMElement $anchor): bool => $anchor->getElementsByTagName('a')->length > 0);

    expect($hrefs)->toContain(route('products.show', $product->slug))
        ->and($hrefs)->toContain(route('categories.show', $category));

    expect($nestedAnchors)->toBeEmpty();

    $this->get(route('products.show', $product->slug))->assertOk();
    $this->get(route('categories.show', $category))->assertOk();
});

test('product detail displays public listing information without seller email', function () {
    $seller = User::factory()->create([
        'name' => 'Abebe Seller',
        'email' => 'private-seller@example.com',
    ]);
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $location = Location::query()->create(['name' => 'Bole']);

    $product = Product::factory()
        ->for($seller, 'seller')
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'City Bicycle',
            'description' => 'A sturdy city bicycle.',
            'price_minor' => 1250000,
            'condition' => Product::CONDITION_GOOD,
            'location_id' => $location->id,
        ]);

    $response = $this->get(route('products.show', $product->slug));

    $response->assertOk()
        ->assertSee('w-full max-w-[96rem] min-w-0', false)
        ->assertSee('City Bicycle')
        ->assertSee('A sturdy city bicycle.')
        ->assertSee('Good')
        ->assertSee('Bicycles')
        ->assertSee('Bole')
        ->assertSee('Abebe Seller')
        ->assertSee('ETB 12,500.00')
        ->assertDontSee('private-seller@example.com');

    expect($response->viewData('product')->relationLoaded('location'))->toBeTrue();
});

test('a product belongs to a location and the location has many products', function () {
    $location = Location::query()->create(['name' => 'Relationship City']);
    $product = Product::factory()->approved()->create([
        'location_id' => $location->id,
    ]);

    expect($product->location->id)->toBe($location->id)
        ->and($location->products->pluck('id'))->toContain($product->id);
});

test('product detail returns 404 when a product is not publicly visible', function (callable $createProduct) {
    $product = $createProduct();

    $this->get(route('products.show', $product->slug))->assertNotFound();
})->with([
    'pending' => fn () => Product::factory()->create(['title' => 'Pending Item']),
    'rejected' => fn () => Product::factory()->rejected()->create(['title' => 'Rejected Item']),
    'inactive category' => fn () => Product::factory()
        ->for(Category::factory()->inactive(), 'category')
        ->approved()
        ->create(['title' => 'Inactive Item']),
]);

test('product detail loads images in ascending sort order', function () {
    $product = Product::factory()->approved()->create(['title' => 'Camera']);

    ProductImage::factory()->for($product, 'product')->create(['path' => 'products/third.jpg', 'sort_order' => 2]);
    ProductImage::factory()->for($product, 'product')->create(['path' => 'products/first.jpg', 'sort_order' => 0]);
    ProductImage::factory()->for($product, 'product')->create(['path' => 'products/second.jpg', 'sort_order' => 1]);

    $response = $this->get(route('products.show', $product->slug));

    $response->assertOk();

    expect($response->viewData('product')->images->pluck('sort_order')->all())->toBe([0, 1, 2]);
});

test('title search only returns matching products', function () {
    Product::factory()->approved()->create(['title' => 'Mountain Bike']);
    Product::factory()->approved()->create(['title' => 'Wooden Desk']);

    $this->get(route('home', ['q' => 'Mountain']))
        ->assertOk()
        ->assertSee('Mountain Bike')
        ->assertDontSee('Wooden Desk');
});

test('category filter only returns products from the selected active category', function () {
    $bicycles = Category::factory()->create(['name' => 'Bicycles']);
    $furniture = Category::factory()->create(['name' => 'Furniture']);

    Product::factory()->for($bicycles, 'category')->approved()->create(['title' => 'Road Bike']);
    Product::factory()->for($furniture, 'category')->approved()->create(['title' => 'Oak Desk']);

    $this->get(route('home', ['category' => $bicycles->id]))
        ->assertOk()
        ->assertSee('Road Bike')
        ->assertDontSee('Oak Desk');
});

test('inactive categories cannot be used as public filters', function () {
    $inactiveCategory = Category::factory()->inactive()->create();

    $this->get(route('home', ['category' => $inactiveCategory->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('category');
});

test('location filter only returns products from the selected location', function () {
    $addisAbaba = Location::query()->create(['name' => 'Addis Ababa']);
    $bole = Location::query()->create(['name' => 'Bole']);

    Product::factory()->approved()->create([
        'title' => 'Addis Bike',
        'location_id' => $addisAbaba->id,
    ]);
    Product::factory()->approved()->create([
        'title' => 'Bole Desk',
        'location_id' => $bole->id,
    ]);
    Product::factory()->approved()->create(['title' => 'Unlocated Lamp']);

    $this->get(route('home', ['location' => 'Addis Ababa']))
        ->assertOk()
        ->assertSee('Addis Bike')
        ->assertDontSee('Bole Desk')
        ->assertDontSee('Unlocated Lamp');
});

test('location dropdown offers every active database location', function () {
    $availableLocation = Location::query()->create(['name' => 'Available City']);
    $emptyLocation = Location::query()->create(['name' => 'Empty City']);
    $inactiveLocation = Location::query()->create(['name' => 'Hidden City', 'is_active' => false]);

    Product::factory()->approved()->create([
        'title' => 'Available City Item',
        'location_id' => $availableLocation->id,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertSee('data-location-name="Available City"', false)
        ->assertSee('data-location-name="Empty City"', false)
        ->assertDontSee('data-location-name="Hidden City"', false);

    expect($response->viewData('locations')->pluck('name')->all())
        ->toBe(['Available City', 'Empty City']);
});

test('location filter excludes unavailable and unapproved products', function () {
    $location = Location::query()->create(['name' => 'Filtered City']);

    $available = Product::factory()->approved()->create([
        'title' => 'Filtered Available Item',
        'location_id' => $location->id,
    ]);
    $reserved = Product::factory()->approved()->create([
        'title' => 'Filtered Reserved Item',
        'location_id' => $location->id,
    ]);
    Product::query()->whereKey($reserved->id)->update(['reservation_token' => 'RM-LOC001']);
    $pending = Product::factory()->create([
        'title' => 'Filtered Pending Item',
        'location_id' => $location->id,
    ]);

    $this->get(route('home', ['location' => 'Filtered City']))
        ->assertOk()
        ->assertSee('Filtered Available Item')
        ->assertDontSee('Filtered Reserved Item')
        ->assertDontSee('Filtered Pending Item');
});

test('unknown and inactive location values fail validation', function (string $location) {
    Location::query()->create(['name' => 'Inactive Town', 'is_active' => false]);

    $this->get(route('home', ['location' => $location]))
        ->assertRedirect()
        ->assertSessionHasErrors('location');
})->with([
    'unknown' => ['Unknown Town'],
    'inactive' => ['Inactive Town'],
]);

test('catalog keeps working when the location schema is unavailable', function () {
    Product::factory()->approved()->create(['title' => 'Still Browsable Item']);

    Schema::shouldReceive('hasTable')
        ->andReturnUsing(fn (string $table): bool => $table !== 'locations');
    Schema::shouldReceive('hasColumn')
        ->andReturnUsing(fn (string $table, string $column): bool => ! ($table === 'products' && $column === 'location_id'));

    $this->get(route('home', ['location' => 'Anywhere']))
        ->assertOk()
        ->assertSee('Still Browsable Item')
        ->assertViewHas('locations', fn ($locations): bool => $locations->isEmpty())
        ->assertDontSee('Location: Anywhere');
});

test('condition filter only returns products with the selected condition', function () {
    Product::factory()->approved()->create([
        'title' => 'Like New Phone',
        'condition' => Product::CONDITION_LIKE_NEW,
    ]);
    Product::factory()->approved()->create([
        'title' => 'Fair Condition Desk',
        'condition' => Product::CONDITION_FAIR,
    ]);

    $this->get(route('home', ['condition' => Product::CONDITION_LIKE_NEW]))
        ->assertOk()
        ->assertSee('Like New Phone')
        ->assertDontSee('Fair Condition Desk');
});

test('price range filters catalog products inclusively', function (array $filters, array $visibleTitles, array $hiddenTitles) {
    Product::factory()->approved()->create([
        'title' => 'Ten Birr Product',
        'price_minor' => 1000,
    ]);
    Product::factory()->approved()->create([
        'title' => 'Twenty Birr Product',
        'price_minor' => 2000,
    ]);
    Product::factory()->approved()->create([
        'title' => 'Thirty Birr Product',
        'price_minor' => 3000,
    ]);

    $response = $this->get(route('home', $filters))->assertOk();

    foreach ($visibleTitles as $title) {
        $response->assertSee($title);
    }

    foreach ($hiddenTitles as $title) {
        $response->assertDontSee($title);
    }
})->with([
    'minimum only' => [
        ['min_price' => '20'],
        ['Twenty Birr Product', 'Thirty Birr Product'],
        ['Ten Birr Product'],
    ],
    'maximum only' => [
        ['max_price' => '20'],
        ['Ten Birr Product', 'Twenty Birr Product'],
        ['Thirty Birr Product'],
    ],
    'inclusive range' => [
        ['min_price' => '10', 'max_price' => '30'],
        ['Ten Birr Product', 'Twenty Birr Product', 'Thirty Birr Product'],
        [],
    ],
]);

test('an inverted price range fails validation', function () {
    $this->get(route('home', ['min_price' => '500', 'max_price' => '100']))
        ->assertRedirect()
        ->assertSessionHasErrors('max_price', 'The maximum price must be greater than or equal to the minimum price.');
});

test('catalog paginates products and preserves active filters', function () {
    foreach (range('A', 'M') as $letter) {
        Product::factory()->approved()->create([
            'title' => "Item {$letter}",
            'price_minor' => ord($letter) * 100,
        ]);
    }

    $this->get(route('home', ['min_price' => '1', 'page' => 2]))
        ->assertOk()
        ->assertSee('Item A')
        ->assertDontSee('Item M')
        ->assertSee('min_price=1', false);
});

test('catalog pagination preserves the location filter and clear filters removes it', function () {
    $location = Location::query()->create(['name' => 'Page Location']);

    foreach (range('A', 'M') as $letter) {
        Product::factory()->approved()->create([
            'title' => "Item {$letter}",
            'location_id' => $location->id,
        ]);
    }

    $response = $this->get(route('home', ['location' => 'Page Location', 'page' => 2]))->assertOk();

    $response
        ->assertSee('Item A')
        ->assertDontSee('Item M')
        ->assertSee('value="Page Location"', false)
        ->assertSee('data-location-name="Page Location"', false)
        ->assertSee('aria-selected="true"', false)
        ->assertSee('location=Page', false);

    expect($response->viewData('clearFiltersUrl'))->toBe(route('home'));
});

test('invalid condition and price values fail validation', function (string $field, string $value) {
    $this->get(route('home', [$field => $value]))
        ->assertRedirect()
        ->assertSessionHasErrors($field);
})->with([
    'condition' => ['condition', 'unknown'],
    'minimum price' => ['min_price', 'unknown'],
    'negative maximum price' => ['max_price', '-1'],
]);

test('product image url falls back for hidden legacy paths and uses the protected endpoint for visible paths', function () {
    Storage::fake('local');
    Storage::fake('public');

    $product = Product::factory()->approved()->create();
    $image = ProductImage::factory()->for($product, 'product')->create(['path' => 'products/missing.jpg']);

    expect(str_starts_with($image->url(), 'data:image/svg+xml,'))->toBeTrue();

    config()->set('marketplace.allow_legacy_public_images', true);

    expect(str_starts_with($image->url(), 'data:image/svg+xml,'))->toBeTrue();

    Storage::disk('public')->put('products/present.jpg', 'fake-image');
    $image->update(['path' => 'products/present.jpg']);

    expect(str_contains($image->url(), '/products/'.$product->id.'/images/'.$image->id))->toBeTrue();

    $this->get($image->url())
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('catalog escapes user provided product titles', function () {
    Product::factory()->approved()->create(['title' => '<script>alert(1)</script> Dangerous']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('search matches product descriptions and category names', function () {
    $bicycles = Category::factory()->create(['name' => 'Bicycles']);

    Product::factory()->for($bicycles, 'category')->approved()->create([
        'title' => 'Commuter Bike',
        'description' => 'A reliable daily ride.',
    ]);
    Product::factory()->approved()->create([
        'title' => 'Oak Desk',
        'description' => 'A sturdy work surface.',
    ]);

    $this->get(route('home', ['q' => 'daily ride']))
        ->assertOk()
        ->assertSee('Commuter Bike')
        ->assertDontSee('Oak Desk');

    $this->get(route('home', ['q' => 'Bicycles']))
        ->assertOk()
        ->assertSee('Commuter Bike')
        ->assertDontSee('Oak Desk');
});

test('search treats percent and underscore as literal characters', function () {
    Product::factory()->approved()->create(['title' => '100% Cotton Sheets']);
    Product::factory()->approved()->create(['title' => '1000 Cotton Sheets']);
    Product::factory()->approved()->create(['title' => 'Desk_ Lamp']);
    Product::factory()->approved()->create(['title' => 'DeskX Lamp']);

    $this->get(route('home', ['q' => '100%']))
        ->assertOk()
        ->assertSee('100% Cotton Sheets')
        ->assertDontSee('1000 Cotton Sheets');

    $this->get(route('home', ['q' => 'Desk_']))
        ->assertOk()
        ->assertSee('Desk_ Lamp')
        ->assertDontSee('DeskX Lamp');
});

test('search never returns products that are not publicly visible', function () {
    Product::factory()->rejected()->create([
        'title' => 'Hidden Treasure',
        'description' => 'A secret map.',
    ]);
    Product::factory()->approved()->create([
        'title' => 'Visible Treasure',
        'description' => 'A secret map.',
    ]);

    $this->get(route('home', ['q' => 'secret map']))
        ->assertOk()
        ->assertSee('Visible Treasure')
        ->assertDontSee('Hidden Treasure');
});

test('reserved and sold products are absent from the public catalog', function () {
    $reservedProduct = Product::factory()->approved()->create(['title' => 'Reserved Chair']);
    $soldProduct = Product::factory()->approved()->create(['title' => 'Sold Table']);

    Product::query()->whereKey($reservedProduct->id)->update(['reservation_token' => 'RM-CARD001']);

    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    OrderItem::factory()->for($order)->create(['product_id' => $soldProduct->id]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Reserved Chair')
        ->assertDontSee('Sold Table')
        ->assertDontSee('Unavailable');
});

test('unavailable product details remain readable without a buy action', function () {
    $product = Product::factory()->approved()->create(['title' => 'Reserved Desk']);
    Product::query()->whereKey($product->id)->update(['reservation_token' => 'RM-DETAIL001']);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Reserved Desk')
        ->assertSee('This item is no longer available.')
        ->assertDontSee('Buy now')
        ->assertDontSee('Log in to buy now');
});

test('product detail page displays all uploaded images in the gallery', function () {
    Storage::fake('local');

    $product = Product::factory()->approved()->create(['title' => 'Gallery Listing']);
    $first = ProductImage::factory()->for($product)->create(['path' => 'seller-products/1/gallery-1.jpg', 'sort_order' => 0]);
    $second = ProductImage::factory()->for($product)->create(['path' => 'seller-products/1/gallery-2.jpg', 'sort_order' => 1]);

    Storage::disk('local')->put($first->path, 'first');
    Storage::disk('local')->put($second->path, 'second');

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('data-product-gallery', false)
        ->assertSee('data-product-gallery-main', false)
        ->assertSee(route('product-images.show', [$product->id, $first->id]), false)
        ->assertSee(route('product-images.show', [$product->id, $second->id]), false)
        ->assertSee('data-product-gallery-thumb', false);
});

test('product detail page shows the original listing creation date', function () {
    $this->travelTo('2026-11-27 12:00:00');

    $product = Product::factory()->approved()->create([
        'title' => 'Dated Detail Listing',
        'created_at' => '2026-11-20 08:30:00',
        'updated_at' => '2026-11-26 09:45:00',
    ]);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Dated Detail Listing')
        ->assertSee(__('Listed: :date', ['date' => Product::formatDate($product->created_at)]), false)
        ->assertDontSee(__('Listed: :date', ['date' => Product::formatDate($product->updated_at)]), false);
});

test('clicking a promoted homepage product navigates to the product detail page', function () {
    $seller = User::factory()->create();
    $category = Category::factory()->create(['is_active' => true]);
    $product = Product::factory()->for($seller, 'seller')->for($category)->approved()->create(['title' => 'Clickable Promoted Product']);
    $image = ProductImage::factory()->for($product)->create(['path' => 'seller-products/'.$seller->id.'/clickable.jpg', 'sort_order' => 0]);
    Storage::fake('local');
    Storage::disk('local')->put($image->path, 'img');

    $package = PromotionPackage::factory()->basic()->create();
    ProductPromotion::factory()->for($product)->for($seller, 'seller')->for($package, 'package')->active()->create([
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('products.show', $product->slug).'"', false)
        ->assertSee('Clickable Promoted Product');
});

test('active filters are labelled and can be cleared', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $location = Location::query()->create(['name' => 'Addis Ababa']);
    Product::factory()->for($category, 'category')->approved()->create([
        'title' => 'Road Bike',
        'location_id' => $location->id,
        'condition' => Product::CONDITION_GOOD,
    ]);

    $response = $this->get(route('home', [
        'q' => 'Road',
        'category' => $category->id,
        'location' => 'Addis Ababa',
        'condition' => Product::CONDITION_GOOD,
        'min_price' => '100',
        'max_price' => '500',
        'page' => 2,
    ]));

    $response
        ->assertOk()
        ->assertSee('Active filters:')
        ->assertSee('Search: Road')
        ->assertSee('Category: Bicycles')
        ->assertSee('Location: Addis Ababa')
        ->assertSee('Condition: Good')
        ->assertSee('Min price: 100')
        ->assertSee('Max price: 500')
        ->assertSee('Clear all filters')
        ->assertDontSee('name="page"', false);

    expect(collect($response->viewData('activeFilters'))->pluck('url', 'label')->all())->toBe([
        'Search: Road' => route('home', [
            'category' => $category->id,
            'location' => 'Addis Ababa',
            'condition' => Product::CONDITION_GOOD,
            'min_price' => '100',
            'max_price' => '500',
        ]),
        'Category: Bicycles' => route('home', [
            'q' => 'Road',
            'location' => 'Addis Ababa',
            'condition' => Product::CONDITION_GOOD,
            'min_price' => '100',
            'max_price' => '500',
        ]),
        'Location: Addis Ababa' => route('home', [
            'q' => 'Road',
            'category' => $category->id,
            'condition' => Product::CONDITION_GOOD,
            'min_price' => '100',
            'max_price' => '500',
        ]),
        'Condition: Good' => route('home', [
            'q' => 'Road',
            'category' => $category->id,
            'location' => 'Addis Ababa',
            'min_price' => '100',
            'max_price' => '500',
        ]),
        'Min price: 100' => route('home', [
            'q' => 'Road',
            'category' => $category->id,
            'location' => 'Addis Ababa',
            'condition' => Product::CONDITION_GOOD,
            'max_price' => '500',
        ]),
        'Max price: 500' => route('home', [
            'q' => 'Road',
            'category' => $category->id,
            'location' => 'Addis Ababa',
            'condition' => Product::CONDITION_GOOD,
            'min_price' => '100',
        ]),
    ]);
});

test('combined catalog filters work together', function () {
    $bicycles = Category::factory()->create(['name' => 'Bicycles']);
    $furniture = Category::factory()->create(['name' => 'Furniture']);
    $trailCity = Location::query()->create(['name' => 'Trail City']);
    $otherCity = Location::query()->create(['name' => 'Other City']);

    Product::factory()->for($bicycles, 'category')->approved()->create([
        'title' => 'Mountain Bike',
        'description' => 'Trail ready.',
        'location_id' => $trailCity->id,
        'condition' => Product::CONDITION_GOOD,
        'price_minor' => 100000,
    ]);
    Product::factory()->for($bicycles, 'category')->approved()->create([
        'title' => 'Road Bike',
        'description' => 'City ready.',
        'location_id' => $otherCity->id,
        'condition' => Product::CONDITION_FAIR,
        'price_minor' => 200000,
    ]);
    Product::factory()->for($furniture, 'category')->approved()->create([
        'title' => 'Mountain Desk',
        'description' => 'Trail ready.',
        'location_id' => $trailCity->id,
        'condition' => Product::CONDITION_GOOD,
        'price_minor' => 300000,
    ]);

    $this->get(route('home', [
        'q' => 'Trail ready',
        'category' => $bicycles->id,
        'location' => 'Trail City',
        'condition' => Product::CONDITION_GOOD,
        'min_price' => '1000',
        'max_price' => '3000',
    ]))
        ->assertOk()
        ->assertSee('Mountain Bike')
        ->assertDontSee('Road Bike')
        ->assertDontSee('Mountain Desk');
});

test('the clear price range link removes only price filters', function () {
    $response = $this->get(route('home', [
        'q' => 'bike',
        'min_price' => '10',
        'max_price' => '20',
    ]));

    $response
        ->assertOk()
        ->assertSee('Clear price range');

    $previousInternalErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML((string) $response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousInternalErrors);

    $form = collect(iterator_to_array($document->getElementsByTagName('form')))
        ->first(fn (DOMElement $candidate): bool => $candidate->getAttribute('method') === 'GET');

    expect($response->viewData('clearPriceRangeUrl'))->toBe(route('home', ['q' => 'bike']))
        ->and($form->textContent)->not->toContain('Clear price range')
        ->and($document->textContent)->toContain('Clear price range');
});

test('product detail ranks eligible similar adverts by category condition and price', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $otherCategory = Category::factory()->create(['name' => 'Desks']);

    $currentProduct = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Current Bike',
            'price_minor' => 100000,
            'condition' => Product::CONDITION_GOOD,
        ]);
    $betterConditionSameCategory = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Premium Bike',
            'price_minor' => 900000,
            'condition' => Product::CONDITION_NEW,
        ]);
    $nearSameCategory = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Near Bike',
            'price_minor' => 110000,
            'condition' => Product::CONDITION_GOOD,
        ]);
    $farSameCategory = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Far Bike',
            'price_minor' => 900000,
            'condition' => Product::CONDITION_GOOD,
        ]);
    $nearOtherCategory = Product::factory()
        ->for($otherCategory, 'category')
        ->approved()
        ->create([
            'title' => 'Near Desk',
            'price_minor' => 105000,
            'condition' => Product::CONDITION_GOOD,
        ]);

    $response = $this->get(route('products.show', $currentProduct->slug))
        ->assertOk()
        ->assertSee('Similar adverts')
        ->assertSee('Premium Bike')
        ->assertSee('Near Bike')
        ->assertSee(route('products.show', $nearSameCategory->slug), false);

    expect($response->viewData('similarProducts')->pluck('id')->all())->toBe([
        $betterConditionSameCategory->id,
        $nearSameCategory->id,
        $farSameCategory->id,
        $nearOtherCategory->id,
    ]);
});

test('product detail breaks equal similarity ties by product id', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);

    $currentProduct = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Current Bike',
            'price_minor' => 100000,
            'condition' => Product::CONDITION_GOOD,
        ]);
    $firstTie = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'First Tie Bike',
            'price_minor' => 100000,
            'condition' => Product::CONDITION_GOOD,
        ]);
    $secondTie = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'Second Tie Bike',
            'price_minor' => 100000,
            'condition' => Product::CONDITION_GOOD,
        ]);

    $response = $this->get(route('products.show', $currentProduct->slug))->assertOk();

    expect($response->viewData('similarProducts')->pluck('id')->all())->toBe([
        $firstTie->id,
        $secondTie->id,
    ]);
});

test('product detail excludes ineligible products from similar adverts', function (callable $createCandidate) {
    $category = Category::factory()->create(['name' => 'Bicycles']);
    $currentProduct = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Current Bike', 'price_minor' => 100000]);

    $candidate = $createCandidate($category);

    $response = $this->get(route('products.show', $currentProduct->slug))
        ->assertOk()
        ->assertDontSee($candidate->title);

    expect($response->viewData('similarProducts')->pluck('id'))->not->toContain($candidate->id);
})->with([
    'pending' => fn (Category $category) => Product::factory()
        ->for($category, 'category')
        ->create(['title' => 'Pending Similar']),
    'rejected' => fn (Category $category) => Product::factory()
        ->for($category, 'category')
        ->rejected()
        ->create(['title' => 'Rejected Similar']),
    'inactive category' => fn () => Product::factory()
        ->for(Category::factory()->inactive(), 'category')
        ->approved()
        ->create(['title' => 'Inactive Similar']),
    'reserved' => function (Category $category) {
        $product = Product::factory()
            ->for($category, 'category')
            ->approved()
            ->create(['title' => 'Reserved Similar']);

        Product::query()->whereKey($product->id)->update(['reservation_token' => 'RM-SIMILAR001']);

        return $product;
    },
    'sold' => function (Category $category) {
        $product = Product::factory()
            ->for($category, 'category')
            ->approved()
            ->create(['title' => 'Sold Similar']);
        $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
        OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

        return $product;
    },
]);

test('price inputs and validation share the same 0.01 minimum', function () {
    $this->get(route('home', ['min_price' => '0']))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('min_price', 'The min price field must be at least 0.01.');

    $this->get(route('home', ['min_price' => '0.01']))->assertOk();

    $response = $this->get(route('home'))->assertOk();

    $previousInternalErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument;
    $document->loadHTML((string) $response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousInternalErrors);

    $minimums = collect(iterator_to_array($document->getElementsByTagName('input')))
        ->filter(fn (DOMElement $input): bool => in_array($input->getAttribute('name'), ['min_price', 'max_price'], true))
        ->mapWithKeys(fn (DOMElement $input): array => [$input->getAttribute('name') => $input->getAttribute('min')])
        ->all();

    expect($minimums)->toBe(['min_price' => '0.01', 'max_price' => '0.01']);
});

test('title sort is preserved in pagination and filter removal URLs', function () {
    foreach (range('A', 'M') as $letter) {
        Product::factory()->approved()->create([
            'title' => "Sofa {$letter}",
            'price_minor' => 2000,
        ]);
    }

    $response = $this->get(route('home', [
        'q' => 'sofa',
        'min_price' => '10',
        'sort' => 'title_asc',
        'page' => 2,
    ]))->assertOk();

    $activeFilterUrls = collect($response['activeFilters'])->pluck('url');

    expect($activeFilterUrls)->not->toBeEmpty();
    $activeFilterUrls->each(fn (string $url) => expect($url)->toContain('sort=title_asc'));

    expect($response['products']->url(2))->toContain('sort=title_asc');
    expect($response['clearPriceRangeUrl'])->toContain('sort=title_asc');
    expect($response['clearFiltersUrl'])->not->toContain('sort=');

    $response->assertSee('sort=title_asc', false);
});

test('title sort still excludes unavailable products', function () {
    $available = Product::factory()
        ->approved()
        ->create(['title' => 'Available Chair']);

    Product::factory()->create(['title' => 'Approved Chair']);

    $reserved = Product::factory()
        ->approved()
        ->create(['title' => 'Reserved Chair']);

    Product::query()->whereKey($reserved->id)->update(['reservation_token' => 'RM-TITLE-001']);

    $sold = Product::factory()
        ->approved()
        ->create(['title' => 'Sold Chair']);

    $order = Order::factory()->for(User::factory()->create(), 'buyer')->create();
    OrderItem::factory()->for($order)->create(['product_id' => $sold->id]);

    $response = $this->get(route('home', ['sort' => 'title_asc']))->assertOk();

    expect($response['products']->pluck('id')->all())->toBe([$available->id]);
});
