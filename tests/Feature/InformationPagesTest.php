<?php

use App\Models\User;

test('renders the how it works page with its title, description, and steps', function () {
    $response = $this->get(route('how-it-works'));

    $response->assertOk()
        ->assertSee('<title>How It Works - '.config('app.name').'</title>', false)
        ->assertSee('name="description" content="Browse approved second-hand listings, create an account to buy or sell, and follow your order from placement to completion."', false)
        ->assertSee('Good items, simple steps')
        ->assertSee('Browse without logging in')
        ->assertSee('Register or log in to take part')
        ->assertSee('Sellers create and submit a listing')
        ->assertSee('Admin review before approval')
        ->assertSee('Choose pickup or cash on delivery')
        ->assertSee('Confirm receipt and complete')
        ->assertSee('At handover, the seller records cash received. After that, you confirm in your order that you received the item. Once you confirm receipt, the seller can mark the item complete and the order is completed.');
});

test('renders the about us page with its title, description, and mission', function () {
    $response = $this->get(route('about-us'));

    $response->assertOk()
        ->assertSee('<title>About Us - '.config('app.name').'</title>', false)
        ->assertSee('name="description" content="Learn about Reuse Marketplace, a marketplace in Ethiopia connecting buyers and sellers of second-hand goods."', false)
        ->assertSee('A marketplace for second chances')
        ->assertSee('What Reuse Marketplace is')
        ->assertSee('Our purpose in Ethiopia')
        ->assertSee('How buyers and sellers meet')
        ->assertSee('Less waste')
        ->assertSee('Affordable shopping')
        ->assertSee('A trustworthy marketplace');
});

test('navigation links point to the dedicated information page routes', function (string $routeName) {
    $response = $this->get(route($routeName));

    $response->assertOk()
        ->assertSee('href="'.route('how-it-works').'"', false)
        ->assertSee('href="'.route('about-us').'"', false);

    expect(parse_url(route('how-it-works'), PHP_URL_PATH))->toBe('/how-it-works')
        ->and(parse_url(route('about-us'), PHP_URL_PATH))->toBe('/about-us');
})->with([
    'home' => 'home',
    'login' => 'login',
    'how it works' => 'how-it-works',
    'about us' => 'about-us',
]);

test('renders the how it works page in each supported locale', function (string $locale, string $heading) {
    $this->withSession(['locale' => $locale])
        ->get(route('how-it-works'))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($heading);
})->with([
    'English' => ['en', 'Good items, simple steps'],
    'Amharic' => ['am', 'ጥሩ እቃዎች፣ ቀላል ደረጃዎች'],
    'Afaan Oromoo' => ['om', 'Meeshaalee gaariif tarkaanfii salphaa'],
]);

test('renders the about us page in each supported locale', function (string $locale, string $heading) {
    $this->withSession(['locale' => $locale])
        ->get(route('about-us'))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee($heading);
})->with([
    'English' => ['en', 'A marketplace for second chances'],
    'Amharic' => ['am', 'ሁለተኛ ዕድል ለሚሹ እቃዎች'],
    'Afaan Oromoo' => ['om', 'Gabaa jireenya lammaffaa'],
]);

test('guests can open each information page', function (string $routeName) {
    $this->get(route($routeName))->assertOk();
})->with([
    'how it works' => 'how-it-works',
    'about us' => 'about-us',
]);

test('authenticated users can open each information page', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertOk();
})->with([
    'how it works' => 'how-it-works',
    'about us' => 'about-us',
]);

test('renders dark mode styling on each information page', function (string $routeName) {
    $this->withSession(['theme' => 'dark'])
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('class="dark"', false)
        ->assertSee('dark:bg-forest-950/70', false);
})->with([
    'how it works' => 'how-it-works',
    'about us' => 'about-us',
]);
