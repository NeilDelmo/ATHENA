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

        if ($this->isResearchOfficeLrecProposal($user, $topicProposal)) {
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
        if ($this->isResearchOfficeLrecProposal($user, $topicProposal)) {
            return true;
        }

        return $this->view($user, $topicProposal)
            && ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) || $topicProposal->user_id === $user->id);
    }

    public function recordLrecFeedback(User $user, TopicProposal $topicProposal): bool
    {
        return $this->isResearchOfficeLrecProposal($user, $topicProposal)
            && $topicProposal->status === TopicProposal::STATUS_LREC_REVIEW;
    }

    public function startLrecReview(User $user, TopicProposal $topicProposal): bool
    {
        return $this->isResearchOfficeLrecProposal($user, $topicProposal)
            && $topicProposal->status === TopicProposal::STATUS_LREC_QUEUED;
    }

    private function isResearchOfficeLrecProposal(User $user, TopicProposal $topicProposal): bool
    {
        return $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE)
            && filled($user->college)
            && $topicProposal->user?->college === $user->college
            && $topicProposal->review_stage === 'lrec'
            && in_array($topicProposal->status, [
                TopicProposal::STATUS_LREC_QUEUED,
                TopicProposal::STATUS_LREC_REVIEW,
                'revision_requested',
                'resubmitted',
                'pending',
                'expert_review',
                'for_final_decision',
            ], true);
    }
}
