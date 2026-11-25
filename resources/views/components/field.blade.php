@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'compact' => false,
])

@php
    $inputId = $attributes->get('id') ?? $name;
    $hasError = $errors->has($name);
    $hasIcon = isset($icon);
    $errorId = $inputId.'-error';

    $inputClasses = $compact
        ? 'block w-full rounded-xl border bg-white/85 px-3 py-2 text-sm text-forest-900 shadow-sm placeholder:text-sage-500 focus:ring-2 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:ring-mint-300/25'
        : 'block w-full rounded-2xl border bg-white/85 py-3.5 text-base text-forest-900 shadow-sm placeholder:text-sage-500 focus:ring-4 focus:ring-forest-600/15 dark:bg-white/5 dark:text-white dark:placeholder:text-sage-400 dark:focus:ring-mint-300/25';
    $inputClasses .= $hasIcon
        ? ($compact ? ' pl-9 pr-3' : ' pl-12 pr-4')
        : ($compact ? '' : ' px-4');
    $inputClasses .= $hasError
        ? ' border-red-400 focus:border-red-500 dark:border-red-400'
        : ' border-sage-200 focus:border-forest-600 dark:border-white/15 dark:focus:border-mint-300';

    $inputAttributes = $attributes->merge([
        'id' => $inputId,
        'name' => $name,
        'type' => $type,
        'value' => old($name, $value),
        'class' => $inputClasses,
    ]);
@endphp

<div class="{{ $compact ? 'space-y-1' : 'space-y-2' }}">
    @if ($label)
        <label for="{{ $inputId }}" class="block text-xs font-medium text-forest-800 dark:text-mint-100">
            {{ $label }}
        </label>
    @endif

    @if ($hasIcon)
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sage-600 dark:text-sage-400">
                {{ $icon }}
            </span>

            <input {{ $inputAttributes }} @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
        </div>
    @else
        <input {{ $inputAttributes }} @if ($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif>
    @endif

    @error($name)
        <p id="{{ $errorId }}" class="text-xs text-red-700 dark:text-red-300">{{ $message }}</p>
    @enderror
</div>
