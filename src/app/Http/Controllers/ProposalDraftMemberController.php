<?php

namespace App\Http\Controllers;

use App\Actions\SendProposalWorkspaceInvitation;
use App\Http\Requests\StoreProposalDraftMemberRequest;
use App\Http\Requests\UpdateProposalDraftMemberRoleRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\TopicProposal;
use App\Services\FacultyProjectCapacityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        FacultyProjectCapacityService $capacityService,
    ): RedirectResponse {
        $validated = $request->validated();
        $linkedUser = $request->linkedUser();

        $membership = DB::transaction(function () use ($proposalDraft, $validated, $linkedUser): ProposalDraftMember {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($proposalDraft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDraft->ensureEditable();

            if ($lockedDraft->members()->count() >= 50) {
                throw ValidationException::withMessages([
                    'email' => 'This proposal workspace already has the maximum of 50 project staff members.',
                ]);
            }

            return $lockedDraft->members()->create([
                'user_id' => $linkedUser?->getKey(),
                'name' => $linkedUser?->name ?? $validated['name'],
                'email' => $linkedUser?->email ?? $validated['email'],
                'accepted_at' => null,
            ]);
        }, 3);

        $workloadWarning = $linkedUser
            ? $capacityService->warningForAdditionalParticipation($linkedUser, $proposalDraft->researchCall)
            : null;

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
                ->with('warning', $membership->name.' was added, but ATHENA could not queue the invitation email. You can resend it from the project staff card.')
                ->with('workload_warning', $workloadWarning);
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $linkedUser
                ? 'Invitation email queued for '.$linkedUser->name.'. Their project staff access starts after accepting it in ATHENA.'
                : 'Invitation email queued for '.$membership->name.'. They must sign in with '.$membership->email.' and accept before getting access.')
            ->with('workload_warning', $workloadWarning);
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

    public function resendSubmitted(
        Request $request,
        TopicProposal $topic,
        int $collaborator,
        SendProposalWorkspaceInvitation $sendInvitation,
    ): RedirectResponse {
        abort_unless($topic->user_id === $request->user()->getKey(), 403);
        $member = $topic->collaborators()->whereNull('accepted_at')->whereHas('latestInvitationEmail')->findOrFail($collaborator);
        $response = redirect()->route('topics.show', $topic);

        try {
            $sendInvitation->resendSubmitted($member);
        } catch (Throwable $exception) {
            report($exception);

            return $response->withErrors(['invitation' => 'ATHENA could not queue the invitation email. Please try again.']);
        }

        return $response->with('success', 'Invitation email queued for '.$member->name.'.');
    }

    public function updateRole(
        UpdateProposalDraftMemberRoleRequest $request,
        ProposalDraft $proposalDraft,
    ): RedirectResponse {
        $validated = $request->validated();
        $selectedMemberId = $validated['member_id'] ?? null;

        DB::transaction(function () use ($proposalDraft, $validated, $selectedMemberId): void {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($proposalDraft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDraft->ensureEditable();

            $lockedDraft->members()
                ->where('project_role', $validated['project_role'])
                ->update(['project_role' => null]);

            if ($selectedMemberId !== null) {
                $lockedDraft->members()
                    ->whereKey($selectedMemberId)
                    ->whereNotNull('accepted_at')
                    ->whereNotNull('user_id')
                    ->firstOrFail()
                    ->update(['project_role' => $validated['project_role']]);
            }
        }, 3);

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $selectedMemberId === null
                ? 'Project Secretary assignment removed.'
                : 'Project Secretary assigned for the full research project.');
    }

    public function destroy(
        ProposalDraft $proposalDraft,
        int $member,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $proposalDraft);

        $name = DB::transaction(function () use ($proposalDraft, $member): string {
            $lockedDraft = ProposalDraft::query()->whereKey($proposalDraft->getKey())->lockForUpdate()->firstOrFail();
            $lockedDraft->ensureEditable();
            $membership = $lockedDraft->members()->lockForUpdate()->findOrFail($member);
            $membership->setRelation('draft', $lockedDraft);
            Gate::authorize('remove', $membership);
            if ($membership->isAccepted() && $lockedDraft->hasPendingDeletionRequest()) {
                throw ValidationException::withMessages([
                    'member' => 'Cancel the pending deletion request before removing an accepted teammate.',
                ]);
            }
            $membership->delete();

            return $membership->name;
        }, 3);

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $name.' was removed from this proposal workspace.');
    }
}
