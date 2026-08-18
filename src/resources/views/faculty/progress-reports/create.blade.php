<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Project monitoring</p>
            <h2 class="mt-1 text-xl font-black text-gray-950 dark:text-white">Progress report</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $topic->title }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-5 py-6 sm:px-6 lg:px-8">
        <a href="{{ route('research.show', $topic) }}#project-monitoring" class="inline-flex items-center gap-2 text-xs font-bold text-red-700 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200">
            <span aria-hidden="true">←</span>
            Back to project monitoring
        </a>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-l-4 border-red-600 px-5 py-5 sm:px-6">
                <h3 class="text-lg font-black text-gray-950 dark:text-white">Submit progress report</h3>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">BatStateU-REC-RES-02 · Revision 02. Save your work privately, review the PDF, then submit the exact prepared copy to the Research Head.</p>
            </div>

            <div class="border-t border-gray-200 dark:border-slate-800">
                <x-progress-report-form :topic="$topic" :prepared-report="$preparedReport" :narrative-report-draft="$narrativeReportDraft" standalone />
            </div>
        </div>
    </div>
</x-app-layout>
