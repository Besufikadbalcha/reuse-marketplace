@if ($errors->any())
    <x-alert type="error" class="mb-4">
        <ul class="list-inside list-disc gap-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

<form method="GET" action="{{ $formAction }}" class="relative z-20 flex min-w-0 max-w-full flex-wrap items-end gap-x-2 gap-y-2 rounded-2xl border border-white/70 bg-white/65 p-2 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 lg:flex-nowrap">
    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:basis-0 lg:grow-2">
        <x-field
            name="q"
            :label="__('Search')"
            type="search"
            value="{{ $filters['q'] }}"
            placeholder="{{ __('Search titles, descriptions, categories') }}"
            maxlength="100"
            :compact="true"
        />
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:w-28 lg:flex-none">
        <div class="relative" data-location-filter>
            <x-field
                name="location"
                :label="__('Location')"
                type="search"
                value="{{ $filters['location'] }}"
                placeholder="{{ __('All locations') }}"
                maxlength="120"
                autocomplete="off"
                role="combobox"
                aria-controls="filter-location-options"
                aria-expanded="false"
                aria-autocomplete="list"
                :compact="true"
            />

            <div
                id="filter-location-options"
                role="listbox"
                hidden
                class="absolute inset-x-0 top-full z-50 mt-1 max-h-[9.25rem] overflow-y-auto overscroll-contain rounded-xl border border-sage-200 bg-white/95 p-1 shadow-soft backdrop-blur-xl dark:border-white/15 dark:bg-forest-900/95"
            >
                @forelse ($locations as $locationOption)
                    <button
                        type="button"
                        role="option"
                        data-location-option
                        data-location-name="{{ $locationOption->name }}"
                        aria-selected="{{ old('location', $filters['location']) === $locationOption->name ? 'true' : 'false' }}"
                        class="block w-full truncate rounded-lg px-2.5 py-1.5 text-left text-xs leading-4 font-medium text-forest-800 transition hover:bg-sage-100 focus-ring dark:text-mint-100 dark:hover:bg-forest-800"
                    >
                        {{ $locationOption->name }}
                    </button>
                @empty
                    <p data-location-empty class="px-2.5 py-1.5 text-xs leading-4 text-sage-700 dark:text-mint-100/70">{{ __('No locations found') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:w-32 lg:flex-none">
        @if ($category)
            <span class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Category') }}</span>
            <p class="mt-1 block w-full truncate rounded-xl border border-sage-200 bg-white/85 px-3 py-2 text-xs text-forest-900 dark:border-white/15 dark:bg-white/5 dark:text-white">{{ $category->name }}</p>
        @else
            <label for="filter-category" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Category') }}</label>
            <select id="filter-category" name="category" class="mt-1 block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-2 text-xs text-forest-900 shadow-sm focus:border-forest-600 focus:ring-2 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('All categories') }}</option>

                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected((string) old('category', $filters['category']) === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:w-28 lg:flex-none">
        <label for="filter-condition" class="block text-xs font-medium text-forest-800 dark:text-mint-100">{{ __('Condition') }}</label>
        <select id="filter-condition" name="condition" class="mt-1 block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-2 text-xs text-forest-900 shadow-sm focus:border-forest-600 focus:ring-2 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
            <option value="">{{ __('All conditions') }}</option>

            @foreach ($conditions as $value => $label)
                <option value="{{ $value }}" @selected(old('condition', $filters['condition']) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:w-44 lg:flex-none">
        <fieldset>
            <legend class="flex w-full items-center justify-between gap-2 text-xs font-medium text-forest-800 dark:text-mint-100">
                <span class="sr-only">{{ __('Price range') }} (ETB)</span>

                @if ($filters['min_price'] !== '' || $filters['max_price'] !== '')
                    <a href="{{ $clearPriceRangeUrl }}" class="inline-flex ml-auto shrink-0 whitespace-nowrap text-xs font-medium text-forest-700 underline hover:text-forest-900 focus-ring dark:text-mint-200 dark:hover:text-white">{{ __('Clear price range') }}</a>
                @endif
            </legend>
            <div class="mt-1 grid min-w-0 grid-cols-2 gap-2">
                <x-field
                    name="min_price"
                    :label="__('Min price')"
                    type="number"
                    value="{{ $filters['min_price'] }}"
                    min="0"
                    max="1000000"
                    step="0.01"
                    :compact="true"
                />
                <x-field
                    name="max_price"
                    :label="__('Max price')"
                    type="number"
                    value="{{ $filters['max_price'] }}"
                    min="0"
                    max="1000000"
                    step="0.01"
                    :compact="true"
                />
            </div>
        </fieldset>
    </div>

    <div class="grid w-full min-w-0 grid-cols-2 gap-2 sm:w-auto sm:flex-none lg:shrink-0">
        <x-gradient-button type="submit" :compact="true" class="w-full whitespace-nowrap">{{ __('Apply filters') }}</x-gradient-button>
        <x-button as="a" href="{{ $clearFiltersUrl }}" variant="secondary" :compact="true" class="w-full whitespace-nowrap">{{ __('Clear filters') }}</x-button>
    </div>
</form>

<script>
    (() => {
        const root = document.querySelector('[data-location-filter]');

        if (! root) {
            return;
        }

        const input = root.querySelector('input[name="location"]');
        const panel = root.querySelector('[role="listbox"]');
        const options = Array.from(root.querySelectorAll('[data-location-option]'));
        const emptyMessage = root.querySelector('[data-location-empty]');

        if (! input || ! panel) {
            return;
        }

        let activeIndex = -1;

        const visibleOptions = () => options.filter((option) => ! option.hidden);

        const setOpen = (open) => {
            panel.hidden = ! open;
            input.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (! open) {
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
            }
        };

        const clearActiveOption = () => {
            options.forEach((option) => option.classList.remove('bg-sage-100', 'dark:bg-forest-800'));
        };

        const setActiveOption = (index) => {
            const visible = visibleOptions();

            if (visible.length === 0) {
                return;
            }

            activeIndex = Math.max(0, Math.min(index, visible.length - 1));
            clearActiveOption();

            const activeOption = visible[activeIndex];
            activeOption.classList.add('bg-sage-100', 'dark:bg-forest-800');
            activeOption.scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', activeOption.id);
        };

        const filterOptions = () => {
            const term = input.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            options.forEach((option) => {
                const matches = option.dataset.locationName.toLocaleLowerCase().includes(term);
                option.hidden = ! matches;

                if (matches) {
                    visibleCount += 1;
                }
            });

            if (emptyMessage) {
                emptyMessage.hidden = visibleCount > 0;
            }

            clearActiveOption();
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            setOpen(true);
        };

        const selectOption = (option) => {
            input.value = option.dataset.locationName;

            options.forEach((item) => {
                item.setAttribute('aria-selected', item === option ? 'true' : 'false');
            });

            setOpen(false);
        };

        options.forEach((option, index) => {
            option.id = option.id || `filter-location-option-${index}`;
            option.addEventListener('click', () => selectOption(option));
        });

        input.addEventListener('focus', filterOptions);
        input.addEventListener('input', filterOptions);
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setOpen(false);

                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setOpen(true);
                setActiveOption(activeIndex + 1);

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setOpen(true);
                setActiveOption(activeIndex - 1);

                return;
            }

            if (event.key === 'Enter' && ! panel.hidden) {
                const activeOption = visibleOptions()[activeIndex];

                if (activeOption) {
                    event.preventDefault();
                    selectOption(activeOption);
                }
            }
        });

        root.addEventListener('focusout', (event) => {
            if (! root.contains(event.relatedTarget)) {
                setOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (! root.contains(event.target)) {
                setOpen(false);
            }
        });
    })();
</script>

@if ($activeFilters->isNotEmpty())
    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-forest-700 dark:text-mint-100/80">
        <span class="font-medium">{{ __('Active filters:') }}</span>

        @foreach ($activeFilters as $activeFilter)
            <a href="{{ $activeFilter['url'] }}" class="inline-flex items-center gap-1.5 rounded-full border border-sage-200 bg-white/80 px-2.5 py-1 font-medium text-forest-800 hover:bg-white focus-ring dark:border-white/15 dark:bg-white/5 dark:text-mint-100 dark:hover:bg-forest-800">
                {{ $activeFilter['label'] }}
                <span aria-hidden="true">&times;</span>
                <span class="sr-only">{{ __('Remove filter') }}</span>
            </a>
        @endforeach

        <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center rounded-full px-2.5 py-1 font-medium text-forest-700 underline hover:text-forest-900 focus-ring dark:text-mint-100 dark:hover:text-white">{{ __('Clear all filters') }}</a>
    </div>
@endif
