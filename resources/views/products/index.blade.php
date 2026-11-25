@extends('layouts.marketplace')

@section('title', ($isLandingPage ? __('homepage.hero.heading') : __('Browse products')).' - '.config('app.name'))

@if ($isLandingPage)
    @section('meta-description', __('homepage.meta_description'))
@else
    @section('meta-description', __('Explore approved second-hand listings from sellers across :app.', ['app' => config('app.name')]))
@endif

@section('content')
    @php
        $categoryIcons = [
            'bicycles' => 'bike',
            'mobile-phones' => 'phone',
            'furniture' => 'sofa',
            'books' => 'book',
            'clothing' => 'shirt',
        ];
        $heroProduct = $products->getCollection()->first(fn ($product): bool => $product->images->isNotEmpty());
        $heroCarouselItems = $heroShowcaseItems;

        if ($heroCarouselItems === [] && $heroProduct !== null) {
            $heroCarouselItems = [[
                'id' => (int) $heroProduct->id,
                'slug' => (string) $heroProduct->slug,
                'title' => (string) $heroProduct->title,
                'imageUrl' => (string) $heroProduct->images->first()?->url(),
                'imageUrls' => $heroProduct->images
                    ->map(fn ($image): string => (string) $image->url())
                    ->all(),
                'price' => $heroProduct->formattedPrice(),
                'condition' => $heroProduct->conditionLabel(),
                'url' => route('products.show', $heroProduct->slug),
                'priority' => 0,
            ]];
        }

        $heroShowcaseTickerBase = $heroCarouselItems;
        $heroShowcaseSequenceCopies = 1;

        if ($heroShowcaseTickerBase !== []) {
            $estimatedAdWidth = 416;
            $estimatedGap = 8;
            $estimatedBoardWidth = 1200;
            $estimatedSequenceWidth = count($heroShowcaseTickerBase) * ($estimatedAdWidth + $estimatedGap);

            $heroShowcaseSequenceCopies = max(1, (int) ceil(($estimatedBoardWidth + $estimatedGap) / $estimatedSequenceWidth));
        }

        $heroShowcaseTickerItems = [];

        for ($copy = 0; $copy < $heroShowcaseSequenceCopies; $copy++) {
            foreach ($heroShowcaseTickerBase as $heroShowcaseItem) {
                $heroShowcaseTickerItems[] = $heroShowcaseItem;
            }
        }

        $heroShowcaseTickerDuration = max(35, count($heroShowcaseTickerItems) * 9);

        $howSteps = __('homepage.how.steps');
        $aboutPoints = __('homepage.about.points');
    @endphp

    @if ($isLandingPage)
        <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] lg:items-center" aria-labelledby="home-hero-heading">
            <div class="min-w-0 max-w-2xl">
                <p class="text-xs font-semibold uppercase leading-none tracking-widest text-forest-600 dark:text-mint-300">{{ __('Find something worth keeping') }}</p>
                <h1 id="home-hero-heading" class="mt-2 text-4xl font-semibold tracking-tight text-forest-950 dark:text-white sm:text-5xl">
                    {{ __('homepage.hero.heading') }}
                </h1>
                <p class="mt-2 max-w-xl text-base leading-6 text-forest-700 dark:text-mint-100/80">
                    {{ __('homepage.hero.description') }}
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <a href="#all-products" class="inline-flex items-center justify-center gap-2 rounded-full bg-forest-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-forest-900/15 transition hover:bg-forest-700 focus-ring dark:bg-mint-400 dark:text-forest-950 dark:hover:bg-mint-300">
                        <x-info-icon name="search" class="size-5" />
                        {{ __('Browse products') }}
                    </a>
                    <a href="{{ route('seller.products.create') }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-sage-300 bg-white/70 px-4 py-2.5 text-sm font-semibold text-forest-800 transition hover:border-forest-400 hover:bg-white focus-ring dark:border-white/15 dark:bg-forest-900/60 dark:text-mint-100 dark:hover:border-mint-400 dark:hover:bg-forest-900">
                        <x-info-icon name="plus" class="size-5" />
                        {{ __('Sell an item') }}
                    </a>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-forest-700 dark:text-mint-100/80">
                    <span class="inline-flex items-center gap-2">
                        <x-info-icon name="shield" class="size-4 text-forest-500 dark:text-mint-300" />
                        {{ __('Approved listings') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <x-info-icon name="leaf" class="size-4 text-forest-500 dark:text-mint-300" />
                        {{ __('Second-life finds') }}
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <x-info-icon name="user" class="size-4 text-forest-500 dark:text-mint-300" />
                        {{ __('Community sellers') }}
                    </span>
                </div>
            </div>

            <div class="relative min-h-0 min-w-0 overflow-hidden rounded-[2rem] border border-white/80 bg-white/70 p-3 shadow-[0_30px_90px_rgba(23,60,41,0.18)] backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/70">
                <x-botanical-background class="opacity-70 dark:opacity-30" />

                <div class="relative flex h-full min-h-0 flex-col justify-between gap-2">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-cream-100/90 px-2.5 py-1 text-xs font-semibold uppercase leading-none tracking-widest text-forest-700 dark:bg-forest-900/80 dark:text-mint-200">
                            <x-info-icon name="bag" class="size-4" />
                            {{ __('homepage.hero.visual_tag') }}
                        </span>
                        <span class="rounded-full bg-sage-100/90 px-2.5 py-1 text-xs font-medium leading-none text-forest-700 dark:bg-forest-900/80 dark:text-mint-200">
                            {{ __('Featured Promotion') }}
                        </span>
                    </div>

                    @if ($heroCarouselItems !== [])
                        <div class="mx-auto w-full" data-hero-showcase-carousel role="group" aria-roledescription="ticker" aria-label="{{ __('Featured promotions') }}">
                            <div class="relative h-60 overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64">
                                <div
                                    data-hero-showcase-ticker
                                    class="hero-promotion-ticker__track flex h-full w-max"
                                    style="animation-duration: {{ $heroShowcaseTickerDuration }}s"
                                >
                                    @foreach ([$heroShowcaseTickerItems, $heroShowcaseTickerItems] as $heroShowcaseSequence)
                                        <div
                                            class="flex h-full w-max shrink-0 gap-2 pr-2"
                                            data-hero-showcase-ticker-sequence
                                            @if ($loop->last) aria-hidden="true" @endif
                                        >
                                            @foreach ($heroShowcaseSequence as $heroShowcaseAd)
                                                <a
                                                    data-hero-showcase-ad
                                                    data-hero-showcase-product-id="{{ $heroShowcaseAd['id'] }}"
                                                    href="{{ $heroShowcaseAd['url'] }}"
                                                    aria-label="{{ __('View :title', ['title' => $heroShowcaseAd['title']]) }}"
                                                    @if ($loop->parent->last) tabindex="-1" @endif
                                                    class="relative block h-full w-64 shrink-0 overflow-hidden focus-ring"
                                                >
                                                    <span class="flex h-full items-center justify-center">
                                                        <img
                                                            src="{{ $heroShowcaseAd['imageUrl'] }}"
                                                            alt="{{ $heroShowcaseAd['title'] }}"
                                                            data-hero-showcase-photo
                                                            class="max-h-full max-w-full object-contain"
                                                            loading="lazy"
                                                        >
                                                    </span>
                                                    <span class="absolute left-3 top-3 rounded-full bg-cream-100/90 px-2 py-0.5 text-[0.65rem] font-semibold uppercase tracking-widest text-forest-700 dark:bg-forest-900/80 dark:text-mint-200">
                                                        {{ __('Sponsored') }}
                                                    </span>
                                                    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-forest-950/90 via-forest-950/60 to-transparent px-3 pb-3 pt-8 text-white">
                                                        <span class="block truncate text-base font-semibold">{{ $heroShowcaseAd['title'] }}</span>
                                                        <span class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                                            <span class="font-semibold">{{ $heroShowcaseAd['price'] }}</span>
                                                            <span class="rounded-full bg-white/20 px-2 py-0.5 font-medium backdrop-blur">{{ $heroShowcaseAd['condition'] }}</span>
                                                        </span>
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                                <span data-hero-showcase-ticker-end aria-hidden="true" class="sr-only"></span>
                            </div>
                        </div>
                    @else
                        <div class="flex h-60 items-center justify-center rounded-3xl border border-dashed border-sage-300 bg-white/60 dark:border-white/15 dark:bg-forest-900/50 sm:h-64 lg:h-64">
                            <x-info-icon name="leaf" class="size-16 text-forest-400 dark:text-mint-300/70" />
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @if ($isLandingPage && $heroCarouselItems !== [])
            <script>
                (() => {
                    const carousel = document.querySelector('[data-hero-showcase-carousel]');
                    const ticker = document.querySelector('[data-hero-showcase-ticker]');
                    let isVisible = true;

                    if (! carousel || ! ticker) {
                        return;
                    }

                    const tickerAnimation = () => ticker.getAnimations()[0] ?? null;

                    const syncTicker = () => {
                        const animation = tickerAnimation();

                        if (! animation) {
                            return;
                        }

                        if (! isVisible || document.hidden) {
                            animation.pause();

                            return;
                        }

                        animation.play();
                    };

                    document.addEventListener('visibilitychange', syncTicker);

                    if ('IntersectionObserver' in window) {
                        const observer = new IntersectionObserver((entries) => {
                            isVisible = entries[0]?.isIntersecting ?? true;
                            syncTicker();
                        });

                        observer.observe(carousel);
                    }

                    syncTicker();
                })();
            </script>
        @endif

        <section id="home-categories" class="mt-2 scroll-mt-24" aria-labelledby="home-categories-label">
            <div class="max-w-2xl">
                <p id="home-categories-label" class="text-xs font-semibold uppercase leading-none tracking-widest text-forest-600 dark:text-mint-300">{{ __('Browse by category') }}</p>
            </div>

            <div class="mt-1.5 flex flex-wrap gap-2">
                <a href="#all-products" class="inline-flex items-center gap-2 rounded-full bg-forest-800 px-3.5 py-1.5 text-sm font-semibold leading-none text-white shadow-sm transition hover:bg-forest-700 focus-ring dark:bg-mint-300 dark:text-forest-950 dark:hover:bg-mint-200">
                    <x-info-icon name="bag" class="size-4" />
                    {{ __('All products') }}
                </a>

                @forelse ($categories as $categoryItem)
                    <a href="{{ route('categories.show', $categoryItem) }}" class="inline-flex items-center gap-2 rounded-full border border-sage-300 bg-white/75 px-3.5 py-1.5 text-sm font-medium leading-none text-forest-800 shadow-sm transition hover:border-forest-400 hover:bg-white focus-ring dark:border-white/15 dark:bg-forest-900/60 dark:text-mint-100 dark:hover:border-mint-400 dark:hover:bg-forest-900">
                        <x-info-icon :name="$categoryIcons[$categoryItem->slug] ?? 'bag'" class="size-4" />
                        {{ $categoryItem->name }}
                    </a>
                @empty
                    <x-alert type="warning">{{ __('homepage.categories.empty') }}</x-alert>
                @endforelse
            </div>
        </section>
    @endif

    <section id="all-products" class="mt-2 scroll-mt-24" aria-label="{{ __('Browse products') }}">
        @if ($category !== null)
            <div class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 p-4 shadow-[0_28px_80px_rgba(23,60,41,0.18)] backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-5">
                <div class="pointer-events-none absolute -right-16 -top-24 size-56 rounded-full bg-sage-200/70 blur-3xl dark:bg-forest-700/30" aria-hidden="true"></div>

                <div class="relative">
                    <x-page-heading
                        :eyebrow="__('Browse by category')"
                        :title="$category->name"
                        :description="$category->description ?: __('Explore approved pre-loved listings in :category.', ['category' => $category->name])"
                    />

                    <div class="flex flex-wrap items-center gap-3 text-sm text-forest-700 dark:text-mint-100/80">
                        <span class="inline-flex items-center gap-2 rounded-full bg-cream-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                            <span class="size-2 rounded-full bg-forest-500" aria-hidden="true"></span>
                            {{ __('Approved listings') }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-sage-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                            <span class="size-2 rounded-full bg-mint-500" aria-hidden="true"></span>
                            {{ __('Second-life finds') }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-beige-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                            <span class="size-2 rounded-full bg-cream-600" aria-hidden="true"></span>
                            {{ __('Community sellers') }}
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-1">
            @include('products.partials.filters', [
                'categories' => $categories,
                'locations' => $locations,
                'conditions' => $conditions,
                'filters' => $filters,
                'category' => $category,
                'formAction' => $formAction,
                'clearFiltersUrl' => $clearFiltersUrl,
                'activeFilters' => $activeFilters,
            ])
        </div>

        @if ($products->isEmpty())
            <x-alert type="warning" class="mt-6 text-center">
                <h2 class="text-2xl font-semibold">{{ __('No products found') }}</h2>
                <p class="mt-2 text-base text-forest-700 dark:text-mint-100/80">{{ __('Try a different search term or clear the filters.') }}</p>
            </x-alert>
        @else
            <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase leading-none tracking-widest text-forest-600 dark:text-mint-300">{{ __('Latest finds') }}</p>
                    <p class="mt-1 text-base text-sage-700 dark:text-mint-100/70">
                        {{ __('Showing :first to :last of :total items', ['first' => $products->firstItem(), 'last' => $products->lastItem(), 'total' => $products->total()]) }}
                    </p>
                </div>

                <p class="text-base text-sage-700 dark:text-mint-100/70">{{ __('Fresh options from the community') }}</p>
            </div>

            <div class="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($products as $product)
                    @include('products.partials.card', [
                        'product' => $product,
                    ])
                @endforeach
            </div>

            <div class="mt-6 rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-5">
                {{ $products->links() }}
            </div>
        @endif
    </section>

    @if ($isLandingPage)
        <section id="home-how" class="mt-16 scroll-mt-24" aria-labelledby="home-how-heading">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Reuse marketplace') }}</p>
                    <h2 id="home-how-heading" class="mt-3 text-2xl font-semibold tracking-tight text-forest-950 dark:text-white">
                        {{ __('homepage.how.heading') }}
                    </h2>
                    <p class="mt-3 text-base leading-7 text-forest-700 dark:text-mint-100/80">
                        {{ __('homepage.how.description') }}
                    </p>
                </div>

                <x-nav-link href="{{ route('how-it-works') }}" class="text-sm font-semibold text-forest-700 dark:text-mint-200">
                    {{ __('homepage.how.link') }}
                    <span aria-hidden="true">→</span>
                </x-nav-link>
            </div>

            <ol class="mt-7 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($howSteps as $step)
                    <li class="rounded-[1.5rem] border border-white/80 bg-white/70 p-5 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60">
                        <span class="inline-flex size-10 items-center justify-center rounded-2xl bg-cream-100 text-forest-700 dark:bg-forest-800 dark:text-mint-200">
                            <x-info-icon :name="$step['icon']" class="size-5" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-forest-950 dark:text-white">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-base leading-7 text-forest-700 dark:text-mint-100/75">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section id="home-about" class="mt-16 scroll-mt-24 rounded-[2rem] border border-white/80 bg-white/70 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-9" aria-labelledby="home-about-heading">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:items-start">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Reuse marketplace') }}</p>
                    <h2 id="home-about-heading" class="mt-3 text-2xl font-semibold tracking-tight text-forest-950 dark:text-white">
                        {{ __('homepage.about.heading') }}
                    </h2>
                    <p class="mt-4 text-base leading-7 text-forest-700 dark:text-mint-100/80">
                        {{ __('homepage.about.description') }}
                    </p>
                    <x-nav-link href="{{ route('about-us') }}" class="mt-5 inline-flex text-sm font-semibold text-forest-700 dark:text-mint-200">
                        {{ __('homepage.about.link') }}
                        <span aria-hidden="true">→</span>
                    </x-nav-link>
                </div>

                <ul class="grid gap-4 sm:grid-cols-3">
                    @foreach ($aboutPoints as $point)
                        <li class="rounded-2xl bg-cream-100/70 p-5 dark:bg-forest-900/60">
                            <x-info-icon :name="$point['icon']" class="size-6 text-forest-600 dark:text-mint-300" />
                            <h3 class="mt-3 text-base font-semibold text-forest-950 dark:text-white">{{ $point['title'] }}</h3>
                            <p class="mt-2 text-base leading-7 text-forest-700 dark:text-mint-100/75">{{ $point['body'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif
@endsection
