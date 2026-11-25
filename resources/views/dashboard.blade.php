@extends('layouts.guest')

@section('title', __('Dashboard').' - '.config('app.name'))

@section('body-class', 'relative min-h-screen overflow-x-hidden bg-cream-200 text-forest-900 antialiased dark:bg-[#0B1A12] dark:text-[#E8F3EA]')

@section('wrapper-class', 'relative z-10 flex min-h-[calc(100vh-5rem)] flex-col items-center justify-center px-4 py-10 sm:px-6 sm:py-14 lg:px-8')

@section('container-class', 'relative z-10 w-full max-w-5xl sm:max-w-7xl')

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

    <div class="flex items-start justify-between gap-6">
        <div>
            <x-badge tone="mint">{{ __('Your account') }}</x-badge>
            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
            <p class="mt-2 text-base text-sage-700 dark:text-[#B7CCBD]">
                {{ __('Your :app account can be used to buy and sell.', ['app' => config('app.name')]) }}
            </p>
        </div>

        <svg class="hidden size-16 shrink-0 text-forest-600/30 sm:block dark:text-mint-300/20" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 56c14-4 24-12 32-24" />
            <path d="M40 32c2-12 10-19 20-20-1 11-8 19-20 20Z" />
            <path d="M27 42c-1-10-8-16-18-17 1 10 7 16 18 17Z" />
        </svg>
    </div>

    <div class="mt-7 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-sage-200 bg-mint-50/70 p-5 dark:border-forest-700 dark:bg-forest-900/50">
            <h2 class="text-lg font-semibold text-forest-900 dark:text-white">{{ __('Sell pre-loved items') }}</h2>
            <p class="mt-2 text-base text-sage-700 dark:text-[#B7CCBD]">{{ __('Keep your listings and images up to date.') }}</p>
            <x-gradient-button as="a" href="{{ route('seller.products.index') }}" class="mt-4">{{ __('Manage my listings') }}</x-gradient-button>
        </div>

        <div class="rounded-2xl border border-beige-200 bg-cream-50/70 p-5 dark:border-forest-700 dark:bg-forest-900/50">
            <h2 class="text-lg font-semibold text-forest-900 dark:text-white">{{ __('Account access') }}</h2>
            <p class="mt-2 text-base text-sage-700 dark:text-[#B7CCBD]">{{ __('Sign out of this device when you are done.') }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <x-button type="submit" variant="secondary" class="w-full">{{ __('Log out') }}</x-button>
            </form>
        </div>
    </div>
@endsection
