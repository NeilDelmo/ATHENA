<?php

namespace App\Http\Controllers;

use App\Http\Requests\VcrdesMonitoringRequest;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ProjectMonitoringHealth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VcrdesDashboardController extends Controller
{
    public function __invoke(VcrdesMonitoringRequest $request, ProjectMonitoringHealth $monitoringHealth): View
    {
        $filters = $request->validated();
        $portfolio = TopicProposal::query()->withIssuedNotice()->withProjectCollege()
            ->with([
                'user:id,name,college',
                'progressReports:id,topic_id,reporting_date,reporting_year,reporting_quarter,period_start,period_end,version_number,supersedes_report_id,submission_status,review_status,review_stage,progress_percentage,work_plan,budget_utilization,reviewed_at,secretary_reviewed_at,forwarded_at,submitted_at,created_at',
                'narrativeReports:id,topic_id,report_type,reporting_date,reporting_quarter,period_start,period_end,version_number,submission_status,review_status,review_stage,reviewed_at,secretary_reviewed_at,forwarded_at,submitted_at,created_at,submission_date',
            ])->lazyById(100)->map(function (TopicProposal $project) use ($monitoringHealth): array {
                $college = $project->projectCollege();

                return [
                    'id' => $project->id, 'title' => $project->title,
                    'lead' => $project->user?->name, 'college' => array_search($college, User::COLLEGES, true) ?: $college,
                    ...$monitoringHealth->assess($project, forResearchHead: true),
                ];
            })->collect();
        $scoped = $portfolio->filter(fn (array $project): bool => empty($filters['college']) || $project['college'] === $filters['college']);
        $counts = $scoped->countBy('status');
        $total = $scoped->count();
        $completed = $counts->get('completed', 0);
        $attention = $scoped->flatMap(fn (array $project) => $project['issues'])
            ->sortByDesc(fn (array $issue): int => $issue['days'] ?? -1)->values();
        $budget = (float) $scoped->sum('budget');
        $utilized = (float) $scoped->sum('utilized');
        $budgetCount = $scoped->whereNotNull('budget')->count();
        $reportedCount = $scoped->whereNotNull('utilized')->count();
        $budgetPercentage = $budgetCount === $total && $reportedCount > 0 && $budget > 0
            ? round($utilized / $budget * 100, 1) : null;
        $completionPercentage = $total > 0 ? (int) round($completed / $total * 100) : 0;
        $colleges = collect(User::COLLEGES)->map(function (string $name, string $code) use ($portfolio): array {
            $projects = $portfolio->where('college', $code);

            return ['name' => $name, 'code' => $code, 'total' => $projects->count(),
                'completed' => $projects->where('status', 'completed')->count(),
                'delayed' => $projects->where('status', 'delayed')->count()];
        });
        $filtered = $scoped->filter(fn (array $project): bool => (empty($filters['status']) || $project['status'] === $filters['status'])
            && (empty($filters['q']) || Str::contains(Str::lower($project['title'].' '.$project['lead']), Str::lower(trim($filters['q'])))))
            ->sortBy(fn (array $project): int => array_search($project['status'], ['delayed', 'awaiting', 'ongoing', 'completed'], true))
            ->values();
        $projects = new LengthAwarePaginator($filtered->forPage(LengthAwarePaginator::resolveCurrentPage(), 12)->values(), $filtered->count(), 12, null, [
            'path' => $request->url(), 'query' => $request->query(), 'fragment' => 'projects',
        ]);

        return view('vcrdes.dashboard', compact('filters', 'counts', 'total', 'completed', 'attention', 'budget', 'budgetCount', 'utilized', 'budgetPercentage', 'reportedCount', 'completionPercentage', 'colleges', 'projects'));
    }
}
