<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreResearchHeadFileRequest;
use App\Http\Requests\StoreTopicProposalRequest;
use App\Models\AnnouncementImage;
use App\Models\ProposalDraft;
use App\Models\ProposalTemplate;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\TopicReviewFileRevision;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalPackageService;
use App\Services\ProposalSignatureWorkflow;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TopicController extends Controller
{
    public function __construct(private ProposalSignatureWorkflow $signatureWorkflow) {}

    public function index(ProposalDraftReadiness $readiness): View
    {
        $user = Auth::user();

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
            ->latest()
            ->get(['id', 'image_path']);

        $researchCallCarouselItems = $researchCallPosters
            ->map(fn (ResearchCall $researchCall): array => [
                'url' => route('research-calls.reference-image', $researchCall),
                'alt' => $researchCall->title,
                'isResearchCall' => true,
                'researchCallId' => $researchCall->id,
                'canSubmitProposal' => $researchCall->isAcceptingSubmissions(),
            ])
            ->concat($announcementImages->map(fn (AnnouncementImage $announcementImage): array => [
                'url' => route('announcement-images.show', $announcementImage),
                'alt' => 'Research Office announcement',
                'isResearchCall' => false,
                'researchCallId' => null,
                'canSubmitProposal' => false,
            ]))
            ->values();

        $proposalDraftQuery = ProposalDraft::query()->accessibleTo($user);
        $proposalDraftCount = (clone $proposalDraftQuery)->count();
        $recentProposalDrafts = $proposalDraftQuery
            ->with(['researchCall', 'documents', 'owner:id,name'])
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $proposalDraftProgress = $recentProposalDrafts->mapWithKeys(function (ProposalDraft $draft) use ($readiness): array {
            $checklist = $readiness->checklist($draft);
            $completed = $checklist->where('complete', true)->count();
            $total = $checklist->count();

            return [$draft->getKey() => [
                'completed' => $completed,
                'total' => $total,
                'percentage' => $total === 0 ? 0 : (int) round(($completed / $total) * 100),
            ]];
        });

        return view('faculty.dashboard', compact(
            'topics',
            'researchCallCarouselItems',
            'proposalDraftCount',
            'recentProposalDrafts',
            'proposalDraftProgress',
        ));
    }

    public function create(Request $request)
    {
        return redirect()->route('faculty.proposal-drafts.index');
    }

    public function researchIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['pending', 'expert_review', 'for_final_decision', 'revision_requested', 'resubmitted', TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved', 'rejected'];

        $topics = TopicProposal::query()
            ->with(['researchCall', 'category', 'latestVersion'])
            ->where('user_id', $request->user()->id)
            ->when(in_array($status, $allowedStatuses, true), fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('research.index', compact('topics', 'status', 'search'));
    }

    public function researchShow(Request $request, TopicProposal $topic)
    {
        return $this->show($request, $topic);
    }

    public function show(Request $request, TopicProposal $topic)
    {
        $this->ensureCanViewTopic($request, $topic);

        $topic->load([
            'user', 'noticeIssuer', 'researchCall', 'category', 'revisionDraft.documents', 'revisionDraft.members', 'versions.submitter', 'versions.files.uploadedBy', 'versions.files.annotations', 'progressReports.submitter', 'progressReports.reviewer', 'narrativeReports.submitter', 'narrativeReports.reviewer',
            'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file', 'fileRevisions.annotations'])->oldest(),
        ]);

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
                && ($file->mime_type === 'application/pdf'
                    || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf'))
            ->pluck('id');
        $reviewDocuments = ($latestVersion?->files ?? collect())
            ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD);

        if (! $request->user()->isUsingWorkspace('research_head') && $topic->status !== 'approved') {
            $reviewDocuments = $reviewDocuments
                ->reject(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED);
        }

        $reviewDocuments = $reviewDocuments
            ->sortByDesc('created_at')
            ->values();
        $availableReviewDocumentIds = $reviewDocuments
            ->filter(fn (ProposalVersionFile $file): bool => Storage::disk('local')->exists($file->file_path))
            ->pluck('id');
        $viewableReviewDocumentIds = $reviewDocuments
            ->filter(fn (ProposalVersionFile $file): bool => $availableReviewDocumentIds->contains($file->id)
                && ($file->mime_type === 'application/pdf'
                    || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf'))
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
        $screeningTemplates = $this->availableTemplatesFor(ProposalTemplate::STAGE_INITIAL_SCREENING);
        $draftHistoryCount = $topic->documentHistory()->count();
        $headUploadWorkspace = $request->user()->isUsingWorkspace('research_head')
            ? $this->headUploadWorkspaceData($topic, $latestVersion)
            : null;

        return view('topics.show', compact(
            'topic',
            'latestVersion',
            'previousVersion',
            'displayProjectCost',
            'comparisonRows',
            'pendingFileRevisions',
            'stagedRevisionFiles',
            'screeningTemplates',
            'draftHistoryCount',
            'submittedFiles',
            'availableSubmittedFileIds',
            'viewableSubmittedFileIds',
            'reviewDocuments',
            'availableReviewDocumentIds',
            'viewableReviewDocumentIds',
            'headUploadWorkspace',
        ));
    }

    public function store(
        StoreTopicProposalRequest $request,
        ProposalPackageService $packageService,
        WorkPlanDocumentService $documentService,
    ) {
        $validated = $request->validated();

        $call = ResearchCall::findOrFail($validated['research_call_id']);

        if (! $call->isAcceptingSubmissions()) {
            return back()->withInput()->withErrors([
                'research_call_id' => 'This research call is not accepting submissions.',
            ], 'submission');
        }

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
            $topic = DB::transaction(function () use ($versionData, $proposalTitle, $call, $packageFiles, $primaryFile) {
                $topic = Auth::user()->proposals()->create([
                    'title' => $proposalTitle,
                    'research_call_id' => $call->id,
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
            ),
        );

        return redirect()->route('faculty.dashboard')->with('success', 'Proposal submitted successfully and sent to the Research Head.');
    }

    public function resubmit(Request $request, TopicProposal $topic, ProposalPackageService $packageService)
    {
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
            'detailed_proposal' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'document' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'work_plan' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'line_item_budget' => 'nullable|file|mimes:pdf,doc,docx|max:25600',
            'expense_breakdown' => 'nullable|file|mimes:xls,xlsx|max:25600',
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
        $revisionErrors = collect([
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => ['input' => 'detailed_proposal', 'provided' => $request->hasFile('detailed_proposal') || $request->hasFile('document') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_DETAILED_PROPOSAL), 'message' => 'Upload a revised detailed proposal as requested by the Research Head.'],
            ProposalVersionFile::TYPE_WORK_PLAN => ['input' => 'work_plan', 'provided' => $request->hasFile('work_plan') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_WORK_PLAN), 'message' => 'Upload a revised work plan as requested by the Research Head.'],
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['input' => 'line_item_budget', 'provided' => $request->hasFile('line_item_budget') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_LINE_ITEM_BUDGET), 'message' => 'Upload a revised line-item budget as requested by the Research Head.'],
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => ['input' => 'expense_breakdown', 'provided' => $request->hasFile('expense_breakdown') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN), 'message' => 'Upload a revised expense breakdown as requested by the Research Head.'],
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => ['input' => 'curricula_vitae', 'provided' => $request->hasFile('curricula_vitae') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_CURRICULUM_VITAE), 'message' => 'Upload the revised curriculum vitae file(s) requested by the Research Head.'],
            ProposalVersionFile::TYPE_GAD_CHECKLIST => ['input' => 'gad_checklist', 'provided' => $request->hasFile('gad_checklist') || $stagedRevisionFiles->has(ProposalVersionFile::TYPE_GAD_CHECKLIST), 'message' => 'Upload the revised GAD checklist requested by the Research Head.'],
        ])->only($requiredDocumentTypes->all())
            ->reject(fn (array $requirement) => $requirement['provided'])
            ->mapWithKeys(fn (array $requirement) => [$requirement['input'] => $requirement['message']])
            ->all();

        if ($revisionErrors !== []) {
            return back()->withInput()->withErrors($revisionErrors, 'resubmission');
        }

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

        $result = ['updated' => false];

        try {
            DB::transaction(function () use ($request, $topic, $validated, $replacementFiles, $packageService, $revisionDraft, &$result) {
                $revisedTopic = TopicProposal::query()
                    ->whereKey($topic->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($revisedTopic->user_id !== $request->user()->id || $revisedTopic->status !== 'revision_requested') {
                    return;
                }

                $nextVersion = ((int) $revisedTopic->versions()->max('version_number')) + 1;
                $previousVersion = $revisedTopic->latestVersion()->with('files')->first();
                $snapshotFiles = $packageService->revisionSnapshot($previousVersion, $replacementFiles, $revisedTopic);
                $primaryFile = $packageService->primaryFile($snapshotFiles);

                $revisedTopic->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? null,
                    'estimated_budget' => $validated['estimated_budget'],
                    'estimated_duration_months' => $validated['estimated_duration_months'],
                    'status' => 'resubmitted',
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
                    $replacementCandidates = $newVersionFiles
                        ->where('document_type', $pendingRevision->document_type)
                        ->where('is_carried_forward', false);
                    $resolutionFile = $replacementCandidates->firstWhere('position', $pendingRevision->file?->position)
                        ?: $replacementCandidates->first();

                    if (! $resolutionFile) {
                        throw ValidationException::withMessages([
                            'document' => 'Every file marked for revision must be replaced before resubmission.',
                        ]);
                    }

                    $pendingRevision->update([
                        'resolved_by_version_file_id' => $resolutionFile->id,
                        'resolved_at' => now(),
                    ]);
                }

                $revisionDraft?->delete();

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
            ),
        );

        $redirectRoute = ($validated['redirect_to'] ?? null) === 'topic' ? 'topics.show' : 'faculty.dashboard';

        return redirect()->route($redirectRoute, $redirectRoute === 'topics.show' ? $topic : [])->with('success', 'Revised proposal submitted for another review.');
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
    ): StreamedResponse {
        $this->ensureCanViewTopic($request, $topic);
        abort_unless($version->topic_id === $topic->id, 404);
        abort_unless($file->proposal_version_id === $version->id, 404);
        $this->ensureCanAccessVersionFile($request, $topic, $file);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);
        abort_unless(
            $file->mime_type === 'application/pdf'
                || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf',
            415,
        );

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
    ): RedirectResponse {
        $validated = $request->validated();

        $latestVersion = $topic->latestVersion()->first();

        if (! $latestVersion instanceof ProposalVersion) {
            return back()
                ->withInput()
                ->withErrors(['review_file' => 'This proposal does not have a submitted version to attach files to.'], 'headUpload');
        }

        $isSupplemental = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL;
        $isSignedCopy = $validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED;
        $sourceFile = $isSupplemental
            ? null
            : $latestVersion->files()
                ->whereKey($validated['source_file_id'])
                ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                ->firstOrFail();

        if ($isSignedCopy && ! in_array($topic->status, [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'], true)) {
            return back()
                ->withInput()
                ->withErrors(['purpose' => 'Signed final copies can only be uploaded after the proposal is ready for signature.'], 'headUpload');
        }

        if ($isSignedCopy
            && $sourceFile
            && ! $this->signatureWorkflow->requiredFiles($latestVersion)->contains('id', $sourceFile->id)) {
            return back()
                ->withInput()
                ->withErrors(['source_file_id' => $sourceFile->label().' was not selected for final signing.'], 'headUpload');
        }

        if ($validated['purpose'] === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION
            && in_array($topic->status, [TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved', 'rejected'], true)) {
            return back()
                ->withInput()
                ->withErrors(['purpose' => 'Revision copies cannot be added after the proposal enters final signing.'], 'headUpload');
        }

        $file = $request->file('review_file');
        $directory = 'proposal-packages/'.$topic->user_id.'/'.$topic->id.'/head-uploads/'.Str::uuid();
        $storedPath = null;
        $replacedPath = null;

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
                ],
            );
            $storedPath = $attributes['file_path'];

            DB::transaction(function () use ($topic, $latestVersion, $attributes, $request, $validated, $isSupplemental, $isSignedCopy, $sourceFile, &$replacedPath): void {
                $lockedVersion = ProposalVersion::query()
                    ->whereKey($latestVersion->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $existingSignedCopy = $isSignedCopy
                    ? $lockedVersion->files()
                        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                        ->where('source_version_file_id', $sourceFile?->id)
                        ->get()
                        ->first(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED)
                    : null;

                if ($existingSignedCopy instanceof ProposalVersionFile) {
                    $replacedPath = $existingSignedCopy->file_path;
                    $replacementAttributes = $attributes;
                    unset($replacementAttributes['position']);

                    $existingSignedCopy->update([
                        ...$replacementAttributes,
                        'uploaded_by' => $request->user()->id,
                    ]);
                } else {
                    $position = ((int) $lockedVersion->files()
                        ->where('document_type', ProposalVersionFile::TYPE_HEAD_UPLOAD)
                        ->max('position')) + 1;

                    $lockedVersion->files()->create([
                        ...$attributes,
                        'position' => $position,
                        'uploaded_by' => $request->user()->id,
                    ]);
                }

                $topic->reviews()->create([
                    'reviewer_id' => $request->user()->id,
                    'decision' => 'head_upload',
                    'comment' => $validated['note']
                        ?? ($isSupplemental ? 'Uploaded supplemental paper: '.$validated['document_title'] : null),
                ]);
            });

            if ($replacedPath !== null && $replacedPath !== $storedPath) {
                Storage::disk('local')->delete($replacedPath);
            }
        } catch (Throwable) {
            if ($storedPath !== null) {
                Storage::disk('local')->delete($storedPath);
            }

            return back()
                ->withInput()
                ->withErrors(['review_file' => 'The Research Head file could not be stored. Please try again.'], 'headUpload');
        }

        return redirect()
            ->to(route('topics.show', $topic).'#proposal-review')
            ->with('success', $isSupplemental
                ? 'Supplemental paper uploaded by the Research Head.'
                : 'Research Head file attached to the faculty submission.');
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
     *     missingSignatureFiles: Collection<int, ProposalVersionFile>
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
            ->sortByDesc('created_at')
            ->values();
        $supplementalHeadUploads = $headUploadedFiles
            ->filter(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL)
            ->values();
        $headUploadsBySource = $headUploadedFiles
            ->reject(fn (ProposalVersionFile $file): bool => ($file->source_data['purpose'] ?? null) === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL)
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
                && ($file->mime_type === 'application/pdf'
                    || Str::lower(pathinfo($file->original_filename, PATHINFO_EXTENSION)) === 'pdf'))
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
        );
    }

    private function ensureCanViewTopic(Request $request, TopicProposal $topic): void
    {
        $user = $request->user();

        abort_unless($user->isUsingWorkspace('research_head') || $topic->user_id === $user->id, 403);
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
            && $topic->status !== 'approved';

        abort_if($isUnreleasedSignedCopy, 404);
    }

    private function revisionDraftForResubmission(Request $request, TopicProposal $topic): ?ProposalDraft
    {
        $revisionDraftId = $request->integer('revision_draft_id');

        if ($revisionDraftId === 0) {
            return null;
        }

        $revisionDraft = ProposalDraft::query()
            ->with('documents')
            ->findOrFail($revisionDraftId);

        abort_unless(
            $revisionDraft->user_id === $request->user()->id
                && $revisionDraft->topic_id === $topic->id,
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

    private function availableTemplatesFor(string $stage)
    {
        return ProposalTemplate::active()
            ->where('workflow_stage', $stage)
            ->orderBy('name')
            ->get()
            ->filter(fn (ProposalTemplate $template) => Storage::disk('local')->exists($template->file_path))
            ->values();
    }
}
