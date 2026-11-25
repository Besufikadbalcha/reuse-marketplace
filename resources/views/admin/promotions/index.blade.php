@extends('layouts.marketplace')

@section('title', __('Promotion payments').' - '.config('app.name'))

@section('content')
    <x-page-heading
        :eyebrow="__('Admin moderation')"
        :title="__('Promotion payments')"
        :description="__('Review pending payment proofs before promotions go live.')"
    />

    @if (session('status'))
        <x-alert class="mb-6">{{ __(session('status')) }}</x-alert>
    @endif

    @if (session('error'))
        <x-alert type="error" class="mb-6">{{ __(session('error')) }}</x-alert>
    @endif

    @if ($promotions->isEmpty())
        <x-alert type="warning" class="text-center">
            {{ __('There are no payments waiting for review.') }}
        </x-alert>
    @else
        <div class="brand-panel overflow-hidden border border-white/70 bg-white/65 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead class="border-b border-sage-200 bg-sage-100/70 text-xs font-semibold uppercase tracking-widest text-forest-700 dark:border-white/10 dark:bg-forest-900/60 dark:text-mint-200">
                        <tr>
                            <th scope="col" class="px-5 py-4">{{ __('Promotion request') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Seller') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Package') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Submitted') }}</th>
                            <th scope="col" class="px-5 py-4">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sage-200 dark:divide-white/10">
                        @foreach ($promotions as $promotion)
                            <tr class="transition hover:bg-cream-50/70 dark:hover:bg-forest-900/40">
                                <td class="px-5 py-4">
                                    <div class="grid gap-1">
                                        <a href="{{ route('admin.promotions.show', $promotion) }}" class="font-semibold text-forest-900 hover:underline focus-ring dark:text-white">{{ $promotion->product?->title ?? __('Listing removed') }}</a>
                                        <span class="text-xs text-sage-700 dark:text-mint-100/70">{{ __('Attempt :number', ['number' => $promotion->paymentSubmissions->first()?->attempt ?? 1]) }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-medium text-forest-900 dark:text-white">{{ $promotion->seller?->name ?? __('Not provided') }}</td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ $promotion->package?->name ?? __($promotion->package_code_snapshot) }}</td>
                                <td class="px-5 py-4 text-sage-700 dark:text-mint-100/70">{{ App\Models\Product::formatDate($promotion->paymentSubmissions->first()?->submitted_at ?? $promotion->created_at) }}</td>
                                <td class="px-5 py-4">
                                    <a href="{{ route('admin.promotions.show', $promotion) }}" class="rounded-full px-2 py-1 text-sm font-medium text-forest-700 hover:bg-sage-100 hover:underline focus-ring dark:text-mint-100 dark:hover:bg-forest-800">{{ __('Review') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-white/70 bg-white/60 p-4 shadow-soft backdrop-blur-xl dark:border-white/10 dark:bg-forest-950/60 sm:p-5">
            {{ $promotions->links() }}
        </div>
    @endif
@endsection
