<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        Auth::login($user);

        $request->session()->regenerate();

        if ($request->validated('redirect') === 'home') {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended(route('home'))
            ->with('status', __('Your account has been created.'))
            ->with('status_key', 'account_created');
    }
}
