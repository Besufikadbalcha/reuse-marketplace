@props([
    'showText' => true,
    'compact' => false,
])

@php
    $iconSize = $compact ? 'size-8' : 'size-10';
    $markSize = $compact ? 'size-5' : 'size-6';
    $textSize = $compact ? 'text-base' : 'text-lg sm:text-xl';
@endphp

<a {{ $attributes->merge(['href' => route('home'), 'class' => 'inline-flex items-center gap-3 rounded-full focus-ring']) }}>
    <span class="grid {{ $iconSize }} place-items-center rounded-full bg-forest-800 text-white shadow-sm dark:bg-forest-600">
        <svg class="{{ $markSize }}" viewBox="0 0 40 40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11.5 15h17l-1.6 16h-13.8L11.5 15Z" />
            <path d="M15 15v-3a5 5 0 0 1 10 0v3" />
            <path d="m18.5 18.5 2.7 4.5h-5.4l2.7-4.5Z" />
            <path d="m15.8 23-3 5h4" />
            <path d="m24.2 23 3 5h-4" />
        </svg>
    </span>

    @if ($showText)
        <span class="{{ $textSize }} font-semibold tracking-tight text-forest-900 dark:text-mint-100">
            {{ config('app.name') }}
        </span>
    @endif
</a>
