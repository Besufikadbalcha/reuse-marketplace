@props([
    'as' => 'button',
    'type' => 'button',
    'compact' => false,
])

@php
    $classes = 'inline-flex w-full items-center justify-center gap-2 rounded-full bg-linear-to-r from-forest-800 via-forest-600 to-[#153B28] font-semibold text-white transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-900 disabled:pointer-events-none disabled:opacity-60 dark:from-forest-600 dark:via-[#3E8158] dark:to-forest-800 dark:focus-visible:outline-mint-300';
    $classes .= $compact
        ? ' px-4 py-2 text-sm shadow-md shadow-forest-950/15 hover:shadow-lg'
        : ' px-6 py-3.5 text-sm shadow-lg shadow-forest-950/25 hover:-translate-y-0.5 hover:shadow-xl';
@endphp

@if ($as === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => $type, 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
