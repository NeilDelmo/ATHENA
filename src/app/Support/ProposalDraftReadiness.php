<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ProposalDraftReadiness
{
    public function __construct(private readonly ProposalPaperCatalog $catalog) {}

    public function projectDetailsAreComplete(ProposalDraft $draft): bool
    {
        return $draft->projectDetailsAreComplete()
            && $draft->duration_months >= 1
            && $draft->duration_months <= 12
            && $draft->planned_end->greaterThanOrEqualTo($draft->planned_start);
    }

    /**
     * @return Collection<string, array{
     *     paper: array<string, mixed>,
     *     documents: Collection<int, ProposalDraftDocument>,
     *     complete: bool,
     *     status: string,
     *     count: int
     * }>
     */
    public function checklist(ProposalDraft $draft): Collection
    {
        $draft->loadMissing('documents');

        return $this->catalog->all()->mapWithKeys(function (array $paper) use ($draft): array {
            $documents = $draft->documents
                ->where('document_type', $paper['document_type'])
                ->sortBy('position')
                ->values();
            $complete = $this->paperIsComplete($paper, $documents);

            return [$paper['slug'] => [
                'paper' => $paper,
                'documents' => $documents,
                'complete' => $complete,
                'status' => $complete ? 'Complete' : ($documents->isEmpty() ? 'Not started' : 'In progress'),
                'count' => $documents->count(),
            ]];
        });
    }

    public function allPapersAreComplete(ProposalDraft $draft): bool
    {
        return $this->checklist($draft)->every('complete');
    }

    public function isReady(ProposalDraft $draft): bool
    {
        return $this->projectDetailsAreComplete($draft)
            && $this->allPapersAreComplete($draft)
            && $draft->researchCall?->isAcceptingSubmissions();
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

        if (! $draft->researchCall?->isAcceptingSubmissions()) {
            $errors['research_call'] = 'This research call is no longer accepting submissions. Your draft remains available.';
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $paper
     * @param  Collection<int, ProposalDraftDocument>  $documents
     */
    private function paperIsComplete(array $paper, Collection $documents): bool
    {
        $minimum = (int) $paper['min_files'];
        $maximum = (int) $paper['max_files'];

        if ($documents->count() < $minimum || $documents->count() > $maximum) {
            return false;
        }

        if ($paper['mode'] === 'generated') {
            $document = $documents->first();

            if (! $document instanceof ProposalDraftDocument
                || $document->completed_at === null
                || ! is_array($document->source_data)) {
                return false;
            }

            return match ($paper['slug']) {
                'work-plan' => is_array($document->source_data['entries'] ?? null)
                    && $document->source_data['entries'] !== [],
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
