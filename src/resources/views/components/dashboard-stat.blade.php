@props(['label', 'value', 'description', 'icon', 'href' => null, 'priority' => false, 'tone' => null])

@php
    $facultyTone = $tone === 'brand';
    $facultyPriority = $facultyTone && $priority;
@endphp

<div {{ $attributes->class(['relative min-w-0 rounded-xl border p-4 sm:p-5', 'border-brand bg-brand' => $facultyPriority, 'border-stone-200 bg-white dark:border-stone-700 dark:bg-stone-900' => $facultyTone && ! $priority, 'border-rose-200 bg-brand-wash dark:border-rose-900 dark:bg-rose-950/30' => ! $facultyTone && $priority, 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $facultyTone && ! $priority, 'hover:border-brand focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2 dark:focus-within:ring-offset-slate-950' => $href, 'dark:hover:border-red-400 dark:focus-within:ring-red-400' => $href && $facultyTone]) }}>
    <dt @class(['flex items-start justify-between font-medium', 'gap-2 text-[13px] sm:gap-3 sm:text-sm' => $facultyTone, 'gap-3 text-sm text-slate-600 dark:text-slate-300' => ! $facultyTone, 'text-white' => $facultyPriority, 'text-stone-700 dark:text-stone-300' => $facultyTone && ! $priority])>
        @if ($href)
            <a @if (! str_starts_with($href, '#')) wire:navigate @endif href="{{ $href }}" class="after:absolute after:inset-0 after:rounded-xl focus:outline-none">{{ $label }}<span class="sr-only">: {{ $value }}</span></a>
        @else
            {{ $label }}
        @endif
        <svg @class(['shrink-0', 'h-6 w-6 rounded-lg p-1 sm:h-8 sm:w-8 sm:p-1.5' => $facultyTone, 'bg-white text-brand' => $facultyPriority, 'bg-brand text-white dark:bg-red-800' => $facultyTone && ! $priority, 'h-4 w-4 text-[#7A0019] dark:text-red-300' => ! $facultyTone]) fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
    </dt>
    <dd @class(['mt-3 text-3xl font-bold tracking-tight tabular-nums', 'text-white' => $facultyPriority, 'text-brand dark:text-red-300' => $facultyTone && ! $priority, 'text-brand dark:text-rose-300' => ! $facultyTone && $priority, 'text-slate-950 dark:text-white' => ! $facultyTone && ! $priority])>{{ $value }}</dd>
    <dd @class(['mt-2 text-sm leading-5', 'text-white' => $facultyPriority, 'text-stone-600 dark:text-stone-300' => $facultyTone && ! $priority, 'text-slate-600 dark:text-slate-400' => ! $facultyTone])>{{ $description }}</dd>
</div>
