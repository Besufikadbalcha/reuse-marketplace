@extends('layouts.marketplace')

@section('title', $product->title.' - '.config('app.name'))

@section('content')
    <div class="mb-6">
        <x-button as="a" href="{{ route('seller.products.index') }}" variant="ghost" class="-ml-3">
            <span aria-hidden="true">&larr;</span>
            {{ __('Back to my listings') }}
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
                <x-badge :tone="$product->status === 'approved' ? 'mint' : ($product->status === 'rejected' ? 'blush' : 'cream')">
                    {{ match ($product->status) {
                        App\Models\Product::STATUS_PENDING => __('Pending'),
                        App\Models\Product::STATUS_APPROVED => __('Approved'),
                        App\Models\Product::STATUS_REJECTED => __('Rejected'),
                        default => __(ucfirst($product->status)),
                    } }}
                </x-badge>
                <span class="rounded-full bg-cream-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ $product->category->name }}</span>
                <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ __($product->conditionLabel()) }}</span>
                @if ($product->location)
                    <span class="rounded-full bg-sage-100/80 px-3 py-1 font-medium text-forest-800 dark:bg-forest-900/70 dark:text-mint-100">{{ $product->location->name }}</span>
                @endif
            </div>

            <h1 class="mt-5 text-4xl font-semibold tracking-tight text-forest-900 dark:text-white sm:text-5xl">{{ $product->title }}</h1>
            <p class="mt-4 text-xl font-semibold text-forest-800 dark:text-mint-200">{{ $product->formattedPrice() }}</p>

            @if ($product->rejection_reason)
                <x-alert type="error" class="mt-6">
                    <h2 class="font-semibold">{{ __('Reviewer note') }}</h2>
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
                        <img src="{{ $image->previewUrl() }}" alt="{{ __('Photo :number of :title', ['number' => $loop->iteration, 'title' => $product->title]) }}" loading="lazy" class="aspect-[4/3] w-full rounded-xl border border-white/70 bg-sage-100 object-cover shadow-sm dark:border-white/10 dark:bg-forest-900">
                    @endforeach
                </div>
            </div>

            <div class="grid gap-3 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
                <x-button as="a" href="{{ route('seller.products.edit', $product) }}" class="w-full">{{ __('Edit listing') }}</x-button>

                <form method="POST" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('{{ __('Delete this listing?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-full border border-blush-300 px-6 py-3 text-sm font-semibold text-blush-700 transition hover:-translate-y-0.5 hover:bg-blush-100 focus-ring dark:border-blush-700 dark:text-blush-200 dark:hover:bg-blush-900/40">{{ __('Delete listing') }}</button>
                </form>
            </div>
        </aside>
    </div>
@endsection
