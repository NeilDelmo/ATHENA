<?php

namespace App\Http\Controllers;

use App\Actions\CreateProposalRevisionDraft;
use App\Actions\SaveProposalDraftDocument;
use App\Http\Requests\StoreProposalDraftRequest;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ProposalFileAnnotation;
use App\Models\ProposalTemplate;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\ProposalRevisionTargetCatalog;
use App\Support\ProposalWorkspacePeople;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProposalDraftController extends Controller
{
    private const DUPLICATE_CREATION_WINDOW_SECONDS = 30;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ProposalDraft::class);

        $proposalDrafts = ProposalDraft::query()
            ->accessibleTo($request->user())
            ->with(['researchCall', 'documents', 'owner:id,name,email'])
            ->latest()
            ->paginate(12)
            ->withQueryString();
        $submittedProposals = TopicProposal::query()
            ->accessibleTo($request->user())
            ->select([
                'id',
                'user_id',
                'research_call_id',
                'title',
                'status',
                'project_status',
                'notice_to_proceed_issued_at',
                'created_at',
                'updated_at',
            ])
            ->with([
                'researchCall:id,title,academic_year',
                'user:id,name',
                'latestVersion',
            ])
            ->latest()
            ->paginate(12, ['*'], 'submitted-page')
            ->withQueryString();

        return view('faculty.proposal-drafts.index', compact(
            'proposalDrafts',
            'submittedProposals',
        ));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ProposalDraft::class);

        return view('faculty.proposal-drafts.create');
    }

    public function store(StoreProposalDraftRequest $request): RedirectResponse
    {
        Gate::authorize('create', ProposalDraft::class);

        $validated = $request->validated();
        $validated['project_title'] = trim($validated['project_title']);
        $researchCallId = $validated['research_call_id'] ?? null;
        $lockKey = 'proposal-draft-create:'.hash('sha256', implode('|', [
            $request->user()->id,
            Str::lower($validated['project_title']),
            $researchCallId ?? 'preparation-draft',
        ]));

        try {
            /** @var array{draft: ProposalDraft, created: bool} $result */
            $result = Cache::lock($lockKey, 10)->block(3, function () use ($request, $validated, $researchCallId): array {
                $existingDraft = $request->user()->proposalDrafts()
                    ->where('project_title', $validated['project_title'])
                    ->where('status', ProposalDraft::STATUS_DRAFT)
                    ->when(
                        $researchCallId === null,
                        fn (Builder $query): Builder => $query->whereNull('research_call_id'),
                        fn (Builder $query): Builder => $query->where('research_call_id', $researchCallId),
                    )
                    ->where('created_at', '>=', now()->subSeconds(self::DUPLICATE_CREATION_WINDOW_SECONDS))
                    ->latest('id')
                    ->first();

                if ($existingDraft instanceof ProposalDraft) {
                    return ['draft' => $existingDraft, 'created' => false];
                }

                return [
                    'draft' => $request->user()->proposalDrafts()->create($validated),
                    'created' => true,
                ];
            });
        } catch (LockTimeoutException) {
            return back()
                ->withInput()
                ->withErrors([
                    'project_title' => 'This draft is already being created. Wait a moment, then open it from your saved drafts.',
                ]);
        }

        return redirect()
            ->route('faculty.proposal-drafts.show', $result['draft'])
            ->with('success', $result['created']
                ? 'Proposal draft created. Complete the project details in the workspace.'
                : 'That draft was already created, so ATHENA opened the existing copy instead.');
    }

    public function show(
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        ProposalDraftReadiness $readiness,
        ProposalWorkspacePeople $proposalWorkspacePeople,
        ProposalBudgetConsistency $proposalBudgetConsistency,
    ): View {
        Gate::authorize('view', $proposalDraft);

        $proposalDraft->load([
            'researchCall',
            'documents',
            'owner:id,name,email,college',
            'members.user:id,name,email,avatar,college',
        ]);
        $checklist = $readiness->checklist($proposalDraft);
        $projectDetailsComplete = $readiness->projectDetailsAreComplete($proposalDraft);
        $readinessErrors = $readiness->errors($proposalDraft);
        $submissionFilesPrepared = $readiness->submissionFilesArePrepared($proposalDraft);
        $readyToPrepare = $readinessErrors === [];
        $readyToSubmit = $readyToPrepare && $submissionFilesPrepared;
        $budgetConsistency = $proposalBudgetConsistency->compare($proposalDraft);
        $workspacePeople = $proposalWorkspacePeople->forDraft($proposalDraft);
        $minimumProjectDate = now()->toDateString();
        $initialDuration = old('duration_months', $proposalDraft->duration_months);
        $initialPlannedStart = old('planned_start', $proposalDraft->planned_start?->toDateString());
        $initialPlannedEnd = old('planned_end', $proposalDraft->planned_end?->toDateString());
        $templates = $this->activeTemplates($catalog);
        $memberCandidates = Gate::allows('manageMembers', $proposalDraft)
            ? $this->memberCandidates($proposalDraft)
            : collect();
        $projectRoleCandidates = $proposalDraft->members
            ->filter(fn (ProposalDraftMember $member): bool => $member->isAccepted() && $member->user !== null)
            ->map(fn (ProposalDraftMember $member): array => [
                'id' => $member->getKey(),
                'name' => $member->user->name,
                'email' => $member->user->email,
                'avatar' => $member->user->avatar,
                'college' => $member->user->college,
            ])
            ->values();
        $projectSecretaryMember = $proposalDraft->members
            ->first(fn (ProposalDraftMember $member): bool => $member->isProjectSecretary());
        $historyCount = $proposalDraft->documentVersions()->count();
        $recentActivity = $proposalDraft->documentVersions()
            ->with('creator:id,name')
            ->limit(5)
            ->get();

        return view('faculty.proposal-drafts.show', compact(
            'proposalDraft',
            'checklist',
            'projectDetailsComplete',
            'readinessErrors',
            'readyToPrepare',
            'submissionFilesPrepared',
            'readyToSubmit',
            'budgetConsistency',
            'workspacePeople',
            'minimumProjectDate',
            'initialDuration',
            'initialPlannedStart',
            'initialPlannedEnd',
            'templates',
            'memberCandidates',
            'projectRoleCandidates',
            'projectSecretaryMember',
            'historyCount',
            'recentActivity',
        ));
    }

    public function revision(
        Request $request,
        TopicProposal $topic,
        CreateProposalRevisionDraft $createProposalRevisionDraft,
        ProposalPaperCatalog $catalog,
        ProposalRevisionTargetCatalog $revisionTargets,
    ): RedirectResponse {
        abort_unless(
            $topic->user_id === $request->user()->id
                && $topic->status === 'revision_requested',
            403,
        );

        $documentType = $request->string('document_type')->toString();
        $editorTarget = null;
        $annotation = null;

        if ($request->filled('annotation')) {
            $annotation = ProposalFileAnnotation::query()
                ->with('file.version')
                ->whereKey($request->integer('annotation'))
                ->whereNotNull('topic_review_file_revision_id')
                ->whereHas('fileRevision', fn ($query) => $query->whereNull('resolved_at'))
                ->whereHas('file.version', fn ($query) => $query->where('topic_id', $topic->id))
                ->first();

            abort_unless($annotation?->file, 404);
            $documentType = $annotation->file->document_type;
            $editorTarget = $revisionTargets->contains($annotation->file, $annotation->editor_target)
                ? $annotation->editor_target
                : null;
        }

        $proposalDraft = $createProposalRevisionDraft->handle($topic, $request->user());
        $paper = $catalog->forDocumentType($documentType);

        $url = $this->revisionWorkspaceUrl($proposalDraft, $paper, $editorTarget);
        if ($annotation && ($paper['mode'] ?? null) === 'generated') {
            $url .= (str_contains($url, '?') ? '&' : '?').'revision_annotation='.$annotation->id;
        }

        if ($request->boolean('revision_embed') && ($paper['mode'] ?? null) === 'generated') {
            $url .= (str_contains($url, '?') ? '&' : '?').'revision_embed=1';
        }

        return redirect()->to($url);
    }

    public function storeRevisionFile(
        Request $request,
        ProposalDraft $proposalDraft,
        ProposalPaperCatalog $catalog,
        SaveProposalDraftDocument $saveProposalDraftDocument,
    ): JsonResponse {
        Gate::authorize('update', $proposalDraft);
        $proposalDraft->loadMissing('topic');

        abort_unless($proposalDraft->topic?->status === 'revision_requested', 403);

        $documentType = $request->string('document_type')->toString();
        $paper = $catalog->forDocumentType($documentType);

        if (! is_array($paper) || $paper['mode'] !== 'generated') {
            throw ValidationException::withMessages([
                'document_type' => 'This file cannot be added to the revision workspace.',
            ]);
        }

        $extensions = 'doc,docx,pdf';
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:'.$extensions, 'max:25600'],
            'document_version' => ['sometimes', 'integer', 'min:0'],
        ]);
        $file = $validated['file'];
        $document = $proposalDraft->documents()
            ->where('document_type', $documentType)
            ->where('position', 0)
            ->first();
        $storedPath = $file->store($proposalDraft->storageDirectory().'/revision/'.$documentType, 'local');

        if (! $storedPath) {
            throw ValidationException::withMessages([
                'file' => 'The downloaded file could not be staged for revision upload.',
            ]);
        }

        try {
            $savedDocument = $saveProposalDraftDocument->handle(
                $proposalDraft,
                $request->user(),
                $documentType,
                0,
                $request->has('document_version') ? $request->integer('document_version') : ($document?->lock_version ?? 0),
                [
                    'source_data' => $document?->source_data,
                    'file_path' => $storedPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'file_size' => $file->getSize() ?: null,
                    'checksum' => hash_file('sha256', Storage::disk('local')->path($storedPath)) ?: null,
                    'completed_at' => now(),
                ],
                changeNote: 'Exact downloaded file staged for revision submission.',
                forceCheckpoint: true,
            );
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            throw $exception;
        }

        if ($document?->file_path && $document->file_path !== $savedDocument->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        return response()->json([
            'filename' => $savedDocument->original_filename,
            'draft_id' => $proposalDraft->id,
            'document_type' => $documentType,
            'document_version' => $savedDocument->lock_version,
            'redirect_url' => route('faculty.topics.revision', $proposalDraft->topic_id).'#review-and-submit',
        ]);
    }

    /** @param array<string, mixed>|null $paper */
    private function revisionWorkspaceUrl(ProposalDraft $proposalDraft, ?array $paper, ?string $editorTarget = null): string
    {
        if (! is_array($paper)) {
            return route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments';
        }

        return match ($paper['slug']) {
            'detailed-proposal' => $this->revisionEditorUrl('faculty.proposal-drafts.detailed-proposal.edit', $proposalDraft, $editorTarget),
            'work-plan' => $this->revisionEditorUrl('faculty.proposal-drafts.work-plan.edit', $proposalDraft, $editorTarget),
            'line-item-budget' => $this->revisionEditorUrl('faculty.proposal-drafts.line-item-budget.edit', $proposalDraft, $editorTarget),
            'expense-breakdown' => $this->revisionEditorUrl('faculty.proposal-drafts.expense-breakdown.edit', $proposalDraft, $editorTarget),
            'curriculum-vitae' => $this->revisionEditorUrl('faculty.proposal-drafts.curriculum-vitae.edit', $proposalDraft, $editorTarget),
            'gad-checklist' => route('faculty.proposal-drafts.gad-checklist.show', $proposalDraft),
            'initial-screening-form' => route('faculty.proposal-drafts.initial-screening-form.show', $proposalDraft),
            default => ($paper['mode'] ?? null) === 'upload'
                ? route('faculty.proposal-drafts.papers.edit', [$proposalDraft, $paper['slug']])
                : route('faculty.proposal-drafts.show', $proposalDraft).'#required-pdf-attachments',
        };
    }

    private function revisionEditorUrl(string $routeName, ProposalDraft $proposalDraft, ?string $editorTarget): string
    {
        return route($routeName, array_filter([
            'proposalDraft' => $proposalDraft,
            'revision_target' => $editorTarget,
        ], fn (mixed $value): bool => filled($value)));
    }

    public function destroy(ProposalDraft $proposalDraft): RedirectResponse
    {
        Gate::authorize('delete', $proposalDraft);

        $storageDirectory = $proposalDraft->storageDirectory();
        $proposalDraft->delete();
        Storage::disk('local')->deleteDirectory($storageDirectory);

        return redirect()
            ->route('faculty.proposal-drafts.index')
            ->with('success', 'Proposal draft deleted.');
    }

    private function activeTemplates(ProposalPaperCatalog $catalog): Collection
    {
        $templateSlugs = $catalog->all()
            ->pluck('template_slug')
            ->filter()
            ->unique()
            ->values();

        return ProposalTemplate::query()
            ->active()
            ->where('workflow_stage', ProposalTemplate::STAGE_INITIAL_SUBMISSION)
            ->whereIn('slug', $templateSlugs)
            ->get()
            ->filter(fn (ProposalTemplate $template): bool => Storage::disk('local')->exists($template->file_path))
            ->keyBy('slug');
    }

    private function memberCandidates(ProposalDraft $proposalDraft): Collection
    {
        $domains = collect(config('services.google.allowed_domains', []))
            ->filter(fn (mixed $domain): bool => is_string($domain) && filled($domain))
            ->map(fn (string $domain): string => mb_strtolower(trim($domain)))
            ->values();
        $memberUserIds = $proposalDraft->members->pluck('user_id')->filter()->all();

        return User::query()
            ->select(['id', 'name', 'email', 'avatar', 'college', 'email_verified_at'])
            ->whereNotNull('email_verified_at')
            ->whereKeyNot($proposalDraft->user_id)
            ->whereHas('roles', fn (Builder $roles): Builder => $roles->whereIn('name', [
                User::WORKSPACE_FACULTY,
                User::WORKSPACE_FACULTY_RESEARCHER,
                User::WORKSPACE_RESEARCH_HEAD,
            ]))
            ->when($memberUserIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $memberUserIds))
            ->when($domains->isNotEmpty(), function (Builder $query) use ($domains): void {
                $query->where(function (Builder $emailQuery) use ($domains): void {
                    foreach ($domains as $domain) {
                        $emailQuery->orWhere('email', 'like', '%@'.$domain);
                    }
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (User $user): array => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'college' => $user->college,
            ])
            ->values();
    }
}
