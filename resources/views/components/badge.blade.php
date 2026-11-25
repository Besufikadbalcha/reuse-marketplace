@props([
    'tone' => 'sage',
])

@php
    $toneClasses = match ($tone) {
        'forest' => 'bg-forest-800 text-white dark:bg-mint-200 dark:text-forest-950',
        'mint' => 'bg-mint-100 text-forest-800 dark:bg-forest-800 dark:text-mint-100',
        'cream' => 'bg-cream-200 text-forest-800 dark:bg-beige-900 dark:text-beige-100',
        'blush' => 'bg-blush-100 text-blush-900 dark:bg-blush-900 dark:text-blush-100',
        default => 'bg-sage-100 text-forest-800 dark:bg-forest-800 dark:text-mint-100',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold tracking-wide '.$toneClasses]) }}>
    {{ $slot }}
</span>
