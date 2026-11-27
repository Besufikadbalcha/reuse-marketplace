@extends('layouts.marketplace')

@section('title', $seller->name.' - '.__('Seller Profile').' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('home') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to marketplace') }}
        </x-button>
    </div>

    <section class="brand-panel border border-white/70 bg-white/70 p-6 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
            @if ($profileImageUrl)
                <img src="{{ $profileImageUrl }}" alt="{{ __('Profile image of :name', ['name' => $seller->name]) }}" class="h-20 w-20 rounded-full border border-sage-200 object-cover dark:border-white/10">
            @else
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-sage-100 text-2xl font-semibold text-forest-700 dark:bg-forest-900 dark:text-mint-100" aria-hidden="true">
                    {{ strtoupper(mb_substr($seller->name, 0, 1)) }}
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Seller profile') }}</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $seller->name }}</h1>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">
                        {{ __('Member since :date', ['date' => \App\Models\Product::formatDate($seller->created_at)]) }}
                    </span>
                    @if ($publicCityRegion)
                        <span class="rounded-full bg-cream-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">
                            {{ $publicCityRegion }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <dl class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-sage-200 bg-white/70 p-4 dark:border-white/10 dark:bg-forest-900/50">
                <dt class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Completed trades') }}</dt>
                <dd class="mt-2 text-2xl font-semibold text-forest-900 dark:text-white">{{ $completedTradesCount }}</dd>
            </div>
            <div class="rounded-2xl border border-sage-200 bg-white/70 p-4 dark:border-white/10 dark:bg-forest-900/50">
                <dt class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Active listings') }}</dt>
                <dd class="mt-2 text-2xl font-semibold text-forest-900 dark:text-white">{{ $activeApprovedListingsCount }}</dd>
            </div>
            <div class="rounded-2xl border border-sage-200 bg-white/70 p-4 dark:border-white/10 dark:bg-forest-900/50 sm:col-span-2 lg:col-span-1">
                <dt class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Ratings & reviews') }}</dt>
                <dd class="mt-2 text-sm text-forest-700 dark:text-mint-100/80">{{ __('No reviews yet.') }}</dd>
            </div>
        </dl>
    </section>

    <section class="mt-10" aria-labelledby="seller-listings-heading">
        <div class="flex items-end justify-between gap-4">
            <div>
                <h2 id="seller-listings-heading" class="text-2xl font-semibold tracking-tight text-forest-900 dark:text-white">{{ __('Other items from this seller') }}</h2>
                <p class="mt-1 text-sm text-forest-700 dark:text-mint-100/80">{{ __('Approved, available listings currently offered by this seller.') }}</p>
            </div>
        </div>

        @if ($listings->isNotEmpty())
            <div class="mt-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($listings as $listing)
                    @include('products.partials.card', ['product' => $listing])
                @endforeach
            </div>
        @else
            <div class="mt-5 rounded-2xl border border-dashed border-sage-300 bg-sage-50/70 p-8 text-sm text-forest-700 dark:border-forest-700 dark:bg-forest-900/40 dark:text-mint-100/80">
                {{ __('No active listings from this seller right now.') }}
            </div>
        @endif
    </section>
@endsection
