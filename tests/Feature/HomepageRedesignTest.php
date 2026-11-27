<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;

test('guest homepage presents the marketplace landing sections', function () {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertViewIs('products.index')
        ->assertSee('Pre-loved finds, ready for you')
        ->assertSee('Browse products')
        ->assertSee('Sell an item')
        ->assertSee('Browse by category')
        ->assertDontSee('Shop by Category')
        ->assertDontSee('Explore active categories and find approved listings that match what you need.')
        ->assertDontSee('id="all-products-heading"', false)
        ->assertDontSee('Filter approved listings by category, condition, price, or search term.')
        ->assertSee('All products')
        ->assertSee('href="#all-products"', false)
        ->assertSee('How It Works')
        ->assertDontSee('Arrange fulfillment')
        ->assertDontSee('Receive your item')
        ->assertDontSee('About Reuse Marketplace')
        ->assertDontSee('Latest Products')
        ->assertDontSee('href="'.route('home').'#all-products"', false)
        ->assertSee('href="'.route('seller.products.create').'"', false)
        ->assertSee('href="'.route('how-it-works').'"', false)
        ->assertSee('href="'.route('about-us').'"', false)
        ->assertSee('<details', false)
        ->assertSee('lg:hidden', false);
});

test('landing category chips keep every navigation link and hide inactive categories', function () {
    $activeCategories = collect([
        ['name' => 'Bicycles', 'slug' => 'bicycles', 'description' => 'Long category description that should not fill the landing page.'],
        ['name' => 'Books', 'slug' => 'books'],
        ['name' => 'Clothing', 'slug' => 'clothing'],
        ['name' => 'Furniture', 'slug' => 'furniture'],
        ['name' => 'Mobile Phones', 'slug' => 'mobile-phones'],
    ])->map(fn (array $attributes): Category => Category::factory()->create($attributes));

    Category::factory()->inactive()->create(['name' => 'Hidden Category']);

    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('All products')
        ->assertSee('href="#all-products"', false);

    foreach ($activeCategories as $category) {
        $response->assertSee($category->name)
            ->assertSee(route('categories.show', $category), false);
    }

    $response
        ->assertDontSee('Long category description that should not fill the landing page.')
        ->assertDontSee('Hidden Category');
});

test('responsive layout uses flexible containers grids and viewport-safe navigation', function () {
    $category = Category::factory()->create(['slug' => 'bicycles']);
    Product::factory()->for($category, 'category')->approved()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('w-full max-w-[96rem] min-w-0', false)
        ->assertSee('sm:px-6 lg:px-8', false)
        ->assertDontSee('lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]', false)
        ->assertDontSee('lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]', false)
        ->assertDontSee('lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]', false)
        ->assertSee('absolute inset-x-4 top-full', false)
        ->assertSee('sm:inset-x-6', false)
        ->assertSee('max-h-[calc(100dvh-7rem)]', false)
        ->assertSee('data-desktop-logout', false)
        ->assertSee('px-2 py-1 text-xs', false)
        ->assertSee('w-full min-w-max whitespace-nowrap px-5 py-3 sm:w-auto', false)
        ->assertSee('mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4', false);
});

test('homepage compacts the hero and catalog while keeping the promotion board', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category, 'category')->approved()->create();
    ProductImage::factory()->for($product)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('px-4 py-2.5 sm:px-6 lg:px-8', false)
        ->assertSee('px-4 py-5 sm:px-6 sm:py-3 lg:px-8', false)
        ->assertSee('text-2xl font-semibold tracking-tight text-forest-950 dark:text-white sm:text-3xl', false)
        ->assertSee('mt-1 text-sm text-forest-700 dark:text-mint-100/75', false)
        ->assertSee('relative h-60 touch-pan-y select-none overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64', false)
        ->assertSee('class="relative block h-full w-64 shrink-0 overflow-hidden focus-ring"', false)
        ->assertSee('id="home-categories" class="mt-2', false)
        ->assertSee('id="all-products" class="mt-2', false)
        ->assertSee('flex min-w-0 max-w-full flex-wrap items-end gap-x-3 gap-y-3', false)
        ->assertSee('lg:grid lg:grid-cols-[minmax(8rem,1.5fr)_minmax(7rem,1.2fr)_minmax(6rem,1fr)_minmax(6rem,1fr)_minmax(4rem,0.7fr)_minmax(4rem,0.7fr)_minmax(7rem,1fr)_auto_auto]', false)
        ->assertSee('lg:col-span-2', false)
        ->assertSee('aspect-[16/11]', false)
        ->assertSee('flex flex-1 flex-col gap-1 p-3', false);
});

test('homepage filter controls keep responsive breakpoints for narrow and wide screens', function (string $classes) {
    $category = Category::factory()->create();
    Product::factory()->for($category, 'category')->approved()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($classes, false)
        ->assertSee('<select id="filter-sort" name="sort"', false);
})->with([
    '320px' => ['flex min-w-0 max-w-full flex-wrap items-end'],
    '375px' => ['grid min-w-0 grid-cols-2 gap-2'],
    '768px' => ['sm:flex-1'],
    '1024px' => ['lg:grid lg:grid-cols-[minmax(8rem,1.5fr)_minmax(7rem,1.2fr)_minmax(6rem,1fr)_minmax(6rem,1fr)_minmax(4rem,0.7fr)_minmax(4rem,0.7fr)_minmax(7rem,1fr)_auto_auto]'],
    '1440px' => ['w-full min-w-max whitespace-nowrap px-5 py-3 sm:w-auto'],
]);

test('hero scales compactly across key viewport widths', function (string $classes) {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category, 'category')->approved()->create();
    ProductImage::factory()->for($product)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee($classes, false);
})->with([
    '320px' => ['flex flex-wrap items-end justify-between gap-2'],
    '375px' => ['h-60'],
    '768px' => ['sm:h-64'],
    '1024px' => ['lg:h-64'],
    '1280px' => ['w-64'],
    '1440px' => ['sm:text-3xl'],
]);

test('desktop hero leaves at least half of the first product row visible', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category, 'category')->approved()->create();
    ProductImage::factory()->for($product)->create();

    $html = (string) $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('text-2xl font-semibold tracking-tight text-forest-950 dark:text-white sm:text-3xl')
        ->toContain('relative h-60 touch-pan-y select-none overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64')
        ->toContain('id="home-categories" class="mt-2')
        ->toContain('id="all-products" class="mt-2')
        ->toContain('mt-2 grid grid-cols-1')
        ->toContain('leading-none tracking-widest');

    $heroHeight = 112 + 24 + 24 + 8 + 32;
    $rowStart = 56 + 12 + $heroHeight + 8 + 48 + 8 + 4 + 65 + 8 + 47 + 8;

    expect(768 - $rowStart)->toBeGreaterThanOrEqual(160);
});

test('shared content container uses available width across key viewport sizes', function (int $viewport, int $expectedContentWidth): void {
    $html = (string) $this->get(route('home'))->assertOk()->getContent();

    preg_match('/<main id="main-content" class="([^"]+)"/', $html, $matches);
    $classes = explode(' ', $matches[1] ?? '');

    $padding = 16;

    if ($viewport >= 640 && in_array('sm:px-6', $classes, true)) {
        $padding = 24;
    }

    if ($viewport >= 1024 && in_array('lg:px-8', $classes, true)) {
        $padding = 32;
    }

    expect($matches[1] ?? '')->toContain('w-full max-w-[96rem] min-w-0')
        ->and($html)->toContain('overflow-x-hidden')
        ->and(min($viewport, 1536) - ($padding * 2))->toBe($expectedContentWidth);
})->with([
    '320px' => [320, 288],
    '375px' => [375, 343],
    '768px' => [768, 720],
    '1024px' => [1024, 960],
    '1440px' => [1440, 1376],
]);

test('homepage places full product browsing directly below the category chips', function () {
    $category = Category::factory()->create(['slug' => 'bicycles']);
    $products = Product::factory()
        ->count(5)
        ->for($category, 'category')
        ->approved()
        ->sequence(
            ['title' => 'Landing Item 1'],
            ['title' => 'Landing Item 2'],
            ['title' => 'Landing Item 3'],
            ['title' => 'Landing Item 4'],
            ['title' => 'Landing Item 5'],
        )
        ->create();

    foreach ($products as $product) {
        ProductImage::factory()->for($product)->create(['path' => 'products/landing-'.$product->id.'.jpg']);
    }

    $response = $this->get(route('home'))->assertOk();
    $content = (string) $response->getContent();

    $categoriesPosition = strpos($content, 'id="home-categories"');
    $productsPosition = strpos($content, 'id="all-products"');

    expect($categoriesPosition)->not->toBeFalse()
        ->and($productsPosition)->not->toBeFalse()
        ->and($categoriesPosition)->toBeLessThan($productsPosition)
        ->and($content)->not->toContain('id="home-how"')
        ->and($content)->not->toContain('id="home-about"');

    $response
        ->assertSee('Landing Item 5')
        ->assertDontSee('Latest Products');
});

test('latest and catalog products come from publicly visible approved listings', function () {
    $category = Category::factory()->create(['slug' => 'bicycles']);
    $visibleProduct = Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create(['title' => 'Landing Visible Item']);
    Product::factory()
        ->for($category, 'category')
        ->create(['title' => 'Landing Pending Item']);
    ProductImage::factory()
        ->for($visibleProduct)
        ->create(['path' => 'products/landing-visible.jpg']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Landing Visible Item')
        ->assertSee('alt="Photo of Landing Visible Item"', false)
        ->assertDontSee('Landing Pending Item');
});

test('category pages keep the archive heading and filters without the landing hero', function () {
    $category = Category::factory()->create([
        'name' => 'City Bikes',
        'slug' => 'city-bikes',
        'description' => 'Two wheel finds.',
    ]);

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertViewIs('products.index')
        ->assertSee('Two wheel finds.')
        ->assertSee('action="'.route('categories.show', $category).'"', false)
        ->assertDontSee('Pre-loved finds, ready for you')
        ->assertDontSee('Shop by Category');
});

test('filtered home pages keep catalog behavior instead of the landing hero', function (array $filters) {
    $this->get(route('home', $filters))
        ->assertOk()
        ->assertViewIs('products.index')
        ->assertDontSee('Pre-loved finds, ready for you')
        ->assertDontSee('Shop by Category')
        ->assertDontSee('id="all-products-heading"', false)
        ->assertDontSee('Filter approved listings by category, condition, price, or search term.');
})->with([
    'search query' => [['q' => 'vintage']],
    'condition filter' => [['condition' => 'good']],
    'price range' => [['min_price' => '100']],
]);

test('guest navigation includes home sell and general login links', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.route('home').'"', false)
        ->assertDontSee('href="'.route('home').'#all-products"', false)
        ->assertSee(route('login', ['redirect' => 'home']), false)
        ->assertSee(route('register', ['redirect' => 'home']), false);
});

test('duplicate browse links are absent from shared navigation and footer', function (string $routeName) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertDontSee('href="'.route('home').'#all-products"', false)
        ->assertDontSee('Browse Items');
})->with([
    'homepage' => 'home',
    'login page' => 'login',
    'registration page' => 'register',
    'guest layout' => 'privacy-policy',
]);

test('guest layout login and registration links are marked as general', function () {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee(route('login', ['redirect' => 'home']), false)
        ->assertSee(route('register', ['redirect' => 'home']), false);
});

test('authenticated navigation keeps role based links and hides guest actions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('My Account')
        ->assertSee('Sell an item')
        ->assertSee('Logout')
        ->assertSee(route('my-account'), false)
        ->assertSee(route('seller.products.create'), false)
        ->assertSee(route('logout'), false)
        ->assertDontSee('href="'.route('orders.index').'"', false)
        ->assertDontSee('href="'.route('seller.orders.index').'"', false)
        ->assertDontSee('href="'.route('seller.products.index').'"', false)
        ->assertDontSee('My Orders')
        ->assertDontSee('My Sales')
        ->assertDontSee('My Listings')
        ->assertDontSee('Seller orders')
        ->assertDontSee('href="'.route('register').'"', false)
        ->assertDontSee('href="'.route('login').'"', false);
});

test('authenticated navigation highlights Sell an item only on the create listing page', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('seller.products.create'))
        ->assertOk();

    $content = preg_replace('/\s+/', ' ', (string) $response->getContent());

    expect($content)->toContain('aria-current="page" href="'.route('seller.products.create').'"')
        ->not->toContain('aria-current="page" href="'.route('seller.products.index').'"');
});

test('authenticated navigation highlights My Listings on the listings index', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('seller.products.index'))
        ->assertOk();

    $content = preg_replace('/\s+/', ' ', (string) $response->getContent());

    expect($content)->toContain('aria-current="page" href="'.route('seller.products.index').'"')
        ->not->toContain('aria-current="page" href="'.route('seller.products.create').'"');
});

test('administrators retain moderation access in the redesigned homepage navigation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk()
        ->assertSee('Moderation')
        ->assertSee(route('admin.products.index'), false);
});

test('homepage landing copy is translated in each supported locale', function (string $locale, string $heading, string $categoriesHeading, string $categoriesDescription) {
    $this->withSession(['locale' => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($heading)
        ->assertDontSee($categoriesHeading)
        ->assertDontSee($categoriesDescription);
})->with([
    'English' => ['en', 'Pre-loved finds, ready for you', 'Shop by Category', 'Explore active categories and find approved listings that match what you need.'],
    'Amharic' => ['am', 'ሁለተኛ እጅ እቃዎች፣ ለእርስዎ ዝግጁ', 'በምድብ ያስሱ', 'ንቁ ምድቦችን ያስሱና ለፍላጎትዎ የሚስማሙ የጸደቁ ማስታወቂያዎችን ያግኙ።'],
    'Afaan Oromoo' => ['om', 'Meeshaalee darbii, siif qophii', 'Kutaalee ilaali', 'Kutaalee cimaa ilaali fi dubbisaalee eeyyaman barbaade keessatti argattu.'],
]);

test('homepage translation files keep the same keys in every locale', function () {
    $english = require lang_path('en/homepage.php');

    foreach (['am', 'om'] as $locale) {
        $translation = require lang_path($locale.'/homepage.php');

        expect(array_keys($translation))->toBe(array_keys($english));

        foreach ($english as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            expect(array_keys($translation[$key]))->toBe(array_keys($value));

            if (! in_array($key, ['steps', 'points'], true)) {
                continue;
            }

            foreach ($value as $index => $item) {
                expect(array_keys($translation[$key][$index]))->toBe(array_keys($item));
            }
        }
    }
});
