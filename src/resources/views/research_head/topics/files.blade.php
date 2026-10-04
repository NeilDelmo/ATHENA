<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Submitted documents" :subtitle="$topic->status === 'revision_requested' ? 'The revision request has been sent. Submitted papers and comments remain available for reference.' : 'Review the submitted papers and save comments where changes are needed.'">
            <x-slot name="actions">
                <x-back-link fixed href="{{ route('topics.show', $topic) }}#proposal-review">Back to submitted proposal</x-back-link>
                <span class="inline-flex w-fit rounded-full border border-gray-300 bg-white px-3.5 py-2 text-sm font-bold text-gray-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                {{ $latestVersion ? 'Version '.$latestVersion->version_number : 'No submitted version' }}
                </span>
            </x-slot>
        </x-page-header>

        <div class="mt-4" data-visible-proposal-workflow>
            <x-proposal-workflow :topic="$topic" :version="$latestVersion" :reviews="$topic->reviews" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        <section data-review-project-folder class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Project files</h3>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Open the folder for submitted papers, review forms, and signed copies.</p>
            </div>
            <x-project-document-drawer :topic="$topic" :library="$projectDocumentLibrary" :floating="false" />
        </section>

        <x-research-head-file-workspace :topic="$topic" :workspace="$workspace" />
    </div>
</x-app-layout>
