@extends('layouts.guest')

@section('title', __('Log in').' - '.config('app.name'))

@section('body-class', 'relative min-h-screen overflow-x-hidden bg-[#F6F4E9] text-[#173F2A] antialiased dark:bg-[#0B1A12] dark:text-[#E8F3EA]')

@section('wrapper-class', 'relative z-10 flex min-h-[calc(100vh-5rem)] flex-col items-center justify-center px-5 py-10 sm:px-8 sm:py-14')

@section('container-class', 'relative z-10 w-full max-w-md sm:max-w-lg')

@section('card-class', 'relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 p-6 shadow-[0_28px_80px_rgba(23,60,41,0.18)] backdrop-blur-2xl dark:border-white/10 dark:bg-[#10261A]/70 sm:p-9')

@section('background')
    <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
        <svg class="absolute inset-0 h-full w-full" viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 0h1440v900H0z" fill="#F6F4E9"/>
            <path d="M0 0c170 74 248 214 210 356-34 126-132 205-210 246V0Z" fill="#DCE9D8"/>
            <path d="M1440 0v900h-386c57-147 130-253 222-344 89-87 139-186 128-306C1396 156 1342 72 1262 0h178Z" fill="#E9DFC9"/>
            <path d="M0 900V681c137-34 274 2 407 82 134 80 280 122 448 89 168-33 344-147 585-119v167H0Z" fill="#D8E5D4"/>
            <path d="M0 900V764c169-58 341-12 501 51 172 68 348 71 523 20 135-40 275-47 416-15v80H0Z" fill="#2F6B47"/>
            <path d="M0 900V842c180-44 357-14 523 33 170 48 343 44 513-6 120-36 270-42 404-11v42H0Z" fill="#173C29"/>
            <circle cx="1080" cy="170" r="118" fill="#F7F1E1" opacity=".6"/>
            <circle cx="350" cy="150" r="92" fill="#F7F1E1" opacity=".45"/>
            <g stroke="#2F6B47" stroke-width="3" stroke-linecap="round" opacity=".28">
                <path d="M110 900c66-121 141-201 235-258"/>
                <path d="M1294 900c-58-118-129-194-222-247"/>
                <path d="M280 900c43-82 94-138 158-179"/>
            </g>
            <g fill="#2F6B47" opacity=".32">
                <path d="M214 780c-47-40-48-89-2-126 21 47 18 90 2 126Z"/>
                <path d="M270 730c-9-52 14-91 61-112 8 52-12 92-61 112Z"/>
                <path d="M1210 780c47-40 48-89 2-126-21 47-18 90-2 126Z"/>
                <path d="M1154 730c9-52-14-91-61-112-8 52 12 92 61 112Z"/>
            </g>
        </svg>
        <div class="absolute -left-24 top-16 h-80 w-80 rounded-full bg-[#DCE9D8] blur-3xl dark:bg-[#1D4B32]/50"></div>
        <div class="absolute -right-24 top-24 h-96 w-96 rounded-full bg-[#EFE4CE] blur-3xl dark:bg-[#5E4C2E]/40"></div>
        <div class="absolute inset-0 hidden bg-[#07140D]/65 dark:block"></div>
    </div>
@endsection

@section('navigation')
    <header class="relative z-20 border-b border-[#CDE0D1]/80 bg-[#E7F3E9]/90 backdrop-blur-md dark:border-white/10 dark:bg-[#10261A]/90">
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 self-center text-[#173F2A] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#245C3B] sm:self-auto dark:text-[#D7EADB] dark:focus-visible:outline-[#BFD8C5]">
                <span class="grid size-9 place-items-center rounded-full bg-[#1F5136] text-white shadow-sm dark:bg-[#2F6B47]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 4c-8.5 0-14 3.8-14 10a6 6 0 0 0 6 6c6.2 0 10-5.5 10-14V4Z"/>
                        <path d="M4 20c3.5-4.5 7.5-7.5 12-9.5"/>
                    </svg>
                </span>
                <span class="text-lg font-semibold tracking-tight sm:text-xl">{{ config('app.name') }}</span>
            </a>

            <nav class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm font-medium text-[#244E36] sm:justify-end dark:text-[#C7DDCE]" aria-label="{{ __('Primary') }}">
                <a href="{{ route('home') }}" aria-current="page" class="rounded-full px-2 py-1 hover:text-[#173F2A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:hover:text-white dark:focus-visible:outline-[#BFD8C5]">{{ __('Home') }}</a>
                <a href="{{ route('how-it-works') }}" class="rounded-full px-2 py-1 hover:text-[#173F2A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:hover:text-white dark:focus-visible:outline-[#BFD8C5]">{{ __('How It Works') }}</a>
                <a href="{{ route('about-us') }}" class="rounded-full px-2 py-1 hover:text-[#173F2A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:hover:text-white dark:focus-visible:outline-[#BFD8C5]">{{ __('About Us') }}</a>
            </nav>
            <x-theme-switcher />
            <x-locale-switcher />
        </div>
    </header>
@endsection

@section('content')
    <div class="pointer-events-none absolute inset-x-10 top-0 h-px bg-linear-to-r from-transparent via-white/50 to-transparent dark:via-white/10"></div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-2xl border border-[#BFD8C5] bg-[#EAF5EC]/90 px-4 py-3 text-sm text-[#1F5136] dark:border-[#3E7151] dark:bg-[#173C29]/70 dark:text-[#D7EADB]">
            {{ session('status') }}
        </div>
    @endif

    <h1 class="text-4xl font-semibold tracking-tight text-[#173F2A] dark:text-white sm:text-5xl">{{ __('Log in to your account') }}</h1>
    <p class="mt-2 text-base text-[#52705E] dark:text-[#B7CCBD]">{{ __('Buy and sell with one :app account.', ['app' => config('app.name')]) }}</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
        @csrf
        @if (request()->query('redirect') === 'home')
            <input type="hidden" name="redirect" value="home">
        @endif

        <div>
            <label for="email" class="block text-xs font-medium text-[#294D38] dark:text-[#D7EADB]">{{ __('Email address') }}</label>
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#4F735D] dark:text-[#9CBCA7]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="3"/>
                        <path d="m4 7 8 6 8-6"/>
                    </svg>
                </span>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="{{ __('you@example.com') }}" class="block w-full rounded-2xl border border-[#D9E7DD] bg-white/85 py-3.5 pl-12 pr-4 text-sm text-[#173F2A] shadow-sm placeholder:text-[#8AA392] focus:border-[#2F6B47] focus:ring-4 focus:ring-[#2F6B47]/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-[#8FAE9B] dark:focus:border-[#8FC3A0] dark:focus:ring-[#8FC3A0]/15">
            </div>
            @error('email')
                <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-medium text-[#294D38] dark:text-[#D7EADB]">{{ __('Password') }}</label>
            <div class="relative mt-2">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#4F735D] dark:text-[#9CBCA7]">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4" y="10" width="16" height="10" rx="3"/>
                        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
                        <path d="M12 14v2"/>
                    </svg>
                </span>
                <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="{{ __('••••••••') }}" class="block w-full rounded-2xl border border-[#D9E7DD] bg-white/85 py-3.5 pl-12 pr-4 text-sm text-[#173F2A] shadow-sm placeholder:text-[#8AA392] focus:border-[#2F6B47] focus:ring-4 focus:ring-[#2F6B47]/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-[#8FAE9B] dark:focus:border-[#8FC3A0] dark:focus:ring-[#8FC3A0]/15">
            </div>
            @error('password')
                <p class="mt-1 text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-xs text-[#3C5A49] dark:text-[#C7DDCE]">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded-md border-[#B9D0BF] accent-[#245C3B] focus:ring-[#2F6B47]/30 dark:border-white/20">
                {{ __('Remember me') }}
            </label>

            <a href="{{ route('password.request') }}" class="text-sm font-medium text-[#245C3B] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:text-[#B7D9C1] dark:focus-visible:outline-[#BFD8C5]">{{ __('Forgot your password?') }}</a>
        </div>

        <button type="submit" class="w-full rounded-full bg-linear-to-r from-[#1D5136] via-[#2F704A] to-[#153B28] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-[#173C29]/25 transition hover:-translate-y-0.5 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#173C29] dark:from-[#2F6B47] dark:via-[#3E8158] dark:to-[#1D5136] dark:focus-visible:outline-[#BFD8C5]">
            {{ __('Log in') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-[#4A6B57] dark:text-[#B7CCBD]">
        {{ __("Don't have an account?") }}
        <a href="{{ route('register', request()->query('redirect') === 'home' ? ['redirect' => 'home'] : []) }}" class="font-semibold text-[#245C3B] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:text-[#B7D9C1] dark:focus-visible:outline-[#BFD8C5]">{{ __('Create an account') }}</a>
    </p>
@endsection

@section('footer')
    <nav class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-[#4A6B57] dark:text-[#B7CCBD]" aria-label="{{ __('Legal') }}">
        <a href="{{ route('privacy-policy') }}" class="underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:focus-visible:outline-[#BFD8C5]">{{ __('Privacy Policy') }}</a>
        <a href="{{ route('terms-and-conditions') }}" class="underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#245C3B] dark:focus-visible:outline-[#BFD8C5]">{{ __('Terms & Conditions') }}</a>
    </nav>
@endsection
