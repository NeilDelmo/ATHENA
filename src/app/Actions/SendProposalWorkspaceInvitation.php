<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Notifications\ProposalActivityNotification;
use App\Notifications\ProposalWorkspaceInvitation;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendProposalWorkspaceInvitation
{
    public function handle(ProposalDraft $proposalDraft, ProposalDraftMember $member): void
    {
        $proposalDraft->loadMissing('owner:id,name');
        $member->loadMissing('user:id,name,email');

        $workspaceUrl = route('faculty.proposal-drafts.show', $proposalDraft);

        if ($member->user && ! $member->isAccepted()) {
            try {
                $member->user->notify(new ProposalActivityNotification(
                    title: 'Proposal workspace invitation',
                    message: $proposalDraft->owner->name.' invited you to collaborate on “'.$proposalDraft->project_title.'”.',
                    url: $workspaceUrl,
                    actionUrl: route('notifications.proposal-invitations.accept', $member),
                    actionData: [
                        'proposal_title' => $proposalDraft->project_title,
                        'inviter_name' => $proposalDraft->owner->name,
                    ],
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        Notification::route('mail', [
            $member->email => $member->name,
        ])->notify(new ProposalWorkspaceInvitation(
            recipientName: $member->name,
            inviterName: $proposalDraft->owner->name,
            projectTitle: $proposalDraft->project_title,
            invitedEmail: $member->email,
            workspaceUrl: $workspaceUrl,
            accountLinked: $member->isLinked(),
            requiresAcceptance: $member->isLinked() && ! $member->isAccepted(),
        ));
    }
}
