@props(['fixed' => false])

@php($showsDraftExitStatus = $fixed && $attributes->has('data-paper-cancel-exit'))

<a
    data-back-link
    @if ($fixed) data-fixed-back-link @endif
    {{ $attributes->class([
        'fixed bottom-4 right-4 z-40 w-auto max-w-[calc(100vw-2rem)] shadow-xl ring-1 ring-black/10 sm:bottom-6 sm:right-6 print:hidden' => $fixed,
        'min-h-12 border-red-200 bg-white text-red-900 hover:border-red-400 hover:bg-red-50 hover:text-red-950 aria-disabled:cursor-wait aria-disabled:border-red-300 aria-disabled:bg-red-50 aria-disabled:text-red-900 dark:border-red-900 dark:bg-slate-900 dark:text-red-100 dark:hover:border-red-700 dark:hover:bg-red-950 dark:hover:text-red-100 dark:aria-disabled:border-red-800 dark:aria-disabled:bg-red-950 dark:aria-disabled:text-red-100' => $showsDraftExitStatus,
        'border-gray-300 bg-white text-gray-700 hover:border-red-300 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-800 dark:hover:bg-red-950/40 dark:hover:text-red-300' => ! $showsDraftExitStatus,
    ])->merge([
        'class' => 'group inline-flex items-center justify-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-black shadow-sm transition duration-150 motion-safe:hover:-translate-y-px hover:shadow focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-950',
    ]) }}
>
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 transition group-hover:bg-red-100 group-hover:text-red-700 dark:bg-red-950/60 dark:text-red-300 dark:group-hover:bg-red-950" aria-hidden="true">
        <svg @class(['h-4 w-4', 'group-aria-disabled:hidden motion-safe:transition-transform motion-safe:group-hover:-translate-x-0.5' => $showsDraftExitStatus]) fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
        @if ($showsDraftExitStatus)
            <svg class="hidden h-4 w-4 group-aria-disabled:block motion-safe:animate-spin" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" data-paper-exit-spinner>
                <path stroke-linecap="round" d="M12 3a9 9 0 1 1-9 9" />
            </svg>
        @endif
    </span>
    @if ($showsDraftExitStatus)
        <span role="status" class="min-w-0 [overflow-wrap:anywhere] empty:sr-only" data-paper-exit-status></span>
    @endif
    <span @class(['group-aria-disabled:hidden' => $showsDraftExitStatus])>{{ $slot }}</span>
</a>
