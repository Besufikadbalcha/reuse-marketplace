@extends('layouts.guest')

@section('title', __('Reset password').' - '.config('app.name'))

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

    @if (session('status'))
        <x-alert class="mb-5">{{ session('status') }}</x-alert>
    @endif

    <h1 class="text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ __('Forgot your password?') }}</h1>
    <p class="mt-2 text-base text-sage-700 dark:text-[#B7CCBD]">{{ __('Enter your email and we will send you a password reset link.') }}</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
        @csrf

        <x-field name="email" label="{{ __('Email address') }}" type="email" :value="old('email')" required autofocus autocomplete="email" placeholder="{{ __('you@example.com') }}">
            <x-slot:icon>
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="5" width="18" height="14" rx="3" />
                    <path d="m4 7 8 6 8-6" />
                </svg>
            </x-slot:icon>
        </x-field>

        <x-gradient-button type="submit">{{ __('Send password reset link') }}</x-gradient-button>
    </form>

    <p class="mt-6 text-center text-sm text-sage-700 dark:text-[#B7CCBD]">
        <a href="{{ route('login') }}" class="font-semibold text-forest-700 underline-offset-4 hover:underline focus-ring dark:text-mint-300">{{ __('Back to log in') }}</a>
    </p>
@endsection
