@props(['label', 'value', 'description', 'icon', 'href' => null, 'priority' => false])

<div {{ $attributes->class(['relative min-w-0 rounded-xl border p-4 sm:p-5', 'border-rose-200 bg-brand-wash dark:border-rose-900 dark:bg-rose-950/30' => $priority, 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' => ! $priority, 'hover:border-brand focus-within:ring-2 focus-within:ring-brand focus-within:ring-offset-2 dark:focus-within:ring-offset-slate-950' => $href]) }}>
    <dt class="flex items-start justify-between gap-3 text-sm font-medium text-slate-600 dark:text-slate-300">
        @if ($href)
            <a @if (! str_starts_with($href, '#')) wire:navigate @endif href="{{ $href }}" class="after:absolute after:inset-0 after:rounded-xl focus:outline-none">{{ $label }}<span class="sr-only">: {{ $value }}</span></a>
        @else
            {{ $label }}
        @endif
        <svg class="h-4 w-4 shrink-0 text-[#7A0019] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
    </dt>
    <dd @class(['mt-3 text-3xl font-bold tracking-tight tabular-nums', 'text-brand dark:text-rose-300' => $priority, 'text-slate-950 dark:text-white' => ! $priority])>{{ $value }}</dd>
    <dd class="mt-2 text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $description }}</dd>
</div>
