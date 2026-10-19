<?php

namespace App\Policies;

use App\Models\ProposalDraft;
use App\Models\User;

class ProposalDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsingWorkspace(User::WORKSPACE_FACULTY);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->viewAny($user)
            && ! $proposalDraft->trashed()
            && ($proposalDraft->isOwnedBy($user) || $proposalDraft->isSharedWith($user));
    }

    public function update(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->view($user, $proposalDraft)
            && $proposalDraft->isEditable();
    }

    public function delete(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft)
            && ! $proposalDraft->trashed()
            && $proposalDraft->status === ProposalDraft::STATUS_DRAFT;
    }

    public function restore(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft)
            && $proposalDraft->trashed()
            && $proposalDraft->status === ProposalDraft::STATUS_DRAFT
            && $proposalDraft->deleted_at->greaterThan(now()->subDays(ProposalDraft::TRASH_RETENTION_DAYS));
    }

    public function download(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->view($user, $proposalDraft);
    }

    public function submit(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft)
            && $proposalDraft->isEditable()
            && ($proposalDraft->topic_id === null || ($proposalDraft->topic?->canUpdateBeforeReview() ?? false));
    }

    public function prepare(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft) && $proposalDraft->isEditable();
    }

    public function manageMembers(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft) && $proposalDraft->isEditable();
    }

    private function owns(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->viewAny($user)
            && $proposalDraft->user_id === $user->id;
    }
}
