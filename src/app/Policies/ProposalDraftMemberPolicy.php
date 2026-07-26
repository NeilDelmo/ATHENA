<?php

namespace App\Policies;

use App\Models\ProposalDraftMember;
use App\Models\User;

class ProposalDraftMemberPolicy
{
    public function accept(User $user, ProposalDraftMember $proposalDraftMember): bool
    {
        return $user->canUseWorkspace(User::WORKSPACE_FACULTY)
            && $proposalDraftMember->user_id === $user->getKey()
            && ! $proposalDraftMember->isAccepted();
    }
}
