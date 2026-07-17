<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalDraftMemberRequest;
use App\Models\ProposalDraft;
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
    ): RedirectResponse {
        $validated = $request->validated();
        $linkedUser = $request->linkedUser();

        DB::transaction(function () use ($proposalDraft, $validated, $linkedUser): void {
            $lockedDraft = ProposalDraft::query()
                ->whereKey($proposalDraft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDraft->members()->count() >= 50) {
                throw ValidationException::withMessages([
                    'email' => 'This proposal workspace already has the maximum of 50 collaborators.',
                ]);
            }

            $lockedDraft->members()->create([
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

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', $linkedUser
                ? $linkedUser->name.' can now contribute to this proposal workspace.'
                : $validated['name'].' was added as an external member. Their details can be reused, but they cannot sign in yet.');
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
