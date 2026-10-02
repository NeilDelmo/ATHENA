<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\TopicProposal;

class SyncTopicCollaborators
{
    public function handle(ProposalDraft $draft, TopicProposal $topic): void
    {
        $members = $draft->members()->get();

        $existing = $topic->collaborators()->get();
        $retainedIds = [];
        foreach ($members as $draftMember) {
            $member = $draftMember->only(['user_id', 'name', 'email', 'accepted_at', 'project_role']);
            $collaborator = $existing->first(fn ($candidate): bool => ($member['user_id'] !== null && $candidate->user_id === $member['user_id'])
                || mb_strtolower(trim($candidate->email)) === mb_strtolower(trim($member['email'])));
            if ($collaborator !== null) {
                $member['user_id'] = $member['user_id'] ?? $collaborator->user_id;
                $member['accepted_at'] = $collaborator->accepted_at ?? $member['accepted_at'];
                $collaborator->update($member);
                $draftMember->update(['user_id' => $member['user_id'], 'accepted_at' => $member['accepted_at']]);
            } else {
                $collaborator = $topic->collaborators()->create($member);
            }
            $retainedIds[] = $collaborator->id;
        }

        $topic->collaborators()->whereNull('accepted_at')->whereNotIn('id', $retainedIds)->delete();
        foreach ($existing->whereNotNull('accepted_at')->whereNotIn('id', $retainedIds) as $collaborator) {
            $draft->members()->create($collaborator->only(['user_id', 'name', 'email', 'accepted_at', 'project_role']));
        }
        $draft->unsetRelation('members');

        $secretaryId = $topic->collaborators()
            ->where('project_role', ProposalDraftMember::ROLE_SECRETARY)
            ->whereNotNull('accepted_at')
            ->whereNotNull('user_id')
            ->value('user_id');

        $topic->update(['research_secretary_id' => $secretaryId]);

        $topic->unsetRelation('collaborators');
        $topic->unsetRelation('researchSecretary');
    }
}
