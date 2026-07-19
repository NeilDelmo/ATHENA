<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; Proposal package</a>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $paper['label'] }}</h2>
                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $gadDocument?->completed_at ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $gadDocument?->completed_at ? 'Complete' : 'Not started' }}</span>
            </div>
            <p class="mt-1 text-xs text-gray-500">The official seven-page Box 7a document is reproduced automatically using the shared project details.</p>
        </div>
    </x-slot>

    @php
        $projectDetailsComplete = app(\App\Support\ProposalDraftReadiness::class)->projectDetailsAreComplete($proposalDraft);
        $sampleDefinition = config('proposal_samples.'.$paper['sample_slug']);
        $sampleAvailable = is_array($sampleDefinition)
            && isset($sampleDefinition['path'])
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($sampleDefinition['path']);
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8"
        x-data="proposalDraftGadChecklist({
            previewUrl: @js(route('faculty.proposal-drafts.gad-checklist.preview', $proposalDraft)),
            downloadUrl: @js(route('faculty.proposal-drafts.gad-checklist.download', $proposalDraft)),
            csrfToken: @js(csrf_token()),
            canPreview: @js($projectDetailsComplete),
        })"
    >
        @if (session('success'))
            <div role="status" class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-bold">The GAD Generic Checklist could not be saved.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div x-show="validationMessage" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" x-text="validationMessage"></div>

        @unless ($projectDetailsComplete)
            <div role="alert" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-black">Complete Project Details first</p>
                <p class="mt-1 leading-6">The Project Title and Project Leader are required before the GAD Generic Checklist can be generated.</p>
                <a href="{{ route('faculty.proposal-drafts.details.edit', $proposalDraft) }}" class="mt-3 inline-flex rounded-xl bg-amber-900 px-4 py-2.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-amber-900 focus:ring-offset-2">Complete Project Details</a>
            </div>
        @endunless

        <section class="rounded-2xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-wider text-blue-700">No form fields to answer</p>
                    <h3 class="mt-1 text-base font-black text-gray-900">Auto-filled from shared project information</h3>
                    <p class="mt-1 max-w-3xl text-sm leading-6 text-gray-600">ATHENA pulls the Project Title and Project Leader from Project Details. Every checklist mark, score, instruction, and signatory stays exactly as provided in the source document.</p>
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

        <form x-ref="form" action="{{ route('faculty.proposal-drafts.gad-checklist.update', $proposalDraft) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="document_version" value="{{ $gadDocument?->lock_version ?? 0 }}">

            <div class="rounded-2xl border {{ $gadDocument?->completed_at ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white' }} p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-black {{ $gadDocument?->completed_at ? 'text-green-900' : 'text-gray-900' }}">{{ $gadDocument?->completed_at ? 'Automatic paper marked ready' : 'Review the automatic preview' }}</p>
                        <p class="mt-1 text-xs leading-5 {{ $gadDocument?->completed_at ? 'text-green-800' : 'text-gray-500' }}">{{ $gadDocument?->completed_at ? 'No further action is required unless you change the shared Project Details.' : 'There are no answers to enter. Check the generated copy, then mark it ready for the proposal package.' }}</p>
                    </div>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:flex-wrap sm:justify-end">
                        <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:w-auto">Return to proposal package</a>
                        <button type="button" x-on:click="generatePreview" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-gray-900 px-5 py-3 text-sm font-bold text-gray-900 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!previewLoading">Refresh preview</span><span x-show="previewLoading" x-cloak>Generating&hellip;</span></button>
                        <button type="button" x-on:click="downloadDocument" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 bg-white px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"><span x-show="!downloadLoading">Download Word file</span><span x-show="downloadLoading" x-cloak>Preparing&hellip;</span></button>
                        @unless ($gadDocument?->completed_at)
                            <button type="submit" @disabled(! $projectDetailsComplete) class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Mark paper ready</button>
                        @endunless
                    </div>
                </div>
            </div>
        </form>

        <div x-show="previewError || downloadError" x-cloak role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><span x-text="previewError || downloadError"></span></div>

        <section x-show="previewHtml || previewLoading" x-cloak aria-labelledby="gad-preview-heading" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6" x-bind:aria-busy="previewLoading">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 id="gad-preview-heading" class="text-base font-black text-gray-900">GAD Generic Checklist preview</h3><p class="mt-1 text-xs text-gray-500">The seven pages below follow the supplied Box 7a document.</p></div>
                <button type="button" x-on:click="printPreview" x-bind:disabled="!previewReady" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-600 focus:ring-offset-2 disabled:opacity-50 sm:w-auto">Print preview</button>
            </div>
            <div x-show="previewLoading" class="flex h-48 items-center justify-center rounded-xl border border-gray-200 bg-gray-50 text-sm font-semibold text-gray-600">Preparing the seven-page preview&hellip;</div>
            <iframe x-show="previewHtml" x-ref="previewFrame" x-bind:srcdoc="previewHtml" x-on:load="previewReady = true" title="GAD Generic Checklist preview" class="h-[80vh] w-full rounded-xl border border-gray-200 bg-white"></iframe>
        </section>
    </div>
</x-app-layout>
