<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Str;

test('defaults to English with a left-to-right document and a locale switcher', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee(route('locale.update'), false)
        ->assertSee('Pre-loved finds, ready for you');
});

test('language switcher appears exactly once on public, guest, and authenticated pages', function () {
    $user = User::factory()->create();

    $responses = [
        $this->get(route('home')),
        $this->get(route('login')),
        $this->actingAs($user)->get(route('orders.index')),
    ];

    foreach ($responses as $response) {
        $response->assertOk();

        expect(substr_count((string) $response->getContent(), 'action="'.route('locale.update').'"'))->toBe(1);
    }
});

test('renders the interface in a supported session locale', function (string $locale, string $heading) {
    $this->withSession(['locale' => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', false)
        ->assertSee('dir="ltr"', false)
        ->assertSee($heading);
})->with([
    'Amharic' => ['am', 'ሁለተኛ እጅ እቃዎች፣ ለእርስዎ ዝግጁ'],
    'Afaan Oromoo' => ['om', 'Meeshaalee darbii, siif qophii'],
]);

test('renders the interface from an encrypted locale cookie when no session preference exists', function () {
    $this->withCookie('locale', 'om')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('lang="om"', false)
        ->assertSee('Meeshaalee darbii, siif qophii');
});

test('the language switcher persists the locale in the session and an encrypted cookie', function () {
    $response = $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'am']);

    $response->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'am');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === 'locale');

    expect($cookie)->not->toBeNull()
        ->and(decrypt($cookie->getValue(), false))
        ->toBe(CookieValuePrefix::create('locale', app('encrypter')->getKey()).'am');
});

test('session preference takes precedence over a cookie preference', function () {
    $this->withSession(['locale' => 'am'])
        ->withCookie('locale', 'om')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('lang="am"', false)
        ->assertSee('ሁለተኛ እጅ እቃዎች፣ ለእርስዎ ዝግጁ');
});

test('an unsupported locale is rejected without changing the active locale', function () {
    $this->withSession(['locale' => 'am'])
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale')
        ->assertSessionHas('locale', 'am');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('ሁለተኛ እጅ እቃዎች፣ ለእርስዎ ዝግጁ');
});

test('framework validation messages use the active locale', function () {
    $this->withSession(['locale' => 'om'])
        ->post(route('locale.update'), ['locale' => ''])
        ->assertSessionHasErrors('locale', 'locale dirree barbaachisa dha.');
});

test('framework authentication messages use the active locale', function () {
    $user = User::factory()->create();

    $this->withSession(['locale' => 'am'])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('email', 'የገቡት መረጃ ከእኛ መዝገብ ጋር አይዛመድም።');
});

test('user generated listings and category names remain unchanged when the interface is translated', function () {
    $category = Category::factory()->create(['name' => 'Bicycles']);

    Product::factory()
        ->for($category, 'category')
        ->approved()
        ->create([
            'title' => 'City Bicycle',
            'description' => 'A sturdy city bicycle.',
        ]);

    $this->withSession(['locale' => 'am'])
        ->get(route('products.show', Product::query()->where('title', 'City Bicycle')->firstOrFail()->slug))
        ->assertOk()
        ->assertSee('City Bicycle')
        ->assertSee('A sturdy city bicycle.')
        ->assertSee('Bicycles')
        ->assertSee('የምርት ዝርዝሮች');
});

test('legal pages use translated legal files', function () {
    $this->withSession(['locale' => 'am'])
        ->get(route('privacy-policy'))
        ->assertOk()
        ->assertSee('የግላዊነት ፖሊሲ')
        ->assertSee('ለግምገማ እና ለማበጀት የመጀመሪያ ይዘት');
});

test('dates and currency formatting follow the active locale', function () {
    config()->set('app.locale_formats.am.thousands_separator', '፣');

    $buyer = User::factory()->create();
    $product = Product::factory()->approved()->create([
        'title' => 'Locale Formatted Item',
        'price_minor' => 123400,
    ]);
    $order = Order::factory()->for($buyer, 'buyer')->create([
        'created_at' => '2026-11-24 10:00:00',
        'total_amount_minor' => 123400,
    ]);
    OrderItem::factory()->for($order)->create([
        'product_id' => $product->id,
        'price_minor' => 123400,
        'quantity' => 1,
    ]);

    $this->actingAs($buyer)
        ->withSession(['locale' => 'am'])
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('ETB 1፣234.00')
        ->assertSee('24 ኖቬ 2026');

    expect(Product::formatDate($order->created_at))->toBe('24 ኖቬ 2026');
});

test('switching locale preserves the intended post-login destination', function () {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => route('seller.products.index')])
        ->post(route('locale.update'), ['locale' => 'am'])
        ->assertSessionHas('locale', 'am')
        ->assertSessionHas('url.intended', route('seller.products.index'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('seller.products.index'));
});

test('order confirmation uses a translated status and a stable status key', function () {
    $buyer = User::factory()->create();
    $product = Product::factory()->approved()->create(['title' => 'Localized Order Item']);

    $this->actingAs($buyer)
        ->withSession(['locale' => 'am'])
        ->post(route('checkout.store', $product), [
            'idempotency_key' => (string) Str::uuid(),
            'fulfillment_method' => Order::FULFILLMENT_PICKUP,
        ])
        ->assertSessionHas('status', 'ትዕዛዙ በተሳካ ሁኔታ ተቀመጠ።')
        ->assertSessionHas('status_key', 'order_placed');

    $order = Order::query()->where('buyer_id', $buyer->id)->firstOrFail();

    $this->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('አመሰግናለሁ። ትዕዛዝዎ ተቀመጠ።');
});

test('all supported locales define the smart search interface keys', function () {
    $keys = [
        'Default order',
        'Latest',
        'Most relevant',
        'No suggestions',
        'Search suggestions',
        'Try fewer words or check the spelling.',
    ];

    foreach (['en', 'am', 'om'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true, 512, JSON_THROW_ON_ERROR);

        expect($translations)->toHaveKeys($keys);
    }
});

test('all supported locales define the admin moderation filter keys', function () {
    $keys = [
        'All',
        'Listing date',
        'No listings match your filters.',
        'Review listings and manage their moderation status.',
        'Search listings',
        'Sold',
    ];

    foreach (['en', 'am', 'om'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true, 512, JSON_THROW_ON_ERROR);

        expect($translations)->toHaveKeys($keys);
    }
});

test('all supported locales define the archive and restore keys', function () {
    $keys = [
        'Archive',
        'Archive listing',
        'Archive this listing?',
        'Archived',
        'Listing archived.',
        'Listing :title deleted.',
        'Listing :title restored.',
        'Only archived listings can be restored.',
        'Only pending or approved listings can be deleted.',
        'Restore',
        'Restore listing',
        'Restore this listing before reviewing it.',
        'This listing is already archived.',
        'This listing is archived and hidden from the public catalog.',
        'This listing is archived. Payment approval is blocked until the listing is restored.',
    ];

    foreach (['en', 'am', 'om'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true, 512, JSON_THROW_ON_ERROR);

        expect($translations)->toHaveKeys($keys);
    }
});

test('all supported locales define the seller product promotion action key', function () {
    $keys = [
        'Promote This Item',
    ];

    foreach (['en', 'am', 'om'] as $locale) {
        $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true, 512, JSON_THROW_ON_ERROR);

        expect($translations)->toHaveKeys($keys);
    }
});

test('smart search controls and empty suggestions use the active locale', function (string $locale, string $defaultOrder, string $noSuggestions) {
    $this->withSession(['locale' => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee($defaultOrder)
        ->assertSee($noSuggestions);
})->with([
    'English' => ['en', 'Default order', 'No suggestions'],
    'Amharic' => ['am', 'ነባሪ ዝርዝር', 'ምንም ጥቆማዎች የሉም።'],
    'Afaan Oromoo' => ['om', 'Indaa jalqabaa', 'Yaanni hin jiru.'],
]);

test('changing locale preserves the selected theme', function () {
    $response = $this->from(route('home'))
        ->withSession(['theme' => 'dark'])
        ->post(route('locale.update'), ['locale' => 'am']);

    $response->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'am')
        ->assertSessionHas('theme', 'dark');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('lang="am"', false)
        ->assertSee('class="dark"', false);
});
