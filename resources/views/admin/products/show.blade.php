@extends('layouts.marketplace')

@section('title', __('Review :title', ['title' => $product->title]).' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('admin.products.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to moderation queue') }}
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
                <x-badge tone="cream">
                    {{ match ($product->status) {
                        App\Models\Product::STATUS_PENDING => __('Pending'),
                        App\Models\Product::STATUS_APPROVED => __('Approved'),
                        App\Models\Product::STATUS_REJECTED => __('Rejected'),
                        default => __(ucfirst($product->status)),
                    } }}
                </x-badge>
                <span class="rounded-full bg-cream-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ $product->category->name }}</span>
                <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __($product->conditionLabel()) }}</span>
                <span class="rounded-full bg-mint-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __('Seller: :name', ['name' => $product->seller->name]) }}</span>
                <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __('Listing date') }}: {{ $product->created_at?->format('Y-m-d H:i') }}</span>
            </div>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $product->title }}</h1>
            <p class="mt-4 text-xl font-semibold text-forest-800 dark:text-mint-200">{{ $product->formattedPrice() }}</p>

            @if ($product->rejection_reason)
                <x-alert type="error" class="mt-6">
                    <h2 class="font-semibold">{{ __('Existing reviewer note') }}</h2>
                    <p class="mt-1 whitespace-pre-line">{{ $product->rejection_reason }}</p>
                </x-alert>
            @endif

            <div class="mt-7 border-t border-sage-200 pt-6 dark:border-white/10">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Description') }}</h2>
                <p class="mt-2 whitespace-pre-line text-base leading-relaxed text-forest-800 dark:text-mint-100/85">{{ $product->description }}</p>
            </div>
        </section>

        <aside class="grid content-start gap-6">
            <div class="brand-panel border border-white/70 bg-white/60 p-4 backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-5">
                <div class="grid grid-cols-2 gap-3">
                    @foreach ($product->images as $image)
                        <img src="{{ $image->adminUrl() }}" alt="{{ __('Photo :number of :title', ['number' => $loop->iteration, 'title' => $product->title]) }}" loading="lazy" class="aspect-[4/3] w-full rounded-xl border border-white/70 bg-sage-100 object-cover shadow-sm dark:border-white/10 dark:bg-forest-900">
                    @endforeach
                </div>
            </div>

            @if ($product->isArchived())
                <div class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 text-sm shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <p class="text-forest-700 dark:text-mint-100/80">{{ __('This listing is archived and hidden from the public catalog.') }}</p>
                    <form method="POST" action="{{ route('admin.products.restore', $product) }}">
                        @csrf
                        <x-button type="submit" variant="secondary" class="w-full">{{ __('Restore listing') }}</x-button>
                    </form>
                </div>
            @elseif ($product->status === \App\Models\Product::STATUS_PENDING)
                <div class="grid gap-4 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <form method="POST" action="{{ route('admin.products.approve', $product) }}">
                        @csrf
                        <x-gradient-button type="submit">{{ __('Approve listing') }}</x-gradient-button>
                    </form>

                    <form method="POST" action="{{ route('admin.products.reject', $product) }}" class="grid gap-3">
                        @csrf

                        <div class="space-y-2">
                            <label for="rejection_reason" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Rejection reason') }}</label>
                            <textarea id="rejection_reason" name="rejection_reason" rows="4" maxlength="1000" required class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('rejection_reason') }}</textarea>

                            @error('rejection_reason')
                                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-full border border-blush-300 px-6 py-3 text-sm font-semibold text-blush-700 transition hover:-translate-y-0.5 hover:bg-blush-100 focus-ring dark:border-blush-700 dark:text-blush-200 dark:hover:bg-blush-900/40">{{ __('Reject listing') }}</button>
                    </form>

                    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Archive this listing?') }}')">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost" class="w-full">{{ __('Archive') }}</x-button>
                    </form>
                </div>
            @else
                <div class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 text-sm shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                    <p class="text-forest-700 dark:text-mint-100/80">{{ __('This listing has already been reviewed.') }}</p>

                    @if ($product->status === \App\Models\Product::STATUS_APPROVED && ! $product->isArchived())
                        <x-button as="a" href="{{ route('products.show', $product->slug) }}" variant="secondary" class="mt-4 w-full">{{ __('View public listing') }}</x-button>
                        <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Archive this listing?') }}')" class="mt-3">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost" class="w-full">{{ __('Archive') }}</x-button>
                        </form>
                    @endif
                </div>
            @endif
        </aside>
    </div>
@endsection
