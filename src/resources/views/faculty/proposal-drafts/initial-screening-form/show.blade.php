<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$paper['label']" subtitle="ATHENA prepares the official evaluator form without adding a faculty questionnaire or evaluator account.">
            <x-slot name="actions">
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $projectDetailsComplete ? 'Complete automatically' : 'Waiting for project details' }}</span>
                <a href="{{ route('signatories.edit', ['proposalDraft' => $proposalDraft, 'paper' => 'initial_screening_form']) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Choose signatories</a>
                <x-back-link fixed href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}#required-pdf-attachments">Back to proposal package</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div x-data="proposalStaticDocumentPreview()" class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The Project Title and Project Leader are the only values ATHENA places on this form.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <div class="proposal-preview-toolbar flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900">
            <button type="button" @click="previewPaneOpen ? closeProposalPreview() : showProposalPreview()" :aria-expanded="previewPaneOpen" aria-controls="initial-screening-preview-panel" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:text-white" x-text="previewPaneOpen ? 'Hide preview' : 'Show preview'"></button>
            <span class="text-xs text-slate-500 dark:text-slate-400">The preview can be moved, resized, or opened full screen.</span>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <h3 class="text-base font-black text-gray-900">No faculty screening answers required</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">ATHENA submits this blank form with the proposal package. The Research Head handles any evaluation outside the system and later uploads the completed document with the official decision.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">Edit shared details</a>
                </div>
            </div>

            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title ?: 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <x-proposal-document-preview
            panel-id="initial-screening-preview-panel"
            title="Initial Screening Form preview"
            description="The one-page preview reproduces BatStateU-FO-RES-03, Revision 02."
            frame-title="Initial Screening Form preview"
            :src="route('faculty.proposal-drafts.initial-screening-form.preview', $proposalDraft)"
        />
    </div>
</x-app-layout>
