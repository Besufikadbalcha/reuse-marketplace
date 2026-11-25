@extends('layouts.guest')

@section('title', __('Choose a new password').' - '.config('app.name'))

@section('body-class', 'relative min-h-screen overflow-x-hidden bg-cream-200 text-forest-900 antialiased dark:bg-[#0B1A12] dark:text-[#E8F3EA]')

@section('wrapper-class', 'relative z-10 flex min-h-[calc(100vh-5rem)] flex-col items-center justify-center px-5 py-10 sm:px-8 sm:py-14')

@section('container-class', 'relative z-10 w-full max-w-md sm:max-w-lg')

@section('card-class', 'relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 p-6 shadow-card backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-9')

@section('background')
    <x-botanical-background />
@endsection

@section('navigation')
    <x-auth-header />
@endsection

@section('content')
    <div class="pointer-events-none absolute inset-x-10 top-0 h-px bg-linear-to-r from-transparent via-white/50 dark:via-white/10 to-transparent"></div>

    <h1 class="text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ __('Choose a new password') }}</h1>
    <p class="mt-2 text-base text-sage-700 dark:text-[#B7CCBD]">{{ __('Use at least eight characters for your new password.') }}</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <x-field name="email" label="{{ __('Email address') }}" type="email" :value="$email" required readonly autocomplete="email">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="3" />
                    <path d="m4 7 8 6 8-6" />
                </svg>
            </x-slot:icon>
        </x-field>

        <div class="space-y-2">
            <label for="password" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('New password') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sage-600 dark:text-sage-400">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="10" rx="3" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        <path d="M12 14v2" />
                    </svg>
                </span>
                <input id="password" name="password" type="password" required autofocus autocomplete="new-password" placeholder="{{ __('••••••••') }}" class="block w-full rounded-2xl border border-sage-200 bg-white/85 py-3.5 pl-12 pr-4 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
            </div>
            @error('password')
                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="password_confirmation" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Confirm new password') }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sage-600 dark:text-sage-400">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="10" rx="3" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        <path d="M12 14v2" />
                    </svg>
                </span>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="{{ __('••••••••') }}" class="block w-full rounded-2xl border border-sage-200 bg-white/85 py-3.5 pl-12 pr-4 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
            </div>
        </div>

        <x-gradient-button type="submit">{{ __('Reset password') }}</x-gradient-button>
    </form>
@endsection
