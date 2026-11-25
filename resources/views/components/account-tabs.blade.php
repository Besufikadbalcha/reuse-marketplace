@props([
    'active' => 'orders',
])

@php
    $tabs = [
        ['key' => 'orders', 'label' => __('My Orders'), 'url' => route('orders.index')],
        ['key' => 'sales', 'label' => __('My Sales'), 'url' => route('seller.orders.index')],
        ['key' => 'listings', 'label' => __('My Listings'), 'url' => route('seller.products.index')],
        ['key' => 'promotions', 'label' => __('Promotions'), 'url' => route('seller.promotions.index')],
    ];
@endphp

<nav aria-label="{{ __('Account sections') }}" {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap gap-2 rounded-[1.25rem] border border-white/70 bg-white/60 p-1.5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70']) }}>
    @foreach ($tabs as $tab)
        <a
            @if ($tab['key'] === $active) aria-current="page" @endif
            href="{{ $tab['url'] }}"
            @class([
                'inline-flex flex-1 items-center justify-center rounded-[0.9rem] px-4 py-2.5 text-sm font-semibold transition focus-ring sm:flex-none',
                'bg-forest-800 text-white shadow-sm dark:bg-mint-200 dark:text-forest-950' => $tab['key'] === $active,
                'text-forest-700 hover:bg-white hover:text-forest-900 dark:text-mint-100 dark:hover:bg-forest-800' => $tab['key'] !== $active,
            ])
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
