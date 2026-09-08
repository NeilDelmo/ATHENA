<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Project monitoring</p>
                <h2 class="mt-1 text-xl font-black text-gray-950 dark:text-white">Quarterly monitoring report</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $topic->title }}</p>
            </div>
            <x-back-link fixed data-paper-cancel-exit href="{{ route('research.show', $topic) }}#project-monitoring">Exit monitoring</x-back-link>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-5 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-lg font-black text-gray-950 dark:text-white">{{ $revisionReport ? 'Revise '.$revisionReport->quarter_label.' Monitoring Tool' : 'Submit monitoring tool' }}</h3>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">1. Complete the quarter’s report. 2. Prepare its PDF. 3. Review and submit to the Research Head. Your entries save as a private draft.</p>
                @if ($revisionReport?->research_head_remarks)
                    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-800 dark:border-red-950 dark:bg-red-950/40 dark:text-red-100">
                        <p class="font-black">Research Head revision remarks</p>
                        <p class="mt-1">{{ $revisionReport->research_head_remarks }}</p>
                    </div>
                @endif
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                @if ($selectedReportingDate || $preparedReport)
                <x-monitoring-tool-form :quarter-options="$quarterOptions" :selected-reporting-date="$selectedReportingDate" :topic="$topic" :prepared-report="$preparedReport" :revision-report="$revisionReport" :monitoring-draft="$monitoringDraft" standalone />
                @else
                    <p class="p-5 text-sm text-gray-600 dark:text-slate-300">There is no new quarter open for reporting. Check the quarter list for submitted reports or wait until the approved project period starts.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
