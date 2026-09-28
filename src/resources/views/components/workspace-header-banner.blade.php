@props([
    'eyebrow',
    'title',
    'description',
])

<div
    data-workspace-header-banner
    {{ $attributes->class(['flex flex-col gap-4 border-l-4 border-[#800000] bg-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between']) }}
>
    <div>
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#800000]">{{ $eyebrow }}</p>
        <h2 class="mt-1 text-xl font-bold tracking-tight text-slate-950 sm:text-2xl">{{ $title }}</h2>
        <p class="mt-1 max-w-3xl text-sm text-slate-500">{{ $description }}</p>
    </div>

    @isset($actions)
        {{ $actions }}
    @endisset
</div>
