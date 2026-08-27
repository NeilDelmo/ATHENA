<?php

namespace App\Policies;

use App\Models\ProposalDraftMember;
use App\Models\User;

class ProposalDraftMemberPolicy
{
    public function review(User $user, ProposalDraftMember $proposalDraftMember): bool
    {
        return $user->canUseWorkspace(User::WORKSPACE_FACULTY)
            && $proposalDraftMember->user_id === $user->getKey();
    }

    public function accept(User $user, ProposalDraftMember $proposalDraftMember): bool
    {
        return $this->review($user, $proposalDraftMember)
            && ! $proposalDraftMember->isAccepted();
    }
}
