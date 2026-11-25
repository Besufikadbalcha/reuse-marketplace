@extends('layouts.guest')

@section('title', __('legal.privacy.title').' - '.config('app.name'))

@section('body-class', 'relative min-h-screen overflow-x-hidden bg-cream-200 text-forest-900 antialiased dark:bg-forest-950 dark:text-mint-100')
@section('wrapper-class', 'relative z-10 mx-auto flex min-h-[calc(100vh-5rem)] w-full max-w-5xl flex-col px-5 py-10 sm:px-8 sm:py-14')
@section('container-class', 'relative z-10 w-full')
@section('card-class', 'relative overflow-hidden rounded-[2rem] border border-white/70 bg-white/65 shadow-[0_28px_80px_rgba(23,60,41,0.18)] backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70')

@section('content')
    <header class="relative overflow-hidden border-b border-mint-200/80 bg-mint-50/85 px-6 py-8 dark:border-white/10 dark:bg-forest-900/60 sm:px-10 sm:py-10">
        <div class="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-mint-200/60 blur-3xl dark:bg-mint-300/10" aria-hidden="true"></div>
        <h1 class="relative text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ __('legal.privacy.title') }}</h1>
    </header>

    <div class="grid gap-6 px-6 py-8 sm:px-10 sm:py-10">
        <x-alert type="warning">{{ __('legal.shared.starter_notice') }}</x-alert>

        <p class="text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
            {{ __('legal.privacy.intro') }}
        </p>

        <div class="grid gap-4">
            <section class="rounded-[1.5rem] border border-white/70 bg-cream-50/70 p-5 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/50 sm:p-6">
                <h2 class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">{{ __('legal.privacy.information_we_collect.title') }}</h2>
                <p class="mt-3 text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                    {{ __('legal.privacy.information_we_collect.body') }}
                </p>
            </section>

            <section class="rounded-[1.5rem] border border-white/70 bg-cream-50/70 p-5 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/50 sm:p-6">
                <h2 class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">{{ __('legal.privacy.how_information_is_used.title') }}</h2>
                <p class="mt-3 text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                    {{ __('legal.privacy.how_information_is_used.body') }}
                </p>
            </section>

            <section class="rounded-[1.5rem] border border-white/70 bg-cream-50/70 p-5 shadow-sm backdrop-blur-xl dark:border-white/10 dark:bg-forest-900/50 sm:p-6">
                <h2 class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">{{ __('legal.privacy.your_choices.title') }}</h2>
                <p class="mt-3 text-base leading-relaxed text-sage-700 dark:text-mint-100/75">
                    {{ __('legal.privacy.your_choices.body') }}
                </p>
            </section>
        </div>
    </div>
@endsection

@section('footer')
    <div class="mt-6 flex justify-center">
        <x-button as="a" href="{{ route('login') }}" variant="secondary">{{ __('legal.shared.back_to_login') }}</x-button>
    </div>
@endsection
