@props([
    'title',
    'description' => null,
    'eyebrow' => null,
])

<div class="mb-8 space-y-3">
    @if ($eyebrow)
        <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">
            {{ $eyebrow }}
        </p>
    @endif

    <h1 {{ $attributes->merge(['class' => 'text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl']) }}>
        {{ $title }}
    </h1>

    @if ($description)
        <p class="max-w-2xl text-base leading-relaxed text-sage-700 dark:text-mint-100/80">
            {{ $description }}
        </p>
    @endif
</div>
