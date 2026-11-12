<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use Illuminate\Support\Str;

class WorkPlanProposalObjectives
{
    /** @return list<string> */
    public function forDraft(ProposalDraft $draft): array
    {
        return collect($this->proposalForDraft($draft)['specific_objectives'])
            ->pluck('description')
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function proposalForDraft(ProposalDraft $draft): array
    {
        $draft->loadMissing('documents');
        $source = $draft->documents
            ->where('document_type', ProposalVersionFile::TYPE_DETAILED_PROPOSAL)
            ->firstWhere('position', 0)?->source_data ?? [];

        return DetailedProposalData::fromValidated($source);
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<string, string>
     */
    public function activities(array $proposal): array
    {
        $groups = collect($proposal['specific_method_objectives'])
            ->groupBy(fn (array $group): string => Str::lower(Str::squish($group['heading'])));

        return collect($proposal['specific_objectives'])->mapWithKeys(function (array $objective) use ($groups): array {
            $description = $objective['description'];
            $methods = $groups->get(Str::lower(Str::squish($description)), collect());

            return [$description => $methods->flatMap(fn (array $group): array => $group['methods'])
                ->pluck('description')->implode("\n")];
        })->all();
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
