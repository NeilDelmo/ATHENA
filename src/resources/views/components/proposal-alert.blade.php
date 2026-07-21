@props(['type' => 'success'])

@php
    $styles = match ($type) {
        'error' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        default => 'border-green-200 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200',
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
    {{ $attributes->class(['rounded-2xl border px-4 py-3 text-sm', $styles, 'font-semibold' => $type !== 'error']) }}
>
    {{ $slot }}
</div>
