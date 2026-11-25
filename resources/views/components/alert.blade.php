@props([
    'type' => 'status',
    'title' => null,
])

@php
    $baseClasses = 'rounded-2xl border px-4 py-3 text-sm';

    $stateClasses = match ($type) {
        'error' => 'border-red-300 bg-red-50 text-red-800 dark:border-red-500/40 dark:bg-red-950/40 dark:text-red-200',
        'warning' => 'border-beige-300 bg-beige-100 text-beige-900 dark:border-beige-700 dark:bg-beige-950/50 dark:text-beige-100',
        'success' => 'border-mint-300 bg-mint-100 text-forest-800 dark:border-mint-700 dark:bg-forest-900/70 dark:text-mint-100',
        default => 'border-sage-200 bg-mint-100/90 text-forest-800 dark:border-forest-600 dark:bg-forest-900/70 dark:text-mint-100',
    };

    $role = $type === 'error' ? 'alert' : 'status';
@endphp

<div {{ $attributes->merge(['class' => $baseClasses.' '.$stateClasses, 'role' => $role]) }}>
    @if ($title)
        <p class="mb-1 font-semibold">{{ $title }}</p>
    @endif

    <div>{{ $slot }}</div>
</div>
