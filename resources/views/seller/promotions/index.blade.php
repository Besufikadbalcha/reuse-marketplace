@extends('layouts.marketplace')

@section('title', __('Promotions').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Promotions')"
        :description="__('Track payment reviews and keep your promotion requests up to date.')"
    />

    <x-account-tabs active="promotions" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-base text-sage-700 dark:text-mint-100/70">{{ __('Promote an approved listing after submitting payment proof.') }}</p>
        <x-gradient-button as="a" :href="route('seller.products.index')" class="w-auto">{{ __('Go to listings') }}</x-gradient-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    @if ($promotions->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('You have no promotion requests yet.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Listing') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Package') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Payment') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Submitted') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                        @foreach ($promotions as $promotion)
                            @php
                                $latestSubmission = $promotion->paymentSubmissions->first();
                                $statusLabel = match ($promotion->status) {
                                    App\Models\ProductPromotion::STATUS_PAYMENT_PENDING => __('Payment pending'),
                                    App\Models\ProductPromotion::STATUS_PAYMENT_REJECTED => __('Payment rejected'),
                                    App\Models\ProductPromotion::STATUS_ACTIVE => __('Active'),
                                    App\Models\ProductPromotion::STATUS_PAUSED => __('Paused'),
                                    App\Models\ProductPromotion::STATUS_EXPIRED => __('Expired'),
                                    App\Models\ProductPromotion::STATUS_STOPPED_SOLD => __('Stopped after sale'),
                                    default => __(ucfirst(str_replace('_', ' ', (string) $promotion->status))),
                                };
                                $statusTone = in_array($promotion->status, [App\Models\ProductPromotion::STATUS_PAYMENT_REJECTED, App\Models\ProductPromotion::STATUS_EXPIRED, App\Models\ProductPromotion::STATUS_STOPPED_SOLD], true) ? 'blush' : 'mint';
                                $paymentLabel = match ($latestSubmission?->status) {
                                    App\Models\PromotionPaymentSubmission::STATUS_PENDING => __('Pending'),
                                    App\Models\PromotionPaymentSubmission::STATUS_APPROVED => __('Approved'),
                                    App\Models\PromotionPaymentSubmission::STATUS_REJECTED => __('Rejected'),
                                    App\Models\PromotionPaymentSubmission::STATUS_SUPERSEDED => __('Superseded'),
                                    default => __('Not submitted'),
                                };
                            @endphp
                            <tr class="transition hover:bg-cream-50/70 dark:hover:bg-forest-900/40">
                                <td class="px-5 py-4">
                                    <div class="grid gap-1">
                                        <span class="font-semibold text-forest-900 dark:text-white">{{ $promotion->product?->title ?? __('Listing removed') }}</span>
                                        <span class="text-xs text-sage-700 dark:text-mint-100/70">{{ App\Models\Product::formatMoney($promotion->price_minor_snapshot) }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">{{ $promotion->package?->name ?? __($promotion->package_code_snapshot) }}</td>
                                <td class="px-5 py-4"><x-badge :tone="$statusTone">{{ $statusLabel }}</x-badge></td>
                                <td class="px-5 py-4">{{ $paymentLabel }}</td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ App\Models\Product::formatDate($promotion->created_at) }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('seller.promotions.show', $promotion) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('View') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-5">
            {{ $promotions->links() }}
        </div>
    @endif
@endsection
