@extends('layouts.marketplace')

@section('title', __('Order :number', ['number' => $order->order_number]).' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('orders.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('My orders') }}
        </x-button>
    </div>

    @if (session('status_key') === 'order_placed')
        <x-alert type="success" aria-live="polite" :title="__('Thank you. Your order is placed.')" class="mb-6">
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-forest-700 dark:text-mint-100/70">{{ __('Order number') }}</dt>
                    <dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->order_number }}</dd>
                </div>
                <div>
                    <dt class="text-forest-700 dark:text-mint-100/70">{{ __('Total to pay') }}</dt>
                    <dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</dd>
                </div>
                <div>
                    <dt class="text-forest-700 dark:text-mint-100/70">{{ __('You chose') }}</dt>
                    <dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY ? __('Cash on delivery') : __('Pickup') }}</dd>
                </div>
                <div>
                    <dt class="text-forest-700 dark:text-mint-100/70">{{ __('When to pay') }}</dt>
                    <dd class="mt-1 font-semibold text-forest-900 dark:text-white">
                        @if ($order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY)
                            {{ __('When the product is delivered to you.') }}
                        @else
                            {{ __('When you collect the item from the seller.') }}
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-base text-forest-700 dark:text-mint-100/70">
                @if ($order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY)
                    {{ __('The seller will deliver to the address you gave. Pay cash when the product arrives.') }}
                @else
                    {{ __('Arrange pickup details with the seller. Pay cash when you collect the item.') }}
                @endif
                {{ __('No online payment is needed.') }}
            </p>
        </x-alert>
    @elseif (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="grid content-start gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="rounded-[1.5rem] border border-mint-200/80 bg-mint-50/80 p-5 dark:border-white/10 dark:bg-forest-900/60 sm:p-6">
                @php
                    $statusTone = match ($order->status) {
                        App\Models\Order::STATUS_PENDING => 'cream',
                        App\Models\Order::STATUS_CANCELLED => 'blush',
                        default => 'mint',
                    };
                @endphp

                <div class="flex flex-wrap items-start justify-between gap-4 [&>div]:mb-0">
                    <x-page-heading
                        :eyebrow="__('Order details')"
                        :title="__('Order :number', ['number' => $order->order_number])"
                        :description="__('Placed :date', ['date' => $order->formattedCreatedAt()])"
                    />
                    <x-badge :tone="$statusTone">
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

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Items') }}</h2>

                <div class="mt-4 grid gap-3">
                    @foreach ($order->items as $item)
                        <div class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                            <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                            <p class="mt-1 text-sage-700 dark:text-mint-100/70">
                                {{ $item->quantity }} &times; {{ App\Models\Product::formatMoney($item->price_minor) }}
                            </p>
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

            @if ($order->status === App\Models\Order::STATUS_PENDING)
                <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('{{ __('Cancel this order?') }}')" class="border-t border-sage-200 pt-6 dark:border-white/10">
                    @csrf
                    <button type="submit" class="rounded-full border border-blush-300 bg-blush-50 px-5 py-2.5 text-sm font-semibold text-blush-800 transition hover:border-blush-400 hover:bg-blush-100 focus-ring dark:border-blush-700 dark:bg-blush-900/30 dark:text-blush-100 dark:hover:bg-blush-900/50">
                        {{ __('Cancel order') }}
                    </button>
                </form>
            @endif
        </section>

        <aside class="grid content-start gap-5 rounded-[1.75rem] border border-white/70 bg-white/60 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Summary') }}</h2>
            <div class="flex items-center justify-between gap-4 text-sm">
                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Fulfillment') }}</span>
                <span class="text-right font-medium text-forest-900 dark:text-white">{{ $order->fulfillment_method === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY ? __('Cash on delivery') : __('Pickup') }}</span>
            </div>
            <div class="flex items-center justify-between gap-4 border-t border-sage-200 pt-4 text-sm dark:border-white/10">
                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Total') }}</span>
                <span class="text-base font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</span>
            </div>
        </aside>
    </div>
@endsection
