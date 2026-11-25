@extends('layouts.marketplace')

@section('title', __('Promotion request').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Promotion request')"
        :description="__('Review the package details and every payment submission for this request.')"
    />

    <x-account-tabs active="promotions" />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <x-button as="a" :href="route('seller.promotions.index')" variant="secondary" class="w-full sm:w-auto">{{ __('Back to promotions') }}</x-button>
        @if ($promotion->status === App\Models\ProductPromotion::STATUS_PAYMENT_REJECTED
            && $latestSubmission?->isRejected()
            && $promotion->product?->status === App\Models\Product::STATUS_APPROVED)
            <x-gradient-button as="a" :href="route('seller.promotions.resubmit', $promotion)" class="w-full sm:w-auto">{{ __('Resubmit payment proof') }}</x-gradient-button>
        @endif
    </div>

    @if ($promotion->status === App\Models\ProductPromotion::STATUS_PAYMENT_REJECTED)
        <x-alert type="error" class="mb-6">
            <p class="font-semibold">{{ __('Payment review was rejected.') }}</p>
            @if ($latestSubmission?->rejection_reason)
                <p class="mt-1">{{ $latestSubmission->rejection_reason }}</p>
            @endif
        </x-alert>
    @elseif ($promotion->status === App\Models\ProductPromotion::STATUS_PAYMENT_PENDING)
        <x-alert class="mb-6">{{ __('Your payment proof is waiting for administrator review. Your listing is not promoted yet.') }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="brand-panel border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-6">
            <h2 class="text-2xl font-semibold text-forest-900 dark:text-white">{{ __('Payment history') }}</h2>

            @if ($promotion->paymentSubmissions->isEmpty())
                <x-alert type="warning" class="mt-4">{{ __('No payment submissions were found.') }}</x-alert>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($promotion->paymentSubmissions as $submission)
                        @php
                            $submissionStatusLabel = match ($submission->status) {
                                App\Models\PromotionPaymentSubmission::STATUS_PENDING => __('Pending'),
                                App\Models\PromotionPaymentSubmission::STATUS_APPROVED => __('Approved'),
                                App\Models\PromotionPaymentSubmission::STATUS_REJECTED => __('Rejected'),
                                App\Models\PromotionPaymentSubmission::STATUS_SUPERSEDED => __('Superseded'),
                                default => __(ucfirst(str_replace('_', ' ', (string) $submission->status))),
                            };
                        @endphp
                        <article class="rounded-[1.35rem] border border-sage-200 bg-cream-50/60 p-4 dark:border-white/10 dark:bg-forest-900/50">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <p class="font-semibold text-forest-900 dark:text-white">{{ __('Attempt :number', ['number' => $submission->attempt]) }}</p>
                                <x-badge :tone="$submission->isRejected() ? 'blush' : ($submission->isPending() ? 'cream' : 'mint')">{{ $submissionStatusLabel }}</x-badge>
                            </div>

                            <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Payment method') }}</dt>
                                    <dd class="mt-1 text-forest-900 dark:text-white">{{ $submission->method === App\Models\PromotionPaymentSubmission::METHOD_TELEBIRR ? __('Telebirr') : __('Bank transfer') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Transaction reference') }}</dt>
                                    <dd class="mt-1 text-forest-900 dark:text-white">{{ $submission->transaction_reference ?: __('Not provided') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Submitted') }}</dt>
                                    <dd class="mt-1 text-forest-900 dark:text-white">{{ App\Models\Product::formatDate($submission->submitted_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Payment proof') }}</dt>
                                    <dd class="mt-1">
                                        <a href="{{ route('seller.promotions.proof', [$promotion, $submission]) }}" class="font-medium text-forest-700 underline focus-ring dark:text-mint-200">{{ __('Download proof') }}</a>
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-3">
                                <p class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Payment details') }}</p>
                                <p class="mt-1 whitespace-pre-line text-base text-forest-900 dark:text-white">{{ $submission->transaction_details }}</p>
                            </div>

                            @if ($submission->rejection_reason)
                                <div class="mt-3">
                                    <p class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Reviewer note') }}</p>
                                    <p class="mt-1 whitespace-pre-line text-xs text-red-700 dark:text-red-300">{{ $submission->rejection_reason }}</p>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="brand-panel h-fit space-y-4 border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Listing') }}</p>
                <p class="mt-2 font-semibold text-forest-900 dark:text-white">{{ $promotion->product?->title ?? __('Listing removed') }}</p>
            </div>

            <dl class="space-y-3 border-t border-sage-200 pt-4 text-sm dark:border-white/10">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Package') }}</dt>
                    <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->package?->name ?? __($promotion->package_code_snapshot) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Price') }}</dt>
                    <dd class="mt-1 text-forest-900 dark:text-white">{{ App\Models\Product::formatMoney($promotion->price_minor_snapshot) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Duration') }}</dt>
                    <dd class="mt-1 text-forest-900 dark:text-white">{{ __(':days days', ['days' => $promotion->duration_days_snapshot]) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Requested') }}</dt>
                    <dd class="mt-1 text-forest-900 dark:text-white">{{ App\Models\Product::formatDate($promotion->created_at) }}</dd>
                </div>
            </dl>

            <div class="border-t border-sage-200 pt-4 dark:border-white/10">
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Lifecycle') }}</p>

                @php
                    $remainingSeconds = $promotion->remainingSecondsAt(now());
                @endphp

                <dl class="mt-3 grid gap-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-badge :tone="$promotion->isActive() ? 'mint' : ($promotion->isClosed() ? 'blush' : 'cream')">{{ $promotion->statusLabel() }}</x-badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Starts') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->starts_at ? App\Models\Product::formatDate($promotion->starts_at) : __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Ends') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->ends_at ? App\Models\Product::formatDate($promotion->ends_at) : __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Paused at') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->paused_at ? App\Models\Product::formatDate($promotion->paused_at) : __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Remaining time') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->ends_at ? __(':seconds seconds', ['seconds' => $remainingSeconds]) : __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Stopped at') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->stopped_at ? App\Models\Product::formatDate($promotion->stopped_at) : __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Stopped by order') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->stoppedByOrder?->order_number ?? __('Not provided') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Display eligibility') }}</dt>
                        <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->isDisplayableAt(now()) ? __('Eligible') : __('Not eligible') }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
@endsection
