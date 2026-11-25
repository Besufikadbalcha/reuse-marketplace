<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('registration screen renders', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertDontSee('Browse Items')
        ->assertDontSee('href="'.route('home').'#all-products"', false);
});

test('the registration page links back to login with the general home indication', function () {
    $this->get(route('register', ['redirect' => 'home']))
        ->assertOk()
        ->assertSee(route('login', ['redirect' => 'home']), false);
});

test('the registration page does not add a general indication to an intercepted flow', function () {
    $this->get(route('seller.products.create'))->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('register'), false)
        ->assertDontSee(route('register', ['redirect' => 'home']), false);
});

test('registers one account that can buy and sell', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ]);

    $response->assertRedirect(route('home'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', ['email' => 'abebe@example.com']);

    $user = User::where('email', 'abebe@example.com')->firstOrFail();

    expect(Hash::check('secure-password', $user->password))->toBeTrue();
});

test('registration requires a name email and password', function () {
    $this->post(route('register.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

test('registration rejects an email that is already in use', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'taken@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertSessionHasErrors('email');
});

test('registration rejects a mismatched password confirmation', function () {
    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');
});

test('registration succeeds when the attempt count is within the limit', function () {
    foreach (range(1, 4) as $attempt) {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    $this->assertGuest();

    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect(route('home'));

    $this->assertAuthenticated();

    $this->assertDatabaseHas('users', ['email' => 'abebe@example.com']);
});

test('registration is rate limited after five attempts', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    $this->post(route('register.store'), [])->assertStatus(429);

    $this->assertGuest();
});

test('registration rate limit is scoped by ip address', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    $this->post(route('register.store'), [])->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.11']);

    $this->post(route('register.store'), [])
        ->assertRedirect()
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

test('registration screen is not rate limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('register.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    $this->post(route('register.store'), [])->assertStatus(429);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Create your account');
});

test('registration resumes an intended protected page', function () {
    $this->get(route('seller.products.create'))->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect(route('seller.products.create'));

    $this->assertAuthenticated();
});

test('registration resumes checkout after a guarded buy action', function () {
    $product = Product::factory()->approved()->create(['title' => 'Registration Buy Item']);

    $this->get(route('checkout.create', $product))->assertRedirect(route('login'));

    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect(route('checkout.create', $product));

    $this->assertAuthenticated();
});

test('general registration overrides a stale intended destination and goes home', function () {
    $this->withSession(['url.intended' => route('seller.products.index')])
        ->post(route('register.store'), [
            'name' => 'Abebe Bekele',
            'email' => 'abebe@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'redirect' => 'home',
        ])->assertRedirect(route('home'));

    $this->assertAuthenticated();
});

test('arbitrary registration redirect values are rejected', function () {
    $this->post(route('register.store'), [
        'name' => 'Abebe Bekele',
        'email' => 'abebe@example.com',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
        'redirect' => 'https://evil.example',
    ])->assertSessionHasErrors('redirect');

    $this->assertGuest();
});

test('one account can access both buyer and seller areas', function () {
    $user = User::factory()->create();
    $product = Product::factory()->approved()->create(['title' => 'Shared Account Item']);

    $this->actingAs($user)
        ->get(route('seller.products.create'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('checkout.create', $product))
        ->assertOk();
});
