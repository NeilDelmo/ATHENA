@props(['label', 'value', 'description', 'icon'])

<div {{ $attributes->class(['min-w-0 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900']) }}>
    <dt class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-600 dark:text-slate-300">
        {{ $label }}
        <svg class="h-4 w-4 shrink-0 text-[#7A0019] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" /></svg>
    </dt>
    <dd class="mt-3 text-3xl font-bold tracking-tight tabular-nums text-slate-950 dark:text-white">{{ $value }}</dd>
    <dd class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</dd>
</div>
