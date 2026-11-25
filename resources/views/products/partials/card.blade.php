@php
    $primaryImage = $product->images->first();
@endphp

<article class="group relative flex h-full min-w-0 flex-col overflow-hidden rounded-[1.75rem] border border-white/70 bg-white/65 shadow-soft backdrop-blur-xl transition duration-300 hover:-translate-y-1 hover:shadow-lift dark:border-white/10 dark:bg-forest-950/70">
    <div class="relative block aspect-[4/3] overflow-hidden bg-sage-100 dark:bg-forest-900">
        <a href="{{ route('products.show', $product->slug) }}" aria-label="{{ __('View :title', ['title' => $product->title]) }}" class="block h-full w-full focus-ring">
            @if ($primaryImage)
                <img src="{{ $primaryImage->url() }}" alt="{{ __('Photo of :title', ['title' => $product->title]) }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <span class="flex h-full items-center justify-center px-4 text-center text-base text-forest-700 dark:text-mint-100/70">{{ __('No image available') }}</span>
            @endif
        </a>

        <span class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-2 p-2.5">
            <span class="flex flex-wrap items-start gap-2">
                <x-badge tone="mint" class="shadow-sm">{{ __($product->conditionLabel()) }}</x-badge>
            </span>
            <a href="{{ route('categories.show', $product->category) }}" class="pointer-events-auto max-w-[70%] truncate rounded-full bg-white/85 px-3 py-1 text-xs font-medium text-forest-800 shadow-sm backdrop-blur hover:bg-white focus-ring dark:bg-forest-950/75 dark:text-mint-100 dark:hover:bg-forest-900">
                {{ $product->category->name }}
            </a>
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-2 p-4">
        <h2 class="text-lg font-semibold leading-snug text-forest-900 dark:text-white">
            <a href="{{ route('products.show', $product->slug) }}" class="break-words rounded-sm hover:underline focus-ring">{{ $product->title }}</a>
        </h2>

        <p class="text-xl font-semibold text-forest-800 dark:text-mint-200">{{ $product->formattedPrice() }}</p>

        <p class="mt-auto border-t border-sage-200 pt-2 text-xs text-forest-700 dark:border-white/10 dark:text-mint-100/70">
            {{ __('Seller: :name', ['name' => $product->seller->name]) }}
        </p>
    </div>
</article>
