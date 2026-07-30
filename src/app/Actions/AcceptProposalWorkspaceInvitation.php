<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;
//accept proposal workspace invitation
class AcceptProposalWorkspaceInvitation
{
    public function handle(User $user, ProposalDraftMember $proposalDraftMember): ProposalDraft
    {
        $proposalDraft = DB::transaction(function () use ($user, $proposalDraftMember): ProposalDraft {
            $membership = ProposalDraftMember::query()
                ->whereKey($proposalDraftMember->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->user_id !== $user->getKey()) {
                throw new AuthorizationException;
            }

            if (! $membership->isAccepted()) {
                $membership->update(['accepted_at' => now()]);
            }

            return $membership->draft()->firstOrFail();
        }, 3);

        $proposalDraft->loadMissing('owner:id,name');

        try {
            $proposalDraft->owner->notify(new ProposalActivityNotification(
                title: 'Collaborator accepted invitation',
                message: $user->name.' accepted your invitation to collaborate on “'.$proposalDraft->project_title.'”.',
                url: route('faculty.proposal-drafts.show', $proposalDraft),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $proposalDraft;
    }
}
