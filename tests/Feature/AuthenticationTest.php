<?php

use App\Models\Product;
use App\Models\User;

test('login screen shows the legal links beneath the form', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Log in to your account')
        ->assertSee(route('privacy-policy'), false)
        ->assertSee(route('terms-and-conditions'), false)
        ->assertSee('Privacy Policy')
        ->assertSee('Terms &amp; Conditions', false)
        ->assertDontSee('Browse Items')
        ->assertDontSee('href="'.route('home').'#all-products"', false);
});

test('the login page registration link carries an explicit general home indication', function () {
    $this->get(route('login', ['redirect' => 'home']))
        ->assertOk()
        ->assertSee(route('register', ['redirect' => 'home']), false);
});

test('the login page registration link preserves an intercepted destination', function () {
    $this->get(route('seller.products.create'))->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee(route('register', ['redirect' => 'home']), false);
});

test('authenticates a user with valid credentials', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('authenticates an administrator and redirects to the marketplace home', function () {
    $admin = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($admin);
});

test('an intended page takes precedence over the home landing page', function () {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => route('seller.products.index')])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('seller.products.index'));
});

test('general login overrides a stale intended destination and goes home', function () {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => route('seller.products.index')])
        ->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => 'home',
        ])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

test('rejects invalid credentials without authenticating', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('arbitrary login redirect values are rejected', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => 'https://evil.example',
    ])->assertSessionHasErrors('redirect');

    $this->assertGuest();
});

test('logout ends the authenticated session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('guests are redirected to login from protected routes', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can open the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome, '.$user->name);
});

test('authenticated users are redirected away from guest forms', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertRedirect(route('home'));
})->with([
    'login' => 'login',
    'register' => 'register',
]);

test('authenticated administrators are redirected away from guest forms', function (string $routeName) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertRedirect(route('home'));
})->with([
    'login' => 'login',
    'register' => 'register',
]);

test('administrators can still use public and seller areas', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('home'))
        ->assertOk();

    $this->get(route('seller.products.index'))
        ->assertOk();

    $this->get(route('dashboard'))
        ->assertOk();
});

test('login is rate limited after repeated failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('a guest buy action resumes checkout after login', function () {
    $user = User::factory()->create();
    $product = Product::factory()->approved()->create(['title' => 'Guest Buy Item']);

    $this->get(route('products.show', $product->slug))
        ->assertOk()
        ->assertSee('Log in to buy now')
        ->assertSee(route('checkout.create', $product), false);

    $this->get(route('checkout.create', $product))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('checkout.create', $product));
});

test('a guest sell action resumes listing creation after login', function () {
    $user = User::factory()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Sell an item')
        ->assertSee(route('seller.products.create'), false);

    $this->get(route('seller.products.create'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('seller.products.create'));
});

test('authenticated top navigation links to My Account and Sell an item followed by logout', function (string $routeName) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk()
        ->assertSee('My Account')
        ->assertSee('Sell an item')
        ->assertSee('Logout')
        ->assertSee(route('my-account'), false)
        ->assertSee(route('seller.products.create'), false)
        ->assertSee(route('logout'), false)
        ->assertDontSee('href="'.route('dashboard').'"', false);

    $content = (string) $response->getContent();

    expect(strpos($content, 'href="'.route('my-account').'"'))->toBeLessThan(strpos($content, 'action="'.route('logout').'"'));
})->with([
    'marketplace layout' => 'home',
    'guest layout' => 'privacy-policy',
    'dashboard auth header' => 'dashboard',
]);

test('seller pages keep My Account in the navigation followed by logout', function (string $routeName) {
    $seller = User::factory()->create();
    Product::factory()->for($seller, 'seller')->approved()->create();

    $response = $this->actingAs($seller)->get(route($routeName));

    $response->assertOk()
        ->assertSee('My Account')
        ->assertSee(route('my-account'), false)
        ->assertSee(route('logout'), false)
        ->assertDontSee('href="'.route('dashboard').'"', false);

    $content = (string) $response->getContent();

    expect(strpos($content, 'href="'.route('my-account').'"'))->toBeLessThan(strpos($content, 'action="'.route('logout').'"'));
})->with([
    'seller listings' => 'seller.products.index',
    'dashboard auth header' => 'dashboard',
]);

test('administrator top navigation keeps the moderation link', function (string $routeName) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route($routeName))
        ->assertOk()
        ->assertSee('Moderation')
        ->assertSee(route('admin.products.index'), false)
        ->assertSee('My Account')
        ->assertSee('Logout');
})->with([
    'marketplace layout' => 'home',
    'dashboard auth header' => 'dashboard',
]);

test('authenticated navigation labels are translated', function (string $locale, string $myAccount, string $logout) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => $locale])
        ->get(route('home'))
        ->assertOk()
        ->assertSee($myAccount)
        ->assertSee($logout);
})->with([
    'English' => ['en', 'My Account', 'Logout'],
    'Amharic' => ['am', 'የኔ መለያ', 'ውጣ'],
    'Afaan Oromoo' => ['om', 'Akauntii koo', "Gadi ba'i"],
]);

test('authenticated desktop logout sits beside the compact language selector', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-desktop-logout', false)
        ->assertSee('data-mobile-logout class="flex items-center lg:hidden"', false)
        ->assertSee('hidden items-center lg:flex', false)
        ->assertSee('px-2 py-1 text-xs', false)
        ->assertSee('title="English"', false)
        ->assertSee('title="አማርኛ"', false)
        ->assertSee('title="Afaan Oromoo"', false)
        ->assertSee('>EN</option>', false)
        ->assertSee('>AM</option>', false)
        ->assertSee('>OM</option>', false);

    $content = (string) $response->getContent();

    expect(strpos($content, 'id="locale-switcher"'))->toBeLessThan(strpos($content, 'data-desktop-logout'));
});
