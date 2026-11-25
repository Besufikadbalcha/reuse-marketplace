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
            <x-botanical-background class="opacity-40 dark:opacity-25" />
        @endif

        @hasSection('navigation')
            @yield('navigation')
        @else
            <x-site-header>
                <x-slot:navigation>
                    <x-nav-link href="{{ route('home') }}" :active="request()->routeIs('home')">{{ __('Home') }}</x-nav-link>
                    <x-nav-link href="{{ route('how-it-works') }}" :active="request()->routeIs('how-it-works')">{{ __('How It Works') }}</x-nav-link>
                    <x-nav-link href="{{ route('about-us') }}" :active="request()->routeIs('about-us')">{{ __('About Us') }}</x-nav-link>

                    @auth
                        <x-nav-link href="{{ route('my-account') }}" :active="request()->routeIs('my-account', 'orders.*', 'seller.orders.*', 'seller.products.index', 'seller.products.show', 'seller.products.edit')">{{ __('My Account') }}</x-nav-link>
                        @can('moderateProducts')
                            <x-nav-link href="{{ route('admin.products.index') }}">{{ __('Moderation') }}</x-nav-link>
                            <x-nav-link href="{{ route('admin.promotions.index') }}">{{ __('Promotion payments') }}</x-nav-link>
                        @endcan
                        <x-nav-link href="{{ route('seller.products.create') }}" :active="request()->routeIs('seller.products.create')" tone="button">{{ __('Sell an item') }}</x-nav-link>
                        <form method="POST" action="{{ route('logout') }}" data-mobile-logout class="flex items-center lg:hidden">
                            @csrf
                            <button type="submit" class="inline-flex items-center justify-center rounded-full px-3 py-2 text-sm font-medium transition focus-ring text-forest-700 hover:bg-sage-100 hover:text-forest-900 dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Logout') }}</button>
                        </form>
                    @else
                        <x-nav-link href="{{ route('seller.products.create') }}">{{ __('Sell an item') }}</x-nav-link>
                        <x-nav-link href="{{ route('login', ['redirect' => 'home']) }}">{{ __('Log in') }}</x-nav-link>
                        <x-nav-link href="{{ route('register', ['redirect' => 'home']) }}" tone="primary">{{ __('Register') }}</x-nav-link>
                    @endauth
                </x-slot:navigation>
            </x-site-header>
        @endif

        <div class="@yield('wrapper-class', 'relative z-10 flex min-h-[calc(100dvh-7rem)] flex-col items-center justify-center px-5 py-10 sm:px-8 sm:py-14')">
            <div class="@yield('container-class', 'relative z-10 w-full max-w-lg')">
                @hasSection('navigation')
                @else
                    <div class="mb-8 text-center">
                        <x-logo class="justify-center" />
                    </div>
                @endif

                <div class="@yield('card-class', 'brand-card relative overflow-hidden border border-sage-200 bg-cream-50/85 p-6 dark:border-white/10 dark:bg-forest-950/70 sm:p-9')">
                    @yield('content')
                </div>

                @hasSection('footer')
                    @yield('footer')
                @else
                    <x-site-footer :bare="true" class="mt-6" />
                @endif
            </div>
        </div>
    </body>
</html>
