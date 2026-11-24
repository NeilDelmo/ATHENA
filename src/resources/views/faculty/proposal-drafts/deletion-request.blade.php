@php
    $deletionRequest = $deletionRequest ?? null;
    $title = $proposalDraft->project_title ?: 'Untitled proposal';
    $draftsUrl = route('faculty.proposal-drafts.index', $proposalDraft->archived_at ? ['view' => 'archived'] : []);
    $retentionDays = \App\Models\ProposalDraft::TRASH_RETENTION_DAYS;
    $recoveryTerms = $proposalDraft->isLinkedToSubmission()
        ? 'The linked workspace stays available for any accepted teammate to restore.'
        : "Any accepted teammate can restore it for {$retentionDays} days before ATHENA permanently removes it.";
    $buttonClasses = 'inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 font-semibold text-slate-800 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$deletionRequest ? 'Deletion request' : 'Request draft deletion'" :subtitle="$title" />
    </x-slot>

    <section class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="deletion-heading">
        @if ($deletionRequest === null)
            <div class="space-y-2">
                <h2 id="deletion-heading" class="text-xl font-bold text-slate-950 dark:text-white">Ask your team to approve</h2>
                <p class="leading-6 text-slate-700 dark:text-slate-300">ATHENA will notify your accepted teammates. The draft stays accessible until everyone approves. If anyone declines, the request closes.</p>
            </div>
            <p class="rounded-xl bg-slate-50 p-4 leading-6 text-slate-700 dark:bg-slate-800 dark:text-slate-200">Once approved, the draft goes to Trash. {{ $recoveryTerms }}</p>
            <form method="POST" action="{{ route('faculty.proposal-drafts.deletion.store', $proposalDraft) }}" class="space-y-4">
                @csrf
                <div>
                    <label for="deletion-reason" class="block font-semibold text-slate-900 dark:text-white">Reason for deletion</label>
                    <textarea id="deletion-reason" name="reason" rows="4" maxlength="1000" required @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror class="mt-2 block w-full rounded-lg border-slate-300 bg-white text-slate-900 focus:border-rose-700 focus:ring-rose-700 dark:border-slate-600 dark:bg-slate-950 dark:text-white">{{ old('reason') }}</textarea>
                    <x-input-error id="reason-error" :messages="$errors->get('reason')" class="mt-2" />
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="dashboard-action text-base">Send deletion request</button>
                    <a wire:navigate href="{{ $draftsUrl }}" class="{{ $buttonClasses }}">Cancel</a>
                </div>
            </form>
        @else
            @php
                $status = match ($deletionRequest->status) {
                    \App\Models\ProposalDraftDeletionRequest::STATUS_APPROVED => 'Approved by the team',
                    \App\Models\ProposalDraftDeletionRequest::STATUS_DECLINED => 'Request declined',
                    \App\Models\ProposalDraftDeletionRequest::STATUS_CANCELLED => 'Request cancelled',
                    default => 'Waiting for team approval',
                };
                $responseUrl = route('faculty.proposal-drafts.deletion.update', [$proposalDraft, $deletionRequest]);
            @endphp
            <div class="space-y-1">
                <h2 id="deletion-heading" class="text-xl font-bold text-slate-950 dark:text-white">{{ $status }}</h2>
                <p class="text-slate-600 dark:text-slate-300">{{ count($deletionRequest->approved_member_ids) }} of {{ count($deletionRequest->member_ids) }} teammate approvals</p>
            </div>
            <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                <h3 class="font-semibold text-slate-900 dark:text-white">Reason from {{ $proposalDraft->owner->name }}</h3>
                <p class="mt-1 whitespace-pre-wrap break-words text-slate-700 dark:text-slate-200">{{ $deletionRequest->reason }}</p>
            </div>
            @if ($teamChanged)
                <p role="status" class="rounded-lg bg-amber-50 p-3 text-amber-900 dark:bg-amber-950 dark:text-amber-200">The accepted team changed. The project leader can cancel this request and start a new one.</p>
            @elseif ($deletionRequest->isPending())
                <p class="leading-6 text-slate-700 dark:text-slate-300">The draft stays accessible until every accepted teammate approves. {{ $recoveryTerms }}</p>
            @endif
            @if ($canRespond && $hasApproved)
                <p role="status" class="font-semibold text-emerald-800 dark:text-emerald-300">You approved. You can still decline while the request is open.</p>
            @endif
            <x-input-error :messages="$errors->get('decision')" />
            <div class="flex flex-wrap items-center gap-3">
                @if ($canRespond)
                    @unless ($hasApproved)
                        <form method="POST" action="{{ $responseUrl }}" data-proposal-confirm data-confirm-icon="question" data-confirm-title="Approve draft deletion?" data-confirm-text="If every accepted teammate approves, this draft goes to Trash. {{ $recoveryTerms }}" data-confirm-button="Approve">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="dashboard-action text-base">Approve</button>
                        </form>
                    @endunless
                    <form method="POST" action="{{ $responseUrl }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="decline">
                        <button type="submit" class="{{ $buttonClasses }}">Decline</button>
                    </form>
                @elseif ($deletionRequest->isPending() && auth()->user()->can('organize', $proposalDraft))
                    <form method="POST" action="{{ $responseUrl }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="cancel">
                        <button type="submit" class="{{ $buttonClasses }}">Cancel request</button>
                    </form>
                @endif
                @if ($proposalDraft->trashed())
                    <a wire:navigate href="{{ route('faculty.proposal-drafts.trash') }}" class="{{ $buttonClasses }}">Open Trash</a>
                @else
                    <a wire:navigate href="{{ route('faculty.proposal-drafts.show', $proposalDraft) }}" class="{{ $buttonClasses }}">Open draft</a>
                @endif
                <a wire:navigate href="{{ $draftsUrl }}" class="{{ $buttonClasses }}">Back to drafts</a>
            </div>
        @endif
    </section>
</x-app-layout>
