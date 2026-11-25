<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetTheme
{
    private const THEMES = ['light', 'dark'];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $theme = $request->session()->get('theme');

        if (! in_array($theme, self::THEMES, true)) {
            $theme = $request->cookie('theme');
        }

        View::share('theme', in_array($theme, self::THEMES, true) ? $theme : null);

        return $next($request);
    }
}
