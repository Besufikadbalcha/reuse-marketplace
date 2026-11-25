@extends('layouts.marketplace')

@section('title', $product->title.' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('home') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to marketplace') }}
        </x-button>
    </div>

    <div class="grid min-w-0 gap-8 lg:grid-cols-2">
        <section aria-label="{{ __('Product images') }}" class="brand-panel min-w-0 overflow-hidden border border-white/70 bg-white/60 p-4 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-6">
            @if ($product->images->isNotEmpty())
                <div class="grid grid-cols-2 gap-4">
                    @foreach ($product->images as $image)
                        <img src="{{ $image->url() }}" alt="{{ __('Photo :number of :title', ['number' => $loop->iteration, 'title' => $product->title]) }}" loading="lazy" class="aspect-[4/3] w-full rounded-2xl border border-white/70 bg-sage-100 object-cover shadow-sm dark:border-white/10 dark:bg-forest-900">
                    @endforeach
                </div>
            @else
                <div class="flex aspect-[4/3] items-center justify-center rounded-2xl border border-dashed border-sage-300 bg-sage-50 text-sm text-forest-700 dark:border-forest-700 dark:bg-forest-900/50 dark:text-mint-100/70">
                    {{ __('No image available') }}
                </div>
            @endif
        </section>

        <section aria-label="{{ __('Product details') }}" class="brand-panel min-w-0 border border-white/70 bg-white/70 p-6 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-badge tone="mint">{{ __($product->conditionLabel()) }}</x-badge>
                <a href="{{ route('categories.show', $product->category) }}" class="rounded-full bg-cream-100/80 px-3 py-1 font-medium text-forest-800 hover:bg-cream-200 focus-ring dark:bg-forest-900/70 dark:text-mint-100 dark:hover:bg-forest-800">
                    {{ $product->category->name }}
                </a>
                @if ($product->location)
                    <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ $product->location->name }}</span>
                @endif
            </div>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $product->title }}</h1>

            <p class="mt-4 text-xl font-semibold text-forest-800 dark:text-mint-200">{{ $product->formattedPrice() }}</p>

            <div class="mt-7 space-y-6 border-t border-sage-200 pt-6 dark:border-white/10">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Description') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-base leading-relaxed text-forest-800 dark:text-mint-100/85">{{ $product->description }}</p>
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Seller') }}</h2>
                    <p class="mt-2 text-base font-medium text-forest-900 dark:text-white">{{ $product->seller->name }}</p>
                </div>

                <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                    @if ($isOwnProduct)
                        <p class="text-base font-medium text-forest-700 dark:text-mint-100/80">{{ __('Sellers cannot buy their own products.') }}</p>
                    @elseif ($canPurchase)
                        @auth
                            <x-button as="a" href="{{ route('checkout.create', $product) }}">{{ __('Buy now') }}</x-button>
                        @else
                            <p class="text-base text-forest-700 dark:text-mint-100/80">{{ __('Log in to buy this item.') }}</p>
                            <x-button as="a" href="{{ route('checkout.create', $product) }}" variant="secondary" class="mt-4">{{ __('Log in to buy now') }}</x-button>
                        @endauth
                    @else
                        <div class="flex flex-wrap items-center gap-3">
                            <x-badge tone="blush">{{ __('Unavailable') }}</x-badge>
                            <p class="text-base font-medium text-forest-700 dark:text-mint-100/80">{{ __('This item is no longer available.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    @if ($similarProducts->isNotEmpty())
        <section class="mt-12" aria-labelledby="similar-adverts-heading">
            <h2 id="similar-adverts-heading" class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">{{ __('Similar adverts') }}</h2>
            <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($similarProducts as $similarProduct)
                    @include('products.partials.card', [
                        'product' => $similarProduct,
                    ])
                @endforeach
            </div>
        </section>
    @endif
@endsection
