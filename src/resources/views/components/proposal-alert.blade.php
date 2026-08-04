@props(['type' => 'success'])

@php
    $styles = match ($type) {
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        'warning' => 'border-red-600 bg-red-50 text-red-950 dark:bg-red-950/40 dark:text-red-100',
        default => 'border-red-600 bg-gray-50 text-gray-950 dark:bg-slate-800/70 dark:text-white',
    };

    $icon = match ($type) {
        'error' => 'error',
        'warning' => 'warning',
        default => 'success',
    };
@endphp

<div
    data-proposal-alert
    data-alert-icon="{{ $icon }}"
    role="{{ $type === 'error' ? 'alert' : 'status' }}"
    {{ $attributes->class(['border-l-4 px-4 py-3 text-sm', $styles, 'font-semibold' => $type !== 'error']) }}
>
    {{ $slot }}
</div>
