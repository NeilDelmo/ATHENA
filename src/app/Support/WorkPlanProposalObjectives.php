<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;

class WorkPlanProposalObjectives
{
    /** @return list<string> */
    public function forDraft(ProposalDraft $draft): array
    {
        $draft->loadMissing('documents');
        $source = $draft->documents
            ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
            ->firstWhere('position', 0)?->source_data ?? [];

        return collect(DetailedProposalData::fromValidated($source)['specific_objectives'])
            ->pluck('description')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<string>  $objectives
     * @return list<array<string, mixed>>
     */
    public function entries(array $entries, array $objectives): array
    {
        $entries = array_values(array_filter($entries, 'is_array'));
        $matches = [];
        $used = [];

        foreach ($objectives as $index => $objective) {
            foreach ($entries as $entryIndex => $entry) {
                if (! isset($used[$entryIndex]) && ($entry['objective'] ?? '') === $objective) {
                    $matches[$index] = $entryIndex;
                    $used[$entryIndex] = true;
                    break;
                }
            }
        }

        return collect($objectives)->map(function (string $objective, int $index) use ($entries, $matches, &$used): array {
            $entryIndex = $matches[$index] ?? null;
            if ($entryIndex === null && isset($entries[$index]) && ! isset($used[$index])) {
                $entryIndex = $index;
                $used[$index] = true;
            }
            $entry = $entryIndex !== null ? $entries[$entryIndex] : [];

            return [
                'objective' => $objective,
                'expected_output' => $entry['expected_output'] ?? '',
                'activity' => $entry['activity'] ?? '',
                'months' => $entry['months'] ?? [],
            ];
        })->all();
    }
}
