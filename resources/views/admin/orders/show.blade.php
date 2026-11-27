@extends('layouts.marketplace')

@section('title', __('Order :number', ['number' => $order->order_number]).' - '.config('app.name'))

@section('content')
    <div class="mb-6"><x-button as="a" href="{{ route('admin.orders.index') }}" variant="ghost" class="-ml-3">&larr; {{ __('Orders') }}</x-button></div>
    <x-page-heading :eyebrow="__('Admin oversight')" :title="__('Order :number', ['number' => $order->order_number])" :description="__('Placed :date', ['date' => $order->formattedCreatedAt()])" />

    <div class="grid gap-6">
        <section class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft dark:border-white/10 dark:bg-forest-950/70">
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Buyer') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->buyer?->name }} ({{ $order->buyer?->email }})</dd></div>
                <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Order status') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->statusLabel() }}</dd></div>
                <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Fulfillment') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->fulfillmentLabel() }}</dd></div>
                <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Total') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</dd></div>
            </dl>
            @if ($order->isCashOnDelivery())
                <dl class="mt-6 grid gap-4 border-t border-sage-200 pt-5 text-sm dark:border-white/10 sm:grid-cols-3">
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Contact name') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->contact_name }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Phone number') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $order->contact_phone }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Delivery address') }}</dt><dd class="whitespace-pre-line font-semibold text-forest-900 dark:text-white">{{ $order->delivery_address }}</dd></div>
                </dl>
            @endif
        </section>

        @foreach ($order->items as $item)
            <section class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft dark:border-white/10 dark:bg-forest-950/70">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</h2>
                        <p class="text-sm text-sage-700 dark:text-mint-100/70">{{ __('Seller') }}: {{ $item->product?->seller?->name }} · {{ __('Quantity: :quantity | Line total: :total', ['quantity' => $item->quantity, 'total' => $item->formattedLineTotal()]) }}</p>
                    </div>
                    <x-badge :tone="$item->isDisputed() ? 'blush' : 'mint'">{{ $item->fulfillmentStatusLabel() }}</x-badge>
                </div>

                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Seller cash received') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $item->seller_cash_received_at?->format('Y-m-d H:i') ?? __('Not recorded') }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Buyer confirmed') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $item->buyer_confirmed_received_at?->format('Y-m-d H:i') ?? __('Not confirmed') }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Disputed at') }}</dt><dd class="font-semibold text-forest-900 dark:text-white">{{ $item->buyer_disputed_at?->format('Y-m-d H:i') ?? __('No') }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Dispute reason') }}</dt><dd class="whitespace-pre-line font-semibold text-forest-900 dark:text-white">{{ $item->buyer_dispute_reason ?: __('—') }}</dd></div>
                </dl>

                <div class="mt-5 border-t border-sage-200 pt-5 dark:border-white/10">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Status history') }}</h3>
                    <div class="mt-3 grid gap-2 text-sm">
                        @forelse ($item->statusHistories->sortBy('created_at') as $history)
                            <div class="rounded-xl bg-cream-50/70 px-4 py-3 dark:bg-forest-900/50">
                                <p class="font-medium text-forest-900 dark:text-white">{{ $history->created_at?->format('Y-m-d H:i') }} · {{ __(ucfirst($history->actor_role)) }} · {{ $history->from_status ? \App\Models\OrderItem::fulfillmentStatusLabelFor($history->from_status).' → ' : '' }}{{ \App\Models\OrderItem::fulfillmentStatusLabelFor($history->to_status) }}</p>
                                @if ($history->note)<p class="mt-1 text-sage-700 dark:text-mint-100/70">{{ $history->note }}</p>@endif
                            </div>
                        @empty
                            <p class="text-sage-700 dark:text-mint-100/70">{{ __('No status history recorded yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </section>
        @endforeach
    </div>
@endsection
