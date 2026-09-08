<?php

namespace App\Services;

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;

class MonitoringQuarterService
{
    /**
     * @return array{year: int, quarter: int, label: string, period: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function forDate(DateTimeInterface|string $reportingDate, ?TopicProposal $topic = null): array
    {
        $date = $reportingDate instanceof DateTimeInterface
            ? CarbonImmutable::instance($reportingDate)
            : CarbonImmutable::parse($reportingDate);

        if ($topic !== null) {
            foreach ($this->projectPeriods($topic) as $period) {
                if ($date->betweenIncluded($period['start'], $period['end'])) {
                    return $period;
                }
            }
        }

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
        if ($report->period_start && $report->period_end) {
            $start = CarbonImmutable::instance($report->period_start);
            $end = CarbonImmutable::instance($report->period_end)->endOfDay();

            return ['year' => $report->reporting_year, 'quarter' => $report->reporting_quarter, 'label' => 'Q'.$report->reporting_quarter, 'period' => $start->format('M j, Y').' – '.$end->format('M j, Y'), 'start' => $start, 'end' => $end];
        }
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
    public function summaryRows(Collection $reports, ?TopicProposal $topic = null): Collection
    {
        $reports = $reports->toBase();

        if ($topic !== null) {
            return $this->projectPeriods($topic)->map(function (array $period) use ($reports): array {
                $report = $reports->filter(fn (ProjectProgressReport $report): bool => $report->reporting_date->betweenIncluded($period['start'], $period['end']))
                    ->sortByDesc(fn (ProjectProgressReport $report): string => sprintf('%05d-%010d', $report->version_number, $report->id))->first();
                $open = CarbonImmutable::now()->greaterThanOrEqualTo($period['opens_at']);
                [$state, $status] = $this->statusFor($report, $period);

                return [...$period, 'report' => $report, 'state' => $report || $open ? $state : 'not_yet_due', 'status' => $report || $open ? $status : 'Upcoming', 'reporting_date' => $open ? $period['end']->toDateString() : null, 'applicable' => true];
            });
        }

        $years = $reports
            ->map(fn (ProjectProgressReport $report): int => $this->forReport($report)['year'])
            ->push(now()->year)
            ->unique()
            ->sortDesc()
            ->values();

        if ($topic !== null) {
            $window = $this->reportingWindow($topic);
            $lastYear = ($window['end'] ?? CarbonImmutable::now())->year;
            if ($lastYear >= $window['start']->year) {
                $years = $years->merge(range($window['start']->year, $lastYear))->unique()->sortDesc()->values();
            }
        }

        return $years->flatMap(function (int $year) use ($reports, $topic): Collection {
            return collect(range(1, 4))->map(function (int $quarter) use ($year, $reports, $topic): array {
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
                if ($report === null && $this->entryDate($period, $topic) === null) {
                    [$state, $status] = ['not_yet_due', 'Not open yet'];
                }

                return [
                    ...$period,
                    'status' => $status,
                    'state' => $state,
                    'report' => $report,
                    'reporting_date' => $this->entryDate($period, $topic),
                    'applicable' => $topic === null || $this->overlapsProject($period, $topic),
                ];
            });
        })->filter(fn (array $row): bool => $row['report'] !== null || $row['applicable'])->values();
    }

    /** @return array{start: CarbonImmutable, end: ?CarbonImmutable} */
    public function reportingWindow(TopicProposal $topic): array
    {
        $issued = CarbonImmutable::instance($topic->notice_to_proceed_issued_at ?? $topic->created_at ?? now())->startOfDay();
        $approvedStart = data_get($topic->notice_to_proceed_data, 'approved_start_date');
        $approvedEnd = data_get($topic->notice_to_proceed_data, 'approved_end_date');

        $start = $approvedStart ? $issued->max(CarbonImmutable::parse($approvedStart)->startOfDay()) : $issued;
        $months = max(1, (int) (data_get($topic->notice_to_proceed_data, 'approved_duration_months') ?: $topic->estimated_duration_months ?: 12));

        return ['start' => $start, 'end' => $approvedEnd ? CarbonImmutable::parse($approvedEnd)->endOfDay() : $start->addMonthsNoOverflow($months)->subDay()->endOfDay()];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function projectPeriods(TopicProposal $topic): Collection
    {
        $window = $this->reportingWindow($topic);
        $periods = collect();
        for ($index = 0; ; $index++) {
            $start = $window['start']->addMonthsNoOverflow($index * 3);
            if ($start->greaterThan($window['end'])) {
                break;
            }
            $end = $window['start']->addMonthsNoOverflow(($index + 1) * 3)->subDay()->endOfDay()->min($window['end']);
            $periods->push(['year' => $start->year, 'quarter' => $index + 1, 'label' => 'Q'.($index + 1), 'period' => $start->format('M j, Y').' – '.$end->format('M j, Y'), 'start' => $start, 'end' => $end, 'opens_at' => $end->addDay()->startOfDay()]);
        }

        return $periods;
    }

    public function canSubmitForDate(TopicProposal $topic, DateTimeInterface|string $date): bool
    {
        $date = $date instanceof DateTimeInterface ? CarbonImmutable::instance($date) : CarbonImmutable::parse($date);

        return $this->projectPeriods($topic)->contains(fn (array $period): bool => $date->betweenIncluded($period['start'], $period['end']) && CarbonImmutable::now()->greaterThanOrEqualTo($period['opens_at']));
    }

    public function terminalOpensAt(TopicProposal $topic): CarbonImmutable
    {
        return $this->reportingWindow($topic)['end']->addDay()->startOfDay();
    }

    public function missingTerminalMonitoringPeriods(TopicProposal $topic): array
    {
        return $this->summaryRows($topic->progressReports()->get(), $topic)
            ->filter(fn (array $row): bool => $row['report'] === null || ! $row['report']->isSubmitted() || $row['report']->review_status === 'revision_requested')
            ->pluck('label')->all();
    }

    public function canSubmitTerminal(TopicProposal $topic): bool
    {
        return $topic->hasIssuedNoticeToProceed() && CarbonImmutable::now()->greaterThanOrEqualTo($this->terminalOpensAt($topic));
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function overlapsProject(array $period, TopicProposal $topic): bool
    {
        $window = $this->reportingWindow($topic);

        return $period['end']->greaterThanOrEqualTo($window['start'])
            && ($window['end'] === null || $period['start']->lessThanOrEqualTo($window['end']));
    }

    /** @param array{start: CarbonImmutable, end: CarbonImmutable} $period */
    private function entryDate(array $period, ?TopicProposal $topic): ?string
    {
        $end = $period['end']->min(CarbonImmutable::now()->startOfDay());
        $start = $period['start'];
        if ($topic !== null) {
            $window = $this->reportingWindow($topic);
            $start = $start->max($window['start']);
            $end = $window['end'] ? $end->min($window['end']) : $end;
        }

        return $end->lessThan($start) ? null : $end->toDateString();
    }

    /**
     * @return array{year: int, quarter: int, label: string, period: string, start: CarbonImmutable, end: CarbonImmutable}
     */
    public function forYearAndQuarter(int $year, int $quarter): array
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
