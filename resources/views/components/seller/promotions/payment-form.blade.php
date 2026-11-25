@props([
    'action',
    'cancelUrl',
    'submitLabel',
    'packages' => null,
    'method' => null,
    'transactionReference' => null,
    'transactionDetails' => null,
])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="brand-panel space-y-6 border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-6">
    @csrf

    @if ($packages !== null)
        <div class="space-y-2">
            <label for="package_id" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Promotion package') }}</label>
            <select id="package_id" name="package_id" required aria-describedby="package_id-error" @class(['block w-full rounded-2xl border bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:ring-4 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:focus:ring-mint-300/25', 'border-red-400 focus:border-red-500' => $errors->has('package_id'), 'border-sage-200 focus:border-forest-600 dark:border-white/15 dark:focus:border-mint-300' => ! $errors->has('package_id')])>
                <option value="">{{ __('Choose a package') }}</option>
                @foreach ($packages as $package)
                    <option value="{{ $package->id }}" @selected(old('package_id') == $package->id)>
                        {{ $package->name }} &middot; {{ App\Models\Product::formatMoney($package->price_minor) }} &middot; {{ $package->duration_days }} {{ __('days') }}
                    </option>
                @endforeach
            </select>
            @error('package_id')
                <p id="package_id-error" class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="space-y-2">
        <label for="method" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Payment method') }}</label>
        <select id="method" name="method" required aria-describedby="method-error" @class(['block w-full rounded-2xl border bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:ring-4 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:focus:ring-mint-300/25', 'border-red-400 focus:border-red-500' => $errors->has('method'), 'border-sage-200 focus:border-forest-600 dark:border-white/15 dark:focus:border-mint-300' => ! $errors->has('method')])>
            <option value="">{{ __('Choose a payment method') }}</option>
            @foreach (App\Models\PromotionPaymentSubmission::METHODS as $paymentMethod)
                <option value="{{ $paymentMethod }}" @selected(old('method', $method) === $paymentMethod)>
                    {{ $paymentMethod === App\Models\PromotionPaymentSubmission::METHOD_TELEBIRR ? __('Telebirr') : __('Bank transfer') }}
                </option>
            @endforeach
        </select>
        @error('method')
            <p id="method-error" class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <x-field
        name="transaction_reference"
        :label="__('Transaction reference (optional)')"
        :value="$transactionReference"
        :maxlength="255"
        autocomplete="off"
    />

    <div class="space-y-2">
        <label for="transaction_details" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Payment details') }}</label>
        <textarea id="transaction_details" name="transaction_details" rows="4" required maxlength="2000" aria-describedby="transaction_details-error" placeholder="{{ __('Include the sender name, date, and any details that will help us verify the payment.') }}" @class(['block w-full rounded-2xl border bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:ring-4 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:ring-mint-300/25', 'border-red-400 focus:border-red-500' => $errors->has('transaction_details'), 'border-sage-200 focus:border-forest-600 dark:border-white/15 dark:focus:border-mint-300' => ! $errors->has('transaction_details')])>{{ old('transaction_details', $transactionDetails) }}</textarea>
        @error('transaction_details')
            <p id="transaction_details-error" class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="space-y-2">
        <label for="proof" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Payment proof') }}</label>
        <input id="proof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required aria-describedby="proof-error proof-help" @class(['block w-full rounded-2xl border bg-white/85 px-4 py-3 text-sm text-forest-900 shadow-sm file:mr-4 file:rounded-full file:border-0 file:bg-forest-800 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white focus:ring-4 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:file:bg-mint-200 dark:file:text-forest-950 dark:focus:ring-mint-300/25', 'border-red-400 focus:border-red-500' => $errors->has('proof'), 'border-sage-200 focus:border-forest-600 dark:border-white/15 dark:focus:border-mint-300' => ! $errors->has('proof')])>
        <p id="proof-help" class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('JPEG, PNG, WebP, or PDF up to 5 MB.') }}</p>
        @error('proof')
            <p id="proof-error" class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <x-gradient-button type="submit" class="w-full sm:w-auto">{{ $submitLabel }}</x-gradient-button>
        <x-button :href="$cancelUrl" as="a" variant="secondary" class="w-full sm:w-auto">{{ __('Cancel') }}</x-button>
    </div>
</form>
