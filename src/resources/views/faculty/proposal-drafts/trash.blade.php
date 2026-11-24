<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proposal Trash" subtitle="Recover your drafts and shared drafts approved for deletion." />
    </x-slot>
    <div class="space-y-5" data-proposal-trash>
        <aside class="flex items-start gap-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-slate-800 sm:px-5 dark:border-rose-900 dark:bg-rose-950/40 dark:text-slate-100" aria-labelledby="proposal-trash-recovery">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-white text-[#7A0019] dark:bg-rose-950 dark:text-rose-200">
                <x-report-library-icon name="history" />
            </span>
            <div class="min-w-0">
                <h2 id="proposal-trash-recovery" class="text-base font-bold">30 days to restore a draft</h2>
                <p class="mt-1 text-base leading-6 text-slate-700 dark:text-slate-300">Restoring a draft brings back its saved papers, history, and staff. Standalone drafts are removed after 30 days. Workspaces linked to submissions remain restorable so you can continue revisions.</p>
            </div>
        </aside>
        @if (session('trash_status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-base font-semibold text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">{{ session('trash_status') }}</p>
        @endif
        @if (session('trash_warning'))
            <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-base font-semibold text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-100">{{ session('trash_warning') }}</p>
        @endif
        @error('confirmed')
            <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-base font-semibold text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200">Confirm permanent deletion to remove this draft.</p>
        @enderror
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-labelledby="trashed-proposals-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-6 dark:border-slate-700 dark:bg-slate-800/60">
                <h2 id="trashed-proposals-heading" class="text-lg font-bold text-slate-950 dark:text-white">Deleted drafts</h2>
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $proposalDrafts->total() }} {{ Str::plural('draft', $proposalDrafts->total()) }}</span>
            </div>
            @if ($proposalDrafts->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-14 text-center">
                    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-200"><x-report-library-icon name="remove" /></span>
                    <h3 class="text-lg font-bold text-slate-950 dark:text-white">Trash is empty</h3>
                    <p class="text-base text-slate-600 dark:text-slate-300">Your deleted drafts and shared drafts approved for deletion will appear here.</p>
                </div>
            @else
                <ul class="divide-y divide-slate-200 dark:divide-slate-700" role="list">
                    @foreach ($proposalDrafts as $proposalDraft)
                        @php
                            $title = $proposalDraft->project_title ?: 'Untitled proposal';
                            $linkedToSubmission = $proposalDraft->isLinkedToSubmission();
                            $restoreBy = $linkedToSubmission ? null : $proposalDraft->deleted_at->copy()->addDays(\App\Models\ProposalDraft::TRASH_RETENTION_DAYS);
                        @endphp
                        <li class="space-y-4 px-4 py-5 sm:px-6">
                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)] lg:items-start">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-document-file-icon />
                                    <div class="min-w-0">
                                        <h3 class="break-words text-lg font-bold leading-snug text-slate-950 dark:text-white">{{ $title }}</h3>
                                        @if ($proposalDraft->shared_trash)
                                            <p class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300">Approved for deletion by the project team</p>
                                        @endif
                                        @if ($linkedToSubmission)
                                            <p class="mt-1 text-sm font-semibold text-slate-600 dark:text-slate-300">Linked to a submitted proposal</p>
                                        @endif
                                    </div>
                                </div>
                                <dl class="grid gap-3 rounded-xl bg-slate-50 px-3 py-3 text-slate-800 min-[440px]:grid-cols-2 dark:bg-slate-800/70 dark:text-slate-100">
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-600 dark:text-slate-300">Moved to Trash</dt>
                                        <dd class="mt-1 text-base"><time datetime="{{ $proposalDraft->deleted_at->toIso8601String() }}">{{ $proposalDraft->deleted_at->format('M j, Y, g:i A') }}</time></dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ $restoreBy ? 'Restore by' : 'Recovery' }}</dt>
                                        <dd class="mt-1 text-base">
                                            @if ($restoreBy)
                                                <time datetime="{{ $restoreBy->toIso8601String() }}">{{ $restoreBy->format('M j, Y, g:i A') }}</time>
                                            @else
                                                Available for restoration
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Actions for {{ $title }}">
                                @if ($proposalDraft->trashRecoveryIsOpen())
                                    <form method="POST" action="{{ route('faculty.proposal-drafts.restore', $proposalDraft) }}" data-proposal-confirm data-confirm-icon="question" data-confirm-title="Restore this draft?" data-confirm-text="{{ $title }} will return to {{ $proposalDraft->archived_at ? 'Archived drafts' : 'Active drafts' }} with its saved papers, history, and project staff." data-confirm-button="Restore draft">
                                        @csrf
                                        <button type="submit" class="dashboard-action gap-2 text-base"><x-report-library-icon name="undo" /> Restore</button>
                                    </form>
                                @else
                                    <span class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">Recovery period ended</span>
                                @endif
                                @can('forceDelete', $proposalDraft)
                                    <form method="POST" action="{{ route('faculty.proposal-drafts.trash.destroy', $proposalDraft) }}" data-proposal-confirm data-confirm-title="Permanently delete this draft?" data-confirm-text="{{ $title }} and its saved papers, history, and staff list will be permanently removed from Draft proposals. This cannot be undone." data-confirm-button="Delete permanently">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="confirmed" value="0" data-proposal-confirmation>
                                        <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-rose-200 bg-white px-3 py-2 text-base font-bold text-[#7A0019] transition hover:border-rose-400 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-rose-800 dark:bg-slate-900 dark:text-rose-200 dark:hover:bg-rose-950/60"><x-report-library-icon name="remove" /> Delete permanently</button>
                                    </form>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
        @if ($proposalDrafts->hasPages())
            <div>{{ $proposalDrafts->links() }}</div>
        @endif
    </div>
    <x-back-link fixed href="{{ route('faculty.proposal-drafts.index') }}">Back to drafts</x-back-link>
</x-app-layout>
