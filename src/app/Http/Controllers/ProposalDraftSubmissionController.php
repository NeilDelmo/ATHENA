<?php

namespace App\Http\Controllers;

use App\Actions\SaveProposalDraftDocument;
use App\Actions\SubmitProposalDraft;
use App\Http\Requests\SubmitProposalDraftRequest;
use App\Models\ProposalDraft;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProposalDraftSubmissionController extends Controller
{
    public function show(
        ProposalDraft $proposalDraft,
        ProposalDraftReadiness $readiness,
        ProposalBudgetConsistency $proposalBudgetConsistency,
    ) {
        Gate::authorize('view', $proposalDraft);

        $proposalDraft->load(['researchCall', 'documents', 'owner', 'members.user']);
        $checklist = $readiness->checklist($proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);
        $readinessErrors = $readiness->errors($proposalDraft);
        $submissionFilesPrepared = $readiness->submissionFilesArePrepared($proposalDraft);
        $readyToPrepare = $readinessErrors === [];
        $readyToSubmit = $readyToPrepare && $submissionFilesPrepared;
        $budgetConsistency = $proposalBudgetConsistency->compare($proposalDraft);

        return view('faculty.proposal-drafts.review', compact(
            'proposalDraft',
            'checklist',
            'projectDetailsComplete',
            'readinessErrors',
            'readyToPrepare',
            'submissionFilesPrepared',
            'readyToSubmit',
            'budgetConsistency',
        ));
    }

    public function prepare(
        Request $request,
        ProposalDraft $proposalDraft,
        SubmitProposalDraft $submitProposalDraft,
    ): RedirectResponse {
        Gate::authorize('submit', $proposalDraft);

        try {
            $submitProposalDraft->prepare($proposalDraft, $request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'preparation' => 'The submission PDFs could not be prepared. Your proposal data was kept, so you can try again.',
            ]);
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('proposal_tab', 'attachments')
            ->with('success', 'Seven PDF attachments prepared. Review or replace them before turning in.');
    }

    public function download(
        ProposalDraft $proposalDraft,
        string $paper,
        ProposalPaperCatalog $catalog,
    ): StreamedResponse {
        Gate::authorize('view', $proposalDraft);

        $paper = $catalog->find($paper);
        abort_unless(is_array($paper), 404);
        $document = $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->where('position', 0)
            ->firstOrFail();

        abort_unless(
            $document->mime_type === 'application/pdf'
                && filled($document->file_path)
                && Storage::disk('local')->exists($document->file_path),
            404,
        );

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function replace(
        Request $request,
        ProposalDraft $proposalDraft,
        string $paper,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): RedirectResponse {
        Gate::authorize('update', $proposalDraft);

        $paper = $catalog->find($paper);
        abort_unless(is_array($paper) && $paper['mode'] !== 'upload', 404);
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:25600'],
            'document_version' => ['required', 'integer', 'min:1'],
        ]);
        $document = $proposalDraft->documents()
            ->where('document_type', $paper['document_type'])
            ->where('position', 0)
            ->firstOrFail();
        $file = $validated['file'];
        $path = $file->storeAs(
            $proposalDraft->storageDirectory().'/prepared/manual/'.$paper['slug'],
            Str::uuid().'.pdf',
            'local',
        );

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => 'The replacement PDF could not be staged.',
            ]);
        }

        try {
            $saveProposalDraftDocument->handle(
                $proposalDraft,
                $request->user(),
                $paper['document_type'],
                0,
                (int) $validated['document_version'],
                [
                    'source_data' => $document->source_data,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => 'application/pdf',
                    'file_size' => $file->getSize() ?: null,
                    'checksum' => hash_file('sha256', Storage::disk('local')->path($path)) ?: null,
                    'completed_at' => $document->completed_at ?? now(),
                ],
                changeNote: 'Replaced the prepared system PDF before Turn in.',
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $proposalDraft)
            ->with('proposal_tab', 'attachments')
            ->with('success', $paper['label'].' replacement PDF staged for Turn in.');
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
            ->with('success', 'Proposal turned in successfully as a seven-PDF package and sent to the Research Head.');
    }
}
