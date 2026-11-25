@props([
    'bare' => false,
])

@php
    $footerClasses = $bare
        ? 'relative px-0 py-6 text-sm text-forest-700 dark:text-mint-100'
        : 'relative border-t border-sage-200 bg-cream-100/80 px-4 py-8 text-sm text-forest-700 dark:border-white/10 dark:bg-forest-950/80 dark:text-mint-100 sm:px-6 lg:px-8';
@endphp

<footer {{ $attributes->merge(['class' => $footerClasses]) }}>
    <div class="mx-auto flex w-full max-w-[96rem] min-w-0 flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-3">
            <x-logo :compact="true" />
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="{{ __('Footer') }}">
            <x-nav-link href="{{ route('home') }}">{{ __('Home') }}</x-nav-link>
            <x-nav-link href="{{ route('seller.products.create') }}">{{ __('Sell an item') }}</x-nav-link>
            <x-nav-link href="{{ route('how-it-works') }}">{{ __('How It Works') }}</x-nav-link>
            <x-nav-link href="{{ route('about-us') }}">{{ __('About Us') }}</x-nav-link>
            <x-nav-link href="{{ route('privacy-policy') }}">{{ __('Privacy Policy') }}</x-nav-link>
            <x-nav-link href="{{ route('terms-and-conditions') }}">{{ __('Terms & Conditions') }}</x-nav-link>
        </nav>

        {{ $slot }}
    </div>
</footer>
