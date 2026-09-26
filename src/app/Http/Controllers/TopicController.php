<?php

namespace App\Http\Controllers;

use App\Actions\SyncTopicCollaborators;
use App\Contracts\DocumentPdfConverter;
use App\Http\Requests\StoreResearchHeadFileRequest;
use App\Http\Requests\StoreTopicProposalRequest;
use App\Models\AnnouncementImage;
use App\Models\ProposalDraft;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\TopicReviewFileRevision;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\CommentResponseFeedback;
use App\Services\GADChecklistScoreExtractor;
use App\Services\InitialScreeningNarrativeExtractor;
use App\Services\MonitoringQuarterService;
use App\Services\NoticeToProceedDataService;
use App\Services\ProjectDocumentLibrary;
use App\Services\ProposalPackageService;
use App\Services\ProposalRevisionSectionMap;
use App\Services\ProposalSignatureWorkflow;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalRevisionFileScope;
use App\Support\WorkPlanData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TopicController extends Controller
{
    public function __construct(
        private ProposalSignatureWorkflow $signatureWorkflow,
        private NoticeToProceedDataService $noticeToProceedDataService,
        private MonitoringQuarterService $monitoringQuarterService,
        private ProjectDocumentLibrary $projectDocumentLibrary,
    ) {}

    public function index(ProposalDraftReadiness $readiness): View
    {
        $user = Auth::user();
        $isFacultyResearcher = $user->isUsingWorkspace('faculty_researcher');

        if ($isFacultyResearcher) {
            return $this->researchDashboard($user);
        }

        $topics = $user->proposals()
            ->with([
                'researchCall', 'category',
                'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file'])->oldest(),
                'versions.submitter',
                'versions.files',
            ])
            ->latest()
            ->get();

        $researchCallPosters = ResearchCall::query()
            ->acceptingSubmissions()
            ->whereNotNull('reference_image_path')
            ->where('reference_image_path', '!=', '')
            ->latest()
            ->get([
                'id',
                'title',
                'academic_year',
                'term',
                'description',
                'reference_image_path',
                'opens_at',
                'closes_at',
                'status',
            ]);

        $announcementImages = AnnouncementImage::query()
            ->with('researchCall:id,status,opens_at,closes_at')
            ->visibleToFaculty()
            ->latest()
            ->get(['id', 'image_path', 'research_call_id']);

        $researchCallCarouselItems = $researchCallPosters
            ->map(fn (ResearchCall $researchCall): array => [
                'url' => route('research-calls.reference-image', $researchCall),
                'alt' => $researchCall->title,
                'isResearchCall' => true,
                'researchCallId' => $researchCall->id,
                'canSubmitProposal' => ! $isFacultyResearcher && $researchCall->isAcceptingSubmissions(),
            ])
            ->concat($announcementImages->map(fn (AnnouncementImage $announcementImage): array => [
                'url' => route('announcement-images.show', $announcementImage),
                'alt' => 'Research Office announcement',
                'isResearchCall' => $announcementImage->research_call_id !== null,
                'researchCallId' => $announcementImage->research_call_id,
                'canSubmitProposal' => ! $isFacultyResearcher
                    && ($announcementImage->researchCall?->isAcceptingSubmissions() ?? false),
            ]))
            ->values();

        $proposalDraftCount = 0;
        $recentProposalDrafts = collect();
        $proposalDraftProgress = collect();

        if (! $isFacultyResearcher) {
            $proposalDraftQuery = ProposalDraft::query()->accessibleTo($user);
            $proposalDraftCount = (clone $proposalDraftQuery)->count();
            $recentProposalDrafts = $proposalDraftQuery
                ->with(['researchCall', 'documents', 'owner:id,name'])
                ->latest('updated_at')
                ->limit(4)
                ->get();

            $proposalDraftProgress = $recentProposalDrafts->mapWithKeys(function (ProposalDraft $draft) use ($readiness): array {
                $checklist = $readiness->checklist($draft);
                $completed = $checklist
                    ->filter(fn (array $item): bool => $item['complete'] && ! $item['needs_attention'])
                    ->count();
                $total = $checklist->count();

                return [$draft->getKey() => [
                    'completed' => $completed,
                    'total' => $total,
                    'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
                ]];
            });
        }

        return view('faculty.dashboard', compact(
            'topics',
            'researchCallCarouselItems',
            'proposalDraftCount',
            'recentProposalDrafts',
            'proposalDraftProgress',
            'isFacultyResearcher',
        ));
    }

    public function create(Request $request)
    {
        return redirect()->route('faculty.proposal-drafts.index');
    }

    public function researchIndex(Request $request)
    {
        return $this->researchWorkspace($request->user(), trim($request->string('search')->toString()));
    }

    public function researchShow(Request $request, TopicProposal $topic)
    {
        return $this->show($request, $topic);
    }

    public function show(Request $request, TopicProposal $topic)
    {
        $this->ensureCanViewTopic($request, $topic);

        $topic->load([
            'user', 'noticeIssuer', 'researchSecretary', 'researchCall', 'category', 'collaborators.user', 'revisionDraft.documents', 'revisionDraft.members', 'versions.submitter', 'versions.files.uploadedBy', 'versions.files.annotations', 'preparedProgressReports.submitter', 'preparedProgressReports.budgetPreparer', 'progressReports.submitter', 'progressReports.reviewer', 'progressReports.supersedes', 'progressReports.nextVersion', 'narrativeReports.submitter', 'narrativeReports.reviewer',
            'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file', 'fileRevisions.annotations.reviewer'])->oldest(),
        ]);

        $monitoringReports = $topic->progressReports->values();

        $monitoringQuarterRows = $this->monitoringQuarterService->summaryRows($monitoringReports, $topic);

        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
        $previousVersion = $topic->versions
            ->where('version_number', '<', $latestVersion?->version_number ?? 0)
            ->sortByDesc('version_number')
            ->first();

        $paperCatalog = app(ProposalPaperCatalog::class);
        $paperOrder = $paperCatalog->all()
            ->pluck('order', 'document_type');
        $submittedFiles = ($latestVersion?->files ?? collect())
            ->whereNotIn('document_type', [
                ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                ProposalVersionFile::TYPE_HEAD_UPLOAD,
            ])
            ->sortBy(fn (ProposalVersionFile $file): string => sprintf(
                '%03d-%03d',
                (int) $paperOrder->get($file->document_type, 999),
                $file->position,
            ))
            ->values();
        $availableSubmittedFileIds = $submittedFiles
            ->filter(fn (ProposalVersionFile $file): bool => Storage::disk('local')->exists($file->file_path))
            ->pluck('id');
        $viewableSubmittedFileIds = $submittedFiles
            ->filter(fn (ProposalVersionFile $file): bool => $availableSubmittedFileIds->contains($file->id)
                && $file->canPreviewAsPdf())
            ->pluck('id');
        $reviewDocuments = ($latestVersion?->files ?? collect())
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD);

        if (! $request->user()->isUsingWorkspace('research_head')) {
            $reviewDocuments = $reviewDocuments
                ->reject(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED
                    && (! $topic->hasIssuedNoticeToProceed() || $file->isSuperseded()));
        }

        $reviewDocuments = $reviewDocuments
            ->sortByDesc('created_at')
            ->values();
        $availableReviewDocumentIds = $reviewDocuments
            ->filter(fn (ProposalVersionFile $file): bool => Storage::disk('local')->exists($file->file_path))
            ->pluck('id');
        $viewableReviewDocumentIds = $reviewDocuments
            ->filter(fn (ProposalVersionFile $file): bool => $availableReviewDocumentIds->contains($file->id)
                && $file->canPreviewAsPdf())
            ->pluck('id');
        $previousProjectCost = $this->projectCostForVersion($previousVersion);
        $latestProjectCost = $this->projectCostForVersion($latestVersion);
        $displayProjectCost = $latestProjectCost ?? (float) $topic->estimated_budget;

        $comparisonRows = collect([
            ['label' => 'Project title', 'previous' => $previousVersion?->title, 'latest' => $latestVersion?->title],
            ['label' => 'Total project cost', 'previous' => $previousProjectCost !== null ? 'PHP '.number_format($previousProjectCost, 2) : null, 'latest' => $latestProjectCost !== null ? 'PHP '.number_format($latestProjectCost, 2) : null],
            ['label' => 'Project duration', 'previous' => $previousVersion ? $previousVersion->estimated_duration_months.' months' : null, 'latest' => $latestVersion ? $latestVersion->estimated_duration_months.' months' : null],
            ['label' => 'Description', 'previous' => $previousVersion?->description ?: 'Not provided', 'latest' => $latestVersion?->description ?: 'Not provided'],
        ])->map(fn (array $row) => [
            ...$row,
            'changed' => $previousVersion && $row['previous'] !== $row['latest'],
        ]);

        $pendingFileRevisions = $topic->reviews
            ->flatMap->fileRevisions
            ->whereNull('resolved_at')
            ->values();
        $stagedRevisionFiles = ($topic->revisionDraft?->documents ?? collect())
            ->filter(fn ($document): bool => filled($document->file_path))
            ->keyBy('document_type');
        $draftHistoryCount = $topic->documentHistory()->count();
        $headUploadWorkspace = $request->user()->isUsingWorkspace('research_head')
            ? $this->headUploadWorkspaceData($topic, $latestVersion)
            : null;
        $noticeToProceedForm = $request->user()->isUsingWorkspace('research_head') && in_array($topic->status, ['approved', TopicProposal::STATUS_READY_FOR_SIGNATURE], true)
            ? $this->noticeToProceedDataService->defaults($topic)
            : null;

        $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
        $commentResponseRows = app(CommentResponseFeedback::class)->rows($latestRevisionReview);
        $projectDocumentLibrary = $this->projectDocumentLibrary->build($topic, $request->user());

        $nextClearanceDecision = match (true) {
            $topic->review_stage === 'lrec' => [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'Clear for signing'],
            $topic->status === TopicProposal::STATUS_GAD_REVIEW => [TopicProposal::STATUS_LREC_QUEUED, 'Send to LREC'],
            default => [TopicProposal::STATUS_GAD_REVIEW, 'Clear for GAD review'],
        };
        $researchHeadDecisionOptions = $request->user()->isUsingWorkspace('research_head')
            ? collect([
                $nextClearanceDecision[0] => $nextClearanceDecision[1],
                'revision_requested' => 'Request revisions',
                'rejected' => 'Reject proposal',
            ])->filter(fn (string $label, string $decision): bool => $topic->canRecordDecision($decision))->all()
            : [];

        return view('topics.show', compact(
            'researchHeadDecisionOptions',
            'commentResponseRows',
            'latestRevisionReview',
            'topic',
            'latestVersion',
            'previousVersion',
            'displayProjectCost',
            'comparisonRows',
            'pendingFileRevisions',
            'stagedRevisionFiles',
            'draftHistoryCount',
            'submittedFiles',
            'availableSubmittedFileIds',
            'viewableSubmittedFileIds',
            'reviewDocuments',
            'availableReviewDocumentIds',
            'viewableReviewDocumentIds',
            'headUploadWorkspace',
            'noticeToProceedForm',
            'monitoringQuarterRows',
            'projectDocumentLibrary',
        ));
    }

    public function revision(Request $request, TopicProposal $topic): View|RedirectResponse
    {
        abort_unless($topic->user_id === $request->user()->id, 403);

        if ($topic->status !== 'revision_requested') {
            return redirect()->route('topics.show', $topic);
        }

        $topic->load([
            'user',
            'researchCall',
            'revisionDraft.documents',
            'versions.files',
            'reviews' => fn ($query) => $query
                ->with(['reviewer', 'fileRevisions.file', 'fileRevisions.annotations.reviewer'])
                ->oldest(),
        ]);

        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
        $pendingFileRevisions = $topic->reviews
            ->flatMap->fileRevisions
            ->whereNull('resolved_at')
            ->values();
        $stagedRevisionFiles = ($topic->revisionDraft?->documents ?? collect())
            ->filter(fn ($document): bool => filled($document->file_path))
            ->keyBy('document_type');
        $displayProjectCost = $this->projectCostForVersion($latestVersion) ?? (float) $topic->estimated_budget;
        $latestRevisionReview = $topic->reviews->where('decision', 'revision_requested')->sortByDesc('id')->first();
        $commentResponseRows = app(CommentResponseFeedback::class)->rows($latestRevisionReview);

        return view('faculty.topics.revision', compact(
            'topic',
            'latestVersion',
            'latestRevisionReview',
            'pendingFileRevisions',
            'stagedRevisionFiles',
            'displayProjectCost',
            'commentResponseRows',
        ));
    }

    public function store(
        StoreTopicProposalRequest $request,
        ProposalPackageService $packageService,
        WorkPlanDocumentService $documentService,
    ) {
        $validated = $request->validated();

        $packageFiles = [];
        $directory = 'proposal-packages/'.Auth::id().'/'.Str::uuid();

        try {
            $packageFiles = $packageService->storeFromRequest(
                $request,
                $directory,
            );

            if (! $request->hasFile('work_plan')) {
                $workPlan = WorkPlanData::fromValidated($validated);
                $packageFiles[] = $packageService->storeGeneratedWorkPlan(
                    $documentService->generate($workPlan),
                    $directory,
                    $workPlan['project_title'],
                    Arr::only($validated, [
                        'project_title',
                        'total_duration_months',
                        'planned_start',
                        'planned_end',
                        'entries',
                        'prepared_by',
                    ]),
                );
            }

            $primaryFile = $packageService->primaryFile($packageFiles);
        } catch (Throwable $exception) {
            $packageService->deleteStored($packageFiles);
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['work_plan' => 'The proposal package or generated Work Plan could not be prepared. Please try again.'], 'submission');
        }

        $proposalTitle = $validated['project_title'] ?? $validated['title'];
        $versionData = [
            ...$validated,
            'title' => $proposalTitle,
            'estimated_duration_months' => $validated['total_duration_months'] ?? null,
        ];

        try {
            $topic = DB::transaction(function () use ($versionData, $proposalTitle, $packageFiles, $primaryFile) {
                $topic = Auth::user()->proposals()->create([
                    'title' => $proposalTitle,
                    'research_call_id' => null,
                    'status' => 'pending',
                ]);

                $version = $topic->versions()->create($this->versionAttributes(
                    $versionData,
                    $primaryFile,
                    1,
                    'initial',
                    Auth::id(),
                ));
                $version->files()->createMany($packageFiles);

                return $topic;
            });
        } catch (Throwable $exception) {
            $packageService->deleteStored($packageFiles);

            throw $exception;
        }

        Notification::send(
            User::role('research_head')->get(),
            new ProposalActivityNotification(
                'New proposal submitted',
                Auth::user()->name.' submitted “'.$topic->title.'” for review.',
                route('topics.show', $topic),
                'info',
                $topic->id,
                workspace: User::WORKSPACE_RESEARCH_HEAD,
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
            ),
        );

        return redirect()->route('faculty.dashboard')->with('success', 'Proposal submitted successfully and sent to the Research Head.');
    }

    public function resubmit(
        Request $request,
        TopicProposal $topic,
        ProposalPackageService $packageService,
        ProposalRevisionFileScope $revisionFileScope,
        SyncTopicCollaborators $syncTopicCollaborators,
    ) {
        abort_unless($topic->user_id === $request->user()->id, 403);

        if ($topic->status !== 'revision_requested') {
            return back()
                ->withInput()
                ->withErrors(['status' => 'Only proposals with a requested revision can be resubmitted.'], 'resubmission');
        }

        $maximumBudget = $topic->researchCall?->budgetCeiling() ?? ResearchCall::MAXIMUM_BUDGET;

        $validated = $request->validateWithBag('resubmission', [
            'title' => 'required|string|max:255',
            'redirect_to' => 'nullable|in:topic',
            'revision_draft_id' => 'nullable|integer',
            'description' => 'nullable|string|max:5000',
            'estimated_budget' => ['required', 'numeric', 'min:0', 'max:'.$maximumBudget],
            'estimated_duration_months' => 'required|integer|min:1|max:120',
            'change_summary' => 'nullable|string|max:2000',
            'feedback_review_id' => 'nullable|integer',
            'feedback_responses' => 'nullable|array|max:500',
            'feedback_responses.*' => 'array:response,remarks',
            'feedback_responses.*.response' => 'required|string|max:5000',
            'feedback_responses.*.remarks' => 'nullable|string|max:300',
            'revision_resolutions' => 'nullable|array',
            'revision_resolutions.*' => 'array',
            'revision_resolutions.*.action' => 'nullable|in:no_change',
            'revision_resolutions.*.explanation' => 'nullable|string|max:1000',
            'detailed_proposal' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'work_plan' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'line_item_budget' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'expense_breakdown' => 'nullable|file|mimes:pdf|max:25600',
            'curricula_vitae' => 'nullable|array|min:1|max:10',
            'curricula_vitae.*' => 'required|file|mimes:pdf,doc,docx|max:25600',
            'gad_checklist' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
        ], [
            'estimated_budget.max' => 'The total project cost may not exceed PHP '.number_format($maximumBudget, 2).'.',
        ], [
            'estimated_budget' => 'total project cost',
            'revision_draft_id' => 'revision workspace',
            'detailed_proposal' => 'detailed proposal',
            'document' => 'detailed proposal',
            'work_plan' => 'work plan',
            'line_item_budget' => 'line-item budget',
            'expense_breakdown' => 'expense breakdown',
            'curricula_vitae.*' => 'curriculum vitae file',
            'gad_checklist' => 'GAD checklist',
        ]);

        $revisionDraft = $this->revisionDraftForResubmission($request, $topic);
        $stagedRevisionFiles = ($revisionDraft?->documents ?? collect())
            ->filter(fn ($document): bool => filled($document->file_path))
            ->keyBy('document_type');

        $pendingFileRevisions = TopicReviewFileRevision::query()
            ->with('file')
            ->whereNull('resolved_at')
            ->whereHas('review', fn ($query) => $query->where('topic_id', $topic->id))
            ->get();
        $requiredDocumentTypes = $pendingFileRevisions->pluck('document_type')->unique();
        $unexpectedRevisionErrors = $revisionFileScope->unexpectedUploadErrors($request, $requiredDocumentTypes);

        if ($unexpectedRevisionErrors !== []) {
            return back()->withInput()->withErrors($unexpectedRevisionErrors, 'resubmission');
        }

        $stagedRevisionFiles = $revisionFileScope->requestedStagedFiles($stagedRevisionFiles, $requiredDocumentTypes);
        $resolutionErrors = $revisionFileScope->unresolvedErrors(
            $request,
            $pendingFileRevisions,
            $stagedRevisionFiles,
        );

        if ($resolutionErrors !== []) {
            return back()->withInput()->withErrors($resolutionErrors, 'resubmission');
        }

        $noChangeResponses = $revisionFileScope->noChangeResponses($request, $requiredDocumentTypes);
        $stagedRevisionFiles = $stagedRevisionFiles->except($noChangeResponses->keys()->all());
        $permanentDirectory = 'proposal-packages/'.$request->user()->id.'/'.Str::uuid();
        $replacementFiles = [];

        try {
            $replacementFiles = $packageService->storeFromRequest(
                $request,
                $permanentDirectory,
            );
            $manualDocumentTypes = collect($replacementFiles)->pluck('document_type')->unique();

            foreach ($stagedRevisionFiles as $stagedRevisionFile) {
                if ($manualDocumentTypes->contains($stagedRevisionFile->document_type)) {
                    continue;
                }

                $replacementFiles[] = $packageService->copyDraftDocument($stagedRevisionFile, $permanentDirectory);
            }
        } catch (Throwable) {
            $packageService->deleteStored($replacementFiles);

            return back()
                ->withInput()
                ->withErrors(['detailed_proposal' => 'The revised proposal package could not be uploaded. Please try again.'], 'resubmission');
        }

        $unchangedReplacementErrors = $revisionFileScope->unchangedReplacementErrors(
            $pendingFileRevisions,
            $replacementFiles,
        );
        if ($unchangedReplacementErrors !== []) {
            $packageService->deleteStored($replacementFiles);

            return back()->withInput()->withErrors($unchangedReplacementErrors, 'resubmission');
        }

        $result = ['updated' => false];

        try {
            DB::transaction(function () use ($request, $topic, $validated, $replacementFiles, $packageService, $revisionDraft, $syncTopicCollaborators, $noChangeResponses, &$result) {
                $revisedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($revisedTopic->user_id !== $request->user()->id || $revisedTopic->status !== 'revision_requested') {
                    return;
                }

                $review = $revisedTopic->reviews()->where('decision', 'revision_requested')->latest('id')->lockForUpdate()->firstOrFail();
                $feedbackRows = app(CommentResponseFeedback::class)->rows($review);
                if ($feedbackRows !== [] && (int) ($validated['feedback_review_id'] ?? 0) !== $review->id) {
                    throw ValidationException::withMessages(['feedback_responses' => 'The review round changed. Reload the revision before submitting.'])->errorBag('resubmission');
                }
                $responses = [];
                foreach ($feedbackRows as $row) {
                    $answer = $validated['feedback_responses'][$row['key']] ?? [];
                    if (blank($answer['response'] ?? null)) {
                        throw ValidationException::withMessages(['feedback_responses' => 'Respond to every review comment before submitting your revision.'])->errorBag('resubmission');
                    }
                    $responses[$row['key']] = $answer;
                }
                $review->update(['feedback_responses' => $responses]);

                $nextVersion = ((int) $revisedTopic->versions()->max('version_number')) + 1;
                $previousVersion = $revisedTopic->latestVersion()->with('files')->first();
                $snapshotFiles = $packageService->revisionSnapshot($previousVersion, $replacementFiles, $revisedTopic);
                $primaryFile = $packageService->primaryFile($snapshotFiles);

                $revisedTopic->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'estimated_budget' => $validated['estimated_budget'],
                    'estimated_duration_months' => $validated['estimated_duration_months'],
                    'status' => match ($revisedTopic->review_stage) {
                        'lrec' => TopicProposal::STATUS_LREC_REVIEW,
                        'gad' => TopicProposal::STATUS_GAD_REVIEW,
                        default => 'resubmitted',
                    },
                ]);

                $version = $revisedTopic->versions()->create($this->versionAttributes(
                    $validated,
                    $primaryFile,
                    $nextVersion,
                    'revision',
                    $request->user()->id,
                ));
                $version->files()->createMany($snapshotFiles);

                $newVersionFiles = $version->files()->get();
                $pendingRevisions = TopicReviewFileRevision::query()
                    ->with('file')
                    ->whereNull('resolved_at')
                    ->whereHas('review', fn ($query) => $query->where('topic_id', $revisedTopic->id))
                    ->lockForUpdate()
                    ->get();

                foreach ($pendingRevisions as $pendingRevision) {
                    $noChangeResponse = $noChangeResponses->get($pendingRevision->document_type);
                    $resolutionCandidates = $newVersionFiles
                        ->where('document_type', $pendingRevision->document_type)
                        ->when(
                            $noChangeResponse === null,
                            fn ($files) => $files->where('is_carried_forward', false),
                        );
                    $resolutionFile = $resolutionCandidates->firstWhere('position', $pendingRevision->file?->position)
                        ?: $resolutionCandidates->first();

                    if (! $resolutionFile) {
                        throw ValidationException::withMessages([
                            'document' => 'Every requested file must be revised or resolved with an explanation before resubmission.',
                        ]);
                    }

                    $pendingRevision->update([
                        'resolved_by_version_file_id' => $resolutionFile->id,
                        'resolution_type' => $noChangeResponse === null ? 'file_revised' : 'no_file_change',
                        'faculty_response' => $noChangeResponse,
                        'resolved_at' => now(),
                    ]);
                }

                if ($revisionDraft) {
                    $syncTopicCollaborators->handle($revisionDraft, $revisedTopic);
                    $revisionDraft->delete();
                }

                $result['updated'] = true;
            });
        } catch (Throwable $exception) {
            $packageService->deleteStored($replacementFiles);

            throw $exception;
        }

        if (! $result['updated']) {
            $packageService->deleteStored($replacementFiles);

            return back()
                ->withInput()
                ->withErrors(['status' => 'This proposal is no longer awaiting a revision.'], 'resubmission');
        }

        if ($revisionDraft) {
            Storage::disk('local')->deleteDirectory($revisionDraft->storageDirectory());
        }

        Notification::send(
            User::role('research_head')->get(),
            new ProposalActivityNotification(
                'Proposal revision submitted',
                $request->user()->name.' submitted a new version of “'.$topic->fresh()->title.'”.',
                route('topics.show', $topic),
                'info',
                $topic->id,
                workspace: User::WORKSPACE_RESEARCH_HEAD,
                sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
            ),
        );

        $redirectRoute = ($validated['redirect_to'] ?? null) === 'topic' ? 'topics.show' : 'faculty.dashboard';

        return redirect()->route($redirectRoute, $redirectRoute === 'topics.show' ? $topic : [])
            ->with('success', 'Revised proposal submitted for another review.')
            ->with('revision_submitted', true);
    }

    public function download(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);

        $version = $topic->latestVersion()->first();
        $path = $version?->file_path ?: ($topic->final_file_path ?: $topic->initial_file_path);

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $version?->original_filename ?: basename($path));
    }

    public function downloadVersion(Request $request, TopicProposal $topic, ProposalVersion $version)
    {
        $this->ensureCanViewTopic($request, $topic);
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless(Storage::disk('local')->exists($version->file_path), 404);

        return Storage::disk('local')->download($version->file_path, $version->original_filename);
    }

    public function downloadVersionFile(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
    ) {
        $this->ensureCanViewTopic($request, $topic);
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless($file->proposal_version_id === $version->id, 404);
        $this->ensureCanAccessVersionFile($request, $topic, $file);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        return Storage::disk('local')->download($file->file_path, $file->original_filename);
    }

    public function viewVersionFile(
        Request $request,
        TopicProposal $topic,
        ProposalVersion $version,
        ProposalVersionFile $file,
        DocumentPdfConverter $pdfConverter,
    ): StreamedResponse {
        $this->ensureCanViewTopic($request, $topic);
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless($file->proposal_version_id === $version->id, 404);
        $this->ensureCanAccessVersionFile($request, $topic, $file);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);
        abort_unless($file->canPreviewAsPdf(), 415);

        if (! $file->isPdf()) {
            $pdfContents = app(ProposalRevisionSectionMap::class)->pdfContents($file);
            $filenameStem = pathinfo($file->original_filename, PATHINFO_FILENAME);
            $pdfFilename = $filenameStem.'.pdf';
            $fallbackFilename = (Str::slug($filenameStem) ?: 'proposal-file-'.$file->id).'.pdf';
            $response = response()->stream(
                static function () use ($pdfContents): void {
                    echo $pdfContents;
                },
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Length' => (string) strlen($pdfContents),
                    'X-Content-Type-Options' => 'nosniff',
                ],
            );
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                'inline',
                $pdfFilename,
                $fallbackFilename,
            ));

            return $response;
        }

        return Storage::disk('local')->response(
            $file->file_path,
            $file->original_filename,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function downloadApproval(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);
        abort_unless($topic->signed_approval_path, 404);
        abort_unless(Storage::disk('local')->exists($topic->signed_approval_path), 404);

        return Storage::disk('local')->download($topic->signed_approval_path, 'signed-approval-'.$topic->id.'.pdf');
    }

    public function headUploads(Request $request, TopicProposal $topic): View
    {
        $this->ensureCanViewTopic($request, $topic);

        $topic->load([
            'user',
            'researchCall',
            'versions.submitter',
            'versions.files.uploadedBy',
            'versions.files.annotations',
        ]);

        $latestVersion = $topic->latestVersion()
            ->with(['submitter', 'files.uploadedBy', 'files.annotations'])
            ->first();
        $headUploadWorkspace = $this->headUploadWorkspaceData($topic, $latestVersion);

        return view('research_head.topics.files', [
            'topic' => $topic,
            'workspace' => $headUploadWorkspace,
            ...$headUploadWorkspace,
        ]);
    }

    public function storeHeadUpload(
        StoreResearchHeadFileRequest $request,
        TopicProposal $topic,
        ProposalPackageService $packageService,
        GADChecklistScoreExtractor $gadScoreExtractor,
        InitialScreeningNarrativeExtractor $narrativeExtractor,
    ): RedirectResponse {
        $validated = $request->validated();
        $isSupplemental = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL;
        $isSignedCopy = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED;
        $isEvaluation = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION;
        $isGadAssessment = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT;
        $isInitialReviewUpload = $isGadAssessment || $isEvaluation;

        if ($isInitialReviewUpload && $topic->status !== TopicProposal::STATUS_GAD_REVIEW) {
            return $this->headUploadErrorResponse(
                $topic,
                true,
                ['review_file' => 'GAD review opens only after the Research Head explicitly clears the latest proposal version.'],
            );
        }

        $latestVersion = $topic->latestVersion()->first();

        if (! $latestVersion instanceof ProposalVersion) {
            return $this->headUploadErrorResponse(
                $topic,
                $isInitialReviewUpload,
                ['review_file' => 'This proposal does not have a submitted version to attach files to.'],
            );
        }

        $sourceFile = $isSupplemental
            ? null
            : $latestVersion->files()
                ->whereKey($validated['source_file_id'])
                ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                ->firstOrFail();

        if ($isGadAssessment && $sourceFile?->document_type !== ProposalVersionFile::TYPE_GAD_CHECKLIST) {
            return $this->headUploadErrorResponse(
                $topic,
                true,
                ['source_file_id' => 'The completed GAD assessment must be linked to the original GAD Checklist in the latest proposal version.'],
            );
        }

        if ($isEvaluation && $sourceFile?->document_type !== ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM) {
            return $this->headUploadErrorResponse(
                $topic,
                true,
                ['source_file_id' => 'A completed central evaluator review must be linked to the original Initial Screening Form in the latest proposal version.'],
            );
        }

        if ($isEvaluation && ! $latestVersion->hasPassingGadAssessment()) {
            return $this->headUploadErrorResponse(
                $topic,
                true,
                ['review_file' => 'Central evaluation opens only after the GAD Office records a passing score and the Research Head confirms the verifier’s signature.'],
            );
        }

        if ($isSignedCopy && $topic->status !== TopicProposal::STATUS_READY_FOR_SIGNATURE) {
            return back()
                ->withInput()
                ->withErrors(['purpose' => 'Signed final copies can only be uploaded while the proposal is ready for signature.'], 'headUpload');
        }

        if ($isSignedCopy
            && $sourceFile
            && ! $this->signatureWorkflow->requiredFiles($latestVersion)->contains('id', $sourceFile->id)) {
            return back()
                ->withInput()
                ->withErrors(['source_file_id' => $sourceFile->label().' does not require a signed copy.'], 'headUpload');
        }

        $file = $request->file('review_file');
        $narrativeEvaluation = null;
        $gadAssessment = null;

        if ($isGadAssessment) {
            try {
                $gadAssessment = $gadScoreExtractor->extract($file);
            } catch (RuntimeException $exception) {
                return $this->headUploadErrorResponse(
                    $topic,
                    true,
                    ['review_file' => $exception->getMessage()],
                );
            }
        }

        if ($isEvaluation) {
            try {
                $narrativeEvaluation = $narrativeExtractor->extract($file);
            } catch (RuntimeException $exception) {
                return $this->headUploadErrorResponse(
                    $topic,
                    true,
                    ['review_file' => $exception->getMessage()],
                );
            }
        }

        $directory = 'proposal-packages/'.$topic->user_id.'/'.$topic->id.'/head-uploads/'.Str::uuid();
        $storedPath = null;
        $replacedSignedCopy = false;

        try {
            $attributes = $packageService->storeHeadUpload(
                $file,
                $directory,
                [
                    'source_version_file_id' => $sourceFile?->id,
                    'target_document_type' => $sourceFile?->document_type,
                    'purpose' => $validated['purpose'],
                    'document_title' => $validated['document_title'] ?? null,
                    'issuing_office' => $validated['issuing_office'] ?? null,
                    'note' => $validated['note'] ?? null,
                    'co_evaluator_name' => $validated['co_evaluator_name'] ?? null,
                    'recommended_action' => $validated['recommended_action'] ?? null,
                    'narrative_evaluation' => $narrativeEvaluation,
                    'gad_score' => $gadAssessment['gad_score'] ?? null,
                    'gad_rating' => $gadAssessment['gad_rating'] ?? null,
                    'gad_interpretation' => $gadAssessment['gad_interpretation'] ?? null,
                    'gad_outcome' => $gadAssessment['gad_outcome'] ?? null,
                    'gad_signature_detected' => $gadAssessment['gad_signature_detected'] ?? false,
                    'gad_signature_confirmed' => $isGadAssessment && $request->boolean('gad_signature_confirmed'),
                    'gad_signature_detection_method' => $gadAssessment['gad_signature_detection_method'] ?? null,
                ],
            );
            $storedPath = $attributes['file_path'];

            DB::transaction(function () use ($topic, $latestVersion, $attributes, $request, $validated, $isSupplemental, $isSignedCopy, $isGadAssessment, $isEvaluation, $sourceFile, &$replacedSignedCopy): void {
                $lockedTopic = TopicProposal::query()->whereKey($topic->id)->lockForUpdate()->firstOrFail();

                if (($isGadAssessment || $isEvaluation) && $lockedTopic->status !== TopicProposal::STATUS_GAD_REVIEW) {
                    throw ValidationException::withMessages(['review_file' => 'GAD review is closed or the proposal stage changed. Reload the proposal workflow.']);
                }

                if ($isSignedCopy && $lockedTopic->status !== TopicProposal::STATUS_READY_FOR_SIGNATURE) {
                    throw ValidationException::withMessages(['review_file' => 'Signing is closed or the proposal version changed. Reload the proposal.']);
                }

                if (($isSignedCopy || $isGadAssessment || $isEvaluation)
                    && $lockedTopic->latestVersion()->value('id') !== $latestVersion->id) {
                    throw ValidationException::withMessages(['review_file' => 'A newer proposal version is available. Reload the initial-review workflow before uploading this file.']);
                }

                $lockedVersion = ProposalVersion::query()
                    ->whereKey($latestVersion->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($isEvaluation && ! $lockedVersion->hasPassingGadAssessment()) {
                    throw ValidationException::withMessages(['review_file' => 'Central evaluation opens only after the GAD Office records a passing score and the Research Head confirms the verifier’s signature.']);
                }

                $existingSignedCopies = $isSignedCopy
                    ? $lockedVersion->files()
                        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                        ->where('source_version_file_id', $sourceFile?->id)
                        ->where('source_data->purpose', ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
                        ->whereNull('superseded_at')
                        ->lockForUpdate()
                        ->get()
                    : collect();
                $position = ((int) $lockedVersion->files()
                    ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                    ->max('position')) + 1;
                $newHeadUpload = $lockedVersion->files()->create([
                    ...$attributes,
                    'position' => $position,
                    'uploaded_by' => $request->user()->id,
                ]);

                if ($existingSignedCopies->isNotEmpty()) {
                    $existingSignedCopies->each(fn (ProposalVersionFile $signedCopy) => $signedCopy->update([
                        'superseded_at' => now(),
                        'superseded_by_version_file_id' => $newHeadUpload->id,
                    ]));
                    $replacedSignedCopy = true;
                }

                $topic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' => 'head_upload',
                    'comment' => $validated['note']
                        ?? ($isSupplemental ? 'Uploaded supplemental paper: '.$validated['document_title'] : null),
                ]);
            });

        } catch (ValidationException $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            return $this->headUploadErrorResponse(
                $topic,
                $isInitialReviewUpload,
                $exception->errors(),
            );
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            report($exception);

            return $this->headUploadErrorResponse(
                $topic,
                $isInitialReviewUpload,
                ['review_file' => 'The Research Head file could not be stored. Please try again.'],
            );
        }

        if ($isGadAssessment) {
            return redirect()
                ->to(route('topics.head-uploads.index', $topic).'#initial-review-workflow')
                ->with('success', 'Completed GAD Checklist uploaded. ATHENA extracted a Total GAD Score of '.number_format($gadAssessment['gad_score'], 2).' ('.$gadAssessment['gad_rating'].') and recorded the verifier signature confirmation.');
        }

        if ($isEvaluation) {
            return redirect()
                ->to(route('topics.head-uploads.index', $topic).'#initial-review-workflow')
                ->with('success', 'Completed Initial Screening Form uploaded. Its Narrative Evaluation was recorded for the central evaluator response.');
        }

        return redirect()
            ->to(route('topics.show', $topic).(in_array($topic->status, ['ready_for_signature', 'approved'], true) ? '#notice-to-proceed' : '#proposal-review'))
            ->with('success', $isSupplemental
                ? 'Supplemental paper uploaded by the Research Head.'
                : ($replacedSignedCopy
                    ? 'Replacement signed PDF uploaded. The previous signed copy was preserved as superseded audit history.'
                    : 'Research Head file attached to the faculty submission.'));
    }

    /**
     * @return array{
     *     latestVersion: ProposalVersion|null,
     *     facultySubmittedFiles: Collection<int, ProposalVersionFile>,
     *     headUploadedFiles: Collection<int, ProposalVersionFile>,
     *     supplementalHeadUploads: Collection<int, ProposalVersionFile>,
     *     headUploadsBySource: Collection<int, Collection<int, ProposalVersionFile>>,
     *     availableFileIds: Collection<int, int>,
     *     viewableFileIds: Collection<int, int>,
     *     requiredSignatureFiles: Collection<int, ProposalVersionFile>,
     *     signedSourceFileIds: Collection<int, int>,
     *     missingSignatureFiles: Collection<int, ProposalVersionFile>,
     *     signaturesComplete: bool,
     *     gadAssessment: ProposalVersionFile|null,
     *     gadPassed: bool,
     *     coEvaluatorEvaluation: ProposalVersionFile|null
     * }
     */
    private function headUploadWorkspaceData(TopicProposal $topic, ?ProposalVersion $latestVersion = null): array
    {
        $latestVersion ??= $topic->versions->sortByDesc('version_number')->first();
        $paperOrder = app(ProposalPaperCatalog::class)
            ->all()
            ->pluck('order', 'document_type');
        $facultySubmittedFiles = ($latestVersion?->files ?? collect())
            ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->sortBy(fn (ProposalVersionFile $file): string => sprintf(
                '%03d-%03d',
                (int) $paperOrder->get($file->document_type, 999),
                $file->position,
            ))
            ->values();
        $headUploadedFiles = ($latestVersion?->files ?? collect())
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
            ->sortByDesc('id')
            ->values();
        $supplementalHeadUploads = $headUploadedFiles
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL)
            ->values();
        $gadChecklist = $facultySubmittedFiles->firstWhere('document_type', ProposalVersionFile::TYPE_GAD_CHECKLIST);
        $initialScreeningForm = $facultySubmittedFiles->firstWhere('document_type', ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
        $gadAssessment = $headUploadedFiles->first(fn (ProposalVersionFile $file): bool => $file->source_version_file_id === $gadChecklist?->id
            && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT
            && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_GAD_CHECKLIST
            && is_numeric($file->source_data['gad_score'] ?? null));
        $coEvaluatorEvaluation = $headUploadedFiles->first(fn (ProposalVersionFile $file): bool => $file->source_version_file_id === $initialScreeningForm?->id
            && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
            && ($file->source_data['target_document_type'] ?? null) === ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM
            && filled($file->source_data['narrative_evaluation'] ?? null));
        $headUploadsBySource = $headUploadedFiles
            ->reject(fn (ProposalVersionFile $file): bool => in_array($file->source_data['purpose'] ?? null, [
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
            ], true))
            ->groupBy(
                fn (ProposalVersionFile $file): int => $file->source_version_file_id
                    ?? $facultySubmittedFiles->firstWhere(
                        'document_type',
                        $file->source_data['target_document_type'] ?? null,
                    )?->id
                    ?? 0,
            );
        $workspaceFiles = $facultySubmittedFiles->concat($headUploadedFiles);
        $availableFileIds = $workspaceFiles
            ->filter(fn (ProposalVersionFile $file): bool => Storage::disk('local')->exists($file->file_path))
            ->pluck('id');
        $viewableFileIds = $workspaceFiles
            ->filter(fn (ProposalVersionFile $file): bool => $availableFileIds->contains($file->id)
                && $file->canPreviewAsPdf())
            ->pluck('id');
        $requiredSignatureFiles = $latestVersion
            ? $this->signatureWorkflow->requiredFiles($latestVersion)
            : collect();
        $signedSourceFileIds = $latestVersion
            ? $this->signatureWorkflow->signedSourceFileIds($latestVersion)
            : collect();
        $missingSignatureFiles = $latestVersion
            ? $this->signatureWorkflow->missingRequiredFiles($latestVersion)
            : collect();
        $signaturesComplete = $latestVersion !== null && $this->signatureWorkflow->isComplete($latestVersion);
        $gadPassed = $latestVersion?->hasPassingGadAssessment() ?? false;

        return compact(
            'latestVersion',
            'facultySubmittedFiles',
            'headUploadedFiles',
            'supplementalHeadUploads',
            'headUploadsBySource',
            'availableFileIds',
            'viewableFileIds',
            'requiredSignatureFiles',
            'signedSourceFileIds',
            'missingSignatureFiles',
            'signaturesComplete',
            'gadAssessment',
            'gadPassed',
            'coEvaluatorEvaluation',
        );
    }

    /** @param array<string, string|array<int, string>> $errors */
    private function headUploadErrorResponse(
        TopicProposal $topic,
        bool $isInitialReviewUpload,
        array $errors,
    ): RedirectResponse {
        $response = $isInitialReviewUpload
            ? redirect()->to(route('topics.head-uploads.index', $topic).'#initial-review-workflow')
            : back();

        return $response
            ->withInput()
            ->withErrors($errors, 'headUpload');
    }

    private function ensureCanViewTopic(Request $request, TopicProposal $topic): void
    {
        Gate::forUser($request->user())->authorize('view', $topic);
    }

    private function researchWorkspace(User $user, string $search = ''): View
    {
        $projects = TopicProposal::query()
            ->accessibleTo($user)
            ->with(['researchCall', 'category', 'latestVersion', 'latestProgressReport'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $activeProjects = (clone $projects)->activeProject()->latest('updated_at')->get();
        $awaitingProjects = (clone $projects)->awaitingNoticeToProceed()->latest('updated_at')->get();
        $completedProjects = (clone $projects)->completedProject()->latest('updated_at')->get();

        return view('research.index', compact(
            'activeProjects',
            'awaitingProjects',
            'completedProjects',
            'search',
        ));
    }

    private function researchDashboard(User $user): View
    {
        $projects = TopicProposal::query()
            ->accessibleTo($user)
            ->with(['researchCall', 'category', 'latestProgressReport']);

        $activeProjects = (clone $projects)->activeProject()->latest('updated_at')->get();
        $awaitingProjects = (clone $projects)->awaitingNoticeToProceed()->latest('updated_at')->get();
        $completedProjects = (clone $projects)->completedProject()->latest('updated_at')->get();

        $averageProgress = $activeProjects->isEmpty()
            ? 0
            : (int) round($activeProjects->avg(
                fn (TopicProposal $topic): int => min(100, max(0, (int) ($topic->latestProgressReport?->progress_percentage ?? 0))),
            ));

        $attentionProjects = $activeProjects
            ->filter(function (TopicProposal $topic): bool {
                $progress = $topic->latestProgressReport?->progress_percentage;

                return $topic->monitoringStatusForProgress($progress) !== TopicProposal::PROJECT_STATUS_ONGOING
                    || $topic->latestProgressReport === null;
            })
            ->sortBy(function (TopicProposal $topic): int {
                $status = $topic->monitoringStatusForProgress($topic->latestProgressReport?->progress_percentage);

                return match ($status) {
                    TopicProposal::PROJECT_STATUS_COMPLETION_PENDING => 0,
                    TopicProposal::PROJECT_STATUS_DELAYED => 1,
                    default => 2,
                };
            })
            ->values();

        return view('research.dashboard', compact(
            'activeProjects',
            'awaitingProjects',
            'completedProjects',
            'attentionProjects',
            'averageProgress',
        ));
    }

    private function ensureCanAccessVersionFile(
        Request $request,
        TopicProposal $topic,
        ProposalVersionFile $file,
    ): void {
        if ($request->user()->isUsingWorkspace('research_head')) {
            return;
        }

        $isUnreleasedSignedCopy = $file->document_type === ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED
            && (! $topic->hasIssuedNoticeToProceed() || $file->isSuperseded());

        abort_if($isUnreleasedSignedCopy, 404);
    }

    private function revisionDraftForResubmission(Request $request, TopicProposal $topic): ?ProposalDraft
    {
        $revisionDraft = $topic->revisionDraft()
            ->with('documents')
            ->first();

        if (! $revisionDraft) {
            return null;
        }

        $submittedDraftId = $request->integer('revision_draft_id');

        abort_unless(
            $revisionDraft->user_id === $request->user()->id
                && $revisionDraft->topic_id === $topic->id
                && ($submittedDraftId === 0 || $submittedDraftId === $revisionDraft->id),
            403,
        );

        return $revisionDraft;
    }

    private function projectCostForVersion(?ProposalVersion $version): ?float
    {
        $lineItemBudget = $version?->files->firstWhere('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET);
        $sourceData = $lineItemBudget?->source_data;

        if (is_array($sourceData) && array_key_exists('project_total', $sourceData) && is_numeric($sourceData['project_total'])) {
            return (float) $sourceData['project_total'];
        }

        return $version?->estimated_budget !== null ? (float) $version->estimated_budget : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $primaryFile
     * @return array<string, mixed>
     */
    private function versionAttributes(
        array $validated,
        array $primaryFile,
        int $versionNumber,
        string $submissionType,
        int $submittedBy,
    ): array {
        return [
            'submitted_by' => $submittedBy,
            'version_number' => $versionNumber,
            'submission_type' => $submissionType,
            'change_summary' => $validated['change_summary'] ?? null,
            'file_path' => $primaryFile['file_path'],
            'original_filename' => $primaryFile['original_filename'],
            'mime_type' => $primaryFile['mime_type'],
            'file_size' => $primaryFile['file_size'],
            'checksum' => $primaryFile['checksum'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_budget' => $validated['estimated_budget'] ?? null,
            'estimated_duration_months' => $validated['estimated_duration_months'] ?? null,
        ];
    }
}
