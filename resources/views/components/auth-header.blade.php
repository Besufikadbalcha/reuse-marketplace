<x-site-header>
    <x-slot:navigation>
        @php($generalLoginRedirect = request()->query('redirect') === 'home')

        @auth
            <x-nav-link href="{{ route('my-account') }}" :active="request()->routeIs('my-account', 'orders.*', 'seller.orders.*', 'seller.products.index', 'seller.products.show', 'seller.products.edit')">{{ __('My Account') }}</x-nav-link>
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
            <x-nav-link href="{{ route('home') }}" :active="request()->routeIs('home')">{{ __('Home') }}</x-nav-link>
            <x-nav-link href="{{ route('how-it-works') }}" :active="request()->routeIs('how-it-works')">{{ __('How It Works') }}</x-nav-link>
            <x-nav-link href="{{ route('about-us') }}" :active="request()->routeIs('about-us')">{{ __('About Us') }}</x-nav-link>
            <x-nav-link href="{{ route('seller.products.create') }}" tone="button">{{ __('Sell an item') }}</x-nav-link>
            <x-nav-link href="{{ route('login', $generalLoginRedirect ? ['redirect' => 'home'] : []) }}" :active="request()->routeIs('login')">{{ __('Log in') }}</x-nav-link>
            <x-nav-link href="{{ route('register', $generalLoginRedirect ? ['redirect' => 'home'] : []) }}" :active="request()->routeIs('register')" tone="primary">{{ __('Register') }}</x-nav-link>
        @endauth
    </x-slot:navigation>
</x-site-header>
