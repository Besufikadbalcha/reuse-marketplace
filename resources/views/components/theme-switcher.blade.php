@php
    $nextTheme = $theme === 'dark' ? 'light' : 'dark';
    $themeLabel = $nextTheme === 'dark' ? __('Switch to dark mode') : __('Switch to light mode');
@endphp

<form method="POST" action="{{ route('theme.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="theme" value="{{ $nextTheme }}">

    <x-button type="submit" variant="ghost" class="px-3 py-1.5 text-sm" :aria-label="$themeLabel" :title="$themeLabel">
        @if ($nextTheme === 'dark')
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
            </svg>
        @else
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="4"/>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42"/>
            </svg>
        @endif
        <span class="sr-only">{{ $themeLabel }}</span>
    </x-button>
</form>
