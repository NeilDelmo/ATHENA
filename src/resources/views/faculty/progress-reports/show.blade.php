<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$topic->title" class="project-page-header report-page-header mx-auto max-w-6xl" />
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-5" data-report-reader-page>
        @if (session('success'))
            <p role="status" class="rounded-lg border border-red-200 bg-red-50 p-4 text-base text-brand dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ session('success') }}</p>
        @endif
        <x-narrative-report-history-item :report="$report" :can-review="$canReview" :can-secretary-review="$canSecretaryReview" :can-record-signed-copy="$canRecordSignedCopy" />
        <x-back-link
            fixed
            x-show="!$store.researchAssistant.drawerOpen && !$store.researchAssistant.workspaceOpen"
            x-cloak
            href="{{ route('topics.show', $topic) }}#narrative-report-{{ $report->id }}"
        >Back to monitoring</x-back-link>
    </div>
</x-app-layout>
