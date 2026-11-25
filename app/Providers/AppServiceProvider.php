<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\MarketplaceNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8);

            return $this->app->isProduction()
                ? $rule->mixedCase()->numbers()->uncompromised()
                : $rule;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip());
        });

        RateLimiter::for('registration', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-update', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::lower($request->string('email')->toString()).'|'.$request->ip());
        });

        RateLimiter::for('checkout', function (Request $request): Limit {
            return Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('order-cancellation', function (Request $request): Limit {
            return Limit::perMinute(5)->by(($request->user()?->id ?? 'guest').'|'.$request->ip());
        });

        Gate::define('moderateProducts', fn (User $user): bool => $user->isAdmin());

        View::composer('components.site-header', function (\Illuminate\Contracts\View\View $view): void {
            $user = auth()->user();

            if ($user === null || ! MarketplaceNotification::databaseTableExists()) {
                $view->with([
                    'unreadNotificationsCount' => 0,
                    'notificationDropdownItems' => collect(),
                ]);

                return;
            }

            $view->with([
                'unreadNotificationsCount' => $user->unreadNotifications()->count(),
                'notificationDropdownItems' => $user->notifications()->latest()->limit(30)->get(),
            ]);
        });
    }
}
