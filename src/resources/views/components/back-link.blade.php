<a
    data-back-link
    {{ $attributes->merge([
        'class' => 'group inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm font-black text-gray-700 shadow-sm transition duration-150 hover:-translate-y-px hover:border-red-300 hover:bg-red-50 hover:text-red-700 hover:shadow focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-800 dark:hover:bg-red-950/40 dark:hover:text-red-300 dark:focus:ring-offset-slate-950',
    ]) }}
>
    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600 transition group-hover:bg-red-100 group-hover:text-red-700 dark:bg-red-950/60 dark:text-red-300 dark:group-hover:bg-red-950" aria-hidden="true">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
        </svg>
    </span>
    <span>{{ $slot }}</span>
</a>
