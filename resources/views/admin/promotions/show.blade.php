@extends('layouts.marketplace')

@section('title', __('Review payment').' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('admin.promotions.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to payment reviews') }}
        </x-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    @if ($promotion->product?->isArchived())
        <x-alert type="error" class="mb-6">
            {{ __('This listing is archived. Payment approval is blocked until the listing is restored.') }}
        </x-alert>
    @elseif ($promotion->product?->status !== App\Models\Product::STATUS_APPROVED)
        <x-alert type="error" class="mb-6">
            {{ __('This listing is no longer approved. Payment approval is blocked until the listing is approved again.') }}
        </x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="grid gap-8">
        <section class="brand-panel border border-white/70 bg-white/65 p-6 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-badge tone="cream">
                    {{ match ($promotion->status) {
                        App\Models\ProductPromotion::STATUS_PAYMENT_PENDING => __('Payment pending'),
                        App\Models\ProductPromotion::STATUS_PAYMENT_REJECTED => __('Payment rejected'),
                        App\Models\ProductPromotion::STATUS_ACTIVE => __('Active'),
                        App\Models\ProductPromotion::STATUS_PAUSED => __('Paused'),
                        App\Models\ProductPromotion::STATUS_EXPIRED => __('Expired'),
                        App\Models\ProductPromotion::STATUS_STOPPED_SOLD => __('Stopped after sale'),
                        default => __(ucfirst(str_replace('_', ' ', (string) $promotion->status))),
                    } }}
                </x-badge>
                <span class="rounded-full bg-mint-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __('Seller: :name', ['name' => $promotion->seller?->name ?? __('Not provided')]) }}</span>
                <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ $promotion->package?->name ?? __($promotion->package_code_snapshot) }}</span>
            </div>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $promotion->product?->title ?? __('Listing removed') }}</h1>

            <dl class="mt-7 grid gap-4 border-t border-sage-200 pt-6 text-sm dark:border-white/10 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Price') }}</dt>
                    <dd class="mt-1 text-xl font-semibold text-forest-900 dark:text-white">{{ App\Models\Product::formatMoney($promotion->price_minor_snapshot) }}</dd>
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

            @php
                $remainingSeconds = $promotion->remainingSecondsAt(now());
            @endphp

            <dl class="mt-4 grid gap-4 border-t border-sage-200 pt-6 text-sm dark:border-white/10 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-widest text-sage-700 dark:text-mint-100/70">{{ __('Lifecycle') }}</dt>
                    <dd class="mt-1 text-forest-900 dark:text-white">{{ $promotion->statusLabel() }}</dd>
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
        </section>

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
                                        <a href="{{ route('admin.promotions.proof', [$promotion, $submission]) }}" class="font-medium text-forest-700 underline focus-ring dark:text-mint-200">{{ __('Download proof') }}</a>
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

                            @if ($submission->reviewer)
                                <p class="mt-3 text-xs text-sage-700 dark:text-mint-100/70">{{ __('Reviewed by :name', ['name' => $submission->reviewer->name]) }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
        </div>

        <aside class="grid content-start gap-6">
            @if ($pendingSubmission !== null && $promotion->status === App\Models\ProductPromotion::STATUS_PAYMENT_PENDING)
                <div class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <form method="POST" action="{{ route('admin.promotions.payments.approve', [$promotion, $pendingSubmission]) }}">
                        @csrf
                        <x-gradient-button type="submit">{{ __('Approve payment') }}</x-gradient-button>
                    </form>

                    <form method="POST" action="{{ route('admin.promotions.payments.reject', [$promotion, $pendingSubmission]) }}" class="grid gap-3">
                        @csrf

                        <div class="space-y-2">
                            <label for="rejection_reason" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Rejection reason') }}</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="1000" required class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('rejection_reason') }}</textarea>

                            @error('rejection_reason')
                                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full border border-blush-300 px-6 py-3 text-sm font-semibold text-blush-700 transition hover:-translate-y-0.5 hover:bg-blush-100 focus-ring dark:border-blush-700 dark:text-blush-200 dark:hover:bg-blush-900/40">{{ __('Reject payment') }}</button>
                    </form>
                </div>
            @else
                <div class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 text-sm shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <p class="text-forest-700 dark:text-mint-100/80">{{ __('This payment has already been reviewed.') }}</p>
                </div>
            @endif
        </aside>
    </div>
@endsection
