<?php

namespace App\Http\Controllers;

use App\Actions\ManageProposalDraftDeletion;
use App\Http\Requests\StoreProposalDraftDeletionRequest;
use App\Models\ProposalDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProposalDraftDeletionRequestController extends Controller
{
    public function __construct(private readonly ManageProposalDraftDeletion $deletion) {}

    public function create(ProposalDraft $proposalDraft): View|RedirectResponse
    {
        Gate::authorize('organize', $proposalDraft);
        $existing = $proposalDraft->pendingDeletionRequest()->first();
        if ($existing !== null) {
            return redirect()->route('faculty.proposal-drafts.deletion.show', [$proposalDraft, $existing]);
        }

        abort_unless($proposalDraft->hasAcceptedMembers(), 404);

        return view('faculty.proposal-drafts.deletion-request', compact('proposalDraft'));
    }

    public function store(StoreProposalDraftDeletionRequest $request, ProposalDraft $proposalDraft): RedirectResponse
    {
        $deletionRequest = $this->deletion->request($proposalDraft, $request->user(), $request->validated('reason'));

        return redirect()->route('faculty.proposal-drafts.deletion.show', [$proposalDraft, $deletionRequest]);
    }

    public function show(Request $request, ProposalDraft $proposalDraft, int $deletionRequest): View
    {
        if ($proposalDraft->trashed()) {
            Gate::authorize('viewAny', ProposalDraft::class);
            abort_unless(ProposalDraft::query()->onlyTrashed()->trashAccessibleTo($request->user())->whereKey($proposalDraft)->exists(), 404);
        } else {
            Gate::authorize('view', $proposalDraft);
        }

        $deletionRequest = $proposalDraft->deletionRequests()->findOrFail($deletionRequest);
        $teamChanged = $deletionRequest->isPending() && $this->deletion->teamHasChanged($proposalDraft, $deletionRequest);
        $memberId = $proposalDraft->acceptedMembers()->forUser($request->user())->value('id');
        $canRespond = $deletionRequest->isPending() && ! $teamChanged && ! $proposalDraft->trashed()
            && $proposalDraft->status === ProposalDraft::STATUS_DRAFT
            && ! $proposalDraft->isOwnedBy($request->user())
            && in_array($memberId, $deletionRequest->member_ids, true);
        $hasApproved = in_array($memberId, $deletionRequest->approved_member_ids, true);

        return view('faculty.proposal-drafts.deletion-request', compact('proposalDraft', 'deletionRequest', 'teamChanged', 'canRespond', 'hasApproved'));
    }

    public function update(Request $request, ProposalDraft $proposalDraft, int $deletionRequest): RedirectResponse
    {
        $validated = $request->validate(['decision' => ['required', Rule::in(['approve', 'decline', 'cancel'])]]);
        $result = $this->deletion->respond($proposalDraft, $deletionRequest, $request->user(), $validated['decision']);

        return redirect()->route('faculty.proposal-drafts.deletion.show', [$proposalDraft, $result]);
    }
}
