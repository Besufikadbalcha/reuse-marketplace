@extends('layouts.marketplace')

@section('title', __('Sellers').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Admin moderation')"
        :title="__('Sellers')"
        :description="__('Review seller accounts and manage archive status.')"
    />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    <form method="GET" action="{{ route('admin.sellers.index') }}" class="mb-6 grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-5 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:grid-cols-[minmax(0,20rem)_auto] sm:items-end">
        <input type="hidden" name="status" value="{{ $status }}">

        <div>
            <label for="q" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Search sellers') }}</label>
            <input id="q" name="q" type="search" value="{{ $search }}" maxlength="100" placeholder="{{ __('Search sellers') }}" class="mt-2 block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
        </div>

        <x-button type="submit" variant="secondary" class="w-full sm:w-auto">{{ __('Search') }}</x-button>
    </form>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @foreach ($filters as $value => $label)
            <a href="{{ route('admin.sellers.index', ['status' => $value] + ($search !== '' ? ['q' => $search] : [])) }}" @if ($status === $value) aria-current="page" @endif class="rounded-full px-4 py-2 text-sm font-medium transition focus-ring {{ $status === $value ? 'bg-forest-800 text-white shadow-sm dark:bg-mint-100 dark:text-forest-900' : 'bg-white/65 text-forest-700 hover:bg-sage-100 dark:bg-white/5 dark:text-mint-100 dark:hover:bg-forest-800' }}">
                {{ __($label) }}
            </a>
        @endforeach

        @if ($status !== 'all' || $search !== '')
            <a href="{{ route('admin.sellers.index') }}" class="rounded-full px-3 py-2 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Clear filters') }}</a>
        @endif
    </div>

    @if ($sellers->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('No sellers match your filters.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Seller') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Status') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Listings') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Joined') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                        @foreach ($sellers as $seller)
                            <tr class="transition hover:bg-cream-50/70 dark:hover:bg-forest-900/40">
                                <td class="px-5 py-4">
                                    <div class="grid gap-1">
                                        <a href="{{ route('admin.sellers.show', $seller) }}" class="text-lg font-semibold text-forest-900 hover:underline focus-ring dark:text-white">{{ $seller->name }}</a>
                                        <p class="text-xs text-sage-700 dark:text-mint-100/70">{{ $seller->email }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <x-badge :tone="$seller->isArchived() ? 'cream' : 'mint'">
                                        {{ $seller->isArchived() ? __('Archived') : __('Active') }}
                                    </x-badge>
                                </td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">
                                    {{ __(':count total', ['count' => $seller->listings_count]) }}
                                    @if ($seller->hidden_listings_count > 0)
                                        &middot; {{ __(':count hidden', ['count' => $seller->hidden_listings_count]) }}
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $seller->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.sellers.show', $seller) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Review') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-5">
            {{ $sellers->links() }}
        </div>
    @endif
@endsection
