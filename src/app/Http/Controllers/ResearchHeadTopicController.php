<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinalizeResearchHeadTopicApprovalRequest;
use App\Http\Requests\UpdateResearchHeadTopicStatusRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalPackageService;
use App\Services\ProposalSignatureWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResearchHeadTopicController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['pending', 'expert_review', 'for_final_decision', 'revision_requested', 'resubmitted', TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved', 'rejected'];

        $summary = [
            'awaiting_review' => TopicProposal::whereIn('status', ['pending', 'resubmitted', 'expert_review', 'for_final_decision'])->count(),
            'revision_requested' => TopicProposal::where('status', 'revision_requested')->count(),
            'ready_for_signature' => TopicProposal::where('status', TopicProposal::STATUS_READY_FOR_SIGNATURE)->count(),
            'awaiting_notice' => TopicProposal::where('status', 'approved')->whereNull('notice_to_proceed_issued_at')->count(),
            'approved' => TopicProposal::monitoringAvailable()->count(),
            'rejected' => TopicProposal::where('status', 'rejected')->count(),
            'drafts' => ProposalDraft::query()
                ->where('status', ProposalDraft::STATUS_DRAFT)
                ->whereHas('researchCall', function ($query): void {
                    $query->where('status', 'open')
                        ->where('opens_at', '<=', now())
                        ->where('closes_at', '>=', now());
                })
                ->count(),
        ];

        $topics = TopicProposal::with([
            'user', 'researchCall', 'category', 'versions.submitter', 'versions.files', 'reviews.reviewer', 'progressReports:id,topic_id,review_status',
        ])
            ->when(in_array($status, $allowedStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('research_head.dashboard', compact('topics', 'summary', 'status', 'search'));
    }

    public function updateStatus(
        UpdateResearchHeadTopicStatusRequest $request,
        TopicProposal $topic,
        ProposalPackageService $packageService,
    ): RedirectResponse {
        $validated = $request->validated();
        $latestVersion = $topic->latestVersion()->with('files')->first();

        if (! $latestVersion instanceof ProposalVersion) {
            throw ValidationException::withMessages([
                'evaluation_document' => 'A submitted proposal version is required before a decision can be recorded.',
            ]);
        }

        $latestFacultyFiles = $latestVersion->files
            ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD);
        $selectedRevisionFiles = collect();
        $selectedSignatureFiles = collect();

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

        $screeningForm = $latestFacultyFiles
            ->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
        $evidenceDirectory = 'proposal-packages/'.$topic->user_id.'/'.$topic->id.'/review-evidence/'.Str::uuid();
        $evaluationAttributes = $packageService->storeHeadUpload(
            $request->file('evaluation_document'),
            $evidenceDirectory,
            [
                'source_version_file_id' => $screeningForm?->id,
                'target_document_type' => $screeningForm?->document_type,
                'purpose' => ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                'document_title' => $validated['evaluation_title'] ?? 'External evaluation document',
                'note' => $validated['comment'] ?? null,
                'decision' => $validated['status'],
                'required_signature_file_ids' => $selectedSignatureFiles->pluck('id')->all(),
            ],
        );

        try {
            DB::transaction(function () use (
                $request,
                $topic,
                $validated,
                $latestVersion,
                $evaluationAttributes,
                $selectedRevisionFiles,
            ): void {
                $reviewedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($reviewedTopic->status, ['pending', 'resubmitted', 'expert_review', 'for_final_decision'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'Only proposals awaiting a Research Head decision can be reviewed.',
                    ]);
                }

                if (in_array($validated['status'], ['approved', TopicProposal::STATUS_READY_FOR_SIGNATURE], true)) {
                    $this->ensureResearchWorkloadAvailable($reviewedTopic);
                }

                $lockedVersion = ProposalVersion::query()
                    ->whereKey($latestVersion->id)
                    ->where('topic_id', $reviewedTopic->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $position = ((int) $lockedVersion->files()
                    ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                    ->max('position')) + 1;
                $lockedVersion->files()->create([
                    ...$evaluationAttributes,
                    'position' => $position,
                    'uploaded_by' => $request->user()->id,
                ]);

                $reviewedTopic->update(['status' => $validated['status']]);
                $reviewedTopic->expertAssignments()
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);

                if ($validated['status'] === 'revision_requested') {
                    $reviewedTopic->expertAssignments()
                        ->where('status', 'completed')
                        ->update(['status' => 'superseded']);
                }

                $review = $reviewedTopic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' => $validated['status'],
                    'comment' => $validated['comment'] ?? null,
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
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($evaluationAttributes['file_path']);

            throw $exception;
        }

        $notificationDetails = match ($validated['status']) {
            'approved' => ['Proposal papers approved', 'Your proposal papers for “'.$topic->title.'” were approved. Wait for the Notice to Proceed before beginning project monitoring.', 'success'],
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
            'rejected' => ['Proposal rejected', 'Your proposal “'.$topic->title.'” was not approved. Review the decision comments and evaluation document.', 'danger'],
        };

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
        ));

        $message = match ($validated['status']) {
            'approved' => 'Proposal papers approved. The faculty member is now waiting for a Notice to Proceed.',
            TopicProposal::STATUS_READY_FOR_SIGNATURE => 'Review completed. Upload the required signed PDFs, then finalize approval.',
            'revision_requested' => 'Revision requested; your comments and evaluation document were shared with the faculty member.',
            'rejected' => 'Proposal rejected; your comments and evaluation document were shared with the faculty member.',
        };

        $redirectRoute = ($validated['redirect_to'] ?? null) === 'topic' ? 'topics.show' : 'research_head.dashboard';

        return redirect()->route($redirectRoute, $redirectRoute === 'topics.show' ? $topic : [])->with('success', $message);
    }

    public function finalizeApproval(
        FinalizeResearchHeadTopicApprovalRequest $request,
        TopicProposal $topic,
        ProposalSignatureWorkflow $signatureWorkflow,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $topic, $signatureWorkflow): void {
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

            $this->ensureResearchWorkloadAvailable($reviewedTopic);
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
            'Proposal papers approved',
            'All required signed final copies for “'.$topic->title.'” are available. Wait for the Notice to Proceed before beginning project monitoring.',
            route('topics.show', $topic),
            'success',
            $topic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
        ));

        return redirect()
            ->to(route('topics.show', $topic).'#proposal-review')
            ->with('success', 'Proposal approved. The signed final copies are available; monitoring will open after the Notice to Proceed is issued.');
    }

    private function ensureResearchWorkloadAvailable(TopicProposal $topic): void
    {
        $researchCall = $topic->researchCall()->firstOrFail();
        $approvedProjectIds = TopicProposal::query()
            ->where('user_id', $topic->user_id)
            ->whereKeyNot($topic->getKey())
            ->where('status', 'approved')
            ->whereHas('researchCall', fn ($query) => $query->where('academic_year', $researchCall->academic_year))
            ->lockForUpdate()
            ->pluck('id');

        if ($approvedProjectIds->count() >= $researchCall->max_active_research_per_faculty) {
            throw ValidationException::withMessages([
                'status' => "This faculty researcher already has the maximum of {$researchCall->max_active_research_per_faculty} approved research projects for academic year {$researchCall->academic_year}. Applications remain unlimited, but another project cannot be approved for that year.",
            ]);
        }
    }

    /**
     * Build the URL a faculty member should land on after receiving a
     * Research Head decision. For revision requests we deep-link straight into
     * the PDF annotation workspace for the first highlighted comment, so the
     * faculty user is dropped on the exact passage that needs a change instead
     * of having to hunt through the proposal overview.
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

        return route('topics.versions.files.annotations.index', [$topic, $latestVersion, $annotation->file])
            .'?annotation='.$annotation->id
            .'#proposal-review';
    }

    private function canAnnotateRevisionFile(ProposalVersionFile $file): bool
    {
        return Storage::disk('local')->exists($file->file_path)
            && ($file->mime_type === 'application/pdf'
                || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf');
    }
}
