<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\TopicProposal;

class SyncTopicCollaborators
{
    public function handle(ProposalDraft $draft, TopicProposal $topic): void
    {
        $members = $draft->members()
            ->get()
            ->map(fn (ProposalDraftMember $member): array => [
                'user_id' => $member->user_id,
                'name' => $member->name,
                'email' => $member->email,
                'accepted_at' => $member->accepted_at,
                'project_role' => $member->project_role,
            ])
            ->all();

        $existing = $topic->collaborators()->get();
        $retainedIds = [];
        foreach ($members as $member) {
            $collaborator = $existing->first(fn ($candidate): bool =>
                ($member['user_id'] !== null && $candidate->user_id === $member['user_id'])
                || mb_strtolower(trim($candidate->email)) === mb_strtolower(trim($member['email'])));
            if ($collaborator !== null) {
                $member['accepted_at'] = $collaborator->accepted_at ?? $member['accepted_at'];
                $collaborator->update($member);
            } else {
                $collaborator = $topic->collaborators()->create($member);
            }
            $retainedIds[] = $collaborator->id;
        }

        $topic->collaborators()->whereNull('accepted_at')->whereNotIn('id', $retainedIds)->delete();

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
