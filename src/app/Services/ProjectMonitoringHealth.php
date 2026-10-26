<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProjectMonitoringHealth
{
    public function __construct(private readonly MonitoringQuarterService $quarters) {}

    /**
     * @return array{status: string, delayed: bool, has_schedule: bool, progress: ?int, deadline: ?string, delay_reason: ?string, delay_days: ?int, report_id: ?int, report_date: ?string, budget: ?float, budget_source: ?string, budget_availability: string, utilized: ?float, percentage: ?float, issues: Collection}
     */
    public function assess(TopicProposal $topic, bool $forResearchHead = false): array
    {
        $attention = collect();
        $completed = $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETED;
        $hasSchedule = $this->hasSchedule($topic);
        $window = $hasSchedule ? $this->quarters->reportingWindow($topic) : null;
        $submittedReports = $topic->progressReports->filter(fn (ProjectProgressReport $report): bool => $report->isSubmitted());
        $visibleReports = $forResearchHead
            ? $submittedReports->filter(fn (ProjectProgressReport $report): bool => $report->isAvailableToResearchHead())
            : $submittedReports;
        $visibleReports = $visibleReports->groupBy(fn (ProjectProgressReport $report): string => ($report->period_start
            ?? $this->quarters->forDate($report->reporting_date, $topic)['start'])->toDateString())
            ->map(fn (Collection $versions): ProjectProgressReport => $versions->sortBy([['version_number', 'desc'], ['id', 'desc']])->first())->values();
        $latest = $forResearchHead
            ? $visibleReports->sortByDesc(fn (ProjectProgressReport $report): string => $report->reporting_date->format('Y-m-d').sprintf('-%010d', $report->id))->first()
            : $topic->latestProgressReport;
        $completionPending = ! $completed && ((int) $latest?->progress_percentage >= 100 || $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETION_PENDING);
        $ended = $window && $window['end']->isPast();
        $delaySince = $ended ? $window['end']->addDay()->startOfDay() : null;
        $milestones = $visibleReports->sortBy(fn ($report): string => $report->reporting_date->format('Y-m-d').sprintf('-%05d-%010d', $report->version_number, $report->id))->flatMap(fn ($report) => collect($report->work_plan ?? [])
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
                $attention->push($this->reportReviewIssue($topic, $terminal, 'terminal_review', 'Terminal Report'));
            }
        }
        if (! $completed) {
            $rows = $hasSchedule ? $this->quarters->summaryRows($topic->progressReports, $topic) : collect();
            $missing = $rows->filter(fn (array $row): bool => $row['state'] === 'not_submitted' && $row['end']->isPast());
            if ($missing->isNotEmpty()) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'quarterly', 'Missing Monitoring Tools: '.$missing->pluck('label')->join(', '), $missing->first()['end']->addDay()->startOfDay(), 'overdue', 'project-monitoring'));
            }
            $missingProgress = $hasSchedule
                ? $this->quarters->narrativeProgressPeriods($topic, $topic->narrativeReports)
                    ->filter(fn (array $row): bool => $row['end']->isPast() && $row['report'] === null)
                : collect();
            if ($missingProgress->isNotEmpty()) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'progress_quarterly', 'Missing Progress Reports: '.$missingProgress->pluck('label')->join(', '), $missingProgress->first()['end']->addDay()->startOfDay(), 'overdue', 'project-monitoring'));
            }
            $reviewRows = $hasSchedule ? $rows : $this->quarters->summaryRows($topic->progressReports);
            foreach ($reviewRows as $row) {
                if ($row['report'] && in_array($row['report']->review_status, ['pending', 'revision_requested'], true)) {
                    $awaiting = true;
                    $attention->push($this->reportReviewIssue($topic, $row['report'], 'report_review', $row['label'].' Monitoring Tool'));
                }
            }
            $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
            if ($ended && ! $terminal) {
                $awaiting = true;
                $attention->push($this->issue($topic, 'terminal', 'Missing terminal report', $window['end']->addDay()->startOfDay(), 'since reporting opened', 'project-monitoring'));
            }
            if ($terminal && $terminal->review_status !== 'reviewed') {
                $awaiting = true;
                $attention->push($this->reportReviewIssue($topic, $terminal, 'terminal_review', 'Terminal Report'));
            }
            $progressNarratives = $topic->narrativeReports->where('report_type', 'progress')
                ->sortByDesc(fn (ProjectNarrativeReport $report): string => sprintf('%010d-%010d', $report->version_number, $report->id))
                ->unique(fn (ProjectNarrativeReport $report): string => $report->reporting_quarter > 0
                    ? 'quarter-'.$report->reporting_quarter
                    : ($report->period_start && $report->period_end
                        ? $report->period_start->toDateString().'/'.$report->period_end->toDateString()
                        : 'legacy-'.$report->id));
            foreach ($progressNarratives->where('review_status', '!=', 'reviewed') as $narrative) {
                $awaiting = true;
                $quarter = $narrative->reporting_quarter > 0 ? 'Q'.$narrative->reporting_quarter.' ' : '';
                $attention->push($this->reportReviewIssue($topic, $narrative, 'narrative_review', $quarter.'Progress Report'));
            }
            if ((int) $latest?->progress_percentage >= 100) {
                $attention->push($this->issue($topic, 'completion', 'Project closeout pending', $latest->submitted_at ?? $latest->created_at, 'since 100% report', 'project-monitoring'));
            }
        }
        $entries = collect($latest?->budget_utilization ?? [])->filter(fn ($entry): bool => is_array($entry)
            && (is_numeric($entry['actual_amount'] ?? null) || is_numeric($entry['utilized'] ?? null)));
        $utilized = (float) $entries->sum(fn (array $entry): float => (float) (is_numeric($entry['actual_amount'] ?? null) ? $entry['actual_amount'] : $entry['utilized']));
        $approvedBudget = data_get($topic->notice_to_proceed_data, 'approved_budget');
        $budgetSource = is_numeric($approvedBudget) && (float) $approvedBudget >= 0 && is_finite((float) $approvedBudget)
            ? 'approved'
            : (is_numeric($topic->estimated_budget) && (float) $topic->estimated_budget >= 0 ? 'estimated' : null);
        $budget = match ($budgetSource) {
            'approved' => (float) $approvedBudget,
            'estimated' => (float) $topic->estimated_budget,
            default => null,
        };
        $percentage = $entries->isNotEmpty() && $budget > 0 ? round(100 * $utilized / $budget, 1) : null;
        if (($ended || $completed) && $percentage !== null && $percentage <= 50) {
            $attention->push($this->issue($topic, 'budget', 'At least 50% remaining in latest reported budget snapshot', $latest->submitted_at ?? $latest->created_at, 'since report', 'project-monitoring'));
        }

        $delayIssue = $attention->firstWhere('type', 'delayed');
        $status = $completed ? 'completed' : ($delayed ? 'delayed' : ($awaiting ? 'awaiting' : 'ongoing'));
        $statusLabel = match ($status) {
            'delayed' => 'Delayed / overdue',
            'awaiting' => 'Awaiting required report / review',
            default => Str::headline($status),
        };

        return [
            'status' => $status,
            'delayed' => $delayed, 'has_schedule' => $hasSchedule,
            'progress' => $latest?->progress_percentage, 'deadline' => $window ? $window['end']->toDateString() : null,
            'delay_reason' => $delayIssue['issue'] ?? null, 'delay_days' => $delayIssue['days'] ?? null,
            'report_id' => $latest?->id, 'report_date' => $latest?->reporting_date?->toDateString(),
            'budget' => $budget, 'utilized' => $entries->isNotEmpty() ? $utilized : null,
            'budget_source' => $budgetSource, 'budget_availability' => $budget === null ? 'missing' : ($budget > 0 ? 'available' : 'zero'),
            'percentage' => $percentage, 'issues' => $attention->map(fn (array $issue): array => [...$issue, 'status' => $statusLabel]),
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

    private function reportReviewIssue(TopicProposal $topic, ProjectProgressReport|ProjectNarrativeReport $report, string $type, string $label): array
    {
        $stage = $report->review_stage ?? 'head';
        $corrections = $report->review_status === 'revision_requested';
        $status = $corrections ? 'Corrections needed' : ($stage === 'secretary' ? 'Office review' : 'Head review');
        $since = match (true) {
            $corrections && $stage === 'secretary' => $report->secretary_reviewed_at,
            $corrections || $stage === 'secretary' => $report->reviewed_at,
            default => $report->forwarded_at,
        };
        $basis = $corrections ? 'since corrections requested' : ($stage === 'secretary' ? 'with Research Office Secretary' : 'with Research Head');
        $anchor = $report instanceof ProjectProgressReport ? 'monitoring-tool-' : 'narrative-report-';

        return [...$this->issue($topic, $type, $label.' · '.$status, $since ?? $report->submitted_at ?? $report->created_at, $basis, $anchor.$report->id),
            'review_status' => $report->review_status, 'review_stage' => $stage, 'report_id' => $report->id];
    }

    private function issue(TopicProposal $topic, string $type, string $label, mixed $since, string $basis, string $anchor): array
    {
        return ['id' => $topic->id, 'title' => $topic->title, 'type' => $type, 'issue' => $label,
            'status' => Str::headline($topic->project_status ?? 'ongoing'),
            'days' => $since ? (int) max(0, CarbonImmutable::parse($since)->diffInDays(now(), false)) : null,
            'basis' => $basis, 'url' => route('topics.show', $topic).'#'.$anchor];
    }
}
