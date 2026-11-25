@extends('layouts.marketplace')

@section('title', __('My orders').' - '.config('app.name'))

@section('content')
    <section class="relative mb-8 overflow-hidden rounded-[1.75rem] border border-mint-200/80 bg-mint-50/85 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
        <div class="pointer-events-none absolute -right-16 -top-20 size-64 rounded-full bg-mint-200/60 blur-3xl dark:bg-mint-300/10" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 left-1/3 size-72 rounded-full bg-beige-200/60 blur-3xl dark:bg-beige-900/20" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between [&>div:first-child]:mb-0">
            <x-page-heading
                :eyebrow="__('Purchase history')"
                :title="__('My orders')"
                :description="__('Orders you have placed as a buyer.')"
            />
            <x-button as="a" href="{{ route('home') }}" variant="secondary" class="self-start sm:self-auto">
                {{ __('Browse products') }}
            </x-button>
        </div>
    </section>

    <x-account-tabs active="orders" />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if ($orders->isEmpty())
        <x-alert type="warning" class="text-center">{{ $emptyMessage }}</x-alert>
    @else
        <div class="grid gap-5">
            @foreach ($orders as $order)
                @php
                    $statusTone = match ($order->status) {
                        App\Models\Order::STATUS_PENDING => 'cream',
                        App\Models\Order::STATUS_CANCELLED => 'blush',
                        default => 'mint',
                    };
                @endphp

                <article class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl transition duration-200 hover:-translate-y-0.5 hover:bg-white/80 dark:border-white/10 dark:bg-forest-950/70 dark:hover:bg-forest-900/70 sm:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Order') }}</p>
                            <p class="mt-2 text-xl font-semibold text-forest-900 dark:text-white">{{ $order->order_number }}</p>
                            <p class="mt-1 text-base text-sage-700 dark:text-mint-100/70">
                                {{ __('Placed :date', ['date' => $order->formattedCreatedAt()]) }}
                            </p>
                        </div>
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

                    <div class="mt-6 grid gap-3 border-t border-sage-200 pt-5 dark:border-white/10">
                        @foreach ($order->items as $item)
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <span class="font-medium text-forest-900 dark:text-white">{{ $item->product_title }}</span>
                                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Quantity :quantity', ['quantity' => $item->quantity]) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-sage-200 pt-5 dark:border-white/10">
                        <p class="text-lg font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</p>
                        <x-button as="a" href="{{ route('orders.show', $order) }}" variant="secondary">{{ __('View order') }}</x-button>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8 flex justify-center">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
