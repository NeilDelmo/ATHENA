<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ResearchHeadReportQueue
{
    public const TYPES = ['quarterly' => 'Quarterly monitoring', 'progress' => 'Progress narrative', 'terminal' => 'Terminal report'];

    public const STATUSES = ['pending' => 'Awaiting review', 'revision_requested' => 'Corrections requested', 'reviewed' => 'Reviewed'];

    public function query(string $type = '', string $status = '', string $search = ''): Builder
    {
        $quarterly = ProjectProgressReport::submitted()
            ->whereHas('topic', fn ($query) => $query->withIssuedNotice())
            ->whereDoesntHave('nextVersion', fn ($query) => $query->submitted())
            ->join('topics', 'topics.id', '=', 'project_progress_reports.topic_id')
            ->join('users', 'users.id', '=', 'topics.user_id')
            ->selectRaw("project_progress_reports.id, topics.id as topic_id, topics.title, users.name as faculty_name, 'quarterly' as report_type, project_progress_reports.review_status, COALESCE(project_progress_reports.submitted_at, project_progress_reports.created_at) as received_at, project_progress_reports.reporting_date as report_date");

        $narratives = ProjectNarrativeReport::submitted()
            ->whereIn('report_type', ['progress', 'terminal'])
            ->whereHas('topic', fn ($query) => $query->withIssuedNotice())
            ->where(function ($query): void {
                $query->where('report_type', '!=', 'terminal')->orWhereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')->from('project_narrative_reports as newer')
                        ->whereColumn('newer.topic_id', 'project_narrative_reports.topic_id')
                        ->whereColumn('newer.id', '>', 'project_narrative_reports.id')
                        ->where('newer.report_type', 'terminal')->where('newer.submission_status', 'submitted');
                });
            })
            ->join('topics', 'topics.id', '=', 'project_narrative_reports.topic_id')
            ->join('users', 'users.id', '=', 'topics.user_id')
            ->selectRaw('project_narrative_reports.id, topics.id as topic_id, topics.title, users.name as faculty_name, project_narrative_reports.report_type, project_narrative_reports.review_status, COALESCE(project_narrative_reports.submitted_at, project_narrative_reports.created_at) as received_at, project_narrative_reports.submission_date as report_date');

        return DB::query()->fromSub($quarterly->toBase()->unionAll($narratives->toBase()), 'reports')
            ->when($type !== '', fn (Builder $query) => $query->where('report_type', $type))
            ->when($status !== '', fn (Builder $query) => $query->where('review_status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")->orWhere('faculty_name', 'like', "%{$search}%");
            }));
    }

    public function pendingCount(): int
    {
        return $this->query(status: 'pending')->count();
    }
}
