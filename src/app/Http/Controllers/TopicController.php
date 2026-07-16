<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTopicProposalRequest;
use App\Models\ProposalTemplate;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\TopicReviewFileRevision;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\ProposalPackageService;
use App\Services\WorkPlanDocumentService;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TopicController extends Controller
{
    public function index()
    {
        $topics = Auth::user()->proposals()
            ->with([
                'researchCall', 'category',
                'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file'])->oldest(),
                'expertAssignments.expert',
                'versions.submitter',
                'versions.files',
            ])
            ->latest()
            ->get();

        $activeCalls = ResearchCall::query()
            ->where('status', 'open')
            ->where('opens_at', '<=', now())
            ->where('closes_at', '>=', now())
            ->orderBy('closes_at')
            ->get();

        $revisionResponseTemplates = $this->availableTemplatesFor(ProposalTemplate::STAGE_REVISION_RESPONSE);

        return view('faculty.dashboard', compact('topics', 'activeCalls', 'revisionResponseTemplates'));
    }

    public function create(Request $request)
    {
        return redirect()->route('faculty.proposal-drafts.index');
    }

    public function researchIndex(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $allowedStatuses = ['pending', 'expert_review', 'for_final_decision', 'revision_requested', 'resubmitted', 'approved', 'rejected'];

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
            'user', 'researchCall', 'category', 'expertAssignments.expert', 'versions.submitter', 'versions.files', 'progressReports.submitter', 'progressReports.reviewer',
            'reviews' => fn ($query) => $query->with(['reviewer', 'fileRevisions.file'])->oldest(),
        ]);

        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
        $previousVersion = $topic->versions
            ->where('version_number', '<', $latestVersion?->version_number ?? 0)
            ->sortByDesc('version_number')
            ->first();

        $requiredDocuments = app(ProposalPaperCatalog::class)
            ->all()
            ->mapWithKeys(fn (array $paper): array => [$paper['document_type'] => $paper['label']]);

        $packageChecklist = collect($requiredDocuments)->map(function (string $label, string $type) use ($latestVersion, $topic) {
            $files = $latestVersion?->files->where('document_type', $type) ?? collect();

            if ($type === ProposalVersionFile::TYPE_DETAILED_PROPOSAL && $files->isEmpty()) {
                $legacyPath = $latestVersion?->file_path ?: ($topic->final_file_path ?: $topic->initial_file_path);
                $available = $legacyPath && Storage::disk('local')->exists($legacyPath);

                return [
                    'type' => $type,
                    'label' => $label,
                    'count' => $available ? 1 : 0,
                    'status' => $available ? 'complete' : 'missing',
                ];
            }

            $availableCount = $files->filter(
                fn (ProposalVersionFile $file) => Storage::disk('local')->exists($file->file_path),
            )->count();

            return [
                'type' => $type,
                'label' => $label,
                'count' => $files->count(),
                'status' => match (true) {
                    $files->isEmpty() => 'missing',
                    $availableCount !== $files->count() => 'file_missing',
                    default => 'complete',
                },
            ];
        })->values();

        $comparisonRows = collect([
            ['label' => 'Project title', 'previous' => $previousVersion?->title, 'latest' => $latestVersion?->title],
            ['label' => 'Total project cost', 'previous' => $previousVersion ? 'PHP '.number_format((float) $previousVersion->estimated_budget, 2) : null, 'latest' => $latestVersion ? 'PHP '.number_format((float) $latestVersion->estimated_budget, 2) : null],
            ['label' => 'Project duration', 'previous' => $previousVersion ? $previousVersion->estimated_duration_months.' months' : null, 'latest' => $latestVersion ? $latestVersion->estimated_duration_months.' months' : null],
            ['label' => 'Description', 'previous' => $previousVersion?->description ?: 'Not provided', 'latest' => $latestVersion?->description ?: 'Not provided'],
        ])->map(fn (array $row) => [
            ...$row,
            'changed' => $previousVersion && $row['previous'] !== $row['latest'],
        ]);

        $experts = $request->user()->isUsingWorkspace('research_head')
            ? User::role('expert')->orderBy('name')->get()
            : collect();
        $expertAssignment = $topic->expertAssignments->firstWhere('expert_id', $request->user()->id);
        $pendingFileRevisions = $topic->reviews
            ->flatMap->fileRevisions
            ->whereNull('resolved_at')
            ->values();
        $screeningTemplates = $this->availableTemplatesFor(ProposalTemplate::STAGE_INITIAL_SCREENING);
        $revisionResponseTemplates = $this->availableTemplatesFor(ProposalTemplate::STAGE_REVISION_RESPONSE);

        return view('topics.show', compact(
            'topic',
            'latestVersion',
            'previousVersion',
            'packageChecklist',
            'comparisonRows',
            'experts',
            'expertAssignment',
            'pendingFileRevisions',
            'screeningTemplates',
            'revisionResponseTemplates',
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
                        'title',
                        'project_title',
                        'total_duration_months',
                        'planned_start',
                        'planned_end',
                        'entries',
                        'prepared_by',
                        'prepared_date',
                        'verified_date',
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
            'comment_response' => 'required|file|mimes:pdf,doc,docx|max:25600',
        ], [
            'estimated_budget.max' => 'The total project cost may not exceed PHP '.number_format($maximumBudget, 2).'.',
        ], [
            'estimated_budget' => 'total project cost',
            'detailed_proposal' => 'detailed proposal',
            'document' => 'detailed proposal',
            'work_plan' => 'work plan',
            'line_item_budget' => 'line-item budget',
            'expense_breakdown' => 'expense breakdown',
            'curricula_vitae.*' => 'curriculum vitae file',
            'gad_checklist' => 'GAD checklist',
            'comment_response' => 'comment-response form',
        ]);

        $pendingFileRevisions = TopicReviewFileRevision::query()
            ->with('file')
            ->whereNull('resolved_at')
            ->whereHas('review', fn ($query) => $query->where('topic_id', $topic->id))
            ->get();
        $requiredDocumentTypes = $pendingFileRevisions->pluck('document_type')->unique();
        $revisionErrors = collect([
            ProposalVersionFile::TYPE_DETAILED_PROPOSAL => ['input' => 'detailed_proposal', 'provided' => $request->hasFile('detailed_proposal') || $request->hasFile('document'), 'message' => 'Upload a revised detailed proposal as requested by the Research Head.'],
            ProposalVersionFile::TYPE_WORK_PLAN => ['input' => 'work_plan', 'provided' => $request->hasFile('work_plan'), 'message' => 'Upload a revised work plan as requested by the Research Head.'],
            ProposalVersionFile::TYPE_LINE_ITEM_BUDGET => ['input' => 'line_item_budget', 'provided' => $request->hasFile('line_item_budget'), 'message' => 'Upload a revised line-item budget as requested by the Research Head.'],
            ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN => ['input' => 'expense_breakdown', 'provided' => $request->hasFile('expense_breakdown'), 'message' => 'Upload a revised expense breakdown as requested by the Research Head.'],
            ProposalVersionFile::TYPE_CURRICULUM_VITAE => ['input' => 'curricula_vitae', 'provided' => $request->hasFile('curricula_vitae'), 'message' => 'Upload the revised curriculum vitae file(s) requested by the Research Head.'],
            ProposalVersionFile::TYPE_GAD_CHECKLIST => ['input' => 'gad_checklist', 'provided' => $request->hasFile('gad_checklist'), 'message' => 'Upload the revised GAD checklist requested by the Research Head.'],
        ])->only($requiredDocumentTypes->all())
            ->reject(fn (array $requirement) => $requirement['provided'])
            ->mapWithKeys(fn (array $requirement) => [$requirement['input'] => $requirement['message']])
            ->all();

        if ($revisionErrors !== []) {
            return back()->withInput()->withErrors($revisionErrors, 'resubmission');
        }

        try {
            $replacementFiles = $packageService->storeFromRequest(
                $request,
                'proposal-packages/'.$request->user()->id.'/'.Str::uuid(),
            );
        } catch (Throwable) {
            return back()
                ->withInput()
                ->withErrors(['detailed_proposal' => 'The revised proposal package could not be uploaded. Please try again.'], 'resubmission');
        }

        $result = ['updated' => false];

        try {
            DB::transaction(function () use ($request, $topic, $validated, $replacementFiles, $packageService, &$result) {
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

        Notification::send(
            User::role('research_head')->get(),
            new ProposalActivityNotification(
                'Proposal revision submitted',
                $request->user()->name.' submitted a new version of “'.$topic->fresh()->title.'”.',
                route('topics.show', $topic),
                'info',
                $topic->id,
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
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        return Storage::disk('local')->download($file->file_path, $file->original_filename);
    }

    public function downloadApproval(TopicProposal $topic)
    {
        $this->ensureCanViewTopic(request(), $topic);
        abort_unless($topic->signed_approval_path, 404);
        abort_unless(Storage::disk('local')->exists($topic->signed_approval_path), 404);

        return Storage::disk('local')->download($topic->signed_approval_path, 'signed-approval-'.$topic->id.'.pdf');
    }

    private function ensureCanViewTopic(Request $request, TopicProposal $topic): void
    {
        $user = $request->user();

        $canExpertView = $user->isUsingWorkspace('expert')
            && $topic->expertAssignments()->where('expert_id', $user->id)->exists();

        abort_unless($user->isUsingWorkspace('research_head') || $canExpertView || $topic->user_id === $user->id, 403);
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
