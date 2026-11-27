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
                <div data-product-gallery class="space-y-4">
                    <div class="overflow-hidden rounded-2xl border border-white/70 bg-sage-100 dark:border-white/10 dark:bg-forest-900">
                        <img
                            src="{{ $product->images->first()->url() }}"
                            alt="{{ __('Photo 1 of :title', ['title' => $product->title]) }}"
                            data-product-gallery-main
                            class="aspect-[4/3] w-full object-contain"
                        >
                    </div>

                    @if ($product->images->count() > 1)
                        <div class="grid grid-cols-4 gap-3 sm:grid-cols-5" aria-label="{{ __('Product image thumbnails') }}">
                            @foreach ($product->images as $image)
                                <button
                                    type="button"
                                    data-product-gallery-thumb
                                    data-image-url="{{ $image->url() }}"
                                    data-image-alt="{{ __('Photo :number of :title', ['number' => $loop->iteration, 'title' => $product->title]) }}"
                                    class="overflow-hidden rounded-xl border-2 {{ $loop->first ? 'border-forest-500 dark:border-mint-300' : 'border-transparent' }} bg-sage-100 focus-ring dark:bg-forest-900"
                                    aria-label="{{ __('View image :number', ['number' => $loop->iteration]) }}"
                                >
                                    <img src="{{ $image->url() }}" alt="" loading="lazy" class="aspect-square w-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
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
            <p class="mt-2 text-sm text-forest-600 dark:text-mint-100/70">{{ __('Listed: :date', ['date' => \App\Models\Product::formatDate($product->created_at)]) }}</p>

            <div class="mt-7 space-y-6 border-t border-sage-200 pt-6 dark:border-white/10">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Description') }}</h2>
                    <p class="mt-2 whitespace-pre-line text-base leading-relaxed text-forest-800 dark:text-mint-100/85">{{ $product->description }}</p>
                </div>

                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Seller') }}</h2>
                    <p class="mt-2 text-base font-medium text-forest-900 dark:text-white">
                        <a href="{{ route('sellers.show', $product->seller) }}" class="rounded-sm hover:underline focus-ring">{{ $product->seller->name }}</a>
                    </p>
                    <p class="mt-1 text-sm text-forest-700 dark:text-mint-100/80">{{ __('Member since :date', ['date' => \App\Models\Product::formatDate($product->seller->created_at)]) }}</p>
                </div>

                <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                    @if ($isOwnProduct)
                        <div class="flex flex-wrap items-center gap-3">
                            <x-button as="a" href="{{ route('seller.products.edit', $product) }}">{{ __('Edit listing') }}</x-button>
                            <form method="POST" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Archive this listing?') }}')">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="ghost">{{ __('Archive listing') }}</x-button>
                            </form>
                        </div>
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

    <script>
        document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
            const main = gallery.querySelector('[data-product-gallery-main]');
            const thumbs = Array.from(gallery.querySelectorAll('[data-product-gallery-thumb]'));

            if (! main || thumbs.length === 0) {
                return;
            }

            thumbs.forEach((thumb) => {
                thumb.addEventListener('click', () => {
                    main.src = thumb.dataset.imageUrl;
                    main.alt = thumb.dataset.imageAlt || main.alt;
                    thumbs.forEach((item) => {
                        item.classList.remove('border-forest-500', 'dark:border-mint-300');
                        item.classList.add('border-transparent');
                    });
                    thumb.classList.add('border-forest-500', 'dark:border-mint-300');
                    thumb.classList.remove('border-transparent');
                });
            });
        });
    </script>
@endsection
