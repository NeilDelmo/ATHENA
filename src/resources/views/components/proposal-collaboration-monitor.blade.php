@props([
    'loadedVersion' => 0,
    'stateUrl',
    'reloadUrl',
    'label' => 'paper',
    'reportRecovery' => false,
    'stateScope' => null,
])

@php
    if (request()->boolean('revision_embed')) {
        $reloadUrl .= (str_contains($reloadUrl, '?') ? '&' : '?').'revision_embed=1';
    }
    $showSaveConfirmation = filled(session('success'))
        && \Illuminate\Support\Str::contains((string) session('success'), 'saved');
@endphp

<section
    @if ($reportRecovery) data-report-version-monitor @else data-proposal-version-monitor @endif
    data-loaded-version="{{ $loadedVersion }}"
    data-state-url="{{ $stateUrl }}"
    @if ($stateScope) data-state-scope="{{ $stateScope }}" @endif
    data-document-label="{{ $label }}"
    class="contents"
    aria-label="Saved version"
>
    @if ($showSaveConfirmation)
        <div data-proposal-save-confirmation role="status" class="border-l-4 border-red-600 bg-gray-50 p-4 text-sm text-gray-950 dark:bg-slate-800/70 dark:text-white">
            <p class="font-black">{{ session('success') }}</p>
            <p class="mt-1 text-xs leading-5">Saved just now. You can continue editing.</p>
        </div>
    @endif

    <div data-proposal-stale-warning hidden role="alert" class="border-l-4 border-red-600 bg-red-50 p-4 text-sm text-red-950 dark:bg-red-950/40 dark:text-red-100 sm:p-5">
        <p class="font-black">The saved version has changed</p>
        <p data-proposal-stale-message class="mt-1 leading-6">This {{ $label }} changed after you opened it.</p>
        <p class="mt-1 text-xs leading-5">Copy any unsaved text you want to keep before loading the latest saved work. Image and attachment files must be selected again.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <a data-proposal-load-latest href="{{ $reloadUrl }}" class="inline-flex rounded-lg bg-gray-950 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:bg-white dark:text-gray-950">Load latest saved work</a>
        </div>
    </div>
</section>
