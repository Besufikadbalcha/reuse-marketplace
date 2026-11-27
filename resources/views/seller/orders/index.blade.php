@extends('layouts.marketplace')

@section('title', __('Seller orders').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('Seller orders')"
        :description="__('Manage fulfillment, record cash received at handover, and complete orders only after buyer confirmation.')"
    />

    <x-account-tabs active="sales" />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif
    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <form method="GET" action="{{ route('seller.orders.index') }}" class="mb-6 grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:grid-cols-[minmax(0,18rem)_auto] sm:items-end">
        <div>
            <label for="status" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Seller status') }}</label>
            <select id="status" name="status" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        <x-button type="submit" variant="secondary" class="w-full sm:w-auto">{{ __('Filter') }}</x-button>
    </form>

    @if ($orders->isEmpty())
        <x-alert type="warning" class="text-center">{{ __('You have no orders yet.') }}</x-alert>
    @else
        <div class="grid gap-5">
            @foreach ($orders as $order)
                @php
                    $orderTone = match ($order->status) { App\Models\Order::STATUS_PENDING => 'cream', App\Models\Order::STATUS_CANCELLED => 'blush', default => 'mint' };
                    $orderIsCancelled = $order->status === App\Models\Order::STATUS_CANCELLED;
                @endphp
                <article class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Order') }}</p>
                            <p class="mt-2 text-xl font-semibold text-forest-900 dark:text-white">{{ $order->order_number }}</p>
                            <p class="mt-1 text-base text-sage-700 dark:text-mint-100/70">{{ __('Placed :date', ['date' => $order->formattedCreatedAt()]) }} · {{ $order->fulfillmentLabel() }}</p>
                        </div>
                        <x-badge :tone="$orderTone">{{ $order->statusLabel() }}</x-badge>
                    </div>

                    <div class="mt-6 grid gap-3 border-t border-sage-200 pt-5 dark:border-white/10">
                        @foreach ($order->items as $item)
                            @php($itemTone = $item->isDisputed() ? 'blush' : ($item->fulfillmentStatus() === App\Models\OrderItem::FULFILLMENT_COMPLETED ? 'mint' : 'cream'))
                            <div class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="grid gap-1">
                                        <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                                        <p class="text-sage-700 dark:text-mint-100/70">{{ __('Quantity: :quantity | Line total: :total', ['quantity' => $item->quantity, 'total' => $item->formattedLineTotal()]) }}</p>
                                        <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ $item->seller_cash_received_at ? __('Cash received recorded') : __('Cash not recorded') }} · {{ $item->buyer_confirmed_received_at ? __('Buyer confirmed') : __('Buyer not confirmed') }}</p>
                                    </div>
                                    <x-badge :tone="$itemTone">{{ $item->fulfillmentStatusLabel() }}</x-badge>
                                </div>
                                @if (! $orderIsCancelled)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if ($item->canBeConfirmedBySeller())
                                            <form method="POST" action="{{ route('seller.orders.confirm', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Confirm') }}</x-button></form>
                                        @elseif ($item->canBeMarkedReadyForPickup())
                                            <form method="POST" action="{{ route('seller.orders.ready-for-pickup', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Ready for pickup') }}</x-button></form>
                                        @elseif ($item->canBeMarkedOutForDelivery())
                                            <form method="POST" action="{{ route('seller.orders.out-for-delivery', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Out for delivery') }}</x-button></form>
                                        @elseif ($item->canSellerRecordCashReceived())
                                            <form method="POST" action="{{ route('seller.orders.cash-received', [$order, $item]) }}">@csrf<x-button type="submit" variant="secondary" class="w-auto">{{ __('Record cash received') }}</x-button></form>
                                        @elseif ($item->canBeCompletedBySeller())
                                            <form method="POST" action="{{ route('seller.orders.complete', [$order, $item]) }}">@csrf<x-button type="submit" class="w-auto">{{ __('Mark complete') }}</x-button></form>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end border-t border-sage-200 pt-5 dark:border-white/10">
                        <x-button as="a" href="{{ route('seller.orders.show', $order) }}" variant="secondary">{{ __('View order') }}</x-button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8 flex justify-center">{{ $orders->links() }}</div>
    @endif
@endsection
