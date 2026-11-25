@extends('layouts.marketplace')

@section('title', __('Seller order :number', ['number' => $order->order_number]).' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('seller.orders.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Seller orders') }}
        </x-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="grid content-start gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="rounded-[1.5rem] border border-mint-200/80 bg-mint-50/80 p-5 dark:border-white/10 dark:bg-forest-900/60 sm:p-6">
                @php
                    $orderTone = match ($order->status) {
                        App\Models\Order::STATUS_PENDING => 'cream',
                        App\Models\Order::STATUS_CANCELLED => 'blush',
                        default => 'mint',
                    };
                    $orderIsCancelled = $order->status === App\Models\Order::STATUS_CANCELLED;
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-4 [&>div]:mb-0">
                    <x-page-heading
                        :eyebrow="__('Seller order')"
                        :title="__('Order :number', ['number' => $order->order_number])"
                        :description="__('Placed :date', ['date' => $order->formattedCreatedAt()])"
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Buyer order:') }}</span>
                        <x-badge :tone="$orderTone">
                            {{ match ($order->status) {
                                App\Models\Order::STATUS_PENDING => __('Pending'),
                                App\Models\Order::STATUS_CONFIRMED => __('Confirmed'),
                                App\Models\Order::STATUS_COMPLETED => __('Completed'),
                                App\Models\Order::STATUS_CANCELLED => __('Cancelled'),
                                default => __(ucfirst($order->status)),
                            } }}
                        </x-badge>
                    </div>
                </div>
            </div>

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Your items') }}</h2>

                <div class="mt-4 grid gap-3">
                    @foreach ($order->items as $item)
                        @php
                            $itemTone = match ($item->seller_status) {
                                App\Models\OrderItem::SELLER_STATUS_PENDING => 'cream',
                                App\Models\OrderItem::SELLER_STATUS_CONFIRMED => 'sage',
                                default => 'mint',
                            };
                        @endphp

                        <div class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="grid gap-1">
                                    <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                                    <p class="text-sage-700 dark:text-mint-100/70">
                                        {{ __('Quantity: :quantity | Line total: :total', ['quantity' => $item->quantity, 'total' => $item->formattedLineTotal()]) }}
                                    </p>
                                </div>
                                <x-badge :tone="$itemTone">{{ __($item->sellerStatusLabel()) }}</x-badge>
                            </div>

                            @if (! $orderIsCancelled && $item->canBeConfirmedBySeller())
                                <form method="POST" action="{{ route('seller.orders.confirm', [$order, $item]) }}" class="mt-4 border-t border-sage-200 pt-4 dark:border-white/10">
                                    @csrf
                                    <x-button type="submit" variant="secondary" class="w-auto">{{ __('Confirm item') }}</x-button>
                                </form>
                            @elseif (! $orderIsCancelled && $item->canBeCompletedBySeller())
                                <form method="POST" action="{{ route('seller.orders.complete', [$order, $item]) }}" class="mt-4 border-t border-sage-200 pt-4 dark:border-white/10">
                                    @csrf
                                    <x-button type="submit" class="w-auto">{{ __('Mark item complete') }}</x-button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY)
                <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                    <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Cash on delivery') }}</h2>
                    <dl class="mt-4 grid gap-3 text-sm">
                        <div>
                            <dt class="text-sage-700 dark:text-mint-100/70">{{ __('Contact name') }}</dt>
                            <dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sage-700 dark:text-mint-100/70">{{ __('Phone number') }}</dt>
                            <dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-sage-700 dark:text-mint-100/70">{{ __('Delivery address') }}</dt>
                            <dd class="mt-1 whitespace-pre-line font-semibold text-forest-900 dark:text-white">{{ $order->delivery_address }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </section>

        <aside class="grid content-start gap-5 rounded-[1.75rem] border border-white/70 bg-white/60 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Summary') }}</h2>
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Fulfillment') }}</span>
                <span class="text-right font-medium text-forest-900 dark:text-white">{{ $order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY ? __('Cash on delivery') : __('Pickup') }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 border-t border-sage-200 pt-4 text-sm dark:border-white/10">
                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Your items') }}</span>
                <span class="font-semibold text-forest-900 dark:text-white">{{ $order->items->count() }}</span>
            </div>
        </aside>
    </div>
@endsection
