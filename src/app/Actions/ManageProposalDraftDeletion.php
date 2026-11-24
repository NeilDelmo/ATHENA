<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDeletionRequest;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ManageProposalDraftDeletion
{
    public function request(ProposalDraft $draft, User $leader, string $reason): ProposalDraftDeletionRequest
    {
        return DB::transaction(function () use ($draft, $leader, $reason): ProposalDraftDeletionRequest {
            $draft = $this->lockDraft($draft);
            Gate::forUser($leader)->authorize('organize', $draft);
            $existing = $draft->pendingDeletionRequest()->first();
            if ($existing !== null) {
                return $existing;
            }

            $memberIds = $this->acceptedMemberIds($draft);
            if ($memberIds === []) {
                throw ValidationException::withMessages(['reason' => 'This draft has no accepted teammates. Use Move to Trash from the draft list.']);
            }

            $deletionRequest = $draft->deletionRequests()->create([
                'reason' => $reason,
                'member_ids' => $memberIds,
                'approved_member_ids' => [],
            ]);
            $title = $draft->project_title ?: 'Untitled proposal';
            $this->notifyTeam(
                $draft,
                'Draft deletion requested',
                "{$leader->name} requested deletion of “{$title}”. Review the reason and approve or decline.",
                route('faculty.proposal-drafts.deletion.show', [$draft, $deletionRequest]),
                includeLeader: false,
            );

            return $deletionRequest;
        }, 3);
    }

    public function respond(ProposalDraft $draft, int $requestId, User $user, string $decision): ProposalDraftDeletionRequest
    {
        return DB::transaction(function () use ($draft, $requestId, $user, $decision): ProposalDraftDeletionRequest {
            $draft = ProposalDraft::withTrashed()->lockForUpdate()->findOrFail($draft->getKey());
            if ($draft->trashed()) {
                Gate::forUser($user)->authorize('viewAny', ProposalDraft::class);
                abort_unless($draft->shared_trash && ($draft->isOwnedBy($user) || $draft->isSharedWith($user)), 404);

                return $draft->deletionRequests()->where('status', '<>', ProposalDraftDeletionRequest::STATUS_PENDING)->findOrFail($requestId);
            }

            $deletionRequest = $draft->deletionRequests()->findOrFail($requestId);
            $cancel = $decision === 'cancel';
            $memberId = null;
            if ($cancel) {
                Gate::forUser($user)->authorize('organize', $draft);
            } else {
                Gate::forUser($user)->authorize('view', $draft);
                $memberId = $draft->acceptedMembers()->forUser($user)->value('id');
                abort_unless(! $draft->isOwnedBy($user) && in_array($memberId, $deletionRequest->member_ids, true), 403);
            }

            if (! $deletionRequest->isPending()) {
                return $deletionRequest;
            }

            if (! $cancel && ($draft->status !== ProposalDraft::STATUS_DRAFT || $this->teamHasChanged($draft, $deletionRequest))) {
                throw ValidationException::withMessages(['decision' => 'The draft or its team changed. Ask the project leader to cancel this request and start a new one.']);
            }

            $title = $draft->project_title ?: 'Untitled proposal';
            $requestUrl = route('faculty.proposal-drafts.deletion.show', [$draft, $deletionRequest]);
            if ($decision !== 'approve') {
                $deletionRequest->update(['status' => $cancel ? ProposalDraftDeletionRequest::STATUS_CANCELLED : ProposalDraftDeletionRequest::STATUS_DECLINED]);
                $verb = $cancel ? 'cancelled' : 'declined';
                $this->notifyTeam($draft, "Deletion request {$verb}", "{$user->name} {$verb} the request for “{$title}”. The draft is still available.", $requestUrl);

                return $deletionRequest;
            }

            $approvedMemberIds = $deletionRequest->approved_member_ids;
            if (in_array($memberId, $approvedMemberIds, true)) {
                return $deletionRequest;
            }

            $approvedMemberIds[] = $memberId;
            $deletionRequest->update(['approved_member_ids' => $approvedMemberIds]);
            if (count($approvedMemberIds) < count($deletionRequest->member_ids)) {
                return $deletionRequest;
            }

            $draft->forceFill(['shared_trash' => true])->save();
            $draft->delete();
            $deletionRequest->update(['status' => ProposalDraftDeletionRequest::STATUS_APPROVED]);

            $recovery = $draft->isLinkedToSubmission()
                ? 'The linked workspace stays available for the accepted team to restore.'
                : 'Any accepted teammate can restore it for '.ProposalDraft::TRASH_RETENTION_DAYS.' days.';
            $this->notifyTeam(
                $draft,
                'Shared draft moved to Trash',
                "The team approved deletion of “{$title}”. {$recovery}",
                route('faculty.proposal-drafts.trash'),
            );

            return $deletionRequest;
        }, 3);
    }

    public function restore(int $draftId, User $user): ProposalDraft
    {
        return DB::transaction(function () use ($draftId, $user): ProposalDraft {
            $draft = ProposalDraft::onlyTrashed()->trashAccessibleTo($user)->lockForUpdate()->findOrFail($draftId);
            Gate::forUser($user)->authorize('restore', $draft);

            $wasShared = $draft->shared_trash;
            $draft->shared_trash = false;
            $draft->restore();

            if ($wasShared) {
                $title = $draft->project_title ?: 'Untitled proposal';
                $this->notifyTeam(
                    $draft,
                    'Shared draft restored',
                    "{$user->name} restored “{$title}” from Trash. The saved papers and team access are available again.",
                    route('faculty.proposal-drafts.show', $draft),
                    except: $user,
                );
            }

            return $draft;
        }, 3);
    }

    public function teamHasChanged(ProposalDraft $draft, ProposalDraftDeletionRequest $deletionRequest): bool
    {
        return $this->acceptedMemberIds($draft) !== $deletionRequest->member_ids;
    }

    private function lockDraft(ProposalDraft $draft): ProposalDraft
    {
        return ProposalDraft::query()->lockForUpdate()->findOrFail($draft->getKey());
    }

    /** @return list<int> */
    private function acceptedMemberIds(ProposalDraft $draft): array
    {
        return $draft->acceptedMembers()->reorder('id')->pluck('id')->all();
    }

    private function notifyTeam(ProposalDraft $draft, string $title, string $message, string $url, bool $includeLeader = true, ?User $except = null): void
    {
        $members = $draft->acceptedMembers()->get(['user_id', 'email']);
        $userIds = $members->pluck('user_id')->filter();
        if ($includeLeader) {
            $userIds->push($draft->user_id);
        }
        $emails = $members->whereNull('user_id')->pluck('email')->filter();
        $recipients = User::query()
            ->where(fn (Builder $users): Builder => $users->whereIn('id', $userIds)
                ->orWhere(fn (Builder $verified): Builder => $verified->whereNotNull('email_verified_at')->whereIn('email', $emails)))
            ->when($except !== null, fn (Builder $users): Builder => $users->whereKeyNot($except->getKey()))
            ->get();

        $notification = new ProposalActivityNotification(
            title: $title,
            message: $message,
            url: $url,
            workspace: User::WORKSPACE_FACULTY,
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
        );

        Notification::sendNow($recipients, $notification, ['database']);
        DB::afterCommit(fn () => rescue(fn () => Notification::sendNow($recipients, $notification, ['broadcast'])));
    }
}
