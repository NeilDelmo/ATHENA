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
use App\Services\FacultyProjectCapacityService;
use App\Services\ProposalSignatureWorkflow;
use App\Services\SidebarAttentionService;
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

        if ($topic->status === 'revision_requested') {
            throw ValidationException::withMessages([
                'status' => 'A revision round is already open. Wait for the faculty member to submit the current revision before recording another decision.',
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
        $selectedRevisionFiles = collect();
        $selectedSignatureFiles = collect();
        $returningFromSigning = $topic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE
            && $validated['status'] === 'revision_requested';

        if ($validated['status'] === 'revision_requested') {
            $selectedIds = collect($validated['revision_file_ids'] ?? [])->map(fn ($id) => (int) $id);
            $selectedRevisionFiles = $latestFacultyFiles->whereIn('id', $selectedIds)->values();

            if ($latestFacultyFiles->isEmpty()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'A submitted proposal file is required before a highlighted revision can be requested.',
                ]);
            }

            if ($selectedIds->isEmpty()) {
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
                ->whereNull('topic_review_file_revision_id')
                ->distinct()
                ->pluck('proposal_version_file_id');
            $filesMissingHighlights = $annotatableRevisionFiles
                ->whereNotIn('id', $highlightedFileIds)
                ->values();

            if ($filesMissingHighlights->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Add and save at least one highlighted comment to each selected PDF before requesting revision: '.$filesMissingHighlights->map->label()->join(', ').'.',
                ]);
            }

            $filesMissingInstructions = $selectedRevisionFiles
                ->reject(fn (ProposalVersionFile $file): bool => $this->canAnnotateRevisionFile($file))
                ->filter(fn (ProposalVersionFile $file): bool => blank($validated['revision_file_notes'][$file->id] ?? null));

            if ($filesMissingInstructions->isNotEmpty()) {
                throw ValidationException::withMessages(
                    $filesMissingInstructions->mapWithKeys(fn (ProposalVersionFile $file): array => [
                        'revision_file_notes.'.$file->id => 'Give exact revision instructions for '.$file->label().' because this file cannot be highlighted in the PDF viewer.',
                    ])->all(),
                );
            }
        }

        if ($validated['status'] === TopicProposal::STATUS_READY_FOR_SIGNATURE) {
            $selectedIds = collect($validated['signature_file_ids'] ?? [])->map(fn ($id) => (int) $id);
            $selectedSignatureFiles = $latestFacultyFiles->whereIn('id', $selectedIds)->values();

            if ($selectedSignatureFiles->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'signature_file_ids' => 'Every selected signature paper must belong to the latest proposal version.',
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
        ): void {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $isReturningFromSigning = $reviewedTopic->status === TopicProposal::STATUS_READY_FOR_SIGNATURE
                && $validated['status'] === 'revision_requested';

            if (! $isReturningFromSigning
                && ! in_array($reviewedTopic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision'], true)) {
                throw ValidationException::withMessages([
                    'status' => $reviewedTopic->status === 'revision_requested'
                        ? 'A revision round is already open. Wait for the faculty member to submit the current revision before recording another decision.'
                        : 'Only proposals awaiting a Research Head decision can be reviewed.',
                ]);
            }

            $reviewedTopic->update(['status' => $validated['status']]);

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
                .'“'.$topic->title.'”. Review the highlighted comments and file-specific instructions, then submit a new version.';

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
            TopicProposal::STATUS_READY_FOR_SIGNATURE => 'Review completed. Upload the required signed PDFs, then finalize approval.',
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
        FacultyProjectCapacityService $capacityService,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $topic, $signatureWorkflow, $capacityService): void {
            $reviewedTopic = TopicProposal::query()
                ->whereKey($topic->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($reviewedTopic->status !== TopicProposal::STATUS_READY_FOR_SIGNATURE) {
                throw ValidationException::withMessages([
                    'status' => 'Only proposals that are ready for signature can be finalized.',
                ]);
            }

            $latestVersion = ProposalVersion::query()
                ->where('topic_id', $reviewedTopic->id)
                ->with('files')
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->firstOrFail();
            $requiredSignatureFiles = $signatureWorkflow->requiredFiles($latestVersion);

            if ($requiredSignatureFiles->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'No papers were selected for final signing. Record the Research Head decision again and select the applicable papers.',
                ]);
            }

            $missingSignatureFiles = $signatureWorkflow->missingRequiredFiles($latestVersion);

            if ($missingSignatureFiles->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => 'Upload signed PDFs for: '.$missingSignatureFiles->map->label()->join(', ').'.',
                ]);
            }

            $capacityService->ensureAvailableFor($reviewedTopic);
            $reviewedTopic->update([
                'status' => 'approved',
                'project_status' => null,
            ]);
            $reviewedTopic->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision' => 'approved',
                'comment' => 'All required signed final copies were uploaded and the proposal was released as approved.',
            ]);
        });

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'Signed documents ready',
            'All required signed final copies for “'.$topic->title.'” are now available. The proposal is approved and waiting for its Notice to Proceed.',
            route('topics.show', $topic),
            'success',
            $topic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
            sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
        ));

        return redirect()
            ->to(route('topics.show', $topic).'#proposal-review')
            ->with('success', 'Signed documents finalized and released. Monitoring will open after the Notice to Proceed is issued.');
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
            ->whereHas('fileRevision.review', fn ($query) => $query->where('topic_id', $topic->id))
            ->whereHas('file', fn ($query) => $query->where('proposal_version_id', $latestVersion->id))
            ->with('file')
            ->orderBy('page_number')
            ->orderBy('id')
            ->first();

        if (! $annotation || ! $annotation->file) {
            return route('topics.show', $topic).'#submit-revision';
        }

        return route('topics.show', ['topic' => $topic, 'revision_annotation' => $annotation->id])
            .'#submit-revision';
    }

    private function canAnnotateRevisionFile(ProposalVersionFile $file): bool
    {
        return Storage::disk('local')->exists($file->file_path)
            && ($file->mime_type === 'application/pdf'
                || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf');
    }
}
