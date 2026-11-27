@php
    $primaryImage = $product->images->first();
@endphp

<article class="group relative flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-white/70 bg-white/70 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-soft dark:border-white/10 dark:bg-forest-950/70">
    <div class="relative block aspect-[16/11] overflow-hidden bg-sage-100 dark:bg-forest-900">
        <a href="{{ route('products.show', $product->slug) }}" aria-label="{{ __('View :title', ['title' => $product->title]) }}" class="block h-full w-full focus-ring">
            @if ($primaryImage)
                <img src="{{ $primaryImage->url() }}" alt="{{ __('Photo of :title', ['title' => $product->title]) }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
            @else
                <span class="flex h-full items-center justify-center px-3 text-center text-sm text-forest-700 dark:text-mint-100/70">{{ __('No image available') }}</span>
            @endif
        </a>

        <span class="pointer-events-none absolute inset-x-0 top-0 z-10 flex items-start justify-between gap-1.5 p-2">
            <x-badge tone="mint" class="shadow-sm">{{ __($product->conditionLabel()) }}</x-badge>
            <a href="{{ route('categories.show', $product->category) }}" class="pointer-events-auto max-w-[68%] truncate rounded-full bg-white/85 px-2 py-0.5 text-[0.7rem] font-medium text-forest-800 shadow-sm hover:bg-white focus-ring dark:bg-forest-950/75 dark:text-mint-100 dark:hover:bg-forest-900">
                {{ $product->category->name }}
            </a>
        </span>
    </div>

    <div class="flex flex-1 flex-col gap-1 p-3">
        <h2 class="text-base font-semibold leading-snug text-forest-900 dark:text-white">
            <a href="{{ route('products.show', $product->slug) }}" class="break-words rounded-sm hover:underline focus-ring">{{ $product->title }}</a>
        </h2>

        <p class="text-lg font-semibold text-forest-800 dark:text-mint-200">{{ $product->formattedPrice() }}</p>

        <div class="mt-auto space-y-0.5 border-t border-sage-200 pt-1 text-[0.7rem] text-forest-700 dark:border-white/10 dark:text-mint-100/70">
            <p>
                <a href="{{ route('sellers.show', $product->seller) }}" class="font-medium text-forest-800 hover:underline focus-ring dark:text-mint-100">{{ __('Seller: :name', ['name' => $product->seller->name]) }}</a>
            </p>
            <p>{{ __('Listed: :date', ['date' => \App\Models\Product::formatDate($product->created_at)]) }}</p>
        </div>
    </div>
</article>
