<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Draft proposals">
            <x-slot:actions>
                <a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action text-base">New Proposal</a>
            </x-slot:actions>
        </x-page-header>
    </x-slot>
    <div class="space-y-5">
        @if (session('success'))
            <x-proposal-alert :data-alert-title="session('deleted_proposal_draft_id') ? 'Draft moved to Trash' : null" :data-alert-button="session('deleted_proposal_draft_id') ? 'OK' : 'Close'">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ session('success') }}</span>
                    @if (session('deleted_proposal_draft_id'))
                        <form data-proposal-alert-undo method="POST" action="{{ route('faculty.proposal-drafts.restore', session('deleted_proposal_draft_id')) }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-stone-300 px-4 font-semibold text-brand"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7 4-4 4 4 4M3 8h10a7 7 0 1 1 0 14" /></svg>Undo</button>
                        </form>
                    @endif
                </div>
            </x-proposal-alert>
        @endif
        @if ($errors->any())
            <x-proposal-alert type="error">
                <p class="font-bold">The requested draft action could not be completed.</p>
                <ul class="mt-1 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-proposal-alert>
        @endif
        @if (session('draft_list_status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-semibold text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('draft_list_status') }}</p>
        @endif
        <section data-faculty-drafts class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="draft-projects-heading">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                <div>
                    <h2 id="draft-projects-heading" class="text-lg font-semibold text-slate-900 dark:text-white">{{ $archived ? 'Archived drafts' : 'Draft projects' }}</h2>
                    @if ($archived)
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Archived drafts and their papers remain available to the project team.</p>
                    @endif
                </div>
                <div class="ml-auto flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                    <span class="whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">{{ $proposalDrafts->total() }} {{ str('draft')->plural($proposalDrafts->total()) }}</span>
                    <a
                        wire:navigate
                        href="{{ route('faculty.proposal-drafts.index', $archived ? [] : ['view' => 'archived']) }}"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <x-report-library-icon :name="$archived ? 'back' : 'archive'" class="!h-4 !w-4" />
                        {{ $archived ? 'Active drafts' : 'Archive' }}
                    </a>
                    <a
                        wire:navigate
                        href="{{ route('faculty.proposal-drafts.trash') }}"
                        data-proposal-trash-entry
                        aria-label="Proposal Trash"
                        class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <x-report-library-icon name="remove" class="!h-4 !w-4" />
                        Trash
                    </a>
                </div>
            </div>
            @forelse ($proposalDrafts as $proposalDraft)
                @php
                    $draftChecklist = app(\App\Support\ProposalDraftReadiness::class)->checklist($proposalDraft);
                    $completeCount = $draftChecklist->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])->count();
                    $paperCount = $draftChecklist->count();
                    $draftTitle = $proposalDraft->project_title ?: 'Untitled proposal';
                    $pendingDeletion = $proposalDraft->pendingDeletionRequest;
                @endphp
                <article data-proposal-draft="{{ $proposalDraft->id }}" class="grid min-w-0 gap-3 border-b border-slate-100 px-4 py-5 last:border-b-0 sm:px-6 dark:border-slate-800 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-6">
                    <div class="flex min-w-0 items-start gap-4">
                        <x-document-file-icon class="shrink-0" />
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="break-words text-base font-semibold text-slate-950 dark:text-white">{{ $draftTitle }}</h3>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $proposalDraft->user_id === auth()->id() ? 'Project leader' : 'Project staff' }}</span>
                            </div>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $proposalDraft->researchCall?->title ?? 'Independent submission' }}</p>
                            <p class="mt-1 break-words text-sm text-slate-500 dark:text-slate-400">Saved {{ $proposalDraft->updated_at->diffForHumans() }} &middot; Project leader: {{ $proposalDraft->owner->name }}</p>
                            <p data-draft-readiness class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                {{ $paperCount > 0 && $completeCount === $paperCount ? "All {$paperCount} papers ready" : "{$completeCount} of {$paperCount} papers ready" }}
                            </p>
                            @if ($pendingDeletion)
                                <p class="mt-2 text-sm font-semibold text-amber-800 dark:text-amber-300">Deletion requested · {{ count($pendingDeletion->approved_member_ids) }} of {{ count($pendingDeletion->member_ids) }} approvals</p>
                            @endif
                        </div>
                    </div>
                    <div data-draft-actions class="grid grid-cols-[max-content_2.75rem] items-center gap-2 justify-self-end">
                        <a wire:navigate.hover href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="dashboard-action justify-center text-base">Resume</a>
                        @if (auth()->user()->can('organize', $proposalDraft) || $pendingDeletion)
                            <x-dropdown align="right" width="w-52" content-classes="p-1 bg-white dark:bg-slate-900">
                                <x-slot:trigger>
                                    <button type="button" aria-label="More actions for {{ $draftTitle }}" x-ref="draftActionTrigger" x-bind:aria-expanded="open.toString()" @keydown.escape.stop="open = false" class="grid h-11 w-11 place-items-center rounded-lg border border-slate-200 text-slate-700 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                        <x-report-library-icon name="actions" class="!h-5 !w-5" />
                                    </button>
                                </x-slot:trigger>
                                <x-slot:content>
                                    <div @keydown.escape.stop="open = false; $refs.draftActionTrigger.focus()">
                                        @can('organize', $proposalDraft)
                                            <form method="POST" action="{{ route('faculty.proposal-drafts.archive', $proposalDraft) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="archived" value="{{ $archived ? '0' : '1' }}">
                                                <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                                                    <x-report-library-icon :name="$archived ? 'undo' : 'archive'" class="!h-4 !w-4" />
                                                    {{ $archived ? 'Unarchive' : 'Archive draft' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @if ($pendingDeletion)
                                            <a wire:navigate href="{{ route('faculty.proposal-drafts.deletion.show', [$proposalDraft, $pendingDeletion]) }}" class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                                                <x-report-library-icon name="history" class="!h-4 !w-4" />
                                                Review deletion request
                                            </a>
                                        @elseif ($proposalDraft->hasAcceptedMembers())
                                            <a wire:navigate href="{{ route('faculty.proposal-drafts.deletion.create', $proposalDraft) }}" class="flex min-h-11 items-center gap-2 rounded-md px-3 text-sm font-semibold text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-rose-950/50">
                                                <x-report-library-icon name="remove" class="!h-4 !w-4" />
                                                Request deletion
                                            </a>
                                        @else
                                            <form method="POST" action="{{ route('faculty.proposal-drafts.destroy', $proposalDraft) }}" data-proposal-confirm data-confirm-title="Move proposal draft to Trash?" data-confirm-text="{{ $proposalDraft->isLinkedToSubmission() ? 'You can restore the linked workspace from Trash to continue revisions.' : 'You can restore this draft with its saved papers and history from Trash for 30 days.' }}" data-confirm-button="Move to Trash">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="flex min-h-11 w-full items-center gap-2 rounded-md px-3 text-left text-sm font-semibold text-rose-800 hover:bg-rose-50 dark:text-rose-200 dark:hover:bg-rose-950/50">
                                                    <x-report-library-icon name="remove" class="!h-4 !w-4" />
                                                    Move to Trash
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </x-slot:content>
                            </x-dropdown>
                        @endif
                    </div>
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $archived ? 'No archived drafts' : 'No active proposal drafts' }}</h3>
                    @unless ($archived)
                        <a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action mt-5 text-base">New Proposal</a>
                    @endunless
                </div>
            @endforelse
        </section>
        @if ($proposalDrafts->hasPages())
            <div>{{ $proposalDrafts->links() }}</div>
        @endif
    </div>
</x-app-layout>
