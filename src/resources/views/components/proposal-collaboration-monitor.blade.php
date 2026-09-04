@props([
    'loadedVersion' => 0,
    'stateUrl',
    'reloadUrl',
    'historyUrl' => null,
    'label' => 'paper',
])

@php
    if (request()->boolean('revision_embed')) {
        $reloadUrl .= (str_contains($reloadUrl, '?') ? '&' : '?').'revision_embed=1';
    }
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
        <div data-proposal-save-confirmation role="status" class="border-l-4 border-red-600 bg-gray-50 p-4 text-sm text-gray-950 dark:bg-slate-800/70 dark:text-white">
            <p class="font-black">{{ session('success') }}</p>
            <p class="mt-1 text-xs leading-5">Saved just now. You can continue editing.</p>
        </div>
    @endif

    <div data-proposal-monitor-summary class="flex flex-col gap-2 border-l-2 border-red-600 bg-gray-50 px-4 py-3 text-xs text-gray-700 dark:bg-slate-800/70 dark:text-slate-200 sm:flex-row sm:items-center sm:justify-between">
        <p><span class="font-black text-gray-950 dark:text-white">Collaboration protection is on.</span> ATHENA checks for teammate saves while this page is open.</p>
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <span data-proposal-monitor-status aria-live="polite" class="inline-flex items-center gap-1.5 font-semibold text-gray-600 dark:text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-red-600" aria-hidden="true"></span>Checking teammate changes&hellip;</span>
            @if ($historyUrl)
                <a href="{{ $historyUrl }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-700 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300 dark:hover:bg-red-950/50 dark:hover:text-red-200" aria-label="Open recovery history" title="Recovery history">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </a>
            @endif
        </div>
    </div>

    <div data-proposal-stale-warning hidden role="alert" class="border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-950 dark:bg-red-950/40 dark:text-red-100 sm:p-5">
        <p class="font-black">A newer teammate change is available</p>
        <p data-proposal-stale-message class="mt-1 leading-6">This {{ $label }} changed after you opened it.</p>
        <p class="mt-1 text-xs leading-5">Your entries remain on this page. Copy anything you need before loading the latest saved work; ATHENA will block this older work from overwriting it.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a data-proposal-load-latest href="{{ $reloadUrl }}" class="inline-flex rounded-lg bg-gray-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-gray-950">Load latest saved work</a>
            @if ($historyUrl)
                <a href="{{ $historyUrl }}" class="inline-flex rounded-lg border border-red-300 bg-white px-4 py-2.5 text-xs font-bold text-red-950 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-transparent dark:text-red-100">Open recovery history</a>
            @endif
        </div>
    </div>
</section>
