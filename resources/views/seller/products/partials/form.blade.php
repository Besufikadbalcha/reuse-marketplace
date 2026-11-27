@php($isEditing = isset($product))

<form method="POST" action="{{ $isEditing ? route('seller.products.update', $product) : route('seller.products.store') }}" enctype="multipart/form-data" class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <div class="grid gap-6 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 sm:p-8">
        <x-field
            name="title"
            :label="__('Title')"
            type="text"
            value="{{ $isEditing ? $product->title : '' }}"
            maxlength="255"
            required
            autofocus
        />

        <div class="space-y-2">
            <label for="description" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Description') }}</label>
            <textarea id="description" name="description" rows="6" maxlength="5000" required class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:border-mint-300 dark:focus:ring-mint-300/15">{{ old('description', $isEditing ? $product->description : '') }}</textarea>
            @error('description')
                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <x-field
                name="price"
                :label="__('Price (ETB)')"
                type="number"
                value="{{ $isEditing ? number_format((int) $product->price_minor / 100, 2, '.', '') : '' }}"
                min="0.01"
                max="1000000"
                step="0.01"
                required
            />

            <div class="space-y-2">
                <label for="condition" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Condition') }}</label>
                <select id="condition" name="condition" required class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                    @foreach ($conditions as $value => $label)
                        <option value="{{ $value }}" @selected(old('condition', $isEditing ? $product->condition : \App\Models\Product::CONDITION_LIKE_NEW) === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>
                @error('condition')
                    <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label for="category_id" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Category') }}</label>
            <select id="category_id" name="category_id" required class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('Select a category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $isEditing ? $product->category_id : '') === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id')
                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="location_id" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Location') }}</label>
            <select id="location_id" name="location_id" @required(! $isEditing) class="block w-full rounded-2xl border border-sage-200 bg-white/85 px-4 py-3.5 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-4 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('Select a location') }}</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('location_id', $isEditing ? $product->location_id : '') === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
            @error('location_id')
                <p class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <aside class="grid content-start gap-6">
        @include('seller.products.partials.images', ['isEditing' => $isEditing])

        <div class="grid gap-3 rounded-[1.75rem] border border-white/70 bg-white/65 p-6 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70">
            <p class="text-xs font-semibold uppercase tracking-widest text-forest-600 dark:text-mint-300">{{ __('Ready to submit?') }}</p>

            <x-gradient-button type="submit">
                {{ $isEditing ? __('Save changes') : __('Submit listing') }}
            </x-gradient-button>

            <x-button as="a" href="{{ $isEditing ? route('seller.products.show', $product) : route('seller.products.index') }}" variant="secondary" class="w-full">{{ __('Cancel') }}</x-button>
        </div>
    </aside>
</form>
