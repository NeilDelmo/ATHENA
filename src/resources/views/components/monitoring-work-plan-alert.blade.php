@props(['activities' => []])

@if ($activities !== [])
    <section data-monitoring-work-plan-alert role="alert" {{ $attributes->class(['rounded-xl border border-slate-200 bg-white px-5 py-4 text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:px-6']) }}>
        <h3 class="text-base font-semibold">Work Plan targets need attention</h3>
        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ count($activities) }} {{ Str::plural('activity', count($activities)) }} {{ count($activities) === 1 ? 'is' : 'are' }} past the approved target date. Record progress and explain delays in the Monitoring Tool.</p>
        <details class="monitoring-disclosure group mt-2">
            <summary>
                <span>View affected objectives and activities</span>
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14" /><path class="group-open:hidden" stroke-linecap="round" d="M12 5v14" /></svg>
            </summary>
            <ul class="mt-2 divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800">
                @foreach ($activities as $activity)
                    <li data-overdue-work-plan-index="{{ $activity['source_work_plan_index'] }}" class="grid min-w-0 gap-1 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:gap-x-6">
                        <p class="break-words text-sm font-semibold">{{ $activity['objective'] }}</p>
                        <p class="break-words text-sm leading-6 text-slate-600 dark:text-slate-300 sm:col-start-1">{{ $activity['activity'] }}</p>
                        <p class="text-xs leading-6 text-slate-600 dark:text-slate-300 sm:col-start-2 sm:row-span-2 sm:row-start-1 sm:self-center">Due {{ \Carbon\CarbonImmutable::parse($activity['target_completion_date'])->format('M j, Y') }} · {{ $activity['progress_recorded'] ? $activity['completion_percentage'].'% complete' : 'No submitted progress yet' }}</p>
                    </li>
                @endforeach
            </ul>
        </details>
    </section>
@endif
