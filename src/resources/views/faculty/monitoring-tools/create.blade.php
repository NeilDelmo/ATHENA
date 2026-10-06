<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Quarterly monitoring report" :subtitle="$topic->title">
            <x-slot name="actions">
            @unless ($selectedReportingDate || $preparedReport)
                <x-back-link fixed href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
            @endunless
            </x-slot>
        </x-page-header>
    </x-slot>

    <div data-monitoring-workspace class="w-full space-y-5">
        <x-monitoring-work-plan-alert :activities="$overdueWorkPlanActivities" />
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-lg font-black text-gray-950 dark:text-white">{{ $revisionReport ? 'Correct '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool' }}</h3>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600 dark:text-slate-300">Record your activities, accomplishments, and spending for this quarter. Your draft saves automatically and you can preview it now. Official PDF preparation and submission open after the quarter ends.</p>
                @if ($revisionReport?->research_head_remarks)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-800 dark:border-red-950 dark:bg-red-950/40 dark:text-red-100">
                        <p class="font-black">Research Head revision remarks</p>
                        <p class="mt-1">{{ $revisionReport->research_head_remarks }}</p>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                @if ($selectedReportingDate || $preparedReport)
                <x-monitoring-tool-form
                    :quarter-options="$quarterOptions"
                    :selected-reporting-date="$selectedReportingDate"
                    :topic="$topic"
                    :prepared-report="$preparedReport"
                    :revision-report="$revisionReport"
                    :monitoring-draft="$monitoringDraft"
                    :approved-work-plan-by-period="$approvedWorkPlanByPeriod"
                    :approved-work-plan-available="$approvedWorkPlanAvailable"
                    :selected-period-key="$selectedPeriodKey"
                    :selected-report-number="$selectedReportNumber"
                    :monitoring-report-count="$monitoringReportCount"
                    :initial-work-plan-rows="$initialWorkPlanRows"
                    :previous-progress-by-period="$previousProgressByPeriod"
                    standalone
                />
                @else
                    <p class="p-5 text-sm text-gray-600 dark:text-slate-300">There is no new quarter open for reporting. Check the quarter list for submitted reports or wait until the approved project period starts.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
