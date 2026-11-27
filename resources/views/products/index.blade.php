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
    @endphp

    @if ($isLandingPage)
        <section class="flex flex-wrap items-end justify-between gap-2" aria-labelledby="home-hero-heading">
            <div class="min-w-0">
                <h1 id="home-hero-heading" class="text-2xl font-semibold tracking-tight text-forest-950 dark:text-white sm:text-3xl">
                    {{ __('homepage.hero.heading') }}
                </h1>
                <p class="mt-1 text-sm text-forest-700 dark:text-mint-100/75">
                    {{ __('homepage.hero.description') }}
                </p>
            </div>
        </section>

        @if ($heroCarouselItems !== [])
            <section class="mt-2" aria-label="{{ __('Featured promotions') }}">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold uppercase leading-none tracking-widest text-forest-600 dark:text-mint-300">
                        <x-info-icon name="bag" class="size-3.5" />
                        {{ __('Featured Promotion') }}
                    </span>
                </div>

                <div class="mx-auto w-full" data-hero-showcase-carousel role="group" aria-roledescription="ticker" aria-label="{{ __('Featured promotions') }}">
                    <div class="relative h-60 touch-pan-y select-none overflow-hidden rounded-3xl bg-sage-100 dark:bg-forest-900 sm:h-64 lg:h-64" data-hero-showcase-board>
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
                                            data-hero-showcase-images='@json($heroShowcaseAd["imageUrls"])'
                                            href="{{ $heroShowcaseAd['url'] }}"
                                            aria-label="{{ __('View :title', ['title' => $heroShowcaseAd['title']]) }}"
                                            @if ($loop->parent->last) tabindex="-1" @endif
                                            class="relative block h-full w-64 shrink-0 overflow-hidden focus-ring"
                                            draggable="false"
                                        >
                                            <span class="relative flex h-full items-center justify-center overflow-hidden" data-hero-showcase-image-frame>
                                                <span class="hero-promotion-image-track flex h-full w-max items-center" data-hero-showcase-image-track>
                                                    @php($originalImageCount = count($heroShowcaseAd['imageUrls']))
                                                    @foreach (array_merge($heroShowcaseAd['imageUrls'], $originalImageCount > 1 ? $heroShowcaseAd['imageUrls'] : []) as $imageUrl)
                                                        @php($isPrimaryImage = $loop->first)
                                                        @php($isDuplicatedTrackImage = $originalImageCount > 1 && $loop->iteration > $originalImageCount)
                                                        <span class="flex h-full w-64 shrink-0 items-center justify-center px-2" @if ($isDuplicatedTrackImage) aria-hidden="true" @endif>
                                                            <img
                                                                src="{{ $imageUrl }}"
                                                                alt="{{ $isPrimaryImage ? $heroShowcaseAd['title'] : '' }}"
                                                                @if ($isPrimaryImage) data-hero-showcase-photo @endif
                                                                @if (! $isPrimaryImage) aria-hidden="true" @endif
                                                                class="max-h-full max-w-full object-contain"
                                                                draggable="false"
                                                                loading="lazy"
                                                            >
                                                        </span>
                                                    @endforeach
                                                </span>
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
            </section>
        @endif

        @if ($isLandingPage && $heroCarouselItems !== [])
            <script>
                (() => {
                    const carousel = document.querySelector('[data-hero-showcase-carousel]');
                    const ticker = document.querySelector('[data-hero-showcase-ticker]');
                    const board = carousel?.querySelector('[data-hero-showcase-board]');
                    const firstSequence = carousel?.querySelector('[data-hero-showcase-ticker-sequence]');
                    const ads = carousel ? Array.from(carousel.querySelectorAll('[data-hero-showcase-ad]')) : [];
                    let isVisible = true;
                    let isHovered = false;
                    let isFocused = false;
                    let isDragging = false;
                    let didDrag = false;
                    let pointerId = null;
                    let startX = 0;
                    let startY = 0;
                    let lastX = 0;
                    let dragAxis = null;
                    let dragDistance = 0;
                    let manualOffset = 0;
                    let resumeTimer = null;

                    if (! carousel || ! ticker) {
                        return;
                    }

                    const tickerAnimation = () => ticker.getAnimations()[0] ?? null;

                    const imageTracks = Array.from(carousel.querySelectorAll('[data-hero-showcase-image-track]'));
                    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

                    imageTracks.forEach((track) => {
                        const images = JSON.parse(track.closest('[data-hero-showcase-ad]')?.dataset.heroShowcaseImages || '[]');
                        const count = Math.max(1, images.length);
                        track.style.setProperty('--hero-image-slide-duration', `${Math.max(8, count * 4)}s`);
                        if (count < 2) {
                            track.style.animation = 'none';
                            track.style.setProperty('transform', 'translateX(0)');
                        }
                    });

                    const syncTicker = () => {
                        const animation = tickerAnimation();

                        if (! animation) {
                            return;
                        }

                        const shouldPause = ! isVisible || document.hidden || isHovered || isFocused || isDragging;

                        if (shouldPause) {
                            animation.pause();
                            imageTracks.forEach((track) => track.getAnimations().forEach((item) => item.pause()));
                            return;
                        }

                        animation.play();
                        imageTracks.forEach((track) => {
                            const images = JSON.parse(track.closest('[data-hero-showcase-ad]')?.dataset.heroShowcaseImages || '[]');
                            if (images.length < 2) {
                                return;
                            }
                            const [item] = track.getAnimations();
                            if (item) {
                                item.play();
                            }
                        });
                    };

                    const cancelResume = () => {
                        if (resumeTimer !== null) {
                            window.clearTimeout(resumeTimer);
                            resumeTimer = null;
                        }
                    };

                    const scheduleResume = () => {
                        cancelResume();

                        resumeTimer = window.setTimeout(() => {
                            resumeTimer = null;
                            syncTicker();
                        }, 700);
                    };

                    const parseDuration = (value) => {
                        if (typeof value === 'number') {
                            return value;
                        }

                        const match = String(value).trim().match(/^([\d.]+)(ms|s)?$/);

                        if (! match) {
                            return 60000;
                        }

                        return Number(match[1]) * (match[2] === 's' ? 1000 : 1);
                    };

                    const durationMs = () => {
                        const animation = tickerAnimation();
                        const timing = animation?.effect?.getComputedTiming();
                        const duration = parseDuration(timing?.duration ?? getComputedStyle(ticker).animationDuration);

                        return duration > 0 ? duration : 60000;
                    };

                    const sequenceWidth = () => firstSequence?.getBoundingClientRect().width || ticker.scrollWidth / 2 || 1;

                    const adStepWidth = () => {
                        if (ads.length < 2) {
                            return sequenceWidth();
                        }

                        return Math.abs(ads[1].getBoundingClientRect().left - ads[0].getBoundingClientRect().left) || sequenceWidth();
                    };

                    const shiftBy = (distance) => {
                        const animation = tickerAnimation();
                        const width = sequenceWidth();

                        if (animation) {
                            const duration = durationMs();
                            const current = Number(animation.currentTime) || 0;
                            const next = ((current + (distance / width) * duration) % duration + duration) % duration;

                            animation.currentTime = next;
                            syncTicker();

                            return;
                        }

                        manualOffset -= distance;
                        ticker.style.transform = `translateX(${manualOffset}px)`;
                    };

                    const isHoverPointer = (event) => event.pointerType === 'mouse' || event.pointerType === 'pen';

                    board?.addEventListener('dragstart', (event) => event.preventDefault());

                    board?.addEventListener('pointerenter', (event) => {
                        if (! isHoverPointer(event)) {
                            return;
                        }

                        cancelResume();
                        isHovered = true;
                        syncTicker();
                    });

                    board?.addEventListener('pointerleave', (event) => {
                        if (! isHoverPointer(event)) {
                            return;
                        }

                        isHovered = false;
                        scheduleResume();
                    });

                    carousel.addEventListener('focusin', (event) => {
                        if (! event.target.matches?.(':focus-visible')) {
                            return;
                        }

                        cancelResume();
                        isFocused = true;
                        syncTicker();
                    });

                    carousel.addEventListener('focusout', (event) => {
                        if (carousel.contains(event.relatedTarget)) {
                            return;
                        }

                        isFocused = false;
                        scheduleResume();
                    });

                    const onPointerMove = (event) => {
                        if (! isDragging || event.pointerId !== pointerId) {
                            return;
                        }

                        const offsetX = event.clientX - startX;
                        const offsetY = event.clientY - startY;

                        if (! dragAxis && Math.hypot(offsetX, offsetY) > 6) {
                            dragAxis = Math.abs(offsetX) > Math.abs(offsetY) ? 'x' : 'y';

                            if (dragAxis === 'y') {
                                stopDragging(false);
                                scheduleResume();

                                return;
                            }
                        }

                        if (dragAxis !== 'x') {
                            return;
                        }

                        event.preventDefault();
                        didDrag = true;
                        dragDistance += lastX - event.clientX;
                        shiftBy(lastX - event.clientX);
                        lastX = event.clientX;
                    };

                    const stopDragging = (snap) => {
                        if (snap && didDrag) {
                            const step = adStepWidth();
                            const direction = dragDistance >= 0 ? 1 : -1;
                            const steps = Math.max(1, Math.round(Math.abs(dragDistance) / step));

                            shiftBy(direction * steps * step - dragDistance);
                        }

                        isDragging = false;
                        pointerId = null;
                        window.removeEventListener('pointermove', onPointerMove);
                        window.removeEventListener('pointerup', onPointerUp);
                        window.removeEventListener('pointercancel', onPointerCancel);

                        if (didDrag) {
                            window.setTimeout(() => {
                                didDrag = false;
                            }, 250);
                        }
                    };

                    const onPointerUp = (event) => {
                        if (! isDragging || event.pointerId !== pointerId) {
                            return;
                        }

                        stopDragging(true);
                        scheduleResume();
                    };

                    const onPointerCancel = (event) => {
                        if (! isDragging || event.pointerId !== pointerId) {
                            return;
                        }

                        stopDragging(false);
                        scheduleResume();
                    };

                    board?.addEventListener('pointerdown', (event) => {
                        if (! event.isPrimary || event.target.closest?.('button')) {
                            return;
                        }

                        if (event.pointerType === 'mouse' && event.button !== 0) {
                            return;
                        }

                        cancelResume();
                        isDragging = true;
                        didDrag = false;
                        dragAxis = null;
                        dragDistance = 0;
                        pointerId = event.pointerId;
                        startX = lastX = event.clientX;
                        startY = event.clientY;
                        window.addEventListener('pointermove', onPointerMove, { passive: false });
                        window.addEventListener('pointerup', onPointerUp);
                        window.addEventListener('pointercancel', onPointerCancel);
                        syncTicker();
                    });

                    carousel.addEventListener('click', (event) => {
                        const link = event.target.closest('a[data-hero-showcase-ad]');

                        if (! link) {
                            return;
                        }

                        if (didDrag) {
                            event.preventDefault();
                            event.stopPropagation();
                            didDrag = false;
                        }
                    }, true);

                    prefersReducedMotion.addEventListener?.('change', syncTicker);
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
            <div class="rounded-2xl border border-white/70 bg-white/65 p-3 shadow-sm dark:border-white/10 dark:bg-forest-950/70 sm:p-4">
                <x-page-heading
                    :eyebrow="__('Browse by category')"
                    :title="$category->name"
                    :description="$category->description ?: __('Explore approved pre-loved listings in :category.', ['category' => $category->name])"
                />
            </div>
        @endif

        <div class="mt-1">
            @include('products.partials.filters', [
                'categories' => $categories,
                'locations' => $locations,
                'conditions' => $conditions,
                'categoryCounts' => $categoryCounts,
                'conditionCounts' => $conditionCounts,
                'locationCounts' => $locationCounts,
                'filters' => $filters,
                'category' => $category,
                'formAction' => $formAction,
                'clearFiltersUrl' => $clearFiltersUrl,
                'activeFilters' => $activeFilters,
            ])
        </div>

        @if ($products->isEmpty())
            <x-alert type="warning" class="mt-6 text-center">
                <div class="mx-auto flex max-w-xl flex-col items-center gap-3">
                    <h2 class="text-2xl font-semibold">{{ __('No products found') }}</h2>
                    <p class="text-base text-forest-700 dark:text-mint-100/80">{{ __('Try fewer words or check the spelling.') }}</p>

                    @if ($activeFilters->isNotEmpty())
                        <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center rounded-full bg-forest-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-forest-800 focus-ring dark:bg-mint-300 dark:text-forest-950 dark:hover:bg-mint-200">{{ __('Clear filters') }}</a>
                    @endif
                </div>
            </x-alert>
        @else
            <div class="mt-2">
                <p class="text-xs text-sage-700 dark:text-mint-100/70">
                    {{ __('Showing :first to :last of :total items', ['first' => $products->firstItem(), 'last' => $products->lastItem(), 'total' => $products->total()]) }}
                </p>
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
@endsection
