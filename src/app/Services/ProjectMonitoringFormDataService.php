<?php

namespace App\Services;

use App\Models\ProjectMonitoringDraft;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectNarrativeReportDraft;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;

class ProjectMonitoringFormDataService
{
    /**
     * @return array{preparedReport: ?ProjectProgressReport, revisionReport: ?ProjectProgressReport, monitoringDraft: ?ProjectMonitoringDraft}
     */
    public function monitoringTool(User $user, TopicProposal $topic, int $revisionReportId = 0): array
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

        abort_if($revisionReport?->nextVersion !== null, 404);

        return [
            'preparedReport' => ProjectProgressReport::query()
                ->prepared()
                ->whereBelongsTo($topic, 'topic')
                ->where('submitted_by', $user->id)
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
    public function narrativeProgress(User $user, TopicProposal $topic): array
    {
        $topic->loadMissing(['user', 'revisionDraft.members']);

        return [
            'preparedReport' => ProjectNarrativeReport::query()
                ->prepared()
                ->whereBelongsTo($topic, 'topic')
                ->where('submitted_by', $user->id)
                ->latest('prepared_at')
                ->first(),
            'narrativeReportDraft' => ProjectNarrativeReportDraft::query()
                ->whereBelongsTo($topic, 'topic')
                ->whereBelongsTo($user, 'user')
                ->first(),
        ];
    }
}
