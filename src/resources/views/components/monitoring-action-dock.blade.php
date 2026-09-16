@props(['fixed' => false])

<div
    data-monitoring-action-dock
    @if ($fixed) data-monitoring-action-dock-fixed @endif
    {{ $attributes->class([
        'flex flex-col gap-2 print:hidden sm:flex-row sm:flex-wrap sm:items-center sm:justify-end',
        'fixed inset-x-4 bottom-4 z-40 max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white/95 p-3 shadow-2xl ring-1 ring-black/5 backdrop-blur sm:inset-x-auto sm:right-6 sm:max-w-[calc(100vw-3rem)] dark:border-slate-700 dark:bg-slate-900/95 dark:ring-white/10' => $fixed,
        'justify-end' => ! $fixed,
        '[&>a]:w-full [&>button]:w-full [&>form]:w-full [&>form>button]:w-full sm:[&>a]:w-auto sm:[&>button]:w-auto sm:[&>form]:w-auto' => true,
    ]) }}
>
    {{ $slot }}
</div>
