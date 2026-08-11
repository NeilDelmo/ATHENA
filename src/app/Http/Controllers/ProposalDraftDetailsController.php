<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDetails;
use App\Http\Requests\UpdateProposalDraftDetailsRequest;
use App\Models\ProposalDraft;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProposalDraftDetailsController extends Controller
{
    public function edit(
        ProposalDraft $proposalDraft,
        ProposalWorkspacePeople $proposalWorkspacePeople,
    ): View {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->load(['researchCall', 'owner:id,name,email,college']);
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);

        return view('faculty.proposal-drafts.details.edit', compact('proposalDraft', 'workspacePeople'));
    }

    public function update(
        UpdateProposalDraftDetailsRequest $request,
        ProposalDraft $proposalDraft,
        SaveProposalDraftDetails $saveProposalDraftDetails,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $savedDraft = $saveProposalDraftDetails->handle(
            $proposalDraft,
            $request->integer('draft_version'),
            $request->validated(),
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Project details saved.',
                'draft_version' => $savedDraft->lock_version,
            ]);
        }

        return redirect()
            ->route(
                $request->boolean('exit_after_save')
                    ? 'faculty.proposal-drafts.show'
                    : 'faculty.proposal-drafts.details.edit',
                $proposalDraft,
            )
            ->with('success', 'Project details saved.');
    }
}
