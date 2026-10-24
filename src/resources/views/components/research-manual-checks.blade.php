@props(['manual'])
@php
    $state = $manual->guidance_check_status;
    $results = $manual->guidance_check_results ?? [];
    $checks = $results['checks'] ?? [];
    $finished = count(array_filter($checks, fn (array $check): bool => $check['status'] !== 'pending'));
    $labels = ['pending' => 'Waiting', 'checked' => 'Edition, pages and citation format checked', 'invalid_references' => 'Reference check needs attention', 'no_evidence' => 'No matching evidence found', 'unavailable' => 'Answer service unavailable'];
@endphp
<section data-manual-checks data-check-status="{{ $state }}" data-status-url="{{ route('research-manuals.show', $manual) }}" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 sm:p-6" aria-labelledby="manual-checks-heading">
    <div>
        <h2 id="manual-checks-heading" class="text-lg font-black tracking-tight text-slate-950 dark:text-white">Automated guidance checks</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The system chooses sample questions from known guidance topics and this edition's sections. No questions or expected answers are needed from you.</p>
        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">These checks verify retrieved edition, indexed page ranges and citation format in sample answers. They do not certify that every answer is correct or that every claim is supported. Activation remains your decision.</p>
    </div>
    <p role="status" aria-live="polite" class="text-sm font-bold text-slate-800 dark:text-slate-200">
        @if ($state === 'pending')
            Checks queued automatically. Results will appear here as the system works.
        @elseif ($state === 'running')
            Checks running — {{ $finished }} of {{ count($checks) }} sample questions processed.
        @elseif ($state === 'completed')
            Checks completed · {{ $manual->guidance_check_completed_at?->format('M j, Y g:i A') }}. Review any missing evidence or unavailable answers below.
        @elseif ($state === 'failed')
            {{ $results['message'] ?? 'Automated checking stopped. The system could not complete this edition’s checks.' }}
        @else
            No automated run is recorded for this previously activated edition.
        @endif
    </p>
    <p data-manual-checks-poll-error hidden role="status" class="text-sm text-amber-800 dark:text-amber-200">Results are temporarily unavailable. Reconnecting automatically…</p>
    @if (($results['unindexed_pages'] ?? []) !== [])
        <p class="rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">Unreadable pages excluded from guidance: {{ implode(', ', $results['unindexed_pages']) }}. Sample checks cannot cover their missing text.</p>
    @endif
    @if ($checks !== [])
        <ol class="divide-y divide-slate-200 dark:divide-slate-700">
            @foreach ($checks as $check)
                <li class="py-4 first:pt-0 last:pb-0">
                    <details data-check-number="{{ $loop->index }}">
                        <summary class="disclosure-summary text-sm font-bold leading-6 text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-white">
                            <span class="min-w-0">
                                {{ $check['question'] }}
                                <span class="block pl-4 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $labels[$check['status']] ?? 'Check unavailable' }}</span>
                            </span>
                        </summary>
                        @if (! empty($check['reply']))
                            <p class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-slate-700 dark:text-slate-200">{{ $check['reply'] }}</p>
                        @endif
                        @if (! empty($check['message']))
                            <p class="mt-3 text-sm leading-6 text-amber-900 dark:text-amber-200">{{ $check['message'] }}</p>
                        @endif
                        @if (! empty($check['sources']))
                            <ul class="mt-3 space-y-2 text-sm" aria-label="Candidate edition evidence">
                                @foreach ($check['sources'] as $source)
                                    <li><a href="{{ $source['url'] }}" class="inline-flex min-h-11 items-center text-red-700 underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-300">[{{ $source['reference'] }}] {{ $source['title'] }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </details>
                </li>
            @endforeach
        </ol>
    @endif
</section>
