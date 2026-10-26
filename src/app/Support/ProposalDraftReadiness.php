<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalSignatory;
use App\Models\ResearchCall;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProposalDraftReadiness
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
        private readonly ProposalBudgetConsistency $proposalBudgetConsistency,
        private readonly WorkPlanProposalObjectives $workPlanProposalObjectives,
    ) {}

    public function projectDetailsAreComplete(ProposalDraft $draft): bool
    {
        return $draft->projectDetailsAreComplete()
            && $draft->duration_months >= 1
            && $draft->duration_months <= (int) config('work_plan.max_duration_months')
            && $draft->planned_end->greaterThanOrEqualTo($draft->planned_start);
    }

    /**
     * @return Collection<string, array{
     *     paper: array<string, mixed>,
     *     documents: Collection<int, ProposalDraftDocument>,
     *     complete: bool,
     *     needs_attention: bool,
     *     status: string,
     *     count: int,
     *     submission_filename: string
     * }>
     */
    public function checklist(ProposalDraft $draft): Collection
    {
        $draft->loadMissing('documents');
        $budgetComparison = $this->proposalBudgetConsistency->compare($draft);
        $budgetNeedsAttention = $budgetComparison['available'] && ! $budgetComparison['consistent'];

        return $this->catalog->all()->mapWithKeys(function (array $paper) use ($budgetNeedsAttention, $draft): array {
            $documents = $draft->documents
                ->where('document_type', $paper['document_type'])
                ->sortBy('position')
                ->values();
            $complete = $this->paperIsComplete($draft, $paper, $documents);
            $needsAttention = $complete
                && $budgetNeedsAttention
                && in_array($paper['slug'], ['line-item-budget', 'expense-breakdown'], true);
            $status = match (true) {
                $needsAttention => 'Needs attention',
                $complete => 'Complete',
                $paper['mode'] === 'automatic' => 'Waiting for project details',
                $documents->isEmpty() => 'Not started',
                default => 'In progress',
            };

            return [$paper['slug'] => [
                'paper' => $paper,
                'documents' => $documents,
                'complete' => $complete,
                'needs_attention' => $needsAttention,
                'status' => $status,
                'count' => $documents->count(),
                'submission_filename' => $documents->first()?->original_filename
                    ?: $this->catalog->submissionFilename($paper, (string) $draft->project_title),
            ]];
        });
    }

    public function allPapersAreComplete(ProposalDraft $draft): bool
    {
        return $this->checklist($draft)->every('complete');
    }

    /**
     * @param  list<array<string, mixed>>  $preparedFiles
     * @return array{complete_documents: bool, initial_screening_form: bool}
     */
    public function detailedProposalChecklist(ProposalDraft $draft, array $preparedFiles = []): array
    {
        $checklist = $this->checklist($draft);
        $submittedFiles = $draft->topic_id !== null
            ? ($draft->topic?->latestVersion?->files ?? collect())
            : collect();
        $hasPdf = function (string $slug) use ($checklist, $preparedFiles, $submittedFiles): bool {
            $item = $checklist->get($slug);
            $preparedFile = collect($preparedFiles)->firstWhere('document_type', $item['paper']['document_type']);
            $file = $preparedFile ?? $item['documents']->first();
            $path = data_get($file, 'file_path');
            $complete = $item['complete'];
            if (blank($path)) {
                $file = $submittedFiles->firstWhere('document_type', $item['paper']['document_type']);
                $path = data_get($file, 'file_path');
                $complete = $file !== null;
            }

            return $complete && filled($path)
                && data_get($file, 'mime_type') === 'application/pdf'
                && Storage::disk('local')->exists($path);
        };

        return [
            'complete_documents' => $checklist->get('detailed-proposal')['complete']
                && ($preparedFiles !== [] || $hasPdf('detailed-proposal'))
                && $hasPdf('line-item-budget') && $hasPdf('work-plan'),
            'initial_screening_form' => $hasPdf('initial-screening-form'),
        ];
    }

    public function detailedProposalIsComplete(
        ProposalDraft $draft,
        ?ProposalDraftDocument $document,
    ): bool {
        if (! $document instanceof ProposalDraftDocument || ! is_array($document->source_data)) {
            return false;
        }

        return DetailedProposalRules::passesComplete([
            ...$document->source_data,
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
        ]);
    }

    public function isReady(ProposalDraft $draft): bool
    {
        return $this->projectDetailsAreComplete($draft)
            && $this->allPapersAreComplete($draft)
            && $this->proposalBudgetConsistency->compare($draft)['consistent']
            && $this->commentResponseSignatoriesAreComplete($draft)
            && $this->submissionFilesArePrepared($draft);
    }

    public function commentResponseSignatoriesAreComplete(ProposalDraft $draft): bool
    {
        $selections = $draft->resolvedSignatorySelections();

        return collect(array_keys(ProposalSignatory::FIELDS['comment_response_form']))
            ->every(fn (string $role): bool => filled($selections[$role]['name'] ?? null));
    }

    public function submissionFilesArePrepared(ProposalDraft $draft): bool
    {
        $draft->loadMissing('documents');

        return $this->catalog->all()->every(function (array $paper) use ($draft): bool {
            $documents = $draft->documents
                ->where('document_type', $paper['document_type'])
                ->sortBy('position')
                ->values();
            $minimumFiles = $paper['mode'] === 'upload' ? (int) $paper['min_files'] : 1;
            $maximumFiles = $paper['mode'] === 'upload' ? (int) $paper['max_files'] : 1;

            if ($documents->count() < $minimumFiles || $documents->count() > $maximumFiles) {
                return false;
            }

            return $documents->every(fn (ProposalDraftDocument $document): bool => $document->hasCurrentPreparedPdf($draft)
                && ($paper['mode'] !== 'automatic' || ! str_contains($document->file_path, '/prepared/manual/')));
        });
    }

    /** @return array<string, string> */
    public function errors(ProposalDraft $draft, ?Collection $checklist = null): array
    {
        $errors = [];

        if (! $this->commentResponseSignatoriesAreComplete($draft)) {
            $errors['signatories.comment_response_form'] = 'Ask the Research Head or Research Office Secretary to configure the default Comment Response signatories before submitting.';
        }

        if (! $this->projectDetailsAreComplete($draft)) {
            $errors['project_details'] = 'Complete Project Details before submitting this project.';
        }

        foreach ($checklist ?? $this->checklist($draft) as $slug => $item) {
            if (! $item['complete']) {
                $errors['papers.'.$slug] = $item['paper']['label'].' is incomplete or its staged file is unavailable.';
            }
        }

        $budgetComparison = $this->proposalBudgetConsistency->compare($draft);

        foreach ($budgetComparison['mismatches'] as $mismatch) {
            $errors['budget_consistency.'.$mismatch['key']] = sprintf(
                '%s does not match: Attachment B is Php %s while the Estimated Expense Breakdown is Php %s.',
                $mismatch['label'],
                number_format($mismatch['line_item_budget'], 2),
                number_format($mismatch['expense_breakdown'], 2),
            );
        }

        if ($budgetComparison['over_budget']) {
            $errors['budget_limit'] = sprintf(
                'The project budget exceeds the project budget limit of Php %s by Php %s.',
                number_format($budgetComparison['budget_ceiling'], 2),
                number_format($budgetComparison['overage'], 2),
            );
        }

        return $errors;
    }

    /**
     * @return list<array{key: string, paper: string|null, field: string|null, owner: 'faculty'|'research_office', reason: 'validation'|'save_confirmation', message: string}>
     */
    public function blockers(ProposalDraft $draft, ?Collection $checklist = null): array
    {
        $checklist ??= $this->checklist($draft);
        $blockers = [];

        foreach ($this->errors($draft, $checklist) as $key => $message) {
            $paper = null;
            $messages = ['' => [$message]];
            $reason = 'validation';

            if ($key === 'project_details') {
                $paper = 'details';
                $messages = $this->projectDetailErrors($draft);
            } elseif (str_starts_with($key, 'papers.')) {
                $paper = substr($key, strlen('papers.'));
                $item = $checklist->get($paper);

                if ($item['paper']['mode'] === 'automatic') {
                    continue;
                }

                if ($paper === 'work-plan' && $this->workPlanProposalObjectives->forDraft($draft) === []) {
                    $paper = 'detailed-proposal';
                    $messages = ['specific_objectives' => ['Save the specific objectives in the Detailed Proposal before completing the Work Plan.']];
                } else {
                    $messages = $this->paperCompletionErrors($draft, $item['paper'], $item['documents']);
                    $document = $item['documents']->first();

                    if ($item['paper']['mode'] === 'generated'
                        && $document instanceof ProposalDraftDocument
                        && is_array($document->source_data)
                        && $document->completed_at === null
                        && array_keys($messages) === ['']) {
                        $reason = 'save_confirmation';
                    }
                }
            } elseif (str_starts_with($key, 'budget_')) {
                $paper = 'line-item-budget';
                $field = match ($key) {
                    'budget_consistency.mooe_total' => 'mooe_total_override',
                    'budget_consistency.co_total' => 'co_total_override',
                    default => 'project_total_override',
                };
                $messages = [$field => [$message]];
            }

            foreach ($messages as $field => $errors) {
                $message = $errors[0];

                if (preg_match('/^(entries|items|people)\.(\d+)\./', $field, $matches)) {
                    $label = ['entries' => 'Objective', 'items' => 'Expense item', 'people' => 'CV member'][$matches[1]];
                    $message = $label.' '.((int) $matches[2] + 1).': '.$message;
                }

                $blockers[] = [
                    'key' => $key,
                    'paper' => $paper,
                    'field' => $field !== '' ? $field : null,
                    'owner' => str_starts_with($key, 'signatories.') ? 'research_office' : 'faculty',
                    'reason' => $reason,
                    'message' => $message,
                ];
            }
        }

        return $blockers;
    }

    /** @return array<string, list<string>> */
    private function projectDetailErrors(ProposalDraft $draft): array
    {
        $errors = [];

        foreach (['project_title' => 'project title', 'project_leader' => 'project leader', 'planned_start' => 'planned start date', 'planned_end' => 'planned end date'] as $field => $label) {
            if (blank($draft->{$field})) {
                $errors[$field] = ['Enter the '.$label.'.'];
            }
        }

        if ($draft->duration_months === null || $draft->duration_months < 1 || $draft->duration_months > (int) config('work_plan.max_duration_months')) {
            $errors['duration_months'] = ['Set a project duration from 1 to '.config('work_plan.max_duration_months').' months.'];
        }

        if ($draft->planned_start !== null && $draft->planned_end !== null && $draft->planned_end->lessThan($draft->planned_start)) {
            $errors['planned_end'] = ['Set the planned end date on or after the planned start date.'];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  Collection<int, ProposalDraftDocument>  $documents
     * @return array<string, list<string>>
     */
    private function paperCompletionErrors(ProposalDraft $draft, array $paper, Collection $documents): array
    {
        if ($paper['mode'] === 'upload') {
            return ['' => [$documents->count() < (int) $paper['min_files']
                ? 'Upload the required file for '.$paper['label'].'.'
                : 'Upload '.$paper['label'].' again using an accepted file format and size; its saved file is unavailable or invalid.']];
        }

        $document = $documents->first();
        $source = is_array($document?->source_data) ? $document->source_data : [];
        $source = [
            ...$source,
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
        ];

        if ($paper['slug'] === 'detailed-proposal') {
            $errors = DetailedProposalRules::completionErrors($source);
        } else {
            $callbacks = [];

            if ($paper['slug'] === 'work-plan') {
                $objectives = $this->workPlanProposalObjectives->forDraft($draft);

                $source['entries'] = $this->workPlanProposalObjectives->entries(is_array($source['entries'] ?? null) ? $source['entries'] : [], $objectives);
                $source['total_duration_months'] = $draft->duration_months;
                $source['prepared_by'] = $draft->project_leader;
                $rules = WorkPlanRules::rules();
                $rules['entries.*.objective'] = ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')];
                $attributes = WorkPlanRules::attributes();
                $callbacks = WorkPlanRules::afterCallbacks($source['entries'], $draft->duration_months);
            } elseif ($paper['slug'] === 'line-item-budget') {
                $expenseSource = $draft->documents->firstWhere('document_type', config('proposal_papers.expense-breakdown.document_type'))?->source_data;
                $source = LineItemBudgetData::synchronizeSourceWithExpenseBreakdown($source, is_array($expenseSource['items'] ?? null) ? $expenseSource['items'] : null);
                $rules = LineItemBudgetRules::rules();
                $attributes = LineItemBudgetRules::attributes();
                $callbacks = LineItemBudgetRules::afterCallbacks($draft->researchCall?->budgetCeiling() ?? ResearchCall::MAXIMUM_BUDGET);
            } elseif ($paper['slug'] === 'expense-breakdown') {
                $source = ExpenseBreakdownRules::normalizeInput($source);
                $rules = ExpenseBreakdownRules::rules();
                $attributes = ExpenseBreakdownRules::attributes();
            } else {
                $source = CurriculumVitaeRules::normalizeInput($source);
                $rules = CurriculumVitaeRules::rules();
                $attributes = CurriculumVitaeRules::attributes();
            }

            $validator = Validator::make($source, $rules, [], $attributes);
            $validator->after($callbacks);
            $errors = $validator->errors()->toArray();
        }

        foreach (['project_title', 'project_leader', 'planned_start', 'planned_end', 'total_duration_months', 'prepared_by'] as $field) {
            unset($errors[$field]);
        }

        if ($paper['slug'] === 'detailed-proposal' && $errors === [] && ! $this->projectDetailsAreComplete($draft)) {
            return [];
        }

        return $errors !== [] ? $errors : ['' => ['Finish saving '.$paper['label'].' to mark its content complete.']];
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  Collection<int, ProposalDraftDocument>  $documents
     */
    private function paperIsComplete(
        ProposalDraft $draft,
        array $paper,
        Collection $documents,
    ): bool {
        if ($paper['mode'] === 'automatic') {
            return $this->projectDetailsAreComplete($draft);
        }

        $minimum = (int) $paper['min_files'];
        $maximum = (int) $paper['max_files'];

        if ($documents->count() < $minimum || $documents->count() > $maximum) {
            return false;
        }

        if ($paper['mode'] === 'generated') {
            $document = $documents->first();

            if (! $document instanceof ProposalDraftDocument
                || ! is_array($document->source_data)) {
                return false;
            }

            if ($paper['slug'] === 'detailed-proposal') {
                return $this->detailedProposalIsComplete($draft, $document);
            }

            if ($document->completed_at === null) {
                return false;
            }

            return match ($paper['slug']) {
                'work-plan' => $this->workPlanIsComplete($draft, $document),
                'expense-breakdown' => is_array($document->source_data['items'] ?? null)
                    && $document->source_data['items'] !== [],
                'curriculum-vitae' => is_array($document->source_data['people'] ?? null)
                    && $document->source_data['people'] !== [],
                default => true,
            };
        }

        return $documents->every(function (ProposalDraftDocument $document) use ($paper): bool {
            if ($document->completed_at === null
                || ! $document->hasStagedFile()
                || ! Storage::disk('local')->exists($document->file_path)) {
                return false;
            }

            $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
            $maximumBytes = ((int) $paper['max_kilobytes']) * 1024;
            $actualSize = Storage::disk('local')->size($document->file_path);

            return in_array($extension, $paper['accepted_extensions'], true)
                && ($maximumBytes === 0 || $actualSize <= $maximumBytes);
        });
    }

    private function workPlanIsComplete(ProposalDraft $draft, ProposalDraftDocument $document): bool
    {
        if (! is_array($document->source_data['entries'] ?? null)) {
            return false;
        }

        $entries = $this->workPlanProposalObjectives->entries(
            $document->source_data['entries'] ?? [],
            $this->workPlanProposalObjectives->forDraft($draft),
        );

        $rules = WorkPlanRules::rules();
        $rules['entries.*.objective'] = ['required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')];
        $validator = Validator::make([
            'project_title' => $draft->project_title,
            'total_duration_months' => $draft->duration_months,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'prepared_by' => $draft->project_leader,
            'entries' => $entries,
        ], $rules, [], WorkPlanRules::attributes());
        $validator->after(WorkPlanRules::afterCallbacks($entries, $draft->duration_months));

        return $validator->passes();
    }
}
