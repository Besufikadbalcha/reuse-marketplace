@extends('layouts.marketplace')

@section('title', __('Seller order :number', ['number' => $order->order_number]).' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('seller.orders.index') }}" variant="ghost" class="-ml-3">&larr; {{ __('Seller orders') }}</x-button>
    </div>

    @if (session('status')) <x-alert class="mb-6">{{ __(session('status')) }}</x-alert> @endif
    @if (session('error')) <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert> @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="grid content-start gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="rounded-[1.5rem] border border-mint-200/80 bg-mint-50/80 p-5 dark:border-white/10 dark:bg-forest-900/60 sm:p-6">
                @php($orderIsCancelled = $order->status === App\Models\Order::STATUS_CANCELLED)
                <div class="flex flex-wrap items-start justify-between gap-4 [&>div]:mb-0">
                    <x-page-heading :eyebrow="__('Seller order')" :title="__('Order :number', ['number' => $order->order_number])" :description="__('Placed :date', ['date' => $order->formattedCreatedAt()]).' · '.$order->fulfillmentLabel()" />
                    <x-badge :tone="$order->status === App\Models\Order::STATUS_CANCELLED ? 'blush' : 'mint'">{{ $order->statusLabel() }}</x-badge>
                </div>
            </div>

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Your items') }}</h2>
                <div class="mt-4 grid gap-4">
                    @foreach ($order->items as $item)
                        @php($itemTone = $item->isDisputed() ? 'blush' : ($item->fulfillmentStatus() === App\Models\OrderItem::FULFILLMENT_COMPLETED ? 'mint' : 'cream'))
                        <article class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                                    <p class="text-sage-700 dark:text-mint-100/70">{{ __('Quantity: :quantity | Line total: :total', ['quantity' => $item->quantity, 'total' => $item->formattedLineTotal()]) }}</p>
                                </div>
                                <x-badge :tone="$itemTone">{{ $item->fulfillmentStatusLabel() }}</x-badge>
                            </div>

                            <dl class="mt-4 grid gap-2 text-xs text-sage-700 dark:text-mint-100/70 sm:grid-cols-2">
                                <div><dt class="font-semibold text-forest-800 dark:text-mint-100">{{ __('Seller cash received') }}</dt><dd>{{ $item->seller_cash_received_at?->format('Y-m-d H:i') ?? __('Not recorded yet') }}</dd></div>
                                <div><dt class="font-semibold text-forest-800 dark:text-mint-100">{{ __('Buyer confirmed') }}</dt><dd>{{ $item->buyer_confirmed_received_at?->format('Y-m-d H:i') ?? __('Not confirmed yet') }}</dd></div>
                                @if ($item->isDisputed())
                                    <div class="sm:col-span-2"><dt class="font-semibold text-blush-700 dark:text-blush-200">{{ __('Buyer problem report') }}</dt><dd class="whitespace-pre-line">{{ $item->buyer_dispute_reason }}</dd></div>
                                @endif
                            </dl>

                            @if (! $orderIsCancelled)
                                <div class="mt-4 flex flex-wrap gap-2 border-t border-sage-200 pt-4 dark:border-white/10">
                                    @if ($item->canBeConfirmedBySeller())
                                        <form method="POST" action="{{ route('seller.orders.confirm', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Confirm item') }}</x-button></form>
                                    @elseif ($item->canBeMarkedReadyForPickup())
                                        <form method="POST" action="{{ route('seller.orders.ready-for-pickup', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Mark ready for pickup') }}</x-button></form>
                                    @elseif ($item->canBeMarkedOutForDelivery())
                                        <form method="POST" action="{{ route('seller.orders.out-for-delivery', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Mark out for delivery') }}</x-button></form>
                                    @elseif ($item->canSellerRecordCashReceived())
                                        <form method="POST" action="{{ route('seller.orders.cash-received', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Record cash received') }}</x-button></form>
                                    @elseif ($item->canBeCompletedBySeller())
                                        <form method="POST" action="{{ route('seller.orders.complete', [$order, $item]) }}">@csrf<x-button type="submit" class="w-auto">{{ __('Mark item complete') }}</x-button></form>
                                    @elseif ($item->fulfillmentStatus() === \App\Models\OrderItem::FULFILLMENT_AWAITING_BUYER_CONFIRMATION)
                                        <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Waiting for the buyer to confirm receipt before this item can be completed.') }}</p>
                                    @elseif ($item->isDisputed())
                                        <p class="text-xs text-blush-700 dark:text-blush-200">{{ __('This item is disputed and awaiting admin review.') }}</p>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>

            @if ($order->isCashOnDelivery())
                <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Cash on delivery') }}</h2>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Contact name') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_name }}</dd></div>
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Phone number') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_phone }}</dd></div>
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Delivery address') }}</dt><dd class="mt-1 whitespace-pre-line font-semibold text-forest-900 dark:text-white">{{ $order->delivery_address }}</dd></div>
                    </dl>
                </div>
            @endif
        </section>

        <aside class="grid content-start gap-5 rounded-[1.75rem] border border-white/70 bg-white/60 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Summary') }}</h2>
            <div class="flex items-center justify-between gap-4 text-sm"><span class="text-sage-700 dark:text-mint-100/70">{{ __('Fulfillment') }}</span><span class="text-right font-medium text-forest-900 dark:text-white">{{ $order->fulfillmentLabel() }}</span></div>
            <div class="flex items-center justify-between gap-4 text-sm"><span class="text-sage-700 dark:text-mint-100/70">{{ __('Payment') }}</span><span class="text-right font-medium text-forest-900 dark:text-white">{{ __('Cash at handover') }}</span></div>
            <p class="border-t border-sage-200 pt-4 text-xs text-sage-700 dark:border-white/10 dark:text-mint-100/70">{{ __('Record cash only when physically received. Completion requires buyer receipt confirmation.') }}</p>
        </aside>
    </div>
@endsection
