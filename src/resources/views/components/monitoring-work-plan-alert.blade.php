@props(['activities' => []])

@if ($activities !== [])
    <section data-monitoring-work-plan-alert role="alert" {{ $attributes->class(['rounded-xl border border-amber-300 border-l-4 bg-amber-50 p-5 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100']) }}>
        <h3 class="text-lg font-bold">Work Plan targets need attention</h3>
        <p class="mt-2 text-sm leading-6">{{ count($activities) }} {{ Str::plural('activity', count($activities)) }} {{ count($activities) === 1 ? 'is' : 'are' }} past the approved target date without full completion recorded in submitted monitoring. Review the delay and record your accomplishments and findings in the Monitoring Tool.</p>
        <details class="mt-3">
            <summary class="w-fit cursor-pointer rounded-lg py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-700">View affected objectives and activities</summary>
            <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach ($activities as $activity)
                    <li data-overdue-work-plan-index="{{ $activity['source_work_plan_index'] }}" class="min-w-0 rounded-lg border border-amber-200 bg-white/70 p-4 dark:border-amber-900 dark:bg-slate-900/70">
                        <p class="break-words text-sm font-bold">{{ $activity['objective'] }}</p>
                        <p class="mt-1 break-words text-sm leading-6">{{ $activity['activity'] }}</p>
                        <p class="mt-2 text-xs font-semibold">Due {{ \Carbon\CarbonImmutable::parse($activity['target_completion_date'])->format('M j, Y') }} · {{ $activity['progress_recorded'] ? $activity['completion_percentage'].'% complete' : 'No submitted progress yet' }}</p>
                    </li>
                @endforeach
            </ul>
        </details>
    </section>
@endif
