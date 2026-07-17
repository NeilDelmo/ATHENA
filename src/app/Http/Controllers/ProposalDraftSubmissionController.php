<?php

namespace App\Http\Controllers;

use App\Actions\SubmitProposalDraft;
use App\Http\Requests\SubmitProposalDraftRequest;
use App\Models\ProposalDraft;
use App\Support\ProposalDraftReadiness;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProposalDraftSubmissionController extends Controller
{
    public function show(
        ProposalDraft $proposalDraft,
        ProposalDraftReadiness $readiness,
    ) {
        Gate::authorize('view', $proposalDraft);

        $proposalDraft->load(['researchCall', 'documents', 'owner']);
        $checklist = $readiness->checklist($proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);
        $readinessErrors = $readiness->errors($proposalDraft);
        $readyToSubmit = $readinessErrors === [];

        return view('faculty.proposal-drafts.review', compact(
            'proposalDraft',
            'checklist',
            'projectDetailsComplete',
            'readinessErrors',
            'readyToSubmit',
        ));
    }

    public function store(
        SubmitProposalDraftRequest $request,
        ProposalDraft $proposalDraft,
        SubmitProposalDraft $submitProposalDraft,
    ) {
        try {
            $submitProposalDraft->handle($proposalDraft, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ModelNotFoundException) {
            return redirect()
                ->route('faculty.proposal-drafts.index')
                ->withErrors(['status' => 'This proposal draft has already been submitted or deleted.']);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'submission' => 'The proposal package could not be finalized. Your draft and staged papers were kept so you can try again.',
            ]);
        }

        return redirect()
            ->route('faculty.dashboard')
            ->with('success', 'Proposal submitted successfully and sent to the Research Head.');
    }
}
