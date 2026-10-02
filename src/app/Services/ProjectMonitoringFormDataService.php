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

        $period = $reportingDate ? app(MonitoringQuarterService::class)->forDate($reportingDate, $topic) : null;

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
                ->when($period !== null && $revisionReport === null, fn ($query) => $query->where('reporting_year', $period['year'])->where('reporting_quarter', $period['quarter']))
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
            ? app(MonitoringQuarterService::class)->narrativeProgressPeriods($topic)->filter(fn (array $period): bool => $period['reporting_date'] !== null)->values()
            : collect();
        $draft = ProjectNarrativeReportDraft::query()->whereBelongsTo($topic, 'topic')->where('report_type', $reportType)->whereBelongsTo($user, 'user')->first();
        $preparedReport = ProjectNarrativeReport::query()
            ->prepared()
            ->whereBelongsTo($topic, 'topic')
            ->where('report_type', $reportType)
            ->latest('prepared_at')
            ->first();
        $selectedReportingDate = $preparedReport?->reporting_date?->toDateString() ?? $reportingDate ?? data_get($draft?->source_data, 'reporting_date') ?? $quarterOptions->first()['reporting_date'] ?? null;

        return [
            'quarterOptions' => $quarterOptions,
            'selectedReportingDate' => $selectedReportingDate,
            'progressDefaults' => $reportType === 'progress' ? app(ProgressReportData::class)->defaults($topic) : [],
            'terminalDefaults' => $reportType === 'terminal' ? app(TerminalReportData::class)->defaults($topic) : [],
            'terminalEvidence' => $reportType === 'terminal' ? app(TerminalReportData::class)->evidence($topic) : [],
            'preparedReport' => $preparedReport,
            'narrativeReportDraft' => ProjectNarrativeReportDraft::query()
                ->whereBelongsTo($topic, 'topic')
                ->where('report_type', $reportType)
                ->whereBelongsTo($user, 'user')
                ->first(),
        ];
    }
}
