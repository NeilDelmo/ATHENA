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

        <div class="mt-4" x-data="{ workflowOpen: true }" x-init="(() => { try { workflowOpen = sessionStorage.getItem('review-workflow-{{ $topic->id }}') !== 'hidden' } catch (error) {} })()">
            <button type="button" data-review-workflow-toggle @click="workflowOpen = !workflowOpen; try { sessionStorage.setItem('review-workflow-{{ $topic->id }}', workflowOpen ? 'shown' : 'hidden') } catch (error) {}" :aria-expanded="workflowOpen" aria-controls="review-workflow" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">
                <svg class="h-4 w-4" :class="workflowOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                <span x-text="workflowOpen ? 'Hide workflow' : 'Show workflow'">Hide workflow</span>
            </button>
            <div id="review-workflow" x-show="workflowOpen" x-cloak>
                <x-proposal-workflow :topic="$topic" :version="$latestVersion" />
            </div>
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
