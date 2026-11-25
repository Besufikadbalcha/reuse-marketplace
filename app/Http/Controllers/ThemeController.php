<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark'])],
        ]);

        $request->session()->put('theme', $validated['theme']);
        Cookie::queue('theme', $validated['theme'], 60 * 24 * 365);

        return back();
    }
}
