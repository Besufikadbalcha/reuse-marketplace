@extends('layouts.marketplace')

@section('title', __('information.about.title').' - '.config('app.name'))
@section('meta-description', __('information.about.meta_description'))

@section('content')
    @php
        $values = __('information.about');
    @endphp

    <section class="relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 p-6 shadow-card backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-9">
        <div class="pointer-events-none absolute -right-16 -top-24 size-72 rounded-full bg-sage-200/70 blur-3xl dark:bg-forest-700/30" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-16 size-56 rounded-full bg-mint-200/50 blur-3xl dark:bg-mint-300/10" aria-hidden="true"></div>

        <div class="relative">
            <x-page-heading
                :eyebrow="$values['eyebrow']"
                :title="$values['heading']"
                :description="$values['intro']"
            />

            <div class="flex flex-wrap gap-2 text-sm text-forest-700 dark:text-mint-100/80">
                <span class="inline-flex items-center gap-2 rounded-full bg-cream-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                    <x-info-icon name="leaf" class="size-4" />
                    {{ __('Reuse marketplace') }}
                </span>
                <span class="inline-flex items-center gap-2 rounded-full bg-sage-100/80 px-3 py-1.5 font-medium dark:bg-forest-900/70">
                    <x-info-icon name="link" class="size-4" />
                    {{ __('Approved listings') }}
                </span>
            </div>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="about-values-heading">
        <h2 id="about-values-heading" class="sr-only">{{ __('information.about.title') }}</h2>

        <div class="grid gap-5 md:grid-cols-3">
            @foreach (['what_we_are', 'our_purpose', 'how_we_connect'] as $key)
                <article class="flex h-full flex-col gap-4 rounded-[1.5rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/70">
                    <span class="grid size-11 place-items-center rounded-2xl bg-sage-100 text-forest-700 dark:bg-forest-800 dark:text-mint-200">
                        <x-info-icon :name="$values[$key]['icon']" class="size-5" />
                    </span>

                    <div class="grid gap-2">
                        <h3 class="text-lg font-semibold tracking-tight text-forest-900 dark:text-white">
                            {{ $values[$key]['title'] }}
                        </h3>
                        <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                            {{ $values[$key]['body'] }}
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]" aria-labelledby="about-impact-heading">
        <div class="grid content-start gap-3 rounded-[1.75rem] border border-mint-200/80 bg-mint-50/80 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/60 sm:p-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">
                {{ __('Reuse marketplace') }}
            </p>
            <h2 id="about-impact-heading" class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">
                {{ $values['impact_title'] }}
            </h2>
            <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                {{ $values['impact_intro'] }}
            </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            @foreach (['less_waste', 'affordable'] as $key)
                <article class="flex h-full flex-col gap-3 rounded-[1.75rem] border border-white/70 bg-cream-50/80 p-6 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/70">
                    <x-info-icon name="leaf" class="size-6 text-forest-600 dark:text-mint-300" />

                    <h3 class="text-lg font-semibold tracking-tight text-forest-900 dark:text-white">
                        {{ $values[$key]['title'] }}
                    </h3>
                    <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                        {{ $values[$key]['body'] }}
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mt-10 grid gap-6 rounded-[2rem] border border-white/70 bg-white/65 p-6 shadow-card backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-9 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] lg:items-center" aria-labelledby="about-trust-heading">
        <div class="grid gap-4">
            <span class="grid size-12 place-items-center rounded-2xl bg-forest-800 text-white shadow-sm dark:bg-mint-200 dark:text-forest-950">
                <x-info-icon name="shield" class="size-6" />
            </span>

            <div class="grid gap-2">
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">
                    {{ __('Admin moderation') }}
                </p>
                <h2 id="about-trust-heading" class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">
                    {{ $values['trust_title'] }}
                </h2>
            </div>
        </div>

        <div class="grid gap-6">
            <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                {{ $values['trust_body'] }}
            </p>

            <div class="flex flex-wrap items-center gap-3 rounded-[1.5rem] border border-sage-200 bg-cream-50/70 p-5 dark:border-white/10 dark:bg-forest-900/50">
                <div class="grid flex-1 gap-1">
                    <p class="font-semibold text-forest-900 dark:text-white">{{ $values['cta']['title'] }}</p>
                    <p class="text-base text-sage-700 dark:text-mint-100/75">{{ $values['cta']['body'] }}</p>
                </div>
                <x-button as="a" href="{{ route('how-it-works') }}" variant="secondary">{{ __('How It Works') }}</x-button>
            </div>
        </div>
    </section>
@endsection
