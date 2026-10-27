@props(['url', 'blockers' => [], 'readyToPrepare' => false, 'readyToSubmit' => false])

@php
    $hasFacultyBlocker = collect($blockers)->contains('owner', 'faculty');
    $status = $readyToSubmit ? 'ready' : ($readyToPrepare ? 'prepare' : ($hasFacultyBlocker || $blockers === [] ? 'incomplete' : 'office'));
    $labels = ['incomplete' => 'Complete these fields', 'office' => 'Awaiting Research Office setup', 'prepare' => 'Content complete · PDFs need preparation', 'ready' => 'Ready to turn in'];
@endphp

<section data-proposal-readiness data-proposal-readiness-url="{{ $url }}" data-proposal-readiness-state="{{ json_encode(['status' => $status, 'blockers' => $blockers], JSON_THROW_ON_ERROR) }}" aria-live="polite" class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-700 dark:bg-slate-900">
    <p data-proposal-readiness-label class="font-semibold text-slate-950 dark:text-white">{{ $labels[$status] }}</p>
    <ul data-proposal-readiness-issues class="mt-2 max-h-48 space-y-1 overflow-y-auto text-sm leading-6 text-slate-600 dark:text-slate-300" aria-label="Remaining submission requirements" @if ($blockers === []) hidden @endif>
        @foreach ($blockers as $blocker)
            <li>{{ $blocker['owner'] === 'research_office' ? 'Research Office action required: ' : '' }}{{ $blocker['message'] }}</li>
        @endforeach
    </ul>
</section>
