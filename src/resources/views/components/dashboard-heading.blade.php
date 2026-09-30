@props(['eyebrow', 'title', 'description'])

<div data-workspace-header-banner {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="min-w-0">
        <p class="text-[11px] font-semibold text-[#7A0019] dark:text-red-300">{{ $eyebrow }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $title }}</h1>
        <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $description }}</p>
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
