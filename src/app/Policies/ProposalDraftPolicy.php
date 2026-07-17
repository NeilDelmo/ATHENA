<?php

namespace App\Policies;

use App\Models\ProposalDraft;
use App\Models\User;

class ProposalDraftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ]);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft);
    }

    public function update(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->owns($user, $proposalDraft)
            && $proposalDraft->status === ProposalDraft::STATUS_DRAFT;
    }

    public function delete(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->update($user, $proposalDraft);
    }

    public function download(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->view($user, $proposalDraft);
    }

    public function submit(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->update($user, $proposalDraft);
    }

    private function owns(User $user, ProposalDraft $proposalDraft): bool
    {
        return $this->viewAny($user)
            && $proposalDraft->user_id === $user->id;
    }
}
