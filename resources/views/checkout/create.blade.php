@extends('layouts.marketplace')

@section('title', __('Checkout').' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('products.show', $product->slug) }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to listing') }}
        </x-button>
    </div>

    <x-page-heading
        :eyebrow="__('Secure checkout')"
        :title="__('Checkout')"
        :description="__('One item per order. The price shown is the price you pay.')"
    />

    @if ($errors->any())
        <x-alert type="error" :title="__('Please check the details below.')" class="mb-6">
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="POST" action="{{ route('checkout.store', $product) }}" class="grid content-start gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            <fieldset class="grid gap-3">
                <legend class="text-sm font-semibold text-forest-900 dark:text-white">{{ __('Fulfillment method') }}</legend>
                <p class="text-base text-sage-700 dark:text-mint-100/70">{{ __('Both options are paid in cash. Choose how you will get the item.') }}</p>

                @foreach ($fulfillmentMethods as $value => $label)
                    <label class="flex items-start gap-3 rounded-2xl border border-white/70 bg-cream-50/70 px-4 py-3.5 text-sm shadow-sm transition hover:border-forest-300 hover:bg-white/80 dark:border-white/10 dark:bg-forest-900/50 dark:hover:border-forest-600 dark:hover:bg-forest-900/70">
                        <input type="radio" name="fulfillment_method" value="{{ $value }}" @checked(old('fulfillment_method', $defaultFulfillmentMethod) === $value) class="mt-0.5 size-4 accent-forest-700 focus-ring dark:accent-mint-300">

                        <span class="grid gap-1">
                            <span class="font-semibold text-forest-900 dark:text-white">{{ __($label) }}</span>
                            @if ($value === App\Models\Order::FULFILLMENT_PICKUP)
                                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Pay cash when you collect the item from the seller. You and the seller arrange the pickup details.') }}</span>
                            @else
                                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Pay cash when the product is delivered to you.') }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach

                @error('fulfillment_method')
                    <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="grid gap-5 border-t border-sage-200 pt-6 dark:border-white/10">
                <div>
                    <h2 class="text-lg font-semibold text-forest-900 dark:text-white">{{ __('Cash on delivery details') }}</h2>
                    <p class="mt-1 text-xs text-sage-700 dark:text-mint-100/70">{{ __('Required only when cash on delivery is selected.') }}</p>
                </div>

                <x-field name="contact_name" :label="__('Contact name')" type="text" maxlength="255" />
                <x-field name="contact_phone" :label="__('Phone number')" type="tel" maxlength="20" />

                <div class="space-y-2">
                    <label for="delivery_address" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Delivery address') }}</label>
                    <textarea id="delivery_address" name="delivery_address" rows="3" maxlength="1000" class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('delivery_address') }}</textarea>
                    @error('delivery_address')
                        <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <x-gradient-button type="submit">{{ __('Place order') }}</x-gradient-button>
        </form>

        <aside class="grid content-start gap-5 rounded-[1.75rem] border border-white/70 bg-white/60 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Order summary') }}</p>

                <div class="mt-4 grid gap-1">
                    <p class="font-semibold text-forest-900 dark:text-white">{{ $product->title }}</p>
                    <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Quantity :quantity', ['quantity' => 1]) }}</p>
                </div>
            </div>

            <div class="grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('You chose') }}</span>
                    <span class="text-right font-medium text-forest-900 dark:text-white">{{ __($fulfillmentMethods[old('fulfillment_method', $defaultFulfillmentMethod)] ?? 'Pickup') }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('Payment') }}</span>
                    <span class="font-medium text-forest-900 dark:text-white">{{ __('Cash') }}</span>
                </div>
            </div>

            <div class="grid gap-3 border-t border-sage-200 pt-5 text-sm dark:border-white/10">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('Item price') }}</span>
                    <span class="font-medium text-forest-900 dark:text-white">{{ $product->formattedPrice() }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 text-base font-semibold text-forest-900 dark:text-white">
                    <span>{{ __('Total to pay') }}</span>
                    <span>{{ $product->formattedPrice() }}</span>
                </div>
                <p class="text-xs leading-relaxed text-sage-700 dark:text-mint-100/70">{{ __('Prices are in :currency. You will pay cash; no online payment is needed.', ['currency' => config('marketplace.currency')]) }}</p>
            </div>
        </aside>
    </div>
@endsection
