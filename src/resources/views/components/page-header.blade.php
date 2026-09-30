@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="min-w-0">
        <h1 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ $title }}</h1>

        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</div>
