<?php

namespace App\Policies;

use App\Models\ResearchOutcome;
use App\Models\TopicProposal;
use App\Models\User;

class ResearchOutcomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isUsingWorkspace([
            User::WORKSPACE_FACULTY_RESEARCHER,
            User::WORKSPACE_RESEARCH_OFFICE,
            User::WORKSPACE_RESEARCH_HEAD,
        ]);
    }

    public function view(User $user, ResearchOutcome $outcome): bool
    {
        if (! $this->viewAny($user) || ($outcome->topic_id && ! $outcome->topic?->isCompletedProject())) {
            return false;
        }

        if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return true;
        }

        if ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE)) {
            return $outcome->topic_id ? $outcome->topic->belongsToCollege($user->college)
                : in_array(mb_strtolower($outcome->projectCollege() ?? ''), array_map(mb_strtolower(...), TopicProposal::collegeAliases($user->college)), true);
        }

        return $outcome->topic_id
            ? $outcome->topic->isAccessibleTo($user)
            : $outcome->leader_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER);
    }

    public function update(User $user, ResearchOutcome $outcome): bool
    {
        return $this->create($user) && $this->view($user, $outcome)
            && $outcome->projectLeader()->id === $user->id;
    }
}
