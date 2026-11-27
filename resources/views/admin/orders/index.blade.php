@extends('layouts.marketplace')

@section('title', __('Orders').' - '.config('app.name'))

@section('content')
    <x-page-heading :eyebrow="__('Admin oversight')" :title="__('Orders')" :description="__('Review buyer orders, fulfillment progress, and reported problems.')" />

    <form method="GET" action="{{ route('admin.orders.index') }}" class="mb-6 grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:grid-cols-4 sm:items-end">
        <div>
            <label class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Order status') }}</label>
            <select name="status" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ([\App\Models\Order::STATUS_PENDING => __('Pending'), \App\Models\Order::STATUS_CONFIRMED => __('Confirmed'), \App\Models\Order::STATUS_COMPLETED => __('Completed'), \App\Models\Order::STATUS_CANCELLED => __('Cancelled')] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Fulfillment') }}</label>
            <select name="fulfillment" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3 text-sm dark:border-white/15 dark:bg-white/5 dark:text-white">
                <option value="">{{ __('All') }}</option>
                <option value="pickup" @selected($fulfillment === 'pickup')>{{ __('Pickup') }}</option>
                <option value="cash_on_delivery" @selected($fulfillment === 'cash_on_delivery')>{{ __('Cash on delivery') }}</option>
            </select>
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-forest-900 dark:text-white">
            <input type="checkbox" name="disputed" value="1" @checked($disputed) class="rounded border-sage-300">
            {{ __('Disputed only') }}
        </label>
        <x-button type="submit" variant="secondary">{{ __('Filter') }}</x-button>
    </form>

    <div class="overflow-x-auto rounded-[1.75rem] border border-white/70 bg-white/65 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
        <table class="min-w-full divide-y divide-sage-200 text-sm dark:divide-white/10">
            <thead class="text-left text-xs uppercase tracking-widest text-forest-600 dark:text-mint-300">
                <tr>
                    <th class="px-5 py-4">{{ __('Order') }}</th>
                    <th class="px-5 py-4">{{ __('Buyer') }}</th>
                    <th class="px-5 py-4">{{ __('Fulfillment') }}</th>
                    <th class="px-5 py-4">{{ __('Status') }}</th>
                    <th class="px-5 py-4">{{ __('Total') }}</th>
                    <th class="px-5 py-4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                @forelse ($orders as $order)
                    <tr>
                        <td class="px-5 py-4 font-semibold text-forest-900 dark:text-white">
                            {{ $order->order_number }}
                            @if ($order->items->contains(fn ($i) => $i->isDisputed()))
                                <span class="ml-2 rounded-full bg-blush-100 px-2 py-0.5 text-xs text-blush-800">{{ __('Disputed') }}</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $order->buyer?->name }}</td>
                        <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $order->fulfillmentLabel() }}</td>
                        <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $order->statusLabel() }}</td>
                        <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $order->formattedTotal() }}</td>
                        <td class="px-5 py-4 text-right"><x-button as="a" href="{{ route('admin.orders.show', $order) }}" variant="secondary">{{ __('Review') }}</x-button></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-sage-700 dark:text-mint-100/70">{{ __('No orders found.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $orders->links() }}</div>
@endsection
