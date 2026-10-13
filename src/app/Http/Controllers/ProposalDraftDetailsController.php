<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDetails;
use App\Http\Requests\UpdateProposalDraftDetailsRequest;
use App\Models\ProposalDraft;
use App\Support\ProposalDraftReadiness;
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

        $proposalDraft->load(['researchCall', 'owner:id,name,email,college,contact_number,professional_title,department,avatar']);
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);

        return view('faculty.proposal-drafts.details.edit', compact('proposalDraft', 'workspacePeople'));
    }

    public function update(
        UpdateProposalDraftDetailsRequest $request,
        ProposalDraft $proposalDraft,
        SaveProposalDraftDetails $saveProposalDraftDetails,
        ProposalDraftReadiness $readiness,
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
                'project_details_complete' => $readiness->projectDetailsAreComplete($savedDraft),
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
