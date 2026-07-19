<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Notifications\ProposalWorkspaceInvitation;
use Illuminate\Support\Facades\Notification;

class SendProposalWorkspaceInvitation
{
    public function handle(ProposalDraft $proposalDraft, ProposalDraftMember $member): void
    {
        $proposalDraft->loadMissing('owner:id,name');

        Notification::route('mail', [
            $member->email => $member->name,
        ])->notify(new ProposalWorkspaceInvitation(
            recipientName: $member->name,
            inviterName: $proposalDraft->owner->name,
            projectTitle: $proposalDraft->project_title,
            invitedEmail: $member->email,
            workspaceUrl: route('faculty.proposal-drafts.show', $proposalDraft),
            accountLinked: $member->isLinked(),
        ));
    }
}
