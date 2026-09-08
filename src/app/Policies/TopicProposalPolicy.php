<?php

namespace App\Policies;

use App\Models\TopicProposal;
use App\Models\User;

class TopicProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsingWorkspace('research_head');
    }

    public function view(User $user, TopicProposal $topicProposal): bool
    {
        if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return true;
        }

        if (! $topicProposal->isAccessibleTo($user)) {
            return false;
        }

        return $user->isUsingWorkspace(User::WORKSPACE_FACULTY)
            || ($user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
                && $topicProposal->isVisibleInResearcherWorkspace());
    }

    public function generateCommentResponseForm(User $user, TopicProposal $topicProposal): bool
    {
        return $this->view($user, $topicProposal)
            && ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) || $topicProposal->user_id === $user->id);
    }
}
