@extends('layouts.marketplace')

@section('title', __('Review :name', ['name' => $seller->name]).' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('admin.sellers.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to sellers') }}
        </x-button>
    </div>

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="brand-panel border border-white/70 bg-white/65 p-6 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <x-badge :tone="$seller->isArchived() ? 'cream' : 'mint'">
                    {{ $seller->isArchived() ? __('Archived') : __('Active') }}
                </x-badge>
                <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __('Joined') }}: {{ $seller->created_at?->format('Y-m-d H:i') }}</span>
                <span class="rounded-full bg-mint-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __(':count listings', ['count' => $products->count()]) }}</span>
            </div>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $seller->name }}</h1>
            <p class="mt-2 text-base text-forest-700 dark:text-mint-100/80">{{ $seller->email }}</p>

            @if ($seller->isArchived())
                <x-alert type="warning" class="mt-6">{{ __('This seller is archived and cannot use the marketplace.') }}</x-alert>
            @endif

            <div class="mt-7 overflow-hidden rounded-[1.5rem] border border-white/70 bg-white/60 dark:border-white/10 dark:bg-forest-950/60">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ __('Listing') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Status') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Archive context') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                            @foreach ($products as $product)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-forest-900 dark:text-white">{{ $product->title }}</td>
                                    <td class="px-4 py-3">
                                        <x-badge :tone="$product->status === App\Models\Product::STATUS_APPROVED ? 'mint' : ($product->status === App\Models\Product::STATUS_REJECTED ? 'blush' : 'cream')">
                                            {{ match ($product->status) {
                                                App\Models\Product::STATUS_PENDING => __('Pending'),
                                                App\Models\Product::STATUS_APPROVED => __('Approved'),
                                                App\Models\Product::STATUS_REJECTED => __('Rejected'),
                                                default => __(ucfirst($product->status)),
                                            } }}
                                        </x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-sage-700 dark:text-mint-100/70">
                                        @if ($product->isArchived())
                                            {{ __('Individually archived') }}
                                        @elseif ($product->isSellerArchived())
                                            {{ __('Hidden by seller archive') }}
                                        @elseif ($product->rejection_reason)
                                            {{ __('Rejected: :reason', ['reason' => $product->rejection_reason]) }}
                                        @else
                                            {{ __('Visible') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('admin.products.show', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Review') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <aside class="grid content-start gap-6">
            @if ($seller->isArchived())
                <div class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 text-sm shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <p class="text-forest-700 dark:text-mint-100/80">{{ __('Restoring the account does not restore any listings.') }}</p>
                    <form method="POST" action="{{ route('admin.sellers.restore', $seller) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" class="w-full">{{ __('Restore seller account') }}</x-button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('admin.sellers.archive', $seller) }}" onsubmit="return confirm('{{ __('Archive this seller?') }}')" class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    @csrf

                    <div class="space-y-2">
                        <label for="reason" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Archive reason') }}</label>
                        <textarea id="reason" name="reason" rows="4" maxlength="500" class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-button type="submit" variant="ghost" class="w-full">{{ __('Archive seller') }}</x-button>
                </form>
            @endif

            <div class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 text-sm shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Archive history') }}</h2>

                @if ($seller->sellerArchiveEvents->isEmpty())
                    <p class="text-forest-700 dark:text-mint-100/80">{{ __('No archive history yet.') }}</p>
                @else
                    <ul class="grid gap-3">
                        @foreach ($seller->sellerArchiveEvents as $event)
                            <li class="rounded-2xl border border-sage-200 bg-white/70 p-3 dark:border-white/10 dark:bg-forest-900/50">
                                <p class="font-medium text-forest-900 dark:text-white">{{ $event->action === App\Models\SellerArchiveEvent::ACTION_ARCHIVE ? __('Archived') : __('Restored') }}</p>
                                <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ $event->created_at?->format('Y-m-d H:i') }} &middot; {{ $event->actor?->name ?? __('System') }}</p>
                                <p class="mt-1 text-xs text-sage-700 dark:text-mint-100/70">
                                    {{ __('Scope: :scope', ['scope' => ($event->metadata['scope'] ?? '') === 'listings' ? __('Listings') : __('Account')]) }}
                                    &middot; {{ __(':count affected', ['count' => $event->listings_affected]) }}
                                    &middot; {{ __(':count restored', ['count' => $event->listings_restored]) }}
                                </p>
                                @if ($event->reason)
                                    <p class="mt-1 whitespace-pre-line text-xs text-forest-800 dark:text-mint-100/85">{{ $event->reason }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </aside>
    </div>

    @if (! $seller->isArchived() && $restorableListings->isNotEmpty())
        <form method="POST" action="{{ route('admin.sellers.listings.restore', $seller) }}" class="mt-8 grid gap-5 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            @csrf

            <div>
                <h2 class="text-2xl font-semibold text-forest-900 dark:text-white">{{ __('Restore selected listings') }}</h2>
                <p class="mt-1 text-sm text-forest-700 dark:text-mint-100/80">{{ __('Only eligible listings hidden by this seller archive can be restored.') }}</p>
            </div>

            <div class="grid gap-3">
                @foreach ($restorableListings as $product)
                    <label for="product-{{ $product->id }}" class="flex items-start gap-3 rounded-2xl border border-sage-200 bg-white/70 p-4 text-sm dark:border-white/10 dark:bg-forest-900/50">
                        <input id="product-{{ $product->id }}" type="checkbox" name="products[]" value="{{ $product->id }}" class="mt-1 size-4 rounded-md border-sage-300 accent-forest-700 focus-ring dark:border-white/20">
                        <span class="grid gap-1">
                            <span class="font-medium text-forest-900 dark:text-white">{{ $product->title }}</span>
                            <span class="text-xs text-sage-700 dark:text-mint-100/70">{{ __($product->status === App\Models\Product::STATUS_APPROVED ? 'Approved' : 'Pending') }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            @error('products')
                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror

            <div class="space-y-2">
                <label for="restore_reason" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Restore reason') }}</label>
                <textarea id="restore_reason" name="reason" rows="3" maxlength="500" class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <x-gradient-button type="submit">{{ __('Restore selected listings') }}</x-gradient-button>
            </div>
        </form>
    @endif

    @if ($blockedListings->isNotEmpty())
        <section class="mt-8 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
            <h2 class="text-2xl font-semibold text-forest-900 dark:text-white">{{ __('Listings unavailable for restoration') }}</h2>
            <p class="mt-1 text-sm text-forest-700 dark:text-mint-100/80">{{ __('These listings remain hidden and require separate review.') }}</p>

            <ul class="mt-4 grid gap-3">
                @foreach ($blockedListings as $product)
                    <li class="rounded-2xl border border-sage-200 bg-white/70 p-4 text-sm dark:border-white/10 dark:bg-forest-900/50">
                        <p class="font-medium text-forest-900 dark:text-white">{{ $product->title }}</p>
                        <p class="mt-1 text-xs text-sage-700 dark:text-mint-100/70">
                            @if ($product->isArchived())
                                {{ __('Individually archived') }}
                            @elseif ($product->status === App\Models\Product::STATUS_REJECTED)
                                {{ __('Rejected') }}
                            @elseif ($product->reservation_token !== null)
                                {{ __('Reserved') }}
                            @elseif ($product->sold_order_items_count > 0)
                                {{ __('Sold') }}
                            @else
                                {{ __('Not eligible') }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
