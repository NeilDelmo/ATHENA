<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$preparedReport?->report_label ?? (request('report_type') === 'terminal' ? 'Terminal report' : 'Progress report')" :subtitle="$topic->title" />
    </x-slot>

    <div class="mx-auto max-w-[90rem] space-y-5 py-6 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="px-5 pt-4 text-sm text-gray-600 dark:text-slate-300 sm:px-6">{{ request('report_type') === 'terminal' ? 'BatStateU-REC-RES-04' : 'BatStateU-REC-RES-02' }} · Revision 02</p>

            <div>
                <x-progress-report-form :topic="$topic" :prepared-report="$preparedReport" :narrative-report-draft="$narrativeReportDraft" :progress-defaults="$progressDefaults" :terminal-defaults="$terminalDefaults" :terminal-evidence="$terminalEvidence" :quarter-options="$quarterOptions" :selected-reporting-date="$selectedReportingDate" :draft-recovery-state="$draftRecoveryState" standalone />
            </div>
        </div>
    </div>
</x-app-layout>
