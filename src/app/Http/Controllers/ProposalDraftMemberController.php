<?php

namespace App\Http\Controllers;

use App\Actions\SendProposalWorkspaceInvitation;
use App\Http\Requests\StoreProposalDraftMemberRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalDraftMemberController extends Controller
{
    public function store(
        StoreProposalDraftMemberRequest $request,
        ProposalDraft $proposalDraft,
        SendProposalWorkspaceInvitation $sendInvitation,
    ): RedirectResponse {
        $validated = $request->validated();
        $linkedUser = $request->linkedUser();

        $membership = DB::transaction(function () use ($proposalDraft, $validated, $linkedUser): ProposalDraftMember {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($proposalDraft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDraft->members()->count() >= 50) {
                throw ValidationException::withMessages([
                    'email' => 'This proposal workspace already has the maximum of 50 collaborators.',
                ]);
            }

            return $lockedDraft->members()->create([
                'user_id' => $linkedUser?->getKey(),
                'name' => $linkedUser?->name ?? $validated['name'],
                'email' => $linkedUser?->email ?? $validated['email'],
            ]);
        }, 3);

        if ($linkedUser) {
            try {
                $linkedUser->notify(new ProposalActivityNotification(
                    'Added to a proposal workspace',
                    $proposalDraft->owner()->value('name').' added you to “'.$proposalDraft->project_title.'”.',
                    route('faculty.proposal-drafts.show', $proposalDraft),
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        try {
            $sendInvitation->handle($proposalDraft, $membership);
            $invitationQueued = true;
        } catch (Throwable $exception) {
            report($exception);
            $invitationQueued = false;
        }

        if (! $invitationQueued) {
            return redirect()
                ->route('faculty.proposal-drafts.show', $proposalDraft)
                ->with('warning', $membership->name.' was added, but ATHENA could not queue the invitation email. You can resend it from the collaborator card.');
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $linkedUser
                ? 'Invitation sent to '.$linkedUser->name.'. They can now contribute to this proposal workspace.'
                : 'Invitation sent to '.$membership->name.'. Access will activate when they sign in with '.$membership->email.'.');
    }

    public function resend(
        ProposalDraft $proposalDraft,
        int $member,
        SendProposalWorkspaceInvitation $sendInvitation,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $proposalDraft);

        $proposalDraftMember = $proposalDraft->members()->findOrFail($member);

        try {
            $sendInvitation->handle($proposalDraft, $proposalDraftMember);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('faculty.proposal-drafts.show', $proposalDraft)
                ->withErrors(['invitation' => 'ATHENA could not queue the invitation email. Please try again.']);
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', 'Invitation email queued for '.$proposalDraftMember->name.'.');
    }

    public function destroy(
        ProposalDraft $proposalDraft,
        int $member,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $proposalDraft);

        $proposalDraftMember = $proposalDraft->members()->findOrFail($member);
        $name = $proposalDraftMember->name;
        $proposalDraftMember->delete();

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $name.' was removed from this proposal workspace.');
    }
}
