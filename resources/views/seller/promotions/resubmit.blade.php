@extends('layouts.marketplace')

@section('title', __('Resubmit payment proof').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Resubmit payment proof')"
        :description="__('Correct the payment details and upload a new receipt. Your package and price stay the same.')"
    />

    <x-account-tabs active="promotions" />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-button as="a" :href="route('seller.promotions.show', $promotion)" variant="secondary" class="w-full sm:w-auto">{{ __('Back to promotion') }}</x-button>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            @if ($latestSubmission?->rejection_reason)
                <x-alert type="error">
                    <p class="font-semibold">{{ __('Reviewer note') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $latestSubmission->rejection_reason }}</p>
                </x-alert>
            @endif

            <x-seller.promotions.payment-form
                :action="route('seller.promotions.resubmit.store', $promotion)"
                :submit-label="__('Resubmit payment proof')"
                :cancel-url="route('seller.promotions.show', $promotion)"
                :method="$latestSubmission?->method"
                :transaction-reference="$latestSubmission?->transaction_reference"
                :transaction-details="$latestSubmission?->transaction_details"
            />
        </div>

        <aside class="brand-panel h-fit space-y-4 border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Unchanged package') }}</p>
                <p class="mt-2 font-semibold text-forest-900 dark:text-white">{{ $promotion->package?->name ?? __($promotion->package_code_snapshot) }}</p>
                <p class="mt-1 text-base text-sage-700 dark:text-mint-100/70">{{ App\Models\Product::formatMoney($promotion->price_minor_snapshot) }} &middot; {{ __(':days days', ['days' => $promotion->duration_days_snapshot]) }}</p>
            </div>

            <x-alert type="warning">{{ __('The previous rejected submission stays in your payment history.') }}</x-alert>
        </aside>
    </div>
@endsection
