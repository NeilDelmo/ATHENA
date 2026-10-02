<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$report->report_label" :subtitle="$topic->title">
            <x-slot:actions>
                <a href="{{ route('topics.show', $topic) }}#project-monitoring" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2 text-base font-semibold text-brand hover:bg-red-50 focus-visible:ring-2 focus-visible:ring-brand dark:border-red-900 dark:bg-slate-900 dark:text-red-200">Back to monitoring</a>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-[90rem] space-y-5 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('success'))
            <p role="status" class="rounded-lg border border-red-200 bg-red-50 p-4 text-base text-brand dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ session('success') }}</p>
        @endif
        @if ($report->reporting_period_label)<p class="text-base font-semibold text-brand dark:text-red-300">{{ $report->reporting_period_label }} · Version {{ $report->version_number }}</p>@endif
        <x-narrative-report-history-item :report="$report" :project-title="$topic->title" :can-review="$canReview" :can-record-signed-copy="$canRecordSignedCopy" />
    </div>
</x-app-layout>
