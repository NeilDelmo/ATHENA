<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$preparedReport?->report_label ?? (request('report_type') === 'terminal' ? 'Terminal report' : 'Progress report')" :subtitle="$topic->title" />
    </x-slot>

    <div class="mx-auto max-w-[90rem] space-y-5 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white">{{ request('report_type') === 'terminal' ? 'Summarize the completed project' : 'Report project accomplishments' }}</h3>
                <p class="mt-2 text-base leading-7 text-gray-600 dark:text-slate-300">{{ request('report_type') === 'terminal' ? 'BatStateU-REC-RES-04' : 'BatStateU-REC-RES-02' }} · Revision 02. Fill and save your work privately. PDF preparation and submission open {{ request('report_type') === 'terminal' ? 'after the project ends' : 'after the reporting quarter ends' }}.</p>
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                <x-progress-report-form :topic="$topic" :prepared-report="$preparedReport" :narrative-report-draft="$narrativeReportDraft" :progress-defaults="$progressDefaults" :terminal-defaults="$terminalDefaults" :terminal-evidence="$terminalEvidence" :quarter-options="$quarterOptions" :selected-reporting-date="$selectedReportingDate" standalone />
            </div>
        </div>
    </div>
</x-app-layout>
