<?php

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;

test('theme defaults to system with a client side preference script', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="system"', false)
        ->assertSee("dataset.theme === 'system'", false);
});

test('theme switcher persists a light or dark preference', function (string $theme) {
    $response = $this->from(route('home'))
        ->put(route('theme.update'), ['theme' => $theme]);

    $response->assertRedirect(route('home'))
        ->assertSessionHas('theme', $theme);

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie): bool => $cookie->getName() === 'theme');

    expect($cookie)->not->toBeNull()
        ->and(decrypt($cookie->getValue(), false))
        ->toBe(CookieValuePrefix::create('theme', app('encrypter')->getKey()).$theme);
})->with([
    'light' => 'light',
    'dark' => 'dark',
]);

test('a stored dark preference renders the dark class', function () {
    $this->withSession(['theme' => 'dark'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('class="dark"', false);
});

test('an encrypted theme cookie is used when no session preference exists', function () {
    $this->withCookie('theme', 'dark')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('class="dark"', false);
});

test('an unsupported theme is rejected without changing the active preference', function () {
    $this->withSession(['theme' => 'dark'])
        ->put(route('theme.update'), ['theme' => 'blue'])
        ->assertSessionHasErrors('theme')
        ->assertSessionHas('theme', 'dark');
});

test('theme switcher appears exactly once on public guest and authenticated pages', function () {
    $user = User::factory()->create();

    $responses = [
        $this->get(route('home')),
        $this->get(route('login')),
        $this->actingAs($user)->get(route('orders.index')),
    ];

    foreach ($responses as $response) {
        $response->assertOk();

        expect(substr_count((string) $response->getContent(), 'action="'.route('theme.update').'"'))->toBe(1);
    }
});

test('theme switcher offers the opposite theme', function () {
    $this->withSession(['theme' => 'dark'])
        ->get(route('home'))
        ->assertOk()
        ->assertSee('value="light"', false);
});

test('search suggestions and empty results include dark styling', function () {
    $this->withSession(['theme' => 'dark'])
        ->get(route('home', ['q' => 'missing']))
        ->assertOk()
        ->assertSee('dark:border-white/15 dark:bg-forest-900/95', false)
        ->assertSee('dark:text-mint-100/80', false)
        ->assertSee('dark:bg-mint-300 dark:text-forest-950', false);
});

test('changing theme preserves the selected locale', function () {
    $response = $this->from(route('home'))
        ->withSession(['locale' => 'am'])
        ->put(route('theme.update'), ['theme' => 'dark']);

    $response->assertRedirect(route('home'))
        ->assertSessionHas('theme', 'dark')
        ->assertSessionHas('locale', 'am');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('lang="am"', false)
        ->assertSee('class="dark"', false);
});
