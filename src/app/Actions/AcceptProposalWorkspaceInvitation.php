<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

// accept proposal workspace invitation
class AcceptProposalWorkspaceInvitation
{
    public function handle(User $user, ProposalDraftMember $proposalDraftMember): ProposalDraft
    {
        [$proposalDraft, $accepted] = DB::transaction(function () use ($user, $proposalDraftMember): array {
            $draft = $proposalDraftMember->draft()->lockForUpdate()->firstOrFail();
            $membership = $draft->members()->lockForUpdate()->findOrFail($proposalDraftMember->getKey());

            if ($membership->user_id !== $user->getKey()) {
                throw new AuthorizationException;
            }

            if ($membership->isAccepted()) {
                return [$draft, false];
            }

            $membership->update(['accepted_at' => now()]);

            return [$draft, true];
        }, 3);

        if (! $accepted) {
            return $proposalDraft;
        }

        $proposalDraft->loadMissing('owner:id,name');

        try {
            $proposalDraft->owner->notify(new ProposalActivityNotification(
                title: 'Project staff invitation accepted',
                message: $user->name.' accepted the project staff invitation for “'.$proposalDraft->project_title.'”.',
                url: route('faculty.proposal-drafts.show', $proposalDraft),
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposalDraft;
    }
}
