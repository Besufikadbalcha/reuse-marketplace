<div class="rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-forest-900 dark:text-white">{{ __('Images') }}</h2>
            <p class="mt-1 text-xs text-sage-700 dark:text-mint-100/70">{{ __('Upload 1–5 JPEG, PNG, or WebP images. Each file may be up to 5 MB.') }}</p>
        </div>

        <x-badge tone="mint">{{ __('JPEG · PNG · WebP') }}</x-badge>
    </div>

    <div class="mt-4 grid gap-2 text-xs text-red-700 dark:text-red-300" role="alert">
        @error('images')
            <p>{{ $message }}</p>
        @enderror
        @foreach ($errors->get('images.*') as $imageErrors)
            @foreach ($imageErrors as $message)
                <p>{{ $message }}</p>
            @endforeach
        @endforeach
    </div>

    @if ($isEditing && $product->images->isNotEmpty())
        <div class="mt-5 grid gap-4">
            @foreach ($product->images as $image)
                <div class="flex gap-3 rounded-2xl border border-white/70 bg-cream-50/70 p-3 shadow-sm dark:border-white/10 dark:bg-forest-900/50">
                    <img src="{{ $image->previewUrl() }}" alt="{{ __('Product image :number', ['number' => $loop->iteration]) }}" loading="lazy" class="size-20 rounded-xl border border-white/70 bg-sage-100 object-cover dark:border-white/10 dark:bg-forest-900">

                    <div class="grid flex-1 content-start gap-3 text-xs">
                        <label class="flex items-center gap-2 font-medium text-forest-800 dark:text-mint-100">
                            <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="size-4 rounded-md border-sage-300 accent-forest-700 focus-ring dark:border-white/20">
                            {{ __('Remove this image') }}
                        </label>

                        <label class="flex flex-wrap items-center gap-2 text-forest-700 dark:text-mint-100/80">
                            {{ __('Order') }}
                            <input type="number" name="image_order[{{ $image->id }}]" value="{{ $loop->index }}" min="0" max="4" class="w-16 rounded-xl border border-sage-200 bg-white/85 px-2 py-1.5 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                        </label>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-2 text-xs text-red-700 dark:text-red-300" role="alert">
            @error('remove_images')
                <p>{{ $message }}</p>
            @enderror
            @error('image_order')
                <p>{{ $message }}</p>
            @enderror
        </div>
    @endif

    <div class="mt-5 grid gap-2">
        <label for="images" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ $isEditing ? __('Add more images') : __('Product images') }}</label>
        <input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple @required(! $isEditing) class="block w-full text-sm text-forest-900 file:mr-3 file:rounded-full file:border-0 file:bg-forest-800 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white file:shadow-lg file:shadow-forest-950/20 transition hover:file:-translate-y-0.5 hover:file:bg-forest-700 focus-ring dark:text-mint-100 dark:file:bg-mint-200 dark:file:text-forest-950 dark:hover:file:bg-mint-100">
    </div>
</div>
