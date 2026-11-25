<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        $locale = $validated['locale'];

        $request->session()->put('locale', $locale);
        Cookie::queue('locale', $locale, 60 * 24 * 365);

        return back();
    }
}
