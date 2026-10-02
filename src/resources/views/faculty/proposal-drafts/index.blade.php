<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Draft proposals" subtitle="Prepare your project, then follow its review in Submitted.">
            <x-slot:actions><a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action">New Proposal</a></x-slot:actions>
        </x-page-header>
    </x-slot>
    <div class="space-y-5">
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif
        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The requested draft action could not be completed.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif
        <section data-faculty-drafts class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Your draft proposals">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Draft projects</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Drafts stay private until submitted.</p>
                </div>
                <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $proposalDrafts->total() }} {{ str('draft')->plural($proposalDrafts->total()) }}</span>
            </div>
            @forelse ($proposalDrafts as $proposalDraft)
                @php
                    $draftChecklist = app(\App\Support\ProposalDraftReadiness::class)->checklist($proposalDraft);
                    $completeCount = $draftChecklist->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])->count();
                @endphp
                <article data-proposal-draft="{{ $proposalDraft->id }}" class="grid min-w-0 gap-4 border-b border-slate-100 px-6 py-5 last:border-b-0 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_11rem] lg:items-center lg:gap-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <h3 class="break-words text-base font-semibold text-slate-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h3>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $proposalDraft->user_id === auth()->id() ? 'Owner' : 'Team member' }}</span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $proposalDraft->researchCall?->title ?? 'Independent submission' }}</p>
                        <p class="mt-1 text-xs text-slate-400">Saved {{ $proposalDraft->updated_at->diffForHumans() }} &middot; Workspace owner: {{ $proposalDraft->owner->name }}</p>
                        <p data-draft-readiness class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                            <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8m-8 4h5" /></svg>
                            <span>{{ $completeCount }} of {{ $draftChecklist->count() }} papers ready</span>
                            @if ($draftChecklist->isNotEmpty() && $completeCount === $draftChecklist->count())
                                <span class="font-semibold text-[#7A0019] dark:text-red-300">Review project</span>
                            @endif
                        </p>
                    </div>
                    <div data-draft-actions class="grid w-44 grid-cols-2 items-center gap-2">
                        <a wire:navigate href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="dashboard-action col-start-1 w-full">Resume</a>
                        @can('delete', $proposalDraft)
                            <form
                                class="col-start-2"
                                action="{{ route('faculty.proposal-drafts.destroy', $proposalDraft) }}"
                                method="POST"
                                data-proposal-confirm
                                data-confirm-title="Delete proposal draft?"
                                data-confirm-text="This permanently deletes the draft and all staged papers. This action cannot be undone."
                                data-confirm-button="Delete draft"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex w-full items-center justify-center min-h-11 rounded-lg border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-xs font-bold text-slate-500 dark:text-slate-400 transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Delete</button>
                            </form>
                        @endcan
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No saved proposal drafts</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">Start a proposal and complete each required paper. You can submit anytime.</p>
                    <a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action mt-5">New Proposal</a>
                </div>
            @endforelse
        </section>
        @if ($proposalDrafts->hasPages())
            <div>{{ $proposalDrafts->links() }}</div>
        @endif
    </div>
</x-app-layout>
