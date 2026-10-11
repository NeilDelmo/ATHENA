<?php

namespace App\Services;

use App\Models\TopicProposal;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectMonitoringHealth
{
    public function __construct(private readonly MonitoringQuarterService $quarters) {}

    /**
     * @return array{status: string, delayed: bool, has_schedule: bool, progress: ?int, deadline: ?string, budget: ?float, utilized: ?float, percentage: ?float, issues: Collection}
     */
    public function assess(TopicProposal $topic): array
    {
        $attention = collect();
        $completed = $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETED;
        $hasSchedule = $this->hasSchedule($topic);
        $window = $hasSchedule ? $this->quarters->reportingWindow($topic) : null;
        $latest = $topic->latestProgressReport;
        $completionPending = ! $completed && ((int) $latest?->progress_percentage >= 100 || $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETION_PENDING);
        $ended = $window && $window['end']->isPast();
        $delaySince = $ended ? $window['end']->addDay()->startOfDay() : null;
        $milestones = collect($topic->progressReports)->sortBy(fn ($report): string => $report->reporting_date->format('Y-m-d').sprintf('-%05d-%010d', $report->version_number, $report->id))->flatMap(fn ($report) => collect($report->work_plan ?? [])
            ->filter(fn ($row): bool => is_array($row) && isset($row['source_work_plan_index'])))->keyBy('source_work_plan_index');
        foreach ($milestones as $milestone) {
            $deadline = data_get($milestone, 'target_completion_date');
            $weight = data_get($milestone, 'percent_weight');
            if ($deadline && is_numeric($weight) && is_numeric(data_get($milestone, 'accomplished_percentage')) && (float) $milestone['accomplished_percentage'] + 0.005 < (float) $weight) {
                $date = $this->recordedDate($deadline)?->endOfDay();
                if ($date?->isPast()) {
                    $delaySince = $delaySince ? $delaySince->min($date->addDay()->startOfDay()) : $date->addDay()->startOfDay();
                }
            }
        }
        $delayed = ! $completed && ! $completionPending && ($topic->project_status === TopicProposal::PROJECT_STATUS_DELAYED || $delaySince !== null);
        if ($delayed) {
            $attention->push($this->issue($topic, 'delayed', $delaySince ? 'Project deadline / reported milestone passed' : 'Project marked delayed', $delaySince, $delaySince ? 'overdue' : 'delay start date not recorded', 'project-monitoring'));
        }
        $awaiting = $completionPending;
        if ($completed) {
            $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
            if (! $terminal) {
                $attention->push($this->issue($topic, 'terminal', 'Completed project has no terminal report recorded', null, 'completion date not recorded', 'project-monitoring'));
            } elseif ($terminal->review_status !== 'reviewed') {
                $attention->push($this->issue($topic, 'terminal_review', 'Terminal report '.($terminal->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $terminal->submitted_at ?? $terminal->created_at, 'waiting', 'project-monitoring'));
            }
        }
        if (! $completed) {
            $rows = $hasSchedule ? $this->quarters->summaryRows($topic->progressReports, $topic) : collect();
            $missing = $rows->filter(fn (array $row): bool => $row['state'] === 'not_submitted');
            if ($missing->isNotEmpty()) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'quarterly', 'Missing Monitoring Tools: '.$missing->pluck('label')->join(', '), $missing->first()['opens_at'], 'since reporting opened', 'project-monitoring'));
            }
            $missingProgress = $hasSchedule
                ? $this->quarters->narrativeProgressPeriods($topic, $topic->narrativeReports)
                    ->filter(fn (array $row): bool => $row['submission_open'] && $row['report'] === null)
                : collect();
            if ($missingProgress->isNotEmpty()) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'progress_quarterly', 'Missing Progress Reports: '.$missingProgress->pluck('label')->join(', '), $missingProgress->first()['opens_at'], 'since reporting opened', 'project-monitoring'));
            }
            $reviewRows = $hasSchedule ? $rows : $this->quarters->summaryRows($topic->progressReports);
            foreach ($reviewRows as $row) {
                if ($row['report'] && in_array($row['report']->review_status, ['pending', 'revision_requested'], true)) {
                    $awaiting = true;
                    $attention->push([...$this->issue($topic, 'report_review', $row['label'].' Monitoring Tool '.($row['report']->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $row['report']->submitted_at ?? $row['report']->created_at, 'waiting', 'project-monitoring'),
                        'review_status' => $row['report']->review_status, 'report_id' => $row['report']->id,
                        'url' => route('topics.show', $topic).'#monitoring-tool-'.$row['report']->id]);
                }
            }
            $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
            if ($ended && ! $terminal) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'terminal', 'Missing terminal report', $window['end']->addDay()->startOfDay(), 'since reporting opened', 'project-monitoring'));
            }
            if ($terminal && $terminal->review_status !== 'reviewed') {
                $awaiting = true;
                $attention->push([...$this->issue($topic, 'terminal_review', 'Terminal report '.($terminal->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $terminal->submitted_at ?? $terminal->created_at, 'waiting', 'project-monitoring'),
                    'review_status' => $terminal->review_status, 'report_id' => $terminal->id,
                    'url' => route('topics.show', $topic).'#narrative-report-'.$terminal->id]);
            }
            foreach ($topic->narrativeReports->where('report_type', 'progress')->where('review_status', '!=', 'reviewed') as $narrative) {
                $awaiting = true;
                $attention->push([...$this->issue($topic, 'narrative_review', 'Progress report '.($narrative->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $narrative->submitted_at ?? $narrative->created_at, 'waiting', 'project-monitoring'),
                    'review_status' => $narrative->review_status, 'report_id' => $narrative->id,
                    'url' => route('topics.show', $topic).'#narrative-report-'.$narrative->id]);
            }
            if ((int) $latest?->progress_percentage >= 100) {
                $attention->push($this->issue($topic, 'completion', 'Reported 100% — completion decision needed', $latest->submitted_at ?? $latest->created_at, 'waiting', 'project-monitoring'));
            }
        }
        $entries = collect($latest?->budget_utilization ?? [])->filter(fn ($entry): bool => is_array($entry)
            && (is_numeric($entry['actual_amount'] ?? null) || is_numeric($entry['utilized'] ?? null)));
        $utilized = (float) $entries->sum(fn (array $entry): float => (float) (is_numeric($entry['actual_amount'] ?? null) ? $entry['actual_amount'] : $entry['utilized']));
        $budget = $topic->estimated_budget === null ? null : (float) $topic->estimated_budget;
        $percentage = $entries->isNotEmpty() && $budget > 0 ? round(100 * $utilized / $budget, 1) : null;
        if (($ended || $completed) && $percentage !== null && $percentage <= 50) {
            $attention->push($this->issue($topic, 'budget', 'At least 50% remaining in latest reported budget snapshot', $latest->submitted_at ?? $latest->created_at, 'since report', 'project-monitoring'));
        }

        return [
            'status' => $completed ? 'completed' : ($delayed ? 'delayed' : ($awaiting ? 'awaiting' : 'ongoing')),
            'delayed' => $delayed, 'has_schedule' => $hasSchedule,
            'progress' => $latest?->progress_percentage, 'deadline' => $window ? $window['end']->toDateString() : null,
            'budget' => $budget, 'utilized' => $entries->isNotEmpty() ? $utilized : null,
            'percentage' => $percentage, 'issues' => $attention,
        ];
    }

    public function hasSchedule(TopicProposal $topic): bool
    {
        $start = data_get($topic->notice_to_proceed_data, 'approved_start_date');
        $end = data_get($topic->notice_to_proceed_data, 'approved_end_date');
        if (filled($start) && $this->recordedDate($start) === null) {
            return false;
        }
        if (filled($end)) {
            $endDate = $this->recordedDate($end);

            $effectiveStart = ($this->recordedDate($start) ?? CarbonImmutable::instance($topic->notice_to_proceed_issued_at))->max(CarbonImmutable::instance($topic->notice_to_proceed_issued_at));

            return $endDate !== null && $endDate->endOfDay()->gte($effectiveStart);
        }

        return (int) (data_get($topic->notice_to_proceed_data, 'approved_duration_months') ?: $topic->estimated_duration_months) > 0;
    }

    private function recordedDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date) || $date === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($date);
        } catch (InvalidFormatException) {
            return null;
        }
    }

    private function issue(TopicProposal $topic, string $type, string $label, mixed $since, string $basis, string $anchor): array
    {
        return ['id' => $topic->id, 'title' => $topic->title, 'type' => $type, 'issue' => $label,
            'status' => Str::headline($topic->project_status ?? 'ongoing'),
            'days' => $since ? (int) max(0, CarbonImmutable::parse($since)->diffInDays(now(), false)) : null,
            'basis' => $basis, 'url' => route('topics.show', $topic).'#'.$anchor];
    }
}
