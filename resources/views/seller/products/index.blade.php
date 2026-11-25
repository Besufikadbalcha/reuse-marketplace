@extends('layouts.marketplace')

@section('title', __('My listings').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Seller workspace')"
        :title="__('My listings')"
        :description="__('Create, review, and manage your marketplace listings.')"
    />

    <x-account-tabs active="listings" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-base text-sage-700 dark:text-mint-100/70">{{ __('Track submissions and keep your photos up to date.') }}</p>

        <x-gradient-button as="a" href="{{ route('seller.products.create') }}" class="w-auto">{{ __('New listing') }}</x-gradient-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <form method="GET" action="{{ route('seller.products.index') }}" class="mb-6 grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:grid-cols-[minmax(0,18rem)_auto] sm:items-end">
        <div>
            <label for="status" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Status') }}</label>
            <select id="status" name="status" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>

        <x-button type="submit" variant="secondary" class="w-full sm:w-auto">{{ __('Filter') }}</x-button>
    </form>

    @if ($products->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('You have no listings yet.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Listing') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Price') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Images') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                        @foreach ($products as $product)
                            <tr class="transition hover:bg-cream-50/70 dark:hover:bg-forest-900/40">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($product->images->first())
                                            <img src="{{ $product->images->first()->previewUrl() }}" alt="" loading="lazy" class="size-14 rounded-xl border border-white/70 bg-sage-100 object-cover shadow-sm dark:border-white/10 dark:bg-forest-900">
                                        @else
                                            <span class="grid size-14 place-items-center rounded-xl bg-sage-100 text-xs text-forest-700 dark:bg-forest-900 dark:text-mint-100/70">{{ __('No image') }}</span>
                                        @endif

                                        <div class="grid gap-1">
                                            <a href="{{ route('seller.products.show', $product) }}" class="text-lg font-semibold text-forest-900 hover:underline focus-ring dark:text-white">{{ $product->title }}</a>
                                            <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ $product->category->name }} &middot; {{ __($product->conditionLabel()) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <x-badge :tone="$product->status === 'approved' ? 'mint' : ($product->status === 'rejected' ? 'blush' : 'cream')">
                                        {{ match ($product->status) {
                                            App\Models\Product::STATUS_PENDING => __('Pending'),
                                            App\Models\Product::STATUS_APPROVED => __('Approved'),
                                            App\Models\Product::STATUS_REJECTED => __('Rejected'),
                                            default => __(ucfirst($product->status)),
                                        } }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-4 text-xl font-semibold text-forest-900 dark:text-white">{{ $product->formattedPrice() }}</td>
                                <td class="px-5 py-4">{{ $product->images->count() }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('seller.products.show', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('View') }}</a>
                                        <a href="{{ route('seller.products.edit', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Edit') }}</a>
                                        @if (in_array($product->id, $openPromotionProductIds, true))
                                            <a href="{{ route('seller.promotions.index') }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('View promotion') }}</a>
                                        @elseif ($product->status === App\Models\Product::STATUS_APPROVED)
                                            <a href="{{ route('seller.promotions.create', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Promote product') }}</a>
                                        @endif
                                        @if ($product->order_items_count > 0)
                                            <span class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Order history') }}</span>
                                        @else
                                            <form method="POST" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Delete this listing?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-full px-2 py-1 text-sm font-medium text-blush-700 hover:bg-blush-100 hover:underline focus-ring dark:text-blush-200 dark:hover:bg-blush-900/40">{{ __('Delete') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-5">
            {{ $products->links() }}
        </div>
    @endif
@endsection
