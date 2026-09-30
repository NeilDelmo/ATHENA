<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" subtitle="The official seven-page Box 7a document is reproduced automatically using the shared project details.">
            <x-slot name="actions">
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $projectDetailsComplete ? 'Complete automatically' : 'Waiting for project details' }}</span>
                <a href="{{ route('signatories.edit', ['proposalDraft' => $proposalDraft, 'paper' => 'gad_checklist']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Choose signatories</a>
                <x-back-link fixed href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Back to proposal package</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div x-data="proposalStaticDocumentPreview()" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The Project Title and Project Leader are the only values ATHENA places on this checklist.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        @php
            $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
            $sampleAvailable = is_array($sampleDefinition)
                && isset($sampleDefinition['path'])
                && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
        @endphp

        <div class="proposal-preview-toolbar flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
            <button type="button" @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="gad-checklist-preview-panel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:text-white" x-text="previewPaneOpen ? 'Hide preview' : 'Show preview'"></button>
            <span class="text-xs text-slate-500 dark:text-slate-400">The preview can be moved, resized, or opened full screen.</span>
        </div>

        <section class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="max-w-3xl">
                    <p class="text-[10px] font-black uppercase tracking-wider text-blue-700">No form fields to answer</p>
                    <h3 class="mt-1 text-base font-black text-gray-900">Auto-filled from shared project information</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">ATHENA pulls the Project Title and Project Leader from Project Details. Every checklist mark, score, instruction, and signatory stays exactly as provided in the source document.</p>
                    <p class="mt-3 text-xs leading-5 text-gray-500">There are no answers to enter. Check the generated copy below; it is attached automatically when the package is turned in.</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($sampleAvailable)<a href="{{ route('proposal-samples.show', $paper['sample_slug']) }}" target="_blank" rel="noopener" class="inline-flex rounded-xl border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">View sample</a>@endif
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex rounded-xl border border-red-200 px-3 py-2 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Edit details</a>
                </div>
            </div>
            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title ?: 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader / Prepared by</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Checked and verified by</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ config('gad_checklist.verifier.name') }}</dd><dd class="text-xs text-gray-600">{{ config('gad_checklist.verifier.role') }}</dd></div>
            </dl>
        </section>

        <x-proposal-document-preview
            panel-id="gad-checklist-preview-panel"
            title="GAD Generic Checklist preview"
            description="The seven-page preview follows the supplied Box 7a document."
            frame-title="GAD Generic Checklist preview"
            :src="route('faculty.proposal-drafts.gad-checklist.preview', $proposalDraft)"
        />
    </div>
</x-app-layout>
