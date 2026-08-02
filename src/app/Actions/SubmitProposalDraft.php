<?php

namespace App\Actions;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalDraftMember;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\CurriculumVitaeDocumentService;
use App\Services\DetailedProposalDocumentService;
use App\Services\ExpenseBreakdownDocumentService;
use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Services\ProposalPackageService;
use App\Services\WorkPlanDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\CurriculumVitaeRules;
use App\Support\DetailedProposalData;
use App\Support\DetailedProposalRules;
use App\Support\ExpenseBreakdownData;
use App\Support\ExpenseBreakdownRules;
use App\Support\GADChecklistData;
use App\Support\LineItemBudgetData;
use App\Support\LineItemBudgetRules;
use App\Support\ProposalDraftReadiness;
use App\Support\ProposalPaperCatalog;
use App\Support\WorkPlanData;
use App\Support\WorkPlanRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubmitProposalDraft
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
        private readonly ProposalDraftReadiness $readiness,
        private readonly ProposalPackageService $packageService,
        private readonly DetailedProposalDocumentService $detailedProposalDocumentService,
        private readonly WorkPlanDocumentService $workPlanDocumentService,
        private readonly LineItemBudgetDocumentService $lineItemBudgetDocumentService,
        private readonly ExpenseBreakdownDocumentService $expenseBreakdownDocumentService,
        private readonly CurriculumVitaeDocumentService $curriculumVitaeDocumentService,
        private readonly GADChecklistDocumentService $gadChecklistDocumentService,
        private readonly InitialScreeningFormDocumentService $initialScreeningFormDocumentService,
        private readonly ArchiveProposalDraftDocumentHistory $archiveDocumentHistory,
    ) {}

    public function handle(ProposalDraft $draft, User $user): TopicProposal
    {
        $permanentDirectory = 'proposal-packages/'.$user->id.'/'.Str::uuid();
        $stagingDirectory = $draft->storageDirectory();
        $permanentFiles = [];
        $collaboratorIds = [];

        try {
            $topic = DB::transaction(function () use (
                $draft,
                $user,
                $permanentDirectory,
                &$collaboratorIds,
                &$permanentFiles,
            ): TopicProposal {
                $lockedDraft = ProposalDraft::query()
                    ->whereKey($draft->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedDraft->user_id !== $user->id) {
                    abort(403);
                }

                if ($lockedDraft->status !== ProposalDraft::STATUS_DRAFT) {
                    throw ValidationException::withMessages([
                        'status' => 'This proposal draft is already being submitted or is no longer available.',
                    ]);
                }

                $lockedDraft->load(['researchCall', 'members']);
                $collaboratorIds = $lockedDraft->members
                    ->filter(fn (ProposalDraftMember $member): bool => $member->isAccepted() && $member->user_id !== $user->id)
                    ->pluck('user_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $documents = ProposalDraftDocument::query()
                    ->where('proposal_draft_id', $lockedDraft->id)
                    ->orderBy('document_type')
                    ->orderBy('position')
                    ->lockForUpdate()
                    ->get();
                $lockedDraft->setRelation('documents', $documents);

                $errors = $this->readiness->errors($lockedDraft);

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }

                $lockedDraft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);

                foreach ($this->catalog->all() as $paper) {
                    $paperDocuments = $documents
                        ->where('document_type', $paper['document_type'])
                        ->sortBy('position')
                        ->values();

                    if ($paper['mode'] === 'automatic') {
                        $permanentFiles[] = match ($paper['slug']) {
                            'gad-checklist' => $this->generateGADChecklist($lockedDraft, $permanentDirectory),
                            'initial-screening-form' => $this->generateInitialScreeningForm($lockedDraft, $permanentDirectory),
                            default => throw ValidationException::withMessages([
                                'papers.'.$paper['slug'] => $paper['label'].' does not have an automatic document generator.',
                            ]),
                        };

                        continue;
                    }

                    if ($paper['mode'] === 'generated') {
                        $document = $paperDocuments->firstOrFail();
                        $permanentFiles[] = match ($paper['slug']) {
                            'detailed-proposal' => $this->generateDetailedProposal($lockedDraft, $document, $permanentDirectory),
                            'work-plan' => $this->generateWorkPlan($lockedDraft, $document, $permanentDirectory),
                            'line-item-budget' => $this->generateLineItemBudget($lockedDraft, $document, $permanentDirectory),
                            'expense-breakdown' => $this->generateExpenseBreakdown($lockedDraft, $document, $permanentDirectory),
                            'curriculum-vitae' => $this->generateCurriculumVitae($lockedDraft, $document, $permanentDirectory),
                            default => throw ValidationException::withMessages([
                                'papers.'.$paper['slug'] => $paper['label'].' does not have a document generator.',
                            ]),
                        };

                        continue;
                    }

                    foreach ($paperDocuments as $document) {
                        $permanentFiles[] = $this->copyStagedDocument(
                            $document,
                            $paper,
                            $permanentDirectory,
                        );
                    }
                }

                $primaryFile = $this->packageService->primaryFile($permanentFiles);
                $topic = $user->proposals()->create([
                    'research_call_id' => $lockedDraft->research_call_id,
                    'title' => $lockedDraft->project_title,
                    'estimated_duration_months' => $lockedDraft->duration_months,
                    'status' => 'pending',
                ]);
                $version = $topic->versions()->create([
                    'submitted_by' => $user->id,
                    'version_number' => 1,
                    'submission_type' => 'initial',
                    'file_path' => $primaryFile['file_path'],
                    'original_filename' => $primaryFile['original_filename'],
                    'mime_type' => $primaryFile['mime_type'],
                    'file_size' => $primaryFile['file_size'],
                    'checksum' => $primaryFile['checksum'],
                    'title' => $lockedDraft->project_title,
                    'estimated_duration_months' => $lockedDraft->duration_months,
                ]);
                $version->files()->createMany($permanentFiles);
                $this->archiveDocumentHistory->handle($lockedDraft, $topic, $permanentDirectory);
                $lockedDraft->delete();

                return $topic;
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($permanentDirectory);

            throw $exception;
        }

        Storage::disk('local')->deleteDirectory($stagingDirectory);

        try {
            Notification::send(
                User::role('research_head')->get(),
                new ProposalActivityNotification(
                    title: 'New proposal submitted',
                    message: $user->name.' submitted “'.$topic->title.'” for review.',
                    url: route('topics.show', $topic),
                    topicId: $topic->id,
                    workspace: User::WORKSPACE_RESEARCH_HEAD,
                ),
            );

            $collaborators = User::query()->whereKey($collaboratorIds)->get();

            if ($collaborators->isNotEmpty()) {
                Notification::send(
                    $collaborators,
                    new ProposalActivityNotification(
                        title: 'Proposal submitted for review',
                        message: 'The collaborative proposal “'.$topic->title.'” was submitted for Research Head review.',
                        url: route('faculty.dashboard'),
                        topicId: $topic->id,
                    ),
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $topic;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @return array<string, mixed>
     */
    private function copyStagedDocument(
        ProposalDraftDocument $document,
        array $paper,
        string $permanentDirectory,
    ): array {
        $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
        $path = $permanentDirectory.'/'.$paper['slug'].'/'.Str::uuid().'.'.$extension;

        if (! Storage::disk('local')->copy($document->file_path, $path)) {
            throw ValidationException::withMessages([
                'papers.'.$paper['slug'] => $paper['label'].' could not be copied into the proposal package.',
            ]);
        }

        $absolutePath = Storage::disk('local')->path($path);

        return [
            'source_version_file_id' => null,
            'document_type' => $document->document_type,
            'position' => $document->position,
            'file_path' => $path,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type ?: Storage::disk('local')->mimeType($path),
            'file_size' => Storage::disk('local')->size($path),
            'checksum' => hash_file('sha256', $absolutePath) ?: null,
            'source_data' => null,
            'is_carried_forward' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function generateDetailedProposal(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            ...($document->source_data ?? []),
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
        ];
        $validator = Validator::make(
            $sourceData,
            DetailedProposalRules::rules(),
            [],
            DetailedProposalRules::attributes(),
        );
        $validator->after(DetailedProposalRules::afterCallbacks());
        $validated = $validator->validate();
        $detailedProposal = DetailedProposalData::fromValidated(
            $validated,
            $this->detailedProposalBudgetTotals($draft),
        );

        return $this->packageService->storeGeneratedDetailedProposal(
            $this->detailedProposalDocumentService->generate($detailedProposal),
            $permanentDirectory,
            $draft->project_title,
            $validated,
        );
    }

    /** @return array{mooe_total: float, co_total: float} */
    private function detailedProposalBudgetTotals(ProposalDraft $draft): array
    {
        $budgetDocument = $draft->documents->firstWhere(
            'document_type',
            config('proposal_papers.line-item-budget.document_type'),
        );
        $sourceData = $budgetDocument?->source_data;

        if (! is_array($sourceData) || $draft->planned_start === null || $draft->planned_end === null) {
            return ['mooe_total' => 0, 'co_total' => 0];
        }

        $budget = LineItemBudgetData::fromValidated([
            ...$sourceData,
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start->toDateString(),
            'planned_end' => $draft->planned_end->toDateString(),
            'project_leader' => $draft->project_leader,
        ]);

        return [
            'mooe_total' => (float) $budget['mooe_total'],
            'co_total' => (float) $budget['co_total'],
        ];
    }

    /** @return array<string, mixed> */
    private function generateWorkPlan(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            'project_title' => $draft->project_title,
            'total_duration_months' => $draft->duration_months,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'entries' => $document->source_data['entries'] ?? null,
            'prepared_by' => $draft->project_leader,
        ];
        $validated = Validator::make(
            $sourceData,
            WorkPlanRules::rules(),
            [],
            WorkPlanRules::attributes(),
        )->validate();
        $workPlan = WorkPlanData::fromValidated($validated);

        return $this->packageService->storeGeneratedWorkPlan(
            $this->workPlanDocumentService->generate($workPlan),
            $permanentDirectory,
            $draft->project_title,
            $validated,
        );
    }

    /** @return array<string, mixed> */
    private function generateLineItemBudget(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            ...($document->source_data ?? []),
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'project_leader' => $draft->project_leader,
        ];
        $validator = Validator::make(
            $sourceData,
            LineItemBudgetRules::rules(),
            [],
            LineItemBudgetRules::attributes(),
        );
        $validator->after(LineItemBudgetRules::afterCallbacks(
            $draft->researchCall?->budgetCeiling() ?? ResearchCall::MAXIMUM_BUDGET,
        ));
        $validated = $validator->validate();
        $lineItemBudget = LineItemBudgetData::fromValidated($validated);
        $submittedSourceData = [
            ...$validated,
            'computed_mooe_total' => $lineItemBudget['computed_mooe_total'],
            'mooe_total' => $lineItemBudget['mooe_total'],
            'computed_co_total' => $lineItemBudget['computed_co_total'],
            'co_total' => $lineItemBudget['co_total'],
            'computed_project_total' => $lineItemBudget['computed_project_total'],
            'project_total' => $lineItemBudget['project_total'],
        ];

        return $this->packageService->storeGeneratedLineItemBudget(
            $this->lineItemBudgetDocumentService->generate($lineItemBudget),
            $permanentDirectory,
            $draft->project_title,
            $submittedSourceData,
        );
    }

    /** @return array<string, mixed> */
    private function generateExpenseBreakdown(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            ...($document->source_data ?? []),
            'project_title' => $draft->project_title,
        ];
        $validated = Validator::make(
            $sourceData,
            ExpenseBreakdownRules::rules(),
            [],
            ExpenseBreakdownRules::attributes(),
        )->validate();
        $expenseBreakdown = ExpenseBreakdownData::fromValidated($validated);

        return $this->packageService->storeGeneratedExpenseBreakdown(
            $this->expenseBreakdownDocumentService->generate($expenseBreakdown),
            $permanentDirectory,
            $draft->project_title,
            $validated,
        );
    }

    /** @return array<string, mixed> */
    private function generateCurriculumVitae(
        ProposalDraft $draft,
        ProposalDraftDocument $document,
        string $permanentDirectory,
    ): array {
        $sourceData = $document->source_data ?? [];
        $validated = Validator::make(
            $sourceData,
            CurriculumVitaeRules::rules(),
            [],
            CurriculumVitaeRules::attributes(),
        )->validate();
        $curriculumVitae = CurriculumVitaeData::fromValidated($validated);

        return $this->packageService->storeGeneratedCurriculumVitae(
            $this->curriculumVitaeDocumentService->generate($curriculumVitae),
            $permanentDirectory,
            $draft->project_title,
            $validated,
        );
    }

    /** @return array<string, mixed> */
    private function generateGADChecklist(
        ProposalDraft $draft,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
        ];
        $checklist = GADChecklistData::fromValidated($sourceData);

        return $this->packageService->storeGeneratedGADChecklist(
            $this->gadChecklistDocumentService->generate($checklist),
            $permanentDirectory,
            $draft->project_title,
            $sourceData,
        );
    }

    /** @return array<string, mixed> */
    private function generateInitialScreeningForm(
        ProposalDraft $draft,
        string $permanentDirectory,
    ): array {
        $sourceData = [
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
        ];

        return $this->packageService->storeGeneratedInitialScreeningForm(
            $this->initialScreeningFormDocumentService->generate($sourceData),
            $permanentDirectory,
            $draft->project_title,
            $sourceData,
        );
    }
}
