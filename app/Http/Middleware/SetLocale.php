<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = array_keys(config('app.supported_locales'));
        $sessionLocale = $request->session()->get('locale');
        $cookieLocale = $request->cookie('locale');

        $locale = in_array($sessionLocale, $supportedLocales, true)
            ? $sessionLocale
            : (in_array($cookieLocale, $supportedLocales, true)
                ? $cookieLocale
                : config('app.fallback_locale'));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
