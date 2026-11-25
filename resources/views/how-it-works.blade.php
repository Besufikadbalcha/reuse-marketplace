@extends('layouts.marketplace')

@section('title', __('information.how_it_works.title').' - '.config('app.name'))
@section('meta-description', __('information.how_it_works.meta_description'))

@section('content')
    @php
        $steps = __('information.how_it_works.steps');
    @endphp

    <section class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 p-6 shadow-card backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-9">
        <div class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-sage-200/70 blur-3xl dark:bg-forest-700/30" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-16 size-56 rounded-full bg-mint-200/50 blur-3xl dark:bg-mint-300/10" aria-hidden="true"></div>

        <div class="relative">
            <x-page-heading
                :eyebrow="__('information.how_it_works.eyebrow')"
                :title="__('information.how_it_works.heading')"
                :description="__('information.how_it_works.intro')"
            />

            <div class="flex flex-wrap gap-2 text-sm text-forest-700 dark:text-mint-100/80">
                <span class="inline-flex items-center gap-2 rounded-full bg-cream-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                    <span class="size-2 rounded-full bg-forest-500" aria-hidden="true"></span>
                    {{ __('Browse products') }}
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-sage-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                    <span class="size-2 rounded-full bg-mint-500" aria-hidden="true"></span>
                    {{ __('Buy now') }}
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-beige-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                    <span class="size-2 rounded-full bg-cream-600" aria-hidden="true"></span>
                    {{ __('Sell an item') }}
                </span>
            </div>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="how-it-works-steps-heading">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="grid gap-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">
                    {{ __('information.how_it_works.steps_title') }}
                </p>
                <h2 id="how-it-works-steps-heading" class="max-w-2xl text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">
                    {{ __('information.how_it_works.steps_intro') }}
                </h2>
            </div>
        </div>

        <ol class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $step)
                <li class="flex h-full flex-col gap-4 rounded-[1.5rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-xl transition hover:-translate-y-1 dark:border-white/10 dark:bg-forest-950/70 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-sage-100 text-forest-700 dark:bg-forest-800 dark:text-mint-200">
                            <x-info-icon :name="$step['icon']" class="size-5" />
                        </span>
                        <span class="text-sm font-semibold tabular-nums text-forest-400 dark:text-mint-300/70">
                            {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                        </span>
                    </div>

                    <div class="grid gap-2">
                        <h3 class="text-base font-semibold leading-snug tracking-tight text-forest-900 dark:text-white">
                            {{ $step['title'] }}
                        </h3>
                        <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                            {{ $step['body'] }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="mt-10 grid gap-5 lg:grid-cols-2" aria-label="{{ __('information.how_it_works.title') }}">
        <div class="relative overflow-hidden rounded-[1.75rem] border border-mint-200/80 bg-mint-50/80 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/60 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-14 size-44 rounded-full bg-mint-200/70 blur-3xl dark:bg-mint-300/10" aria-hidden="true"></div>
            <x-info-icon name="bag" class="size-7 text-forest-700 dark:text-mint-200" />

            <div class="relative mt-5 grid gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">
                    {{ __('information.how_it_works.buyer_panel.title') }}
                </h2>
                <p class="max-w-xl text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                    {{ __('information.how_it_works.buyer_panel.body') }}
                </p>
            </div>

            <div class="relative mt-6 flex flex-wrap gap-3">
                <x-button as="a" href="{{ route('home') }}">{{ __('Browse products') }}</x-button>
                @guest
                    <x-button as="a" href="{{ route('register') }}" variant="secondary">{{ __('Create an account') }}</x-button>
                    <x-button as="a" href="{{ route('login') }}" variant="ghost">{{ __('Log in') }}</x-button>
                @else
                    <x-button as="a" href="{{ route('orders.index') }}" variant="secondary">{{ __('My orders') }}</x-button>
                    <x-button as="a" href="{{ route('seller.products.index') }}" variant="ghost">{{ __('Manage my listings') }}</x-button>
                @endguest
            </div>
        </div>

        <div class="relative overflow-hidden rounded-[1.75rem] border border-cream-400/70 bg-cream-100/80 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/60 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-14 size-44 rounded-full bg-cream-400/50 blur-3xl dark:bg-cream-600/10" aria-hidden="true"></div>
            <x-info-icon name="plus" class="size-7 text-forest-700 dark:text-mint-200" />

            <div class="relative mt-5 grid gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">
                    {{ __('information.how_it_works.seller_panel.title') }}
                </h2>
                <p class="max-w-xl text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                    {{ __('information.how_it_works.seller_panel.body') }}
                </p>
            </div>

            <div class="relative mt-6 flex flex-wrap gap-3">
                <x-button as="a" href="{{ route('seller.products.create') }}">{{ __('Sell an item') }}</x-button>
                <x-button as="a" href="{{ route('about-us') }}" variant="secondary">{{ __('About Us') }}</x-button>
            </div>
        </div>
    </section>
@endsection
