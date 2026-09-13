<?php

namespace App\Services;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use DateTimeInterface;
use Illuminate\Support\Arr;

class ApprovedWorkPlanMonitoringService
{
    public function __construct(
        private readonly MonitoringQuarterService $monitoringQuarterService,
    ) {}

    public function hasApprovedWorkPlan(TopicProposal $topic): bool
    {
        return $this->approvedEntries($topic) !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function defaultsForDate(TopicProposal $topic, DateTimeInterface|string $reportingDate): array
    {
        $entries = $this->approvedEntries($topic);

        if ($entries === []) {
            return [];
        }

        $period = $this->monitoringQuarterService->forDate($reportingDate, $topic);
        $window = $this->monitoringQuarterService->reportingWindow($topic);
        $firstMonth = (($period['quarter'] - 1) * 3) + 1;
        $lastMonth = $firstMonth + 2;
        $totalScheduledMonths = collect($entries)->sum(
            fn (array $entry): int => count($entry['months']),
        );
        $allocatedWeight = 0.0;
        $lastEntryIndex = array_key_last($entries);

        return collect($entries)
            ->map(function (array $entry, int $sourceIndex) use ($totalScheduledMonths, &$allocatedWeight, $lastEntryIndex, $window): array {
                $weight = $sourceIndex === $lastEntryIndex
                    ? round(100 - $allocatedWeight, 2)
                    : round((count($entry['months']) / $totalScheduledMonths) * 100, 2);
                $allocatedWeight += $weight;
                $targetMonth = max($entry['months']);
                $targetDate = $window['start']->addMonthsNoOverflow($targetMonth)->subDay()->min($window['end']);

                return [
                    'source_work_plan_index' => $sourceIndex,
                    'objective' => $entry['objective'],
                    'activity' => $entry['activity'],
                    'percent_weight' => number_format($weight, 2, '.', ''),
                    'physical_target' => $entry['expected_output'],
                    'target_completion_date' => $targetDate->toDateString(),
                    'work_plan_months' => $entry['months'],
                    'actual_accomplishment' => '',
                    'accomplished_percentage' => '0',
                    'findings' => '',
                ];
            })
            ->filter(fn (array $entry): bool => collect($entry['work_plan_months'])
                ->contains(fn (int $month): bool => $month >= $firstMonth && $month <= $lastMonth))
            ->values()
            ->all();
    }

    /**
     * Keep the approved plan fields authoritative while retaining the researcher's progress fields.
     *
     * @param  array<int, mixed>  $submittedRows
     * @return list<array<string, mixed>>
     */
    public function synchronizeForDate(TopicProposal $topic, DateTimeInterface|string $reportingDate, array $submittedRows): array
    {
        $defaults = $this->defaultsForDate($topic, $reportingDate);

        if ($defaults === []) {
            return array_values(array_filter($submittedRows, 'is_array'));
        }

        $rows = collect($submittedRows)->filter(fn (mixed $row): bool => is_array($row));

        return collect($defaults)->map(function (array $default) use ($rows): array {
            $submitted = $rows->first(
                fn (array $row): bool => isset($row['source_work_plan_index'])
                    && (int) $row['source_work_plan_index'] === $default['source_work_plan_index'],
            ) ?? $rows->first(
                fn (array $row): bool => trim((string) ($row['activity'] ?? '')) === $default['activity'],
            ) ?? [];

            return [
                ...$default,
                ...Arr::only($submitted, [
                    'actual_accomplishment',
                    'accomplished_percentage',
                    'findings',
                ]),
            ];
        })->all();
    }

    /**
     * @return list<array{objective: string, activity: string, expected_output: string, months: list<int>}>
     */
    private function approvedEntries(TopicProposal $topic): array
    {
        $topic->loadMissing('versions.files');
        $latestVersion = $topic->versions->sortByDesc('version_number')->first();
        $sourceData = $latestVersion?->files
            ->firstWhere('document_type', ProposalVersionFile::TYPE_WORK_PLAN)?->source_data;

        if (! is_array($sourceData)) {
            return [];
        }

        $duration = max(1, (int) ($sourceData['total_duration_months']
            ?? data_get($topic->notice_to_proceed_data, 'approved_duration_months')
            ?? $topic->estimated_duration_months
            ?? 1));

        return collect($sourceData['entries'] ?? [])
            ->filter(fn (mixed $entry): bool => is_array($entry))
            ->map(function (array $entry) use ($duration): array {
                $months = collect($entry['months'] ?? [])
                    ->filter(fn (mixed $month): bool => is_numeric($month) && (int) $month >= 1 && (int) $month <= $duration)
                    ->map(fn (mixed $month): int => (int) $month)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'objective' => trim((string) ($entry['objective'] ?? '')),
                    'activity' => trim((string) ($entry['activity'] ?? '')),
                    'expected_output' => trim((string) ($entry['expected_output'] ?? '')),
                    'months' => $months,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['objective'] !== ''
                && $entry['activity'] !== ''
                && $entry['expected_output'] !== ''
                && $entry['months'] !== [])
            ->values()
            ->all();
    }
}
