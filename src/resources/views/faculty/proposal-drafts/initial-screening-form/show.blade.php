<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $projectDetailsComplete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $projectDetailsComplete ? 'Complete automatically' : 'Waiting for project details' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">ATHENA prepares the official evaluator form without adding a faculty questionnaire or evaluator account.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The Project Title and Project Leader are the only values ATHENA places on this form.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-3xl">
                    <h3 class="text-base font-black text-gray-900">No faculty screening answers required</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-600">The Research/RDES Head and assigned central co-evaluator complete the submitted-document checklist, rubric, recommendation, narrative evaluation, names, dates, and signatures after the proposal is submitted. Those fields remain blank here.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2">Edit shared details</a>
                    <a href="{{ route('faculty.proposal-drafts.initial-screening-form.preview', $proposalDraft) }}" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-xl border border-gray-900 px-4 py-2.5 text-xs font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Open full preview</a>
                    <a href="{{ route('faculty.proposal-drafts.initial-screening-form.download', $proposalDraft) }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Download Word file</a>
                </div>
            </div>

            <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-5 sm:grid-cols-2">
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Title</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_title ?: 'Not provided' }}</dd></div>
                <div><dt class="text-[10px] font-black uppercase tracking-wider text-gray-500">Project Leader</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ $proposalDraft->project_leader ?: 'Not provided' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="initial-screening-preview-heading" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6">
            <div class="mb-4">
                <h3 id="initial-screening-preview-heading" class="text-base font-black text-gray-900">Initial Screening Form preview</h3>
                <p class="mt-1 text-xs text-gray-500">The one-page preview reproduces BatStateU-FO-RES-03, Revision 02. Only the two shared values are overlaid.</p>
            </div>
            <iframe src="{{ route('faculty.proposal-drafts.initial-screening-form.preview', $proposalDraft) }}" title="Initial Screening Form preview" class="h-[85vh] w-full rounded-xl border border-gray-200 bg-gray-100"></iframe>
        </section>
    </div>
</x-app-layout>
