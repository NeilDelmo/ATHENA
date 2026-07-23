@props([
    'loadedVersion' => 0,
    'stateUrl',
    'reloadUrl',
    'historyUrl' => null,
    'label' => 'paper',
])

@php
    $showSaveConfirmation = filled(session('success'))
        && \Illuminate\Support\Str::contains((string) session('success'), 'saved');
@endphp

<section
    data-proposal-version-monitor
    data-loaded-version="{{ $loadedVersion }}"
    data-state-url="{{ $stateUrl }}"
    data-document-label="{{ $label }}"
    class="space-y-3"
    aria-label="Collaboration status"
>
    @if ($showSaveConfirmation)
        <div data-proposal-save-confirmation role="status" class="rounded-2xl border border-green-300 bg-green-50 p-4 text-sm text-green-950 shadow-sm dark:border-green-800 dark:bg-green-950/50 dark:text-green-100">
            <p class="font-black">{{ session('success') }}</p>
            <p class="mt-1 text-xs leading-5">Saved as version {{ $loadedVersion }}. You stayed on this page and can continue editing.</p>
        </div>
    @endif

    <div class="flex flex-col gap-2 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-200 sm:flex-row sm:items-center sm:justify-between">
        <p><span class="font-black">Collaboration protection is on.</span> You are editing version {{ $loadedVersion }}; ATHENA checks for teammate saves while this page is open.</p>
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <span data-proposal-monitor-status aria-live="polite" class="font-semibold text-blue-700 dark:text-blue-300">Checking teammate changes&hellip;</span>
            @if ($historyUrl)
                <a href="{{ $historyUrl }}" class="font-black text-blue-800 underline decoration-blue-300 underline-offset-2 hover:text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-700 dark:text-blue-200 dark:hover:text-white">View history</a>
            @endif
        </div>
    </div>

    <div data-proposal-stale-warning hidden role="alert" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 shadow-sm dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100 sm:p-5">
        <p class="font-black">A newer teammate change is available</p>
        <p data-proposal-stale-message class="mt-1 leading-6">This {{ $label }} changed after you opened it.</p>
        <p class="mt-1 text-xs leading-5">Your entries remain on this page. Copy anything you need before loading the latest saved version; ATHENA will block this older version from overwriting it.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a data-proposal-load-latest href="{{ $reloadUrl }}" class="inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white hover:bg-amber-950 focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Load latest version</a>
            @if ($historyUrl)
                <a href="{{ $historyUrl }}" class="inline-flex rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-xs font-bold text-amber-950 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-800 focus:ring-offset-2 dark:bg-transparent dark:text-amber-100">Compare version history</a>
            @endif
        </div>
    </div>
</section>
