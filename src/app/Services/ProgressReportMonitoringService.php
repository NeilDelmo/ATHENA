<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Support\ProgressReportData;
use DateTimeImmutable;

class ProgressReportMonitoringService
{
    public function __construct(private readonly MonitoringQuarterService $quarters) {}

    public function forDate(TopicProposal $topic, ?string $date, ?int $id = null): ?ProjectProgressReport
    {
        $parsed = $date ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            return null;
        }
        $period = $this->quarters->forDate($date, $topic);

        return $topic->progressReports()->submitted()
            ->whereBetween('reporting_date', [$period['start']->toDateString(), $period['end']->toDateString()])
            ->when($id !== null, fn ($query) => $query->whereKey($id))
            ->reorder()->orderByDesc('version_number')->orderByDesc('id')->first();
    }

    public function forReport(ProjectNarrativeReport $report): ?ProjectProgressReport
    {
        if ($report->report_type !== 'progress' || ! $report->reporting_date || ! $report->monitoring_report_id) {
            return null;
        }
        $source = $report->monitoringReport;
        $period = $this->quarters->forDate($report->reporting_date, $report->topic);

        return $source?->isSubmitted() && $source->topic_id === $report->topic_id
            && $source->reporting_date->betweenIncluded($period['start'], $period['end']) ? $source : null;
    }

    /** @return list<array<string, mixed>> */
    public function rowsForObjective(ProjectProgressReport $source, string $objective): array
    {
        $data = app(ProgressReportData::class);
        $objective = $data->plain($objective);

        return collect($source->work_plan ?? [])->filter(fn (mixed $row): bool => is_array($row)
            && $data->plain((string) ($row['objective'] ?? '')) === $objective)->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function evidenceForObjective(TopicProposal $topic, ProjectProgressReport $source, string $objective): array
    {
        if ($source->topic_id !== $topic->id || ! $source->isSubmitted()) {
            return [];
        }

        return collect($this->rowsForObjective($source, $objective))->flatMap(fn (array $row): array => $row['evidence'] ?? [])
            ->filter(fn (mixed $file): bool => is_array($file) && app(ProjectReportEvidence::class)->isStoredEvidence($topic, 'monitoring', $file))
            ->unique('id')->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function evidenceForReport(ProjectNarrativeReport $report): array
    {
        $source = $this->forReport($report);
        if (! $source) {
            return [];
        }

        return collect($report->accomplishments ?? [])->flatMap(fn (array $row): array => $this->evidenceForObjective(
            $report->topic, $source, (string) ($row['objective'] ?? ''),
        ))->unique('id')->values()->all();
    }
}
