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
            ])
            ->all();

        $topic->collaborators()->delete();

        if ($members !== []) {
            $topic->collaborators()->createMany($members);
        }

        $topic->unsetRelation('collaborators');
    }
}
