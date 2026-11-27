@extends('layouts.marketplace')

@section('title', __('Moderation queue').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Admin moderation')"
        :title="__('Moderation queue')"
        :description="__('Review listings and manage their moderation status.')"
    />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <form method="GET" action="{{ route('admin.products.index') }}" class="mb-6 grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:grid-cols-[minmax(0,20rem)_auto] sm:items-end">
        <input type="hidden" name="status" value="{{ $status }}">

        <div>
            <label for="q" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Search listings') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="{{ __('Search listings') }}" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
        </div>

        <x-button type="submit" variant="secondary" class="w-full sm:w-auto">{{ __('Search') }}</x-button>
    </form>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @foreach ($filters as $value => $label)
            <a href="{{ route('admin.products.index', ['status' => $value] + ($search !== '' ? ['q' => $search] : [])) }}" @if ($status === $value) aria-current="page" @endif class="rounded-full px-4 py-2 text-sm font-medium transition focus-ring {{ $status === $value ? 'bg-forest-800 text-white shadow-sm dark:bg-mint-100 dark:text-forest-900' : 'bg-white/65 text-forest-700 hover:bg-sage-100 dark:bg-white/5 dark:text-mint-100 dark:hover:bg-forest-800' }}">
                {{ __($label) }}
            </a>
        @endforeach

        @if ($status !== 'all' || $search !== '')
            <a href="{{ route('admin.products.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Clear filters') }}</a>
        @endif
    </div>

    @if ($products->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('No listings match your filters.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Listing') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Seller') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Listing date') }}</th>
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
                                            <img src="{{ $product->images->first()->adminUrl() }}" alt="" loading="lazy" class="size-14 rounded-xl border border-white/70 bg-sage-100 object-cover shadow-sm dark:border-white/10 dark:bg-forest-900">
                                        @else
                                            <span class="grid size-14 place-items-center rounded-xl bg-sage-100 text-xs text-forest-700 dark:bg-forest-900 dark:text-mint-100/70">{{ __('No image') }}</span>
                                        @endif

                                        <div class="grid gap-1">
                                            <a href="{{ route('admin.products.show', $product) }}" class="text-lg font-semibold text-forest-900 hover:underline focus-ring dark:text-white">{{ $product->title }}</a>
                                            <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ $product->category->name }} &middot; {{ __($product->conditionLabel()) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($product->isArchived())
                                            <x-badge tone="cream">{{ __('Archived') }}</x-badge>
                                        @endif
                                        <x-badge :tone="$product->status === App\Models\Product::STATUS_APPROVED ? 'mint' : ($product->status === App\Models\Product::STATUS_REJECTED ? 'blush' : 'cream')">
                                            {{ match ($product->status) {
                                                App\Models\Product::STATUS_PENDING => __('Pending'),
                                                App\Models\Product::STATUS_APPROVED => __('Approved'),
                                                App\Models\Product::STATUS_REJECTED => __('Rejected'),
                                                default => __(ucfirst($product->status)),
                                            } }}
                                        </x-badge>
                                        @if ($product->sold_order_items_count > 0)
                                            <x-badge tone="blush">{{ __('Sold') }}</x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-medium text-forest-900 dark:text-white">{{ $product->seller->name }}</td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $product->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">{{ $product->images->count() }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('admin.products.show', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Review') }}</a>
                                        @if ($product->isArchived())
                                            <form method="POST" action="{{ route('admin.products.restore', $product) }}">
                                                @csrf
                                                <button type="submit" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Restore') }}</button>
                                            </form>
                                        @else
                                            @if ($product->status === App\Models\Product::STATUS_PENDING)
                                                <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-full bg-mint-100 px-3 py-1.5 text-sm font-semibold text-forest-800 transition hover:-translate-y-0.5 hover:bg-mint-200 focus-ring dark:bg-forest-800 dark:text-mint-100 dark:hover:bg-forest-700">{{ __('Approve') }}</button>
                                                </form>
                                            @endif
                                            @if (in_array($product->status, [App\Models\Product::STATUS_PENDING, App\Models\Product::STATUS_APPROVED], true))
                                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Archive this listing?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-full px-2 py-1 text-sm font-medium text-blush-700 hover:bg-blush-100 hover:underline focus-ring dark:text-blush-200 dark:hover:bg-blush-900/40">{{ __('Archive') }}</button>
                                                </form>
                                            @endif
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
