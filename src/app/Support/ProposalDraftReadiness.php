<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProposalDraftReadiness
{
    public function __construct(
        private readonly ProposalPaperCatalog $catalog,
        private readonly ProposalBudgetConsistency $proposalBudgetConsistency,
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
            && $this->submissionFilesArePrepared($draft)
            && $draft->researchCall?->isAcceptingSubmissions();
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

            return $documents->every(fn (ProposalDraftDocument $document): bool => filled($document->file_path)
                && $document->mime_type === 'application/pdf'
                && Storage::disk('local')->exists($document->file_path));
        });
    }

    /** @return array<string, string> */
    public function errors(ProposalDraft $draft): array
    {
        $errors = [];

        if (! $this->projectDetailsAreComplete($draft)) {
            $errors['project_details'] = 'Complete Project Details before submitting this proposal package.';
        }

        foreach ($this->checklist($draft) as $slug => $item) {
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
                'The project budget exceeds the research call limit of Php %s by Php %s.',
                number_format($budgetComparison['budget_ceiling'], 2),
                number_format($budgetComparison['overage'], 2),
            );
        }

        if ($draft->researchCall === null) {
            $errors['research_call'] = 'An open research call must be selected before preparing submission PDFs. The proposal owner can use Choose research call; your draft remains available.';
        } elseif (! $draft->researchCall->isAcceptingSubmissions()) {
            $errors['research_call'] = 'This research call is no longer accepting submissions. Your draft remains available.';
        }

        return $errors;
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
                'work-plan' => is_array($document->source_data['entries'] ?? null)
                    && $document->source_data['entries'] !== [],
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
}
