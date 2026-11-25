@props([
    'compact' => false,
])

@php
    $currentLocale = app()->getLocale();
    $locales = config('app.supported_locales');
    $selectClasses = $compact
        ? 'rounded-full border border-sage-200 bg-cream-50/90 px-2 py-1 text-xs font-medium leading-none text-forest-800 shadow-sm focus-ring dark:border-white/15 dark:bg-forest-900/80 dark:text-mint-100'
        : 'rounded-full border border-sage-200 bg-cream-50/90 px-3 py-1.5 text-xs font-medium text-forest-800 shadow-sm focus-ring dark:border-white/15 dark:bg-forest-900/80 dark:text-mint-100';
@endphp

<form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-1.5">
    @csrf
    <label for="locale-switcher" class="sr-only">{{ __('Language') }}</label>
    <select
        id="locale-switcher"
        name="locale"
        onchange="this.form.submit()"
        class="{{ $selectClasses }}"
    >
        @foreach ($locales as $code => $details)
            <option value="{{ $code }}" title="{{ $details['label'] }}" @selected($code === $currentLocale)>{{ $compact ? strtoupper($code) : $details['label'] }}</option>
        @endforeach
    </select>
    <noscript>
        <x-button type="submit" variant="secondary" class="px-3 py-1.5 text-sm">{{ __('Change') }}</x-button>
    </noscript>
</form>
