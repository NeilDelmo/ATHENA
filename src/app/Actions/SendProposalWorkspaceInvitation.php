<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ProposalInvitationEmail;
use App\Models\TopicCollaborator;
use App\Notifications\ProposalActivityNotification;
use App\Notifications\ProposalWorkspaceInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class SendProposalWorkspaceInvitation
{
    public function handle(ProposalDraft $proposalDraft, ProposalDraftMember $member): void
    {
        $proposalDraft->loadMissing('owner:id,name');
        $member->loadMissing('user:id,name,email');

        $workspaceUrl = route('notifications.proposal-invitations.show', $member);

        if ($member->user && ! $member->isAccepted()) {
            try {
                $member->user->notify(new ProposalActivityNotification(
                    title: 'Project staff invitation',
                    message: $proposalDraft->owner->name.' invited you to serve as project staff for “'.$proposalDraft->project_title.'”.',
                    url: $workspaceUrl,
                    actionUrl: route('notifications.proposal-invitations.accept', $member),
                    actionData: [
                        'proposal_title' => $proposalDraft->project_title,
                        'inviter_name' => $proposalDraft->owner->name,
                    ],
                    sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $delivery = DB::transaction(function () use ($member): ProposalInvitationEmail {
            $activeMember = ProposalDraftMember::query()->lockForUpdate()->find($member->id);
            if ($activeMember === null) {
                return ProposalInvitationEmail::query()->whereNotNull('topic_collaborator_id')->findOrFail($member->id);
            }

            return ProposalInvitationEmail::query()->firstOrCreate(
                ['id' => $member->id],
                ['draft_member_id' => $activeMember->id],
            );
        }, 3);

        $this->queueEmail($delivery, new ProposalWorkspaceInvitation(
            recipientName: $member->name,
            inviterName: $proposalDraft->owner->name,
            projectTitle: $proposalDraft->project_title,
            invitedEmail: $member->email,
            workspaceUrl: $workspaceUrl,
            accountLinked: $member->isLinked(),
            requiresAcceptance: ! $member->isAccepted(),
        ));
    }

    public function resendSubmitted(TopicCollaborator $collaborator): void
    {
        $delivery = $collaborator->latestInvitationEmail()->firstOrFail();
        $collaborator->loadMissing('topic.user:id,name');

        $this->queueEmail($delivery, new ProposalWorkspaceInvitation(
            recipientName: $collaborator->name,
            inviterName: $collaborator->topic->user->name,
            projectTitle: $collaborator->topic->title,
            invitedEmail: $collaborator->email,
            workspaceUrl: route('notifications.proposal-invitations.show', $delivery->id),
            accountLinked: $collaborator->user_id !== null,
            requiresAcceptance: true,
        ));
    }

    private function queueEmail(ProposalInvitationEmail $delivery, ProposalWorkspaceInvitation $invitation): void
    {
        $attempt = (string) Str::uuid();
        $delivery->update(['attempt' => $attempt, 'status' => ProposalInvitationEmail::QUEUED]);
        $invitation->invitationId = $delivery->id;
        $invitation->attempt = $attempt;

        try {
            Notification::route('mail', [
                $invitation->invitedEmail => $invitation->recipientName,
            ])->notify($invitation);
        } catch (Throwable $exception) {
            ProposalInvitationEmail::recordResult($delivery->id, $attempt, ProposalInvitationEmail::FAILED);

            throw $exception;
        }
    }
}
