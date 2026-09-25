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

        $topic->collaborators()->delete();

        if ($members !== []) {
            $topic->collaborators()->createMany($members);
        }

        $secretaryId = $draft->members()
            ->where('project_role', ProposalDraftMember::ROLE_SECRETARY)
            ->whereNotNull('accepted_at')
            ->whereNotNull('user_id')
            ->value('user_id');

        $topic->update(['research_secretary_id' => $secretaryId]);

        $topic->unsetRelation('collaborators');
        $topic->unsetRelation('researchSecretary');
    }
}
