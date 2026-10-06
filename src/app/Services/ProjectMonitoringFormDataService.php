<?php

namespace App\Services;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;

class ProjectMonitoringFormDataService
{
    /**
     * @return array{preparedReport: ?ProjectProgressReport, revisionReport: ?ProjectProgressReport, monitoringDraft: ?ProjectMonitoringDraft}
     */
    public function monitoringTool(User $user, TopicProposal $topic, int $revisionReportId = 0, ?string $reportingDate = null): array
    {
        $topic->loadMissing('user');

        $revisionReport = $revisionReportId === 0
            ? null
            : ProjectProgressReport::query()
                ->submitted()
                ->whereBelongsTo($topic, 'topic')
                ->where('review_status', 'revision_requested')
                ->with(['submitter', 'reviewer', 'nextVersion'])
                ->findOrFail($revisionReportId);

        abort_if($revisionReport?->nextVersion !== null && ! $revisionReport->nextVersion->isPrepared(), 404);

        return [
            'preparedReport' => ProjectProgressReport::query()
                ->prepared()
                ->whereBelongsTo($topic, 'topic')
                ->with('nextVersion')
                ->when(
                    $revisionReport !== null,
                    fn ($query) => $query->where('supersedes_report_id', $revisionReport->id),
                    fn ($query) => $query->whereNull('supersedes_report_id'),
                )
                ->latest('prepared_at')
                ->first(),
            'revisionReport' => $revisionReport,
            'monitoringDraft' => ProjectMonitoringDraft::query()
                ->whereBelongsTo($topic, 'topic')
                ->whereBelongsTo($user, 'user')
                ->forSource($revisionReport)
                ->first(),
        ];
    }

    /**
     * @return array{preparedReport: ?ProjectNarrativeReport, narrativeReportDraft: ?ProjectNarrativeReportDraft}
     */
    public function narrativeProgress(User $user, TopicProposal $topic, string $reportType = 'progress', ?string $reportingDate = null): array
    {
        $topic->loadMissing(['user', 'revisionDraft.members']);
        $quarterOptions = $reportType === 'progress'
            ? app(MonitoringQuarterService::class)->narrativeProgressPeriods($topic, $topic->narrativeReports()
                ->where('report_type', 'progress')
                ->get(['id', 'topic_id', 'report_type', 'reporting_quarter', 'submission_status', 'review_status']))
                ->filter(fn (array $period): bool => $period['drafting_date'] !== null)
                ->map(fn (array $period): array => [...$period, 'reporting_date' => $period['drafting_date']])->values()
            : collect();
        $draft = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->where('report_type', $reportType)->whereBelongsTo($user, 'user')->first();
        $preparedReport = ProjectNarrativeReport::query()
            ->prepared()
            ->whereBelongsTo($topic, 'topic')
            ->where('report_type', $reportType)
            ->latest('prepared_at')
            ->first();
        $draftDate = data_get($draft?->source_data, 'reporting_date');
        $selectedReportingDate = $preparedReport?->reporting_date?->toDateString() ?? $draftDate ?? $reportingDate ?? $quarterOptions->first()['reporting_date'] ?? null;
        if ($draftDate && $reportType === 'progress') {
            $draftPeriod = app(MonitoringQuarterService::class)->forDate($draftDate, $topic);
            $quarterOptions = $quarterOptions->filter(fn (array $period): bool => $period['start']->eq($draftPeriod['start']))->values();
        }
        $progressDefaults = $reportType === 'progress' ? app(ProgressReportData::class)->defaults($topic) : [];
        if ($reportType === 'progress' && $draft === null && $preparedReport === null && $selectedReportingDate) {
            $period = app(MonitoringQuarterService::class)->forDate($selectedReportingDate, $topic);
            $revisionSource = $topic->narrativeReports()->submitted()->where('report_type', 'progress')
                ->where('reporting_quarter', $period['quarter'])->where('review_status', ProjectNarrativeReport::STATUS_REVISION_REQUESTED)
                ->latest('id')->first();
            if ($revisionSource !== null) {
                $progressDefaults = [...$progressDefaults, ...app(ProgressReportData::class)->normalize($topic, ['accomplishments' => $revisionSource->accomplishments ?? []])];
            }
        }

        return [
            'quarterOptions' => $quarterOptions,
            'selectedReportingDate' => $selectedReportingDate,
            'progressDefaults' => $progressDefaults,
            'terminalDefaults' => $reportType === 'terminal' ? app(TerminalReportData::class)->defaults($topic) : [],
            'terminalEvidence' => $reportType === 'terminal' ? app(TerminalReportData::class)->evidence($topic) : [],
            'preparedReport' => $preparedReport,
            'narrativeReportDraft' => $draft,
        ];
    }
}
