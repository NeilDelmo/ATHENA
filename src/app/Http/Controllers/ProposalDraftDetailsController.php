<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDetails;
use App\Http\Requests\UpdateProposalDraftDetailsRequest;
use App\Models\ProposalDraft;
use App\Support\ProposalWorkspacePeople;
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
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $saveProposalDraftDetails->handle(
            $proposalDraft,
            $request->integer('draft_version'),
            $request->validated(),
        );

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
