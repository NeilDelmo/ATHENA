<?php

namespace App\Services;

use App\Models\ProjectProgressReport;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

class MonitoringQuarterService
{
    /**
     * @return array{year: int, quarter: int, label: string, period: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function forDate(DateTimeInterface|string $reportingDate): array
    {
        $date = $reportingDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($reportingDate)
            : CarbonImmutable::parse($reportingDate);

        return $this->forYearAndQuarter(
            $date->year,
            (int) ceil($date->month / 3),
        );
    }

    /**
     * @return array{year: int, quarter: int, label: string, period: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function forReport(ProjectProgressReport $report): array
    {
        $derivedPeriod = $this->forDate($report->reporting_date);

        return $this->forYearAndQuarter(
            $report->reporting_year ?? $derivedPeriod['year'],
            $report->reporting_quarter ?? $derivedPeriod['quarter'],
        );
    }

    /**
     * @param  Collection<int, ProjectProgressReport>  $reports
     * @return Collection<int, array{year: int, quarter: int, label: string, period: string, status: string, state: string, report: ?ProjectProgressReport}>
     */
    public function summaryRows(Collection $reports): Collection
    {
        $reports = $reports->toBase();

        $years = $reports
            ->map(fn (ProjectProgressReport $report): int => $this->forReport($report)['year'])
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        return $years->flatMap(function (int $year) use ($reports): Collection {
            return collect(range(1, 4))->map(function (int $quarter) use ($year, $reports): array {
                $period = $this->forYearAndQuarter($year, $quarter);
                $report = $reports
                    ->filter(function (ProjectProgressReport $report) use ($year, $quarter): bool {
                        $reportPeriod = $this->forReport($report);

                        return $reportPeriod['year'] === $year && $reportPeriod['quarter'] === $quarter;
                    })
                    ->sortByDesc(fn (ProjectProgressReport $report): string => sprintf(
                        '%05d-%010d',
                        $report->version_number,
                        $report->id,
                    ))
                    ->first();

                [$state, $status] = $this->statusFor($report, $period);

                return [
                    ...$period,
                    'status' => $status,
                    'state' => $state,
                    'report' => $report,
                ];
            });
        })->values();
    }

    /**
     * @return array{year: int, quarter: int, label: string, period: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    private function forYearAndQuarter(int $year, int $quarter): array
    {
        $quarter = min(max($quarter, 1), 4);
        $start = CarbonImmutable::create($year, (($quarter - 1) * 3) + 1, 1)->startOfMonth();
        $end = $start->addMonths(2)->endOfMonth();

        return [
            'year' => $year,
            'quarter' => $quarter,
            'label' => 'Q'.$quarter,
            'period' => $start->format('M j').'–'.$end->format('M j, Y'),
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @param  array{start: CarbonImmutable, end: CarbonImmutable}  $period
     * @return array{0: string, 1: string}
     */
    private function statusFor(?ProjectProgressReport $report, array $period): array
    {
        if ($report === null) {
            return now()->startOfDay()->isBefore($period['start'])
                ? ['not_yet_due', 'Not yet due']
                : ['not_submitted', 'Not submitted'];
        }

        if ($report->isPrepared()) {
            return ['prepared', 'PDF prepared'];
        }

        return match ($report->review_status) {
            'reviewed' => ['reviewed', 'Reviewed'],
            'revision_requested' => ['revision_required', 'Revision required'],
            default => [
                $report->version_number > 1 ? 'resubmitted' : 'submitted',
                $report->version_number > 1 ? 'Resubmitted – awaiting review' : 'Submitted – awaiting review',
            ],
        };
    }
}
