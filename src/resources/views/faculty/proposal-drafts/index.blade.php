<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Proposal Package Workspace</h2>
                <p class="mt-1 text-xs text-gray-500">Create proposal packages and track every submitted proposal in one place.</p>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 sm:w-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    New Proposal
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif

        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The requested draft action could not be completed.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif

        <section aria-labelledby="saved-drafts-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="saved-drafts-heading" class="text-lg font-black text-gray-900">Draft packages</h3>
                    <p class="mt-1 text-xs text-gray-500">Drafts stay here until you submit or delete them.</p>
                </div>
                <span class="text-xs font-bold text-gray-500">{{ $proposalDrafts->total() }} {{ Str::plural('draft', $proposalDrafts->total()) }}</span>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($proposalDrafts as $proposalDraft)
                    @php
                        $draftChecklist = app(\App\Support\ProposalDraftReadiness::class)->checklist($proposalDraft);
                        $completeCount = $draftChecklist
                            ->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])
                            ->count();
                    @endphp
                    <article class="flex min-h-64 flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800">Draft</span>
                                <span class="rounded-full bg-blue-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-blue-800">{{ $proposalDraft->user_id === auth()->id() ? 'Owner' : 'Collaborator' }}</span>
                            </div>
                            <span class="text-[11px] text-gray-500">Saved {{ $proposalDraft->updated_at->diffForHumans() }}</span>
                        </div>
                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-900">{{ $proposalDraft->project_title }}</h4>
                        <p class="mt-2 text-xs leading-5 text-gray-500">{{ $proposalDraft->researchCall?->title ?? 'No research call selected yet' }}</p>
                        <p class="mt-1 text-[11px] font-semibold text-gray-500">Workspace owner: {{ $proposalDraft->owner->name }}</p>

                        <div class="mt-5" aria-label="{{ $completeCount }} of {{ $draftChecklist->count() }} papers complete">
                            <div class="flex items-center justify-between text-[11px] font-bold text-gray-600">
                                <span>Package progress</span>
                                <span>{{ $completeCount }}/{{ $draftChecklist->count() }} papers</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-red-600" style="width: {{ $draftChecklist->isEmpty() ? 0 : ($completeCount / $draftChecklist->count()) * 100 }}%"></div>
                            </div>
                        </div>

                        <div class="mt-auto grid gap-2 pt-6 {{ $proposalDraft->user_id === auth()->id() ? 'sm:grid-cols-[1fr_auto]' : '' }}">
                            <a href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2">Resume</a>
                            @can('delete', $proposalDraft)
                            <form
                                action="{{ route('faculty.proposal-drafts.destroy', $proposalDraft) }}"
                                method="POST"
                                data-proposal-confirm
                                data-confirm-title="Delete proposal draft?"
                                data-confirm-text="This permanently deletes the draft and all staged papers. This action cannot be undone."
                                data-confirm-button="Delete draft"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Delete</button>
                            </form>
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center md:col-span-2 xl:col-span-3">
                        <h4 class="text-base font-black text-gray-900">No saved proposal drafts</h4>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500">Start a proposal package and complete each required paper at your own pace.</p>
                        <a href="{{ route('faculty.proposal-drafts.create') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">New Proposal</a>
                    </div>
                @endforelse
            </div>

            @if ($proposalDrafts->hasPages())
                <div class="mt-6">{{ $proposalDrafts->links() }}</div>
            @endif
        </section>

        <section aria-labelledby="submitted-proposals-heading">
            <div class="mb-4 flex items-end justify-between gap-4">
                <div>
                    <h3 id="submitted-proposals-heading" class="text-lg font-black text-gray-900 dark:text-white">Submitted proposals</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Track review decisions, requested revisions, signatures, and approved project records.</p>
                </div>
                <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ $submittedProposals->total() }} {{ Str::plural('proposal', $submittedProposals->total()) }}</span>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($submittedProposals as $proposal)
                    @php
                        [$statusLabel, $statusDescription, $statusStyle] = match ($proposal->status) {
                            'expert_review' => ['Under expert review', 'Your package is being evaluated by the assigned expert.', 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-200'],
                            'for_final_decision' => ['Awaiting decision', 'The review stage is complete and the Research Head is deciding.', 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/50 dark:text-cyan-200'],
                            'revision_requested' => ['Revision required', 'Open the proposal to review comments and prepare the requested changes.', 'bg-blue-100 text-blue-800 dark:bg-blue-950/50 dark:text-blue-200'],
                            'resubmitted' => ['Resubmitted', 'Your revised package has been received for another review.', 'bg-purple-100 text-purple-800 dark:bg-purple-950/50 dark:text-purple-200'],
                            'ready_for_signature' => ['Final signing', 'The selected final papers are waiting for signed copies.', 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200'],
                            'approved' => ['Approved project', 'This proposal is approved. Its project records and monitoring remain in the project workflow.', 'bg-green-100 text-green-800 dark:bg-green-950/50 dark:text-green-200'],
                            'rejected' => ['Not approved', 'This proposal received a final decision and is available as a record.', 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200'],
                            default => ['Submitted', 'Your package was received and is waiting for the Research Office review.', 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'],
                        };
                        $latestSubmission = $proposal->latestVersion;
                        $submittedAt = $latestSubmission?->created_at ?? $proposal->created_at;
                    @endphp

                    <article class="flex min-h-64 flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start justify-between gap-3">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusStyle }}">{{ $statusLabel }}</span>
                            <time datetime="{{ $submittedAt?->toIso8601String() }}" class="shrink-0 text-[11px] text-gray-500 dark:text-slate-400">{{ $submittedAt?->diffForHumans() }}</time>
                        </div>

                        <h4 class="mt-4 line-clamp-2 text-base font-black leading-6 text-gray-900 dark:text-white">{{ $proposal->title }}</h4>
                        <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">{{ $proposal->researchCall?->title ?? 'Research call unavailable' }}</p>
                        @if ($proposal->researchCall?->academic_year)
                            <p class="mt-1 text-[11px] font-semibold text-gray-500 dark:text-slate-400">AY {{ $proposal->researchCall->academic_year }}</p>
                        @endif

                        <p class="mt-4 text-xs leading-5 text-gray-600 dark:text-slate-300">{{ $statusDescription }}</p>

                        <div class="mt-4 rounded-xl bg-gray-50 px-3 py-2.5 text-[11px] font-semibold text-gray-600 dark:bg-slate-950 dark:text-slate-300">
                            @if ($latestSubmission)
                                Version {{ $latestSubmission->version_number }} · {{ $latestSubmission->submission_type === 'revision' ? 'Revision submitted' : 'Initial package submitted' }}
                            @else
                                Submitted proposal record
                            @endif
                        </div>

                        <div class="mt-auto pt-6">
                            <a href="{{ route('topics.show', $proposal) }}" class="inline-flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:bg-red-600 dark:hover:bg-red-700 dark:focus:ring-red-500 dark:focus:ring-offset-slate-900">
                                {{ $proposal->status === 'revision_requested' ? 'Open revision request' : ($proposal->status === 'approved' ? 'Open project record' : 'View status') }}
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-900 md:col-span-2 xl:col-span-3">
                        <h4 class="text-base font-black text-gray-900 dark:text-white">No submitted proposals yet</h4>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-gray-500 dark:text-slate-400">When you turn in a proposal package, its review status and future decisions will appear here.</p>
                    </div>
                @endforelse
            </div>

            @if ($submittedProposals->hasPages())
                <div class="mt-6">{{ $submittedProposals->links() }}</div>
            @endif
        </section>
    </div>
</x-app-layout>
