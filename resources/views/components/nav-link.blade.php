@props([
    'active' => false,
    'tone' => 'quiet',
])

@php
    $baseClasses = 'inline-flex items-center justify-center rounded-full px-3 py-1.5 text-sm font-medium transition focus-ring';

    $toneClasses = match ($tone) {
        'button' => 'border border-forest-200 bg-white/70 text-forest-800 hover:bg-white dark:border-forest-600 dark:bg-forest-900/60 dark:text-mint-100 dark:hover:bg-forest-800',
        'primary' => 'bg-forest-800 px-4 text-white shadow-sm hover:bg-forest-700 dark:bg-mint-200 dark:text-forest-950 dark:hover:bg-mint-100',
        default => 'text-forest-700 hover:bg-sage-100 hover:text-forest-900 dark:text-mint-100 dark:hover:bg-forest-800',
    };

    if ($active) {
        $toneClasses = 'bg-forest-800 text-white shadow-sm dark:bg-mint-200 dark:text-forest-950';
    }
@endphp

<a @if ($active) aria-current="page" @endif {{ $attributes->merge(['href' => route('home'), 'class' => $baseClasses.' '.$toneClasses]) }}>
    {{ $slot }}
</a>
