@extends('layouts.marketplace')

@section('title', __('Moderation queue').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Admin moderation')"
        :title="__('Moderation queue')"
        :description="__('Review pending listings before they appear in the public catalog.')"
    />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    @if ($products->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('There are no listings waiting for review.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Listing') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Seller') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Submitted') }}</th>
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
                                <td class="px-5 py-4 font-medium text-forest-900 dark:text-white">{{ $product->seller->name }}</td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $product->created_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4">{{ $product->images->count() }}</td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <a href="{{ route('admin.products.show', $product) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Review') }}</a>
                                        <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                                            @csrf
                                            <button type="submit" class="rounded-full bg-mint-100 px-3 py-1.5 text-sm font-semibold text-forest-800 transition hover:-translate-y-0.5 hover:bg-mint-200 focus-ring dark:bg-forest-800 dark:text-mint-100 dark:hover:bg-forest-700">{{ __('Approve') }}</button>
                                        </form>
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
