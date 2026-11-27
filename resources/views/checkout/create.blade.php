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
        :description="__('Review the order clearly before placing it. This marketplace uses cash only - no online payment is needed.')"
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

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form method="POST" action="{{ route('checkout.store', $product) }}" class="grid content-start gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8" data-checkout-form>
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            <section class="grid gap-4">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Order summary') }}</h2>
                <div class="flex gap-4 rounded-2xl border border-white/70 bg-cream-50/70 p-4 shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                    @if ($product->images->isNotEmpty())
                        <img src="{{ $product->images->first()->url() }}" alt="{{ $product->title }}" class="size-24 shrink-0 rounded-xl object-cover">
                    @endif
                    <div class="min-w-0">
                        <p class="font-semibold text-forest-900 dark:text-white">{{ $product->title }}</p>
                        <p class="mt-1 text-sm text-sage-700 dark:text-mint-100/70">{{ __('Condition') }}: {{ $product->conditionLabel() }}</p>
                        <p class="mt-1 text-sm text-sage-700 dark:text-mint-100/70">{{ __('Seller') }}: {{ $product->seller?->name }}</p>
                        <p class="mt-2 text-sm font-semibold text-forest-900 dark:text-white">{{ $product->formattedPrice() }}</p>
                    </div>
                </div>
            </section>

            <fieldset class="grid gap-3 border-t border-sage-200 pt-6 dark:border-white/10">
                <legend class="text-sm font-semibold text-forest-900 dark:text-white">{{ __('Fulfillment method') }}</legend>
                <p class="text-base text-sage-700 dark:text-mint-100/70">{{ __('Choose how you will get the item. Both options are paid in cash.') }}</p>

                @foreach ($fulfillmentMethods as $value => $label)
                    <label class="flex items-start gap-3 rounded-2xl border border-white/70 bg-cream-50/70 px-4 py-3.5 text-sm shadow-sm transition hover:border-forest-300 hover:bg-white/80 dark:border-white/10 dark:bg-forest-900/50 dark:hover:border-forest-600 dark:hover:bg-forest-900/70">
                        <input type="radio" name="fulfillment_method" value="{{ $value }}" @checked(old('fulfillment_method', $defaultFulfillmentMethod) === $value) class="mt-0.5 size-4 accent-forest-700 focus-ring dark:accent-mint-300" data-fulfillment-radio>

                        <span class="grid gap-1">
                            <span class="font-semibold text-forest-900 dark:text-white">{{ __($label) }}</span>
                            @if ($value === App\Models\Order::FULFILLMENT_PICKUP)
                                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Pay cash when you collect the item from the seller. You and the seller will arrange pickup details after placing the order.') }}</span>
                            @else
                                <span class="text-sage-700 dark:text-mint-100/70">{{ __('Pay cash when the product is delivered to you. Provide a delivery address and contact details so the seller can arrange delivery.') }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach

                @error('fulfillment_method')
                    <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
            </fieldset>

            <section class="grid gap-4 rounded-2xl border border-mint-200/80 bg-mint-50/70 p-4 text-sm text-forest-800 dark:border-white/10 dark:bg-forest-900/50 dark:text-mint-100/90">
                <h2 class="font-semibold text-forest-900 dark:text-white">{{ __('Payment and handover') }}</h2>
                <p>{{ __('No online payment is collected by the marketplace. Cash is exchanged directly at handover.') }}</p>
                <p>{{ __('After placing the order, the seller confirms the item, prepares pickup or delivery, records cash received at handover, and you confirm receipt before the order can be completed.') }}</p>
            </section>

            <div class="grid gap-5 border-t border-sage-200 pt-6 dark:border-white/10" data-delivery-fields>
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

            <div class="rounded-2xl border border-sage-200 bg-white/70 p-4 text-sm dark:border-white/10 dark:bg-forest-900/40">
                <p class="font-semibold text-forest-900 dark:text-white">{{ __('Final confirmation') }}</p>
                <p class="mt-2 text-sage-700 dark:text-mint-100/70">{{ __('By placing this order, you confirm the item, fulfillment method, and cash payment timing shown in the summary.') }}</p>
            </div>

            <x-gradient-button type="submit">{{ __('Place order') }}</x-gradient-button>
        </form>

        <aside class="grid content-start gap-5 rounded-[1.75rem] border border-white/70 bg-white/60 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-7">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Order summary') }}</p>
                <div class="mt-4 grid gap-1">
                    <p class="font-semibold text-forest-900 dark:text-white">{{ $product->title }}</p>
                    <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Condition') }}: {{ $product->conditionLabel() }}</p>
                    <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Seller') }}: {{ $product->seller?->name }}</p>
                    <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Quantity :quantity', ['quantity' => 1]) }}</p>
                </div>
            </div>

            <div class="grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('You chose') }}</span>
                    <span class="text-right font-medium text-forest-900 dark:text-white" data-checkout-fulfillment-label>{{ __($fulfillmentMethods[old('fulfillment_method', $defaultFulfillmentMethod)] ?? 'Pickup') }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('Payment') }}</span>
                    <span class="font-medium text-forest-900 dark:text-white">{{ __('Cash only') }}</span>
                </div>
            </div>

            <div class="grid gap-3 border-t border-sage-200 pt-5 text-sm dark:border-white/10">
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('Item price') }}</span>
                    <span class="font-medium text-forest-900 dark:text-white">{{ $product->formattedPrice() }}</span>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <span class="text-sage-700 dark:text-mint-100/70">{{ __('Delivery fee') }}</span>
                    <span class="font-medium text-forest-900 dark:text-white">{{ __('Not applicable / arranged separately') }}</span>
                </div>
                <div class="flex items-center justify-between gap-4 text-base font-semibold text-forest-900 dark:text-white">
                    <span>{{ __('Total to pay') }}</span>
                    <span>{{ $product->formattedPrice() }}</span>
                </div>
                <p class="text-xs leading-relaxed text-sage-700 dark:text-mint-100/70" data-checkout-payment-note>
                    {{ old('fulfillment_method', $defaultFulfillmentMethod) === App\Models\Order::FULFILLMENT_CASH_ON_DELIVERY ? __('You will pay cash when the product is delivered to you.') : __('You will pay cash when you collect the item from the seller.') }}
                </p>
                <p class="text-xs leading-relaxed text-sage-700 dark:text-mint-100/70">{{ __('No online payment is needed.') }}</p>
            </div>
        </aside>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const radios = document.querySelectorAll('[data-fulfillment-radio]');
            const delivery = document.querySelector('[data-delivery-fields]');
            const label = document.querySelector('[data-checkout-fulfillment-label]');
            const note = document.querySelector('[data-checkout-payment-note]');
            const labels = {
                pickup: @json(__('Pickup')),
                cash_on_delivery: @json(__('Cash on delivery')),
            };
            const notes = {
                pickup: @json(__('You will pay cash when you collect the item from the seller.')),
                cash_on_delivery: @json(__('You will pay cash when the product is delivered to you.')),
            };
            function sync() {
                const selected = document.querySelector('[data-fulfillment-radio]:checked')?.value || 'pickup';
                if (delivery) delivery.style.display = selected === 'cash_on_delivery' ? '' : 'none';
                if (label) label.textContent = labels[selected] || labels.pickup;
                if (note) note.textContent = notes[selected] || notes.pickup;
            }
            radios.forEach((radio) => radio.addEventListener('change', sync));
            sync();
        });
    </script>
@endsection
