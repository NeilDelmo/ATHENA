<?php

namespace App\Services;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;

class ProjectReportDraftState
{
    /** @return array{version: int, scope: string, is_editable: bool} */
    public function forEditor(TopicProposal $topic, User $user, string $kind, ?string $reportingDate, int $source = 0): array
    {
        $schedule = app(MonitoringQuarterService::class);
        $reportingDate ??= $kind === 'terminal' ? null : $schedule->projectPeriods($topic)->first()['end']->toDateString();
        $period = $kind !== 'terminal' && filled($reportingDate) ? $schedule->forDate($reportingDate, $topic) : null;
        $query = $kind === 'monitoring' ? ProjectProgressReport::query()->whereBelongsTo($topic, 'topic')
            : ProjectNarrativeReport::query()->whereBelongsTo($topic, 'topic')->where('report_type', $kind);
        $reports = $query->get(['id', 'reporting_date', 'period_end', 'reporting_quarter', 'submission_status', 'review_status']);
        $prepared = $reports->first(fn (ProjectProgressReport|ProjectNarrativeReport $report): bool => $report->isPrepared());
        $latest = $reports->filter(function (ProjectProgressReport|ProjectNarrativeReport $report) use ($kind, $period): bool {
            if ($kind === 'terminal') {
                return true;
            }
            if ($period === null) {
                return false;
            }
            if ($report instanceof ProjectNarrativeReport) {
                return $report->isForReportingPeriod($period);
            }

            return $report->reporting_date?->betweenIncluded($period['start'], $period['end']) ?? false;
        })->sortByDesc('id')->first();
        $draftQuery = $kind === 'monitoring'
            ? ProjectMonitoringDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($user, 'user')->where('source_key', $source ? 'revision:'.$source : 'new')
            : ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->whereBelongsTo($user, 'user')->where('report_type', $kind);
        $draft = $draftQuery->first(['lock_version', 'source_data->reporting_date as saved_reporting_date']);
        $savedDate = $draft?->saved_reporting_date;
        $savedDate = $savedDate === 'null' ? null : $savedDate;
        $samePeriod = $period === null || ! $savedDate || $schedule->forDate($savedDate, $topic)['start']->eq($period['start']);
        $editable = $topic->isMonitoringAvailable() && $prepared === null && $samePeriod
            && ($kind === 'terminal' || ($period !== null && $schedule->canDraftForDate($topic, $reportingDate)));
        if ($kind === 'monitoring') {
            $editable = $editable && ($source === 0 ? $latest === null : $latest instanceof ProjectProgressReport
                && $latest->id === $source && $latest->isSubmitted() && $latest->review_status === 'revision_requested');
        } else {
            $editable = $editable && $source === 0 && ($latest === null || ($latest->isSubmitted() && $latest->review_status === ProjectNarrativeReport::STATUS_REVISION_REQUESTED));
        }

        return [
            'version' => $draft?->lock_version ?? 0,
            'scope' => hash('sha256', implode(':', [$kind, $source, $latest?->id, $latest?->review_status, $latest?->submission_status, $prepared?->id])),
            'is_editable' => $editable,
        ];
    }
}
