<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('forgot password screen renders', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Forgot your password?');
});

test('reset password screen renders with the token', function () {
    $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'user@example.com']))
        ->assertOk()
        ->assertSee('Choose a new password')
        ->assertSee('test-token', false);
});

test('sends a password reset link', function () {
    $user = User::factory()->create();

    Notification::fake();

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

test('resets the password with a valid token', function () {
    $user = User::factory()->create();

    Notification::fake();

    $this->post(route('password.email'), ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', __('passwords.reset'));

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('rejects an invalid reset token without changing the password', function () {
    $user = User::factory()->create();

    $originalPassword = $user->password;

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('password reset invalidates a previously authenticated session', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $previousSession = $this->app['session']->all();

    $this->app['auth']->guard()->forgetUser();
    $this->app['session']->flush();

    Notification::fake();

    $this->post(route('password.email'), ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', __('passwords.reset'));

    $this->app['auth']->guard()->forgetUser();
    $this->app['session']->flush();
    $this->withSession($previousSession);

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('login with the new password succeeds after a password reset', function () {
    $user = User::factory()->create();

    Notification::fake();

    $this->post(route('password.email'), ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'brand-new-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user->fresh());
});

test('password reset submissions are rate limited after five attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => strtoupper($user->email),
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertStatus(429);

    expect($user->fresh()->password)->toBe($user->password);
});

test('a valid password reset succeeds before the submission limit is reached', function () {
    $user = User::factory()->create();

    Notification::fake();

    $this->post(route('password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors('email');

    $this->post(route('password.email'), ['email' => $user->email]);

    $token = null;

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', __('passwords.reset'));

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();

    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('forgot password throttling does not block password reset submissions', function () {
    $users = User::factory()->count(6)->create();

    Notification::fake();

    foreach ($users->take(5) as $user) {
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));
    }

    $this->post(route('password.email'), ['email' => $users[5]->email])->assertStatus(429);

    $token = null;

    Notification::assertSentTo($users[0], ResetPassword::class, function (ResetPassword $notification) use (&$token) {
        $token = $notification->token;

        return true;
    });

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $users[0]->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', __('passwords.reset'));

    expect(Hash::check('brand-new-password', $users[0]->fresh()->password))->toBeTrue();
});
