@props([
    'as' => 'button',
    'type' => 'button',
    'variant' => 'primary',
    'compact' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition focus-ring disabled:pointer-events-none disabled:opacity-60';
    $baseClasses .= $compact ? ' px-4 py-2 text-sm' : ' px-6 py-3 text-sm';

    $variantClasses = match ($variant) {
        'secondary' => 'border border-forest-200 bg-white/80 text-forest-800 shadow-sm hover:bg-white dark:border-forest-600 dark:bg-forest-900/60 dark:text-mint-100 dark:hover:bg-forest-800',
        'ghost' => 'text-forest-700 hover:bg-sage-100 dark:text-mint-100 dark:hover:bg-forest-800',
        default => 'bg-forest-800 text-white shadow-lg shadow-forest-950/25 hover:-translate-y-0.5 hover:bg-forest-700 dark:bg-mint-200 dark:text-forest-950 dark:hover:bg-mint-100',
    };
@endphp

@if ($as === 'a')
    <a {{ $attributes->merge(['class' => $baseClasses.' '.$variantClasses]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => $baseClasses.' '.$variantClasses]) }}>
        {{ $slot }}
    </button>
@endif
