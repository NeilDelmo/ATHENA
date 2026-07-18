<?php

namespace App\Policies;

use App\Models\TopicProposal;
use App\Models\User;

class TopicProposalPolicy
{
    public function generateCommentResponseForm(User $user, TopicProposal $topicProposal): bool
    {
        return $user->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ])
            && $topicProposal->user_id === $user->id
            && $topicProposal->status === 'revision_requested';
    }
}
