@extends('layouts.marketplace')

@section('title', __('Promote :title', ['title' => $product->title]).' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Promote :title', ['title' => $product->title])"
        :description="__('Choose a package, send your payment, and upload the receipt for review.')"
    />

    <x-account-tabs active="promotions" />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    @if ($errors->has('product'))
        <x-alert type="error" class="mb-6">{{ $errors->first('product') }}</x-alert>
    @endif

    @if ($packages->isEmpty())
        <x-alert type="warning">
            {{ __('No promotion packages are available right now.') }}
        </x-alert>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <x-seller.promotions.payment-form
                :action="route('seller.promotions.store', $product)"
                :packages="$packages"
                :submit-label="__('Submit payment proof')"
                :cancel-url="route('seller.products.index')"
            />

            <aside class="brand-panel h-fit space-y-4 border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Listing') }}</p>
                    <p class="mt-2 font-semibold text-forest-900 dark:text-white">{{ $product->title }}</p>
                    <p class="mt-1 text-base text-sage-700 dark:text-mint-100/70">{{ $product->formattedPrice() }}</p>
                </div>

                <div class="border-t border-sage-200 pt-4 dark:border-white/10">
                    <p class="text-base font-semibold text-forest-900 dark:text-white">{{ __('How it works') }}</p>
                    <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm text-sage-700 dark:text-mint-100/70">
                        <li>{{ __('Choose a package and submit your payment proof.') }}</li>
                        <li>{{ __('An administrator reviews the payment.') }}</li>
                        <li>{{ __('If rejected, you can correct the submission and resubmit.') }}</li>
                    </ol>
                </div>

                <x-alert type="warning">{{ __('Your listing will not be promoted until an administrator approves the payment.') }}</x-alert>
            </aside>
        </div>
    @endif
@endsection
