<?php

namespace App\Services;

use App\Models\ProposalStageTransition;
use App\Models\ProposalVersion;
use App\Models\TopicProposal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ResearchDashboardAnalytics
{
    public const STAGES = [
        'pending' => 'Initial review', 'resubmitted' => 'Resubmission review',
        'expert_review' => 'Expert review', 'for_final_decision' => 'Final decision',
        'gad_review' => 'GAD and central evaluation',
        'lrec_queued' => 'Awaiting LREC', 'lrec_review' => 'LREC review',
        'revision_requested' => 'Faculty revision', 'ready_for_signature' => 'Signing',
    ];

    public function topics(?int $callId): Builder
    {
        return TopicProposal::query()->when($callId, fn (Builder $query) => $query->where('research_call_id', $callId));
    }

    public function summarize(?int $callId): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfWeek()->subWeeks(7);
        $daily = ProposalVersion::query()->whereBetween('created_at', [$start, $now])
            ->when($callId, fn (Builder $query) => $query->whereHas('topic', fn (Builder $topics) => $topics->where('research_call_id', $callId)))
            ->selectRaw('DATE(created_at) as submitted_date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')->get();
        $weeks = collect(range(0, 7))->map(function (int $offset) use ($start, $daily, $now): array {
            $week = $start->addWeeks($offset);
            $end = $week->endOfWeek()->min($now);

            return ['label' => $week->format('M j'), 'start' => $week->toDateString(), 'end' => $end->toDateString(),
                'count' => (int) $daily->filter(fn ($day) => $day->submitted_date >= $week->toDateString() && $day->submitted_date <= $end->toDateString())->sum('total')];
        });
        $statusCounts = $this->topics($callId)
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total);
        $reviewStatuses = array_values(array_diff(array_keys(self::STAGES), ['revision_requested', 'ready_for_signature']));
        $pipelineFunnel = collect([
            ['key' => 'in_review', 'label' => 'In review', 'count' => (int) $statusCounts->only($reviewStatuses)->sum()],
            ['key' => 'revision_requested', 'label' => 'Faculty revision', 'count' => (int) $statusCounts->get('revision_requested', 0)],
            ['key' => 'approved', 'label' => 'Approved', 'count' => (int) $statusCounts->get('approved', 0)],
            ['key' => 'rejected', 'label' => 'Rejected', 'count' => (int) $statusCounts->get('rejected', 0)],
        ]);
        $pipelineTotal = (int) $pipelineFunnel->sum('count');
        $stageDurations = ProposalStageTransition::query()
            ->whereNotNull('previous_started_at')->whereColumn('changed_at', '>=', 'previous_started_at')
            ->whereBetween('changed_at', [$now->subDays(90), $now])
            ->whereIn('from_status', array_keys(self::STAGES))
            ->when($callId, fn (Builder $query) => $query->whereHas('topic', fn (Builder $topics) => $topics->where('research_call_id', $callId)))
            ->selectRaw('from_status, COUNT(*) as samples, AVG(TIMESTAMPDIFF(SECOND, previous_started_at, changed_at)) / 86400 as average_days')
            ->groupBy('from_status')->orderByDesc('average_days')->get();
        $attention = $this->topics($callId)->whereIn('status', array_keys(self::STAGES))
            ->with(['user:id,name', 'researchCall:id,title,paper_revisions_end_date', 'latestVersion'])
            ->withMax('versions as last_submitted_at', 'created_at')
            ->orderByRaw('COALESCE(status_started_at, last_submitted_at) IS NULL')
            ->orderByRaw('COALESCE(status_started_at, last_submitted_at) ASC')->limit(5)->get()
            ->map(function (TopicProposal $topic) use ($now): array {
                $since = $topic->status_started_at ?? $topic->latestVersion?->created_at;
                $deadline = $topic->status === 'revision_requested' ? $topic->researchCall?->paper_revisions_end_date : null;

                return ['topic' => $topic, 'days' => $since ? (int) max(0, $since->diffInDays($now, false)) : null,
                    'basis' => $topic->status_started_at ? 'in this stage' : 'since submission',
                    'past_revision_deadline' => $deadline && $deadline->endOfDay()->lt($now)];
            });
        $repeatRevisions = $this->topics($callId)->whereIn('status', array_keys(self::STAGES))
            ->whereHas('reviews', fn (Builder $reviews) => $reviews->where('decision', 'revision_requested'), '>=', 2)->count();
        $budgetProjects = $this->topics($callId)
            ->withIssuedNotice()
            ->with('latestProgressReport')
            ->get(['id', 'title', 'estimated_budget']);
        $budgetProjectUtilizations = $budgetProjects->map(function (TopicProposal $topic): array {
            $approvedBudget = (float) ($topic->estimated_budget ?? 0);
            $budgetEntries = collect($topic->latestProgressReport?->budget_utilization ?? []);
            $reportedUtilized = (float) $budgetEntries->sum(
                fn (array $entry): float => (float) ($entry['utilized'] ?? $entry['actual_amount'] ?? 0),
            );
            $hasReport = $topic->latestProgressReport !== null;

            return [
                'id' => $topic->id,
                'title' => $topic->title,
                'approved_budget' => $approvedBudget,
                'reported_utilized' => $reportedUtilized,
                'percentage' => $approvedBudget > 0 ? round(($reportedUtilized / $approvedBudget) * 100, 1) : 0.0,
                'has_report' => $hasReport,
                'has_utilization' => $budgetEntries->isNotEmpty(),
            ];
        })->values();
        $approvedBudget = (float) $budgetProjectUtilizations->sum('approved_budget');
        $reportedUtilized = (float) $budgetProjectUtilizations->sum('reported_utilized');
        $reportedProjects = $budgetProjectUtilizations->filter(fn (array $project): bool => $project['has_utilization'])->count();
        $budgetPercentage = $approvedBudget > 0 ? round(($reportedUtilized / $approvedBudget) * 100, 1) : 0.0;
        $budgetUtilization = [
            'approved_budget' => $approvedBudget,
            'reported_utilized' => $reportedUtilized,
            'percentage' => $budgetPercentage,
            'project_count' => $budgetProjectUtilizations->count(),
            'reported_project_count' => $reportedProjects,
            'projects' => $budgetProjectUtilizations,
        ];

        return ['weeks' => $weeks, 'chartMax' => max(1, $weeks->max('count')),
            'pipelineFunnel' => $pipelineFunnel, 'pipelineTotal' => $pipelineTotal,
            'budgetUtilization' => $budgetUtilization,
            'stageDurations' => $stageDurations, 'attention' => $attention,
            'repeatRevisions' => $repeatRevisions,
            'unknownTiming' => $this->topics($callId)->whereIn('status', array_keys(self::STAGES))->whereNull('status_started_at')->count(),
            'recentTotal' => $weeks->slice(4)->sum('count'), 'previousTotal' => $weeks->take(4)->sum('count')];
    }
}
