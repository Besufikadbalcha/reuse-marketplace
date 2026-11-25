<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ config('app.supported_locales.'.app()->getLocale().'.direction', 'ltr') }}"
    data-theme="{{ $theme ?? 'system' }}"
    @class(['dark' => $theme === 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>@yield('title', config('app.name'))</title>

        @hasSection('meta-description')
            <meta name="description" content="@yield('meta-description')">
        @endif

        @fonts

        <script>
            if (document.documentElement.dataset.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="@yield('body-class', 'relative min-h-screen overflow-x-hidden bg-cream-200 text-forest-900 antialiased dark:bg-forest-950 dark:text-mint-100')">
        @hasSection('background')
            @yield('background')
        @else
            <x-botanical-background class="opacity-35 dark:opacity-20" />
        @endif

        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-forest-900 focus:shadow-lg focus-ring dark:focus:bg-forest-900 dark:focus:text-white">{{ __('Skip to content') }}</a>

        <div class="relative z-10 flex min-h-screen flex-col">
            <x-site-header>
                <x-slot:navigation>
                    <x-nav-link href="{{ route('home') }}" :active="request()->routeIs('home')">{{ __('Home') }}</x-nav-link>
                    <x-nav-link href="{{ route('how-it-works') }}" :active="request()->routeIs('how-it-works')">{{ __('How It Works') }}</x-nav-link>
                    <x-nav-link href="{{ route('about-us') }}" :active="request()->routeIs('about-us')">{{ __('About Us') }}</x-nav-link>

                    @auth
                        <x-nav-link href="{{ route('my-account') }}" :active="request()->routeIs('my-account', 'orders.*', 'seller.orders.*', 'seller.products.index', 'seller.products.show', 'seller.products.edit', 'seller.promotions.*')">{{ __('My Account') }}</x-nav-link>
                        @can('moderateProducts')
                            <x-nav-link href="{{ route('admin.products.index') }}" :active="request()->routeIs('admin.products.*')">{{ __('Moderation') }}</x-nav-link>
                            <x-nav-link href="{{ route('admin.promotions.index') }}" :active="request()->routeIs('admin.promotions.*')">{{ __('Promotion payments') }}</x-nav-link>
                        @endcan
                        <x-nav-link href="{{ route('seller.products.create') }}" :active="request()->routeIs('seller.products.create')" tone="button">{{ __('Sell an item') }}</x-nav-link>
                        <form method="POST" action="{{ route('logout') }}" data-mobile-logout class="flex items-center lg:hidden">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-full px-3 py-2 text-sm font-medium transition focus-ring text-forest-700 hover:bg-sage-100 hover:text-forest-900 dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Logout') }}</button>
                        </form>
                    @else
                        <x-nav-link href="{{ route('seller.products.create') }}" tone="button">{{ __('Sell an item') }}</x-nav-link>
                        <x-nav-link href="{{ route('login', ['redirect' => 'home']) }}">{{ __('Log in') }}</x-nav-link>
                        <x-nav-link href="{{ route('register', ['redirect' => 'home']) }}" tone="primary">{{ __('Register') }}</x-nav-link>
                    @endauth
                </x-slot:navigation>
            </x-site-header>

            <main id="main-content" class="mx-auto w-full max-w-[96rem] min-w-0 flex-1 px-4 py-5 sm:px-6 sm:py-3 lg:px-8">
                @yield('content')
            </main>

            <x-site-footer />
        </div>
    </body>
</html>
