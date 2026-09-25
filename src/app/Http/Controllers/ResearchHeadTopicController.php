<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeResearchHeadTopicApprovalRequest;
use App\Http\Requests\UpdateResearchHeadTopicStatusRequest;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalSignatureWorkflow;
use App\Services\SidebarAttentionService;
use App\Support\InitialScreeningSubmissionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchHeadTopicController extends Controller
{
    public function index(Request $request)
    {
        return view('research_head.dashboard');
    }

    public function updateStatus(
        UpdateResearchHeadTopicStatusRequest $request,
        TopicProposal $topic,
        SidebarAttentionService $sidebarAttention,
    ): RedirectResponse {
        $validated = $request->validated();

        if (! $topic->canRecordDecision($validated['status'])) {
            $message = $topic->status === 'revision_requested'
                ? 'A revision round is already open. Wait for the faculty member to submit the current revision before recording another decision.'
                : 'Follow the current proposal stage. Research Head clearance is required before GAD review, and GAD and central evaluation must be complete before LREC.';

            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }

        $latestVersion = $topic->latestVersion()->with('files')->first();

        if (! $latestVersion instanceof ProposalVersion) {
            throw ValidationException::withMessages([
                'status' => 'A submitted proposal version is required before a decision can be recorded.',
            ]);
        }

        $latestFacultyFiles = $latestVersion->files
            ->whereNotIn('document_type', [
                ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                ProposalVersionFile::TYPE_HEAD_UPLOAD,
            ]);
        $committeeComments = $topic->review_stage === 'lrec' ? ($validated['committee_comments'] ?? []) : [];
        $gadChecklist = $latestFacultyFiles->firstWhere('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST);
        $initialScreeningForm = $latestFacultyFiles->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
        $hasPassingGadAssessment = $latestVersion->hasPassingGadAssessment();
        $coEvaluatorEvaluation = $latestVersion->files
            ->filter(fn (ProposalVersionFile $file): bool => $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
                && $file->source_version_file_id === $initialScreeningForm?->id
                && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
                && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM
                && filled($file->source_data['narrative_evaluation'] ?? null))
            ->sortByDesc('id')
            ->first();
        $hasCoEvaluatorNarrative = $coEvaluatorEvaluation instanceof ProposalVersionFile;
        $coEvaluatorRecommendedAction = $coEvaluatorEvaluation?->source_data['recommended_action'] ?? null;
        $coEvaluationRequiresRevision = in_array($coEvaluatorRecommendedAction, [
            InitialScreeningSubmissionOrder::MINOR_REVISION,
            InitialScreeningSubmissionOrder::MAJOR_REVISION,
        ], true);
        $selectedRevisionFiles = collect();
        $selectedSignatureFiles = collect();
        $returningFromSigning = $topic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE
            && $validated['status'] === 'revision_requested';

        if ($validated['status'] === TopicProposal::STATUS_LREC_QUEUED
            && (! $hasPassingGadAssessment || ! $hasCoEvaluatorNarrative || $coEvaluationRequiresRevision)) {
            $missingSteps = collect();

            if (! $hasPassingGadAssessment) {
                $missingSteps->push('record a passing GAD Office assessment');
            }

            if (! $hasCoEvaluatorNarrative) {
                $missingSteps->push('record the central evaluator’s Narrative Evaluation');
            } elseif ($coEvaluationRequiresRevision) {
                $missingSteps->push('complete the central evaluator’s '.str($coEvaluatorRecommendedAction)->replace('_', ' ')->toString().' and upload a new endorsed evaluation');
            }

            throw ValidationException::withMessages([
                'status' => 'Complete the required sequence before sending this proposal to LREC: '.$missingSteps->join(', ', ', then ').'.',
            ]);
        }

        if ($validated['status'] === 'revision_requested') {
            $selectedIds = collect($validated['revision_file_ids'] ?? [])->map(fn ($id) => (int) $id);
            $selectedRevisionFiles = $latestFacultyFiles->whereIn('id', $selectedIds)->values();

            if ($latestFacultyFiles->isEmpty()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'A submitted proposal file is required before a highlighted revision can be requested.',
                ]);
            }

            if ($selectedIds->isEmpty() && $committeeComments === []) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Select at least one proposal file that requires revision.',
                ]);
            }

            if ($selectedRevisionFiles->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Every selected file must belong to the latest proposal version.',
                ]);
            }

            $annotatableRevisionFiles = $selectedRevisionFiles
                ->filter(fn (ProposalVersionFile $file): bool => $this->canAnnotateRevisionFile($file));
            $highlightedFileIds = ProposalFileAnnotation::query()
                ->whereIn('proposal_version_file_id', $annotatableRevisionFiles->pluck('id'))
                ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
                ->whereNull('topic_review_file_revision_id')
                ->distinct()
                ->pluck('proposal_version_file_id');
            $filesMissingHighlights = $annotatableRevisionFiles
                ->whereNotIn('id', $highlightedFileIds)
                ->values();

            if ($filesMissingHighlights->isNotEmpty() && $committeeComments === [] && ! $hasCoEvaluatorNarrative) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Add and save at least one highlighted comment to each selected PDF before requesting revision: '.$filesMissingHighlights->map->label()->join(', ').'.',
                ]);
            }

            $filesMissingInstructions = $selectedRevisionFiles
                ->reject(fn (ProposalVersionFile $file): bool => $this->canAnnotateRevisionFile($file))
                ->filter(fn (ProposalVersionFile $file): bool => blank($validated['revision_file_notes'][$file->id] ?? null));

            if ($filesMissingInstructions->isNotEmpty() && $committeeComments === [] && ! $hasCoEvaluatorNarrative) {
                throw ValidationException::withMessages(
                    $filesMissingInstructions->mapWithKeys(fn (ProposalVersionFile $file): array => [
                        'revision_file_notes.'.$file->id => 'Give exact revision instructions for '.$file->label().' because this file cannot be highlighted in the PDF viewer.',
                    ])->all(),
                );
            }
        }

        if ($validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE) {
            $signatureWorkflow = app(ProposalSignatureWorkflow::class);
            $selectedSignatureFiles = $signatureWorkflow->requiredFiles($latestVersion);

            if (! $signatureWorkflow->hasRequiredPapers($latestVersion)) {
                throw ValidationException::withMessages([
                    'status' => 'The latest package must contain the Detailed Proposal, Work Plan, Line-Item Budget, GAD Checklist, and Initial Screening Form before signing.',
                ]);
            }
        }

        DB::transaction(function () use (
            $request,
            $topic,
            $validated,
            $latestVersion,
            $selectedRevisionFiles,
            $selectedSignatureFiles,
            $committeeComments,
        ): void {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isReturningFromSigning = $reviewedTopic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE
                && $validated['status'] === 'revision_requested';

            if (! $reviewedTopic->canRecordDecision($validated['status'])) {
                throw ValidationException::withMessages(['status' => 'This action is no longer available. Refresh the proposal and follow its current review stage.']);
            }

            $lockedVersion = $reviewedTopic->latestVersion()->lockForUpdate()->firstOrFail();
            if ($lockedVersion->id !== $latestVersion->id) {
                throw ValidationException::withMessages(['status' => 'A newer version was submitted. Review it before continuing.']);
            }

            $reviewStage = $reviewedTopic->review_stage;
            $reviewedTopic->update([
                'status' => $validated['status'],
                'review_stage' => match (true) {
                    $validated['status'] === TopicProposal::STATUS_GAD_REVIEW => 'gad',
                    $validated['status'] === TopicProposal::STATUS_LREC_QUEUED || $isReturningFromSigning => 'lrec',
                    default => $reviewStage,
                },
                'lrec_cleared_at' => $validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE ? now() : null,
                'notice_to_proceed_data' => $isReturningFromSigning ? null : $reviewedTopic->notice_to_proceed_data,
            ]);

            $reviewedTopic->expertAssignments()
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            if ($validated['status'] === 'revision_requested') {
                $reviewedTopic->expertAssignments()
                    ->where('status', 'completed')
                    ->update(['status' => 'superseded']);

                if ($isReturningFromSigning) {
                    $lockedVersion = ProposalVersion::query()
                        ->where('topic_id', $reviewedTopic->id)
                        ->with('files')
                        ->orderByDesc('version_number')
                        ->lockForUpdate()
                        ->firstOrFail();

                    $reviewedTopic->reviews()
                        ->where('decision', TopicProposal::STATUS_READY_FOR_SIGNATURE)
                        ->where('signature_proposal_version_id', $lockedVersion->id)
                        ->whereNull('signature_superseded_at')
                        ->update(['signature_superseded_at' => now()]);

                    $lockedVersion->files()
                        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
                        ->whereNull('superseded_at')
                        ->update(['superseded_at' => now()]);
                }
            }

            $review = $reviewedTopic->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision' => $validated['status'],
                'review_stage' => $reviewStage,
                'committee_comments' => $committeeComments,
                'comment' => $validated['status'] === 'rejected'
                    ? $validated['rejection_reason']
                    : null,
                'required_signature_file_ids' => $validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE
                    ? $selectedSignatureFiles->pluck('id')->all()
                    : [],
                'signature_proposal_version_id' => $validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE
                    ? $latestVersion->id
                    : null,
            ]);

            if ($validated['status'] === 'revision_requested') {
                $fileRevisions = $review->fileRevisions()->createMany($selectedRevisionFiles->map(fn ($file) => [
                    'proposal_version_file_id' => $file->id,
                    'document_type' => $file->document_type,
                    'original_filename' => $file->original_filename,
                    'revision_note' => $validated['revision_file_notes'][$file->id] ?? null,
                ])->all());

                foreach ($fileRevisions as $fileRevision) {
                    ProposalFileAnnotation::query()
                        ->where('proposal_version_file_id', $fileRevision->proposal_version_file_id)
                        ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
                        ->whereNull('topic_review_file_revision_id')
                        ->update(['topic_review_file_revision_id' => $fileRevision->id]);
                }
            }

        });

        $sidebarAttention->markTopicAsRead(
            $request->user(),
            ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
            $topic->id,
        );

        $notificationDetails = match ($validated['status']) {
            TopicProposal::STATUS_GAD_REVIEW => ['Cleared for GAD review', 'The Research Head cleared “'.$topic->title.'”. The corrected proposal now proceeds to the GAD Office before central evaluation.', 'info'],
            TopicProposal::STATUS_LREC_QUEUED => ['Queued for LREC', 'Initial review is complete for “'.$topic->title.'”. Await the LREC presentation schedule from the research office.', 'info'],
            TopicProposal::STATUS_LREC_REVIEW => ['LREC review started', 'The research office is recording the LREC outcome for “'.$topic->title.'”. Any revisions will be shared in one Comment-Response Form.', 'info'],
            TopicProposal::STATUS_READY_FOR_SIGNATURE => [
                'Proposal ready for signature',
                'The review of “'.$topic->title.'” is complete. The Research Head is preparing the required signed final copies.',
                'info',
            ],
            'revision_requested' => [
                'Revision requested',
                ($selectedRevisionFiles->isNotEmpty() ? $selectedRevisionFiles->count().' proposal file(s) require changes in ' : 'Changes were requested for ').'“'.$topic->title.'”. Review the comments and evaluation document, then submit a new version.',
                'warning',
            ],
            'rejected' => [
                'Proposal rejected',
                'The Research Head rejected the proposal “'.$topic->title.'”. This proposal is now closed.',
                'danger',
            ],
        };

        if ($validated['status'] === 'revision_requested') {
            $notificationDetails[1] = ($selectedRevisionFiles->isNotEmpty()
                ? $selectedRevisionFiles->count().' proposal file(s) require changes in '
                : 'Changes were requested for ')
                .'“'.$topic->title.'”. Review the Comment-Response Forms and the selected papers, then submit a new version.';

            if ($returningFromSigning) {
                $notificationDetails[1] .= ' Final signing is paused; previous signed copies were retained as superseded records and cannot be reused.';
            }
        }

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            $notificationDetails[0],
            $notificationDetails[1],
            $this->revisionDeepLinkUrl($topic, $latestVersion, $validated['status']),
            $notificationDetails[2],
            $topic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
        ));

        $message = match ($validated['status']) {
            TopicProposal::STATUS_GAD_REVIEW => 'Research Head review cleared. The proposal is now available for GAD Office assessment.',
            TopicProposal::STATUS_LREC_QUEUED => 'GAD and central evaluation cleared. The proposal is queued for LREC presentation.',
            TopicProposal::STATUS_LREC_REVIEW => 'LREC review opened. Record committee comments or confirm clearance.',
            TopicProposal::STATUS_READY_FOR_SIGNATURE => 'LREC cleared. Upload the signed papers and prepare the Notice to Proceed for one final release.',
            'revision_requested' => $returningFromSigning
                ? 'Revision requested. Final signing is paused and existing signed copies were retained as superseded records.'
                : 'Revision requested; highlighted comments and file-specific instructions were shared with the faculty member.',
            'rejected' => 'Proposal rejected.',
        };

        $redirectUrl = ($validated['redirect_to'] ?? null) === 'topic'
            ? route('topics.show', $topic)
            : route('research_head.dashboard');

        if ($validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE
            && ($validated['redirect_to'] ?? null) === 'topic') {
            $redirectUrl .= '#proposal-review';
        }

        return redirect()->to($redirectUrl)->with('success', $message);
    }

    public function finalizeApproval(
        FinalizeResearchHeadTopicApprovalRequest $request,
        TopicProposal $topic,
        ProposalSignatureWorkflow $signatureWorkflow,
    ): RedirectResponse {
        abort_unless($topic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE, 422);
        $version = $topic->latestVersion()->with('files')->firstOrFail();
        if (! $signatureWorkflow->isComplete($version)) {
            throw ValidationException::withMessages(['status' => 'Upload every required signed paper before preparing the final release.']);
        }

        return redirect()->to(route('topics.show', $topic).'#notice-to-proceed')
            ->with('success', 'Signed papers are ready. Upload the signed Notice to Proceed to release the complete package to faculty.');
    }

    /**
     * Build the URL a faculty member should land on after receiving a
     * Research Head decision. For revision requests we deep-link straight into
     * the PDF annotation workspace for the first highlighted comment, so the
     * faculty user sees the requested feedback beside the working editor.
     */
    private function revisionDeepLinkUrl(
        TopicProposal $topic,
        ?ProposalVersion $latestVersion,
        string $status,
    ): string {
        if ($status !== 'revision_requested' || ! $latestVersion instanceof ProposalVersion) {
            return route('topics.show', $topic);
        }

        $annotation = ProposalFileAnnotation::query()
            ->where('feedback_source', ProposalFileAnnotation::SOURCE_HEAD)
            ->whereHas('fileRevision.review', fn ($query) => $query->where('topic_id', $topic->id))
            ->whereHas('file', fn ($query) => $query->where('proposal_version_id', $latestVersion->id))
            ->with('file')
            ->orderBy('page_number')
            ->orderBy('id')
            ->first();

        if (! $annotation || ! $annotation->file) {
            return route('faculty.topics.revision', $topic);
        }

        return route('faculty.topics.revision', ['topic' => $topic, 'revision_annotation' => $annotation->id]);
    }

    private function canAnnotateRevisionFile(ProposalVersionFile $file): bool
    {
        return Storage::disk('local')->exists($file->file_path)
            && ($file->mime_type === 'application/pdf'
                || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf');
    }
}
