<div
    data-paper-submit-status
    hidden
    role="status"
    aria-live="assertive"
    aria-atomic="true"
    {{ $attributes->merge(['class' => 'pointer-events-none fixed inset-x-0 top-0 z-[100] px-3 pt-3 sm:px-6']) }}
>
    <div class="mx-auto max-w-xl overflow-hidden rounded-2xl border border-red-200 bg-white shadow-2xl ring-1 ring-black/5">
        <div class="flex items-center gap-3 px-4 py-3">
            <svg aria-hidden="true" class="h-6 w-6 shrink-0 animate-spin text-red-600" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"></path>
            </svg>
            <div>
                <p data-paper-submit-message class="text-sm font-black text-gray-900">Saving changes&hellip;</p>
                <p class="mt-0.5 text-xs font-semibold text-gray-500">Please keep this page open while ATHENA updates the paper.</p>
            </div>
        </div>
        <div class="h-1.5 overflow-hidden bg-red-100">
            <div class="h-full w-2/3 animate-pulse rounded-r-full bg-red-600"></div>
        </div>
    </div>
</div>
