@if ($errors->any())
    <x-alert type="error" class="mb-4">
        <ul class="list-inside list-disc gap-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

<form method="GET" action="{{ $formAction }}" class="relative z-20 flex min-w-0 max-w-full flex-wrap items-end gap-x-3 gap-y-3 rounded-2xl border border-white/70 bg-white/65 p-3 shadow-soft backdrop-blur-2xl dark:border-white/10 dark:bg-forest-950/70 lg:grid lg:grid-cols-[minmax(8rem,1.5fr)_minmax(7rem,1.2fr)_minmax(6rem,1fr)_minmax(6rem,1fr)_minmax(4rem,0.7fr)_minmax(4rem,0.7fr)_minmax(7rem,1fr)_auto_auto] sm:p-3.5">
    <div class="relative w-full min-w-0 sm:w-auto sm:flex-1" data-product-search data-suggestions-url="{{ route('search.suggestions') }}" data-category="{{ $category?->id }}">
        <label class="sr-only" for="q">{{ __('Search') }}</label>
        <x-field
            name="q"
            type="search"
            value="{{ $filters['q'] }}"
            placeholder="{{ __('Search products...') }}"
            maxlength="100"
            autocomplete="off"
            role="combobox"
            aria-controls="filter-product-suggestions"
            aria-expanded="false"
            aria-autocomplete="list"
            :compact="true"
            class="py-3"
        />

        <div
            id="filter-product-suggestions"
            role="listbox"
            aria-label="{{ __('Search suggestions') }}"
            hidden
            class="absolute inset-x-0 top-full z-50 mt-1 max-h-[16rem] overflow-y-auto overscroll-contain rounded-xl border border-sage-200 bg-white/95 p-1 shadow-soft backdrop-blur-xl dark:border-white/15 dark:bg-forest-900/95"
        >
            <p data-product-suggestion-empty hidden class="px-2.5 py-1.5 text-xs leading-4 text-sage-700 dark:text-mint-100/70">{{ __('No suggestions') }}</p>
        </div>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1">
        <label class="sr-only" for="location">{{ __('Location') }}</label>
        <div class="relative" data-location-filter>
            <x-field
                name="location"
                type="search"
                value="{{ $filters['location'] }}"
                placeholder="{{ __('Location') }}"
                maxlength="120"
                autocomplete="off"
                role="combobox"
                aria-controls="filter-location-options"
                aria-expanded="false"
                aria-autocomplete="list"
                :compact="true"
                class="py-3"
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
                        class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-1.5 text-left text-xs leading-4 font-medium text-forest-800 transition hover:bg-sage-100 focus-ring dark:text-mint-100 dark:hover:bg-forest-800"
                    >
                        <span class="min-w-0 truncate">{{ $locationOption->name }}</span>
                        <span class="shrink-0 tabular-nums text-sage-700 dark:text-mint-100/70">{{ $locationCounts->get($locationOption->id, 0) }}</span>
                    </button>
                @empty
                    <p data-location-empty class="px-2.5 py-1.5 text-xs leading-4 text-sage-700 dark:text-mint-100/70">{{ __('No locations found') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1">
        @if ($category)
            <span class="sr-only">{{ __('Category') }}</span>
            <p class="block w-full truncate rounded-xl border border-sage-200 bg-white/85 px-3 py-3 text-sm text-forest-900 dark:border-white/15 dark:bg-white/5 dark:text-white">{{ $category->name }}</p>
        @else
            <label class="sr-only" for="filter-category">{{ __('Category') }}</label>
            <select id="filter-category" name="category" class="block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-3 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-2 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
                <option value="">{{ __('All categories') }}</option>

                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected((string) old('category', $filters['category']) === (string) $categoryOption->id)>{{ $categoryOption->name }} ({{ $categoryCounts->get($categoryOption->id, 0) }})</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1">
        <label class="sr-only" for="filter-condition">{{ __('Condition') }}</label>
        <select id="filter-condition" name="condition" class="block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-3 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-2 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
            <option value="">{{ __('All conditions') }}</option>

            @foreach ($conditions as $value => $label)
                <option value="{{ $value }}" @selected(old('condition', $filters['condition']) === $value)>{{ $label }} ({{ $conditionCounts->get($value, 0) }})</option>
            @endforeach
        </select>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1 lg:col-span-2">
        <fieldset aria-label="{{ __('Price range') }} (ETB)">
            <label class="sr-only" for="min_price">{{ __('Min price') }}</label>
            <label class="sr-only" for="max_price">{{ __('Max price') }}</label>
            <div class="grid min-w-0 grid-cols-2 gap-2">
                <x-field
                    name="min_price"
                    type="number"
                    value="{{ $filters['min_price'] }}"
                    placeholder="{{ __('Min price') }}"
                    min="0.01"
                    max="1000000"
                    step="0.01"
                    :compact="true"
                    class="py-3"
                />
                <x-field
                    name="max_price"
                    type="number"
                    value="{{ $filters['max_price'] }}"
                    placeholder="{{ __('Max price') }}"
                    min="0.01"
                    max="1000000"
                    step="0.01"
                    :compact="true"
                    class="py-3"
                />
            </div>
        </fieldset>
    </div>

    <div class="w-full min-w-0 sm:w-auto sm:flex-1">
        <label class="sr-only" for="filter-sort">{{ __('Sort by') }}</label>
        <select id="filter-sort" name="sort" class="block w-full rounded-xl border border-sage-200 bg-white/85 px-3 py-3 text-sm text-forest-900 shadow-sm focus:border-forest-600 focus:ring-2 focus:ring-forest-600/15 dark:border-white/15 dark:bg-white/5 dark:text-white dark:focus:border-mint-300 dark:focus:ring-mint-300/15">
            <option value="" @selected($filters['sort'] === '')>{{ __('Default order') }}</option>
            <option value="relevance" @selected($filters['sort'] === 'relevance')>{{ __('Most relevant') }}</option>
            <option value="latest" @selected($filters['sort'] === 'latest')>{{ __('Latest') }}</option>
            <option value="title_asc" @selected($filters['sort'] === 'title_asc')>{{ __('Title: A to Z') }}</option>
            <option value="price_asc" @selected($filters['sort'] === 'price_asc')>{{ __('Price: low to high') }}</option>
            <option value="price_desc" @selected($filters['sort'] === 'price_desc')>{{ __('Price: high to low') }}</option>
        </select>
    </div>

    <x-gradient-button type="submit" :compact="true" class="w-full min-w-max whitespace-nowrap px-5 py-3 sm:w-auto">{{ __('Search') }}</x-gradient-button>
    <x-button as="a" href="{{ $clearFiltersUrl }}" variant="secondary" :compact="true" class="w-full min-w-max whitespace-nowrap px-4 py-3 text-sm sm:w-auto">{{ __('Clear filters') }}</x-button>
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

<script>
    (() => {
        const root = document.querySelector('[data-product-search]');

        if (! root) {
            return;
        }

        const input = root.querySelector('input[name="q"]');
        const panel = root.querySelector('[role="listbox"]');
        const emptyMessage = root.querySelector('[data-product-suggestion-empty]');
        const form = root.closest('form');
        const url = root.dataset.suggestionsUrl;

        if (! input || ! panel || ! form || ! url) {
            return;
        }

        let debounceTimer = null;
        let requestController = null;
        let options = [];
        let activeIndex = -1;

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

        const selectOption = (option) => {
            if (! option || ! option.dataset.url) {
                return;
            }

            window.location.assign(option.dataset.url);
        };

        const setActiveOption = (index) => {
            if (options.length === 0) {
                return;
            }

            activeIndex = Math.max(0, Math.min(index, options.length - 1));
            clearActiveOption();

            const option = options[activeIndex];
            option.classList.add('bg-sage-100', 'dark:bg-forest-800');
            option.scrollIntoView({ block: 'nearest' });
            input.setAttribute('aria-activedescendant', option.id);
        };

        const render = (items) => {
            panel.querySelectorAll('[data-product-suggestion-option]').forEach((option) => option.remove());

            options = items.map((item, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = `filter-product-suggestion-${index}`;
                option.dataset.productSuggestionOption = '';
                option.dataset.url = String(item.url ?? '');
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.className = 'block w-full rounded-lg px-2.5 py-1.5 text-left transition hover:bg-sage-100 focus-ring dark:hover:bg-forest-800';

                const title = document.createElement('span');
                title.className = 'block truncate text-xs leading-4 font-medium text-forest-800 dark:text-mint-100';
                title.textContent = String(item.title ?? '');
                option.appendChild(title);

                const metaParts = [item.category, item.price].filter((part) => part !== null && part !== undefined && part !== '');

                if (metaParts.length > 0) {
                    const meta = document.createElement('span');
                    meta.className = 'block truncate text-[0.7rem] leading-4 text-sage-700 dark:text-mint-100/70';
                    meta.textContent = metaParts.map((part) => String(part)).join(' · ');
                    option.appendChild(meta);
                }

                option.addEventListener('click', () => selectOption(option));
                panel.appendChild(option);

                return option;
            });

            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');

            if (emptyMessage) {
                emptyMessage.hidden = options.length > 0;
            }

            setOpen(true);
        };

        const loadSuggestions = async () => {
            const term = input.value.trim();

            if (term.length < 2) {
                setOpen(false);

                return;
            }

            if (requestController) {
                requestController.abort();
            }

            requestController = new AbortController();

            const params = new URLSearchParams(new FormData(form));
            params.set('q', term);
            params.delete('sort');

            if (root.dataset.category) {
                params.set('category', root.dataset.category);
            }

            try {
                const response = await fetch(`${url}?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                    signal: requestController.signal,
                });

                if (! response.ok) {
                    setOpen(false);

                    return;
                }

                const payload = await response.json();
                render(Array.isArray(payload.data) ? payload.data : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    setOpen(false);
                }
            }
        };

        const scheduleSuggestions = () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(loadSuggestions, 250);
        };

        input.addEventListener('input', scheduleSuggestions);
        input.addEventListener('focus', () => {
            if (input.value.trim().length >= 2) {
                scheduleSuggestions();
            }
        });
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
                const option = options[activeIndex];

                if (option) {
                    event.preventDefault();
                    selectOption(option);
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

        @if ($filters['min_price'] !== '' || $filters['max_price'] !== '')
            <a href="{{ $clearPriceRangeUrl }}" class="inline-flex items-center rounded-full px-2.5 py-1 font-medium text-forest-700 underline hover:text-forest-900 focus-ring dark:text-mint-100 dark:hover:text-white">{{ __('Clear price range') }}</a>
        @endif

        <a href="{{ $clearFiltersUrl }}" class="inline-flex items-center rounded-full px-2.5 py-1 font-medium text-forest-700 underline hover:text-forest-900 focus-ring dark:text-mint-100 dark:hover:text-white">{{ __('Clear all filters') }}</a>
    </div>
@endif
