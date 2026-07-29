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
use Spatie\Permission\Models\Role;
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
            'approved' => TopicProposal::where('status', 'approved')->count(),
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

            if ($latestFacultyFiles->isNotEmpty() && $selectedIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Select at least one proposal file that requires revision.',
                ]);
            }

            if ($selectedRevisionFiles->count() !== $selectedIds->count()) {
                throw ValidationException::withMessages([
                    'revision_file_ids' => 'Every selected file must belong to the latest proposal version.',
                ]);
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

                if ($validated['status'] === 'approved') {
                    $reviewedTopic->update(['project_status' => 'ongoing']);
                    $this->grantFacultyResearcherAccess($reviewedTopic);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($evaluationAttributes['file_path']);

            throw $exception;
        }

        $notificationDetails = match ($validated['status']) {
            'approved' => ['Proposal approved', 'Your proposal “'.$topic->title.'” was approved. The evaluation document is available in the proposal review.', 'success'],
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
            route('topics.show', $topic),
            $notificationDetails[2],
            $topic->id,
            workspace: [
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_FACULTY,
            ],
        ));

        $message = match ($validated['status']) {
            'approved' => 'Proposal approved and the evaluation document was shared with the faculty member.',
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
                'project_status' => 'ongoing',
            ]);
            $reviewedTopic->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'decision' => 'approved',
                'comment' => 'All required signed final copies were uploaded and the proposal was released as approved.',
            ]);
            $this->grantFacultyResearcherAccess($reviewedTopic);
        });

        $topic->user()->firstOrFail()->notify(new ProposalActivityNotification(
            'Proposal approved',
            'All required signed final copies for “'.$topic->title.'” are available in the proposal review.',
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
            ->with('success', 'Proposal approved. The signed final copies are now available to the faculty member.');
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

    private function grantFacultyResearcherAccess(TopicProposal $topic): void
    {
        $facultyRole = Role::firstOrCreate(['name' => 'faculty']);
        $facultyResearcherRole = Role::firstOrCreate(['name' => 'faculty_researcher']);

        $topic->user()->firstOrFail()->assignRole([$facultyRole, $facultyResearcherRole]);
    }
}
