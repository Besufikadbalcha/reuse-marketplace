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
                <div><dt class="text-forest-700 dark:text-mint-100/70">{{ __('Order number') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->order_number }}</dd></div>
                <div><dt class="text-forest-700 dark:text-mint-100/70">{{ __('Total to pay') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</dd></div>
                <div><dt class="text-forest-700 dark:text-mint-100/70">{{ __('You chose') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->fulfillmentLabel() }}</dd></div>
                <div><dt class="text-forest-700 dark:text-mint-100/70">{{ __('When to pay') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->isCashOnDelivery() ? __('When the product is delivered to you.') : __('When you collect the item from the seller.') }}</dd></div>
            </dl>
            <p class="mt-4 text-base text-forest-700 dark:text-mint-100/70">{{ __('No online payment is needed.') }}</p>
        </x-alert>
    @elseif (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
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
                    <x-badge :tone="$statusTone">{{ $order->statusLabel() }}</x-badge>
                </div>
            </div>

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Items') }}</h2>
                <div class="mt-4 grid gap-4">
                    @foreach ($order->items as $item)
                        @php($itemTone = $item->isDisputed() ? 'blush' : ($item->fulfillmentStatus() === App\Models\OrderItem::FULFILLMENT_COMPLETED ? 'mint' : 'cream'))
                        <article class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                                    <p class="mt-1 text-sage-700 dark:text-mint-100/70">{{ __('Condition') }}: {{ $item->product?->conditionLabel() ?? __('—') }}</p>
                                    <p class="mt-1 text-sage-700 dark:text-mint-100/70">{{ __('Seller') }}: {{ $item->product?->seller?->name ?? __('Seller') }}</p>
                                    <p class="mt-1 text-sage-700 dark:text-mint-100/70">{{ $item->quantity }} &times; {{ App\Models\Product::formatMoney($item->price_minor) }}</p>
                                </div>
                                <x-badge :tone="$itemTone">{{ $item->fulfillmentStatusLabel() }}</x-badge>
                            </div>

                            <div class="mt-4 grid gap-2 rounded-xl bg-white/70 p-3 text-xs text-forest-800 dark:bg-forest-950/40 dark:text-mint-100/80">
                                <p><span class="font-semibold">{{ __('Payment:') }}</span> {{ __('Cash only, paid at handover. No online payment is collected.') }}</p>
                                <p><span class="font-semibold">{{ __('Seller cash received:') }}</span> {{ $item->seller_cash_received_at?->format('Y-m-d H:i') ?? __('Not recorded yet') }}</p>
                                <p><span class="font-semibold">{{ __('Your receipt confirmation:') }}</span> {{ $item->buyer_confirmed_received_at?->format('Y-m-d H:i') ?? __('Not confirmed yet') }}</p>
                                @if ($item->isDisputed())
                                    <p class="text-blush-700 dark:text-blush-200"><span class="font-semibold">{{ __('Problem reported:') }}</span> {{ $item->buyer_dispute_reason }}</p>
                                @endif
                            </div>

                            @if ($order->status !== App\Models\Order::STATUS_CANCELLED)
                                <div class="mt-4 flex flex-wrap gap-3">
                                    @if ($item->canBuyerConfirmReceived())
                                        <form method="POST" action="{{ route('orders.items.confirm-received', [$order, $item]) }}">
                                            @csrf
                                            <x-button type="submit" class="w-auto">{{ __('I received this item') }}</x-button>
                                        </form>
                                    @endif
                                </div>

                                @if ($item->canBuyerReportProblem())
                                    <details class="mt-4 rounded-xl border border-sage-200 p-3 dark:border-white/10">
                                        <summary class="cursor-pointer text-sm font-semibold text-forest-900 dark:text-white">{{ __('Report a problem') }}</summary>
                                        <form method="POST" action="{{ route('orders.items.report-problem', [$order, $item]) }}" class="mt-3 grid gap-3">
                                            @csrf
                                            <label class="text-xs text-sage-700 dark:text-mint-100/70" for="reason-{{ $item->id }}">{{ __('Describe the issue for admin review. Do not include sensitive payment details.') }}</label>
                                            <textarea id="reason-{{ $item->id }}" name="reason" rows="3" maxlength="1000" required class="block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">{{ old('reason') }}</textarea>
                                            <x-button type="submit" variant="secondary" class="w-auto">{{ __('Submit problem report') }}</x-button>
                                        </form>
                                    </details>
                                @endif
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Fulfillment details') }}</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Method') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->fulfillmentLabel() }}</dd></div>
                    <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Payment timing') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->isCashOnDelivery() ? __('Pay cash when the product is delivered to you.') : __('Pay cash when you collect the item from the seller.') }}</dd></div>
                    @if ($order->isCashOnDelivery())
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Contact name') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_name }}</dd></div>
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Phone number') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ $order->contact_phone }}</dd></div>
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Delivery address') }}</dt><dd class="mt-1 whitespace-pre-line font-semibold text-forest-900 dark:text-white">{{ $order->delivery_address }}</dd></div>
                    @else
                        <div><dt class="text-sage-700 dark:text-mint-100/70">{{ __('Pickup') }}</dt><dd class="mt-1 font-semibold text-forest-900 dark:text-white">{{ __('Arrange pickup details with the seller after placing the order.') }}</dd></div>
                    @endif
                </dl>
            </div>

            <div class="border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Status history') }}</h2>
                <div class="mt-4 grid gap-4">
                    @foreach ($order->items as $item)
                        <div class="rounded-2xl border border-white/70 bg-cream-50/70 p-4 text-sm shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                            <p class="font-semibold text-forest-900 dark:text-white">{{ $item->product_title }}</p>
                            <div class="mt-3 grid gap-2">
                                @forelse ($item->statusHistories->sortBy('created_at') as $history)
                                    <div class="rounded-xl bg-white/70 px-3 py-2 text-xs dark:bg-forest-950/40">
                                        <p class="font-medium text-forest-900 dark:text-white">{{ $history->created_at?->format('Y-m-d H:i') }} · {{ __(ucfirst($history->actor_role)) }} · {{ $history->from_status ? \App\Models\OrderItem::fulfillmentStatusLabelFor($history->from_status).' → ' : '' }}{{ \App\Models\OrderItem::fulfillmentStatusLabelFor($history->to_status) }}</p>
                                        @if ($history->note)<p class="mt-1 text-sage-700 dark:text-mint-100/70">{{ $history->note }}</p>@endif
                                    </div>
                                @empty
                                    <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('No status history recorded yet.') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

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
            <div class="flex items-center justify-between gap-4 text-sm"><span class="text-sage-700 dark:text-mint-100/70">{{ __('Fulfillment') }}</span><span class="text-right font-medium text-forest-900 dark:text-white">{{ $order->fulfillmentLabel() }}</span></div>
            <div class="flex items-center justify-between gap-4 text-sm"><span class="text-sage-700 dark:text-mint-100/70">{{ __('Payment') }}</span><span class="text-right font-medium text-forest-900 dark:text-white">{{ __('Cash only at handover') }}</span></div>
            <div class="flex items-center justify-between gap-4 border-t border-sage-200 pt-4 text-sm dark:border-white/10"><span class="text-sage-700 dark:text-mint-100/70">{{ __('Total') }}</span><span class="text-base font-semibold text-forest-900 dark:text-white">{{ $order->formattedTotal() }}</span></div>
            <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Orders are completed only after cash is recorded and you confirm receipt. If there is a problem, report it for admin review.') }}</p>
        </aside>
    </div>
@endsection
