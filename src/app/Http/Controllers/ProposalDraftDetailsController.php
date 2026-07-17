<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProposalDraftDetailsRequest;
use App\Models\ProposalDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProposalDraftDetailsController extends Controller
{
    public function edit(ProposalDraft $proposalDraft): View
    {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->load('researchCall');

        return view('faculty.proposal-drafts.details.edit', compact('proposalDraft'));
    }

    public function update(
        UpdateProposalDraftDetailsRequest $request,
        ProposalDraft $proposalDraft,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $proposalDraft->update($request->validated());

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('success', 'Project details saved.');
    }
}
