<?php

namespace App\Services;

use App\Models\PersonalReminder;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class DashboardCalendar
{
    public function __construct(
        private readonly MonitoringQuarterService $quarters,
        private readonly ApprovedWorkPlanMonitoringService $workPlan,
        private readonly ProjectMonitoringHealth $health,
    ) {}

    public const MILESTONES = [
        'opens_at' => 'Submissions open',
        'closes_at' => 'Submission deadline',
        'initial_evaluation_start_date' => 'Initial evaluation begins',
        'initial_evaluation_end_date' => 'Initial evaluation ends',
        'paper_revisions_start_date' => 'Paper revisions begin',
        'paper_revisions_end_date' => 'Paper revision deadline',
        'lrec_start_date' => 'LREC begins',
        'lrec_end_date' => 'LREC ends',
        'implementation_start_date' => 'Implementation begins',
        'implementation_end_date' => 'Implementation ends',
    ];

    public function calls(User $user): Builder
    {
        return ResearchCall::query()->when(! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), function (Builder $query) use ($user): void {
            $query->visibleToFaculty()->where(function (Builder $visible) use ($user): void {
                $visible->where(function (Builder $open): void {
                    $open->where('status', 'open')->where('closes_at', '>=', now());
                })->orWhereHas('topics', fn (Builder $topics) => $topics->accessibleTo($user))
                    ->orWhereHas('proposalDrafts', fn (Builder $drafts) => $drafts->accessibleTo($user));
            });
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function events(User $user, CarbonImmutable $from, CarbonImmutable $until, ?int $callId = null): Collection
    {
        $calls = $this->calls($user)->when($callId !== null, fn (Builder $query) => $query->whereKey($callId))
            ->where(function (Builder $query) use ($from, $until): void {
                foreach (array_keys(self::MILESTONES) as $field) {
                    $query->orWhereBetween($field, [$from->startOfDay(), $until->endOfDay()]);
                }
            })->get(['id', 'title', 'status', ...array_keys(self::MILESTONES)]);
        $events = collect();
        foreach ($calls as $call) {
            foreach (self::MILESTONES as $field => $label) {
                if (! $call->{$field}) {
                    continue;
                }
                $at = CarbonImmutable::instance($call->{$field});
                $allDay = ! in_array($field, ['opens_at', 'closes_at'], true);
                if ($allDay) {
                    $at = str_contains($field, '_end_') ? $at->endOfDay() : $at->startOfDay();
                }
                if ($at->lt($from) || $at->gt($until)) {
                    continue;
                }
                $events->push([
                    'id' => 'call-'.$call->id.'-'.$field, 'title' => $label,
                    'context' => $call->title, 'date' => $at->toDateString(),
                    'at' => $at->toDateTimeString(), 'display_at' => $at->format('M j, Y').($allDay ? ' · All day' : $at->format(' · g:i A')),
                    'kind' => 'official', 'draft' => $call->status === 'draft',
                    'deadline' => $field === 'closes_at' || str_contains($field, '_end_'),
                    'url' => route('research-calls.index', ['call' => $call->id]),
                    'notes' => 'Schedule managed through the research call.',
                ]);
            }
        }
        $events = $events->merge($this->projectEvents($user, $from, $until, $callId));
        $reminders = PersonalReminder::where('user_id', $user->id)->whereBetween('starts_at', [$from, $until])->get();
        foreach ($reminders as $reminder) {
            $events->push([
                'id' => 'reminder-'.$reminder->id, 'reminder_id' => $reminder->id,
                'title' => $reminder->title, 'context' => 'Only you',
                'date' => $reminder->starts_at->toDateString(), 'at' => $reminder->starts_at->toDateTimeString(),
                'display_at' => $reminder->starts_at->format('M j, Y · g:i A'),
                'kind' => 'personal', 'draft' => false, 'deadline' => false,
                'url' => null, 'notes' => $reminder->notes,
            ]);
        }

        return $events->sortBy('at')->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function projectEvents(User $user, CarbonImmutable $from, CarbonImmutable $until, ?int $callId): Collection
    {
        $projects = TopicProposal::query()->monitoringAvailable()
            ->when($callId !== null, fn (Builder $query) => $query->where('research_call_id', $callId));
        if ($user->isUsingWorkspace([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER])) {
            $projects->accessibleTo($user);
        } elseif ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_SECRETARY)) {
            $projects->forImplementationReportReview($user);
        } elseif ($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_OFFICE)) {
            $projects->accessibleForDocumentRelease($user);
        } elseif (! $user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD)) {
            return collect();
        }
        $projects->with([
            'latestVersion.files' => fn (HasMany $query) => $query->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)
                ->select('id', 'proposal_version_id', 'document_type', 'source_data'),
            'progressReports' => fn (HasMany $query) => $query
                ->when($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), fn (Builder $query) => $query->visibleToResearchHead())
                ->select('id', 'topic_id', 'reporting_date', 'reporting_year', 'reporting_quarter', 'period_start', 'period_end', 'version_number', 'supersedes_report_id', 'submission_status', 'review_status', 'review_stage', 'work_plan'),
            'narrativeReports' => fn (HasMany $query) => $query
                ->when($user->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD), fn (Builder $query) => $query->visibleToResearchHead())
                ->select('id', 'topic_id', 'report_type', 'reporting_date', 'reporting_quarter', 'period_end', 'version_number', 'submission_status', 'review_status', 'review_stage'),
        ]);
        $events = collect();
        foreach ($projects->get(['id', 'user_id', 'research_call_id', 'title', 'status', 'project_status', 'notice_to_proceed_issued_at', 'notice_to_proceed_data', 'estimated_duration_months', 'created_at']) as $topic) {
            if (! $this->health->hasSchedule($topic)) {
                continue;
            }
            $url = route('topics.show', $topic).($user->isUsingWorkspace(User::WORKSPACE_FACULTY) ? '#proposal-details' : '#project-monitoring');
            $window = $this->quarters->reportingWindow($topic);
            $dates = collect([
                ['id' => 'start', 'title' => 'Project implementation begins', 'at' => $window['start'], 'label' => 'Project date', 'deadline' => false, 'notes' => 'Implementation starts under the issued Notice to Proceed.'],
                ['id' => 'end', 'title' => 'Project completion target', 'at' => $window['end'], 'label' => 'Project date', 'deadline' => false, 'notes' => 'Implementation ends under the issued Notice to Proceed.'],
            ]);
            foreach ([
                'monitoring' => $this->quarters->summaryRows($topic->progressReports, $topic),
                'progress' => $this->quarters->narrativeProgressPeriods($topic, $topic->narrativeReports),
            ] as $type => $periods) {
                foreach ($periods as $period) {
                    if ($period['report'] !== null && $period['report']->review_status !== 'revision_requested') {
                        continue;
                    }
                    $dates->push([
                        'id' => $type.'-'.$period['quarter'],
                        'title' => ($type === 'monitoring' ? 'Monitoring Tool ' : 'Progress Report ').$period['label'].' deadline',
                        'at' => $period['end'], 'label' => 'Report deadline', 'deadline' => true,
                        'notes' => $period['period']."\n".$period['status']."\nSubmissions open ".$period['opens_at']->format('M j, Y').'.',
                    ]);
                }
            }
            $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
            if ($terminal === null || $terminal->review_status === 'revision_requested') {
                $dates->push([
                    'id' => 'terminal-open', 'title' => 'Terminal report submissions open',
                    'at' => $this->quarters->terminalOpensAt($topic), 'label' => 'Report opening', 'deadline' => false,
                    'notes' => 'Terminal report submission becomes available after the implementation period. Required quarterly reports must be submitted first.',
                ]);
            }
            foreach ($this->workPlan->overdueActivities($topic, $until->addDay()->startOfDay()->max(CarbonImmutable::now())) as $activity) {
                $dates->push([
                    'id' => 'work-plan-'.$activity['source_work_plan_index'], 'title' => 'Work Plan target: '.$activity['activity'],
                    'at' => CarbonImmutable::parse($activity['target_completion_date'])->endOfDay(), 'label' => 'Work Plan target', 'deadline' => true,
                    'notes' => $activity['objective']."\nExpected output: ".$activity['physical_target'],
                ]);
            }
            foreach ($dates as $date) {
                $at = $date['at'];
                if ($at->lt($from) || $at->gt($until)) {
                    continue;
                }
                $events->push([
                    'id' => 'project-'.$topic->id.'-'.$date['id'], 'title' => $date['title'],
                    'context' => $topic->title, 'date' => $at->toDateString(), 'at' => $at->toDateTimeString(),
                    'display_at' => $at->format('M j, Y').' · All day', 'kind' => 'project', 'label' => $date['label'],
                    'draft' => false, 'deadline' => $date['deadline'], 'url' => $url,
                    'url_label' => $user->isUsingWorkspace(User::WORKSPACE_FACULTY) ? 'Open project' : 'Open project reports', 'notes' => $date['notes'],
                ]);
            }
        }

        return $events;
    }
}
