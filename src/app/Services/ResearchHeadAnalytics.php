<?php

namespace App\Services;

use App\Models\ProjectJournalSubmission;
use App\Models\ProposalVersion;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\ResearchPublication;
use App\Models\TopicProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResearchHeadAnalytics
{
    public const REVIEW_STATUSES = ['pending', 'resubmitted', 'expert_review', 'for_final_decision'];

    public const PIPELINE = [
        'submitted' => 'Submitted', 'head_review' => 'Research Head Review',
        'revision_requested' => 'Faculty Revision', 'gad_review' => 'GAD / Co-evaluator Review',
        'lrec' => 'LREC', 'signing' => 'Final Approval / Signing', 'approved' => 'Approved',
    ];

    public function __construct(private readonly MonitoringQuarterService $quarters, private readonly ProjectMonitoringHealth $projectHealth) {}

    public function topics(string $academicYear = '', string $from = '', string $to = ''): Builder
    {
        $target = $academicYear !== '' ? ResearchAnnualTarget::where('academic_year', $academicYear)->first() : null;
        $firstSubmission = ProposalVersion::selectRaw('MIN(created_at)')->whereColumn('topic_id', (new TopicProposal)->getTable().'.id');

        return TopicProposal::query()->where('status', '!=', 'draft')
            ->when($academicYear !== '', function (Builder $query) use ($academicYear, $target, $firstSubmission): void {
                $query->where(function (Builder $year) use ($academicYear, $target, $firstSubmission): void {
                    $year->whereIn('research_call_id', ResearchCall::where('academic_year', $academicYear)->select('id'));
                    if ($target) {
                        $year->orWhere(fn (Builder $independent) => $independent->whereNull('research_call_id')
                            ->whereRaw('COALESCE(('.$firstSubmission->toSql().'), created_at) BETWEEN ? AND ?', [
                                ...$firstSubmission->getBindings(), $target->starts_on->startOfDay(), $target->ends_on->endOfDay(),
                            ]));
                    }
                });
            })
            ->when($from !== '', fn (Builder $query) => $query->whereRaw('COALESCE(('.$firstSubmission->toSql().'), created_at) >= ?', [...$firstSubmission->getBindings(), CarbonImmutable::parse($from)->startOfDay()]))
            ->when($to !== '', fn (Builder $query) => $query->whereRaw('COALESCE(('.$firstSubmission->toSql().'), created_at) <= ?', [...$firstSubmission->getBindings(), CarbonImmutable::parse($to)->endOfDay()]));
    }

    public function filterPipeline(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'submitted' => $query->where('status', 'pending')->whereNull('research_head_viewed_version_id'),
            'head_review', 'awaiting_review' => $query->whereIn('status', self::REVIEW_STATUSES)
                ->when($stage === 'head_review', fn (Builder $review) => $review->where(fn (Builder $opened) => $opened->where('status', '!=', 'pending')->orWhereNotNull('research_head_viewed_version_id'))),
            'lrec' => $query->whereIn('status', [TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW]),
            'signing' => $query->where(fn (Builder $signing) => $signing->where('status', TopicProposal::STATUS_READY_FOR_SIGNATURE)->orWhere(fn (Builder $approved) => $approved->where('status', 'approved')->whereNull('notice_to_proceed_issued_at'))),
            'approved' => $query->withIssuedNotice(),
            'revision_requested', 'gad_review', 'ready_for_signature' => $query->where('status', $stage),
            default => $query,
        };
    }

    private function stage(TopicProposal $topic): ?string
    {
        return match ($topic->status) {
            'pending' => $topic->research_head_viewed_version_id === null ? 'submitted' : 'head_review',
            'resubmitted', 'expert_review', 'for_final_decision' => 'head_review',
            'revision_requested' => 'revision_requested',
            TopicProposal::STATUS_GAD_REVIEW => 'gad_review',
            TopicProposal::STATUS_LREC_QUEUED, TopicProposal::STATUS_LREC_REVIEW => 'lrec',
            TopicProposal::STATUS_READY_FOR_SIGNATURE => 'signing',
            'approved' => $topic->notice_to_proceed_issued_at ? 'approved' : 'signing',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function summarize(string $academicYear = '', string $from = '', string $to = ''): array
    {
        $base = $this->topics($academicYear, $from, $to);
        $pipeline = collect(self::PIPELINE)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'count' => 0])->all();
        $attention = collect();
        $projectRows = collect();
        $currentParticipants = collect();
        $unknownSchedules = 0;
        $target = $academicYear !== '' ? ResearchAnnualTarget::where('academic_year', $academicYear)->first() : null;
        $periodAvailable = $academicYear === '' || $from !== '' || $to !== '' || $target !== null;
        $periodEnd = CarbonImmutable::parse($to ?: $target?->ends_on?->toDateString() ?: now()->toDateString())->endOfDay();
        $periodStart = CarbonImmutable::parse($from ?: $target?->starts_on?->toDateString() ?: $periodEnd->startOfMonth()->subMonths(11)->toDateString())->startOfDay();
        $counts = ['review' => 0, 'active' => 0, 'delayed' => 0, 'completed' => 0];

        foreach ((clone $base)->with(['user:id,name', 'researchCall:id,paper_revisions_end_date',
            'collaborators:id,topic_id,user_id,email,accepted_at,project_role',
            'progressReports:id,topic_id,reporting_date,reporting_year,reporting_quarter,period_start,period_end,version_number,supersedes_report_id,submission_status,review_status,review_stage,progress_percentage,work_plan,budget_utilization,reviewed_at,secretary_reviewed_at,forwarded_at,submitted_at,created_at',
            'narrativeReports:id,topic_id,report_type,reporting_date,reporting_quarter,period_start,period_end,version_number,submission_status,review_status,review_stage,reviewed_at,secretary_reviewed_at,forwarded_at,submitted_at,created_at,submission_date'])
            ->withMin('versions', 'created_at')->lazyById(100) as $topic) {
            $stage = $this->stage($topic);
            if ($stage !== null) {
                $pipeline[$stage]['count']++;
            }
            $since = $topic->status_started_at ?? ($topic->versions_min_created_at ? CarbonImmutable::parse($topic->versions_min_created_at) : null);
            if (in_array($topic->status, self::REVIEW_STATUSES, true)) {
                $counts['review']++;
                $attention->push($this->issue($topic, 'head_review', 'Waiting for Research Head review', $since, 'waiting', 'proposal-review'));
            }
            $revisionDeadline = $topic->review_stage !== 'lrec' ? $topic->researchCall?->paper_revisions_end_date : null;
            if ($topic->status === 'revision_requested' && $revisionDeadline && $revisionDeadline->copy()->endOfDay()->isPast()) {
                $attention->push($this->issue($topic, 'revision', 'Overdue faculty revision', $revisionDeadline->copy()->addDay()->startOfDay(), 'overdue', 'proposal-review'));
            } elseif ($topic->status === 'revision_requested' && ! $revisionDeadline) {
                $attention->push($this->issue($topic, 'revision', 'Faculty revision waiting — no applicable deadline recorded', $since, 'waiting, not an overdue calculation', 'proposal-review'));
            }
            if (! $topic->hasIssuedNoticeToProceed()) {
                continue;
            }

            $participants = collect([['id' => $topic->user_id, 'role' => 'lead']])->merge($topic->collaborators
                ->filter(fn ($member): bool => $member->accepted_at !== null && ! $member->isProjectSecretary())
                ->map(fn ($member): array => ['id' => $member->user_id, 'email' => $member->email, 'role' => 'member'])->all());
            $completed = $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETED;
            $counts[$completed ? 'completed' : 'active']++;
            if (! $completed) {
                $currentParticipants = $currentParticipants->merge($participants->map(fn (array $participant): array => [
                    ...$participant, 'project' => ['id' => $topic->id, 'title' => $topic->title, 'role' => $participant['role']],
                ]));
            }
            $health = $this->projectHealth->assess($topic, forResearchHead: true);
            $unknownSchedules += ! $completed && ! $health['has_schedule'] ? 1 : 0;
            $counts['delayed'] += $health['delayed'] ? 1 : 0;
            $attention = $attention->merge($health['issues']);
            $projectRows->push(['id' => $topic->id, 'title' => $topic->title, 'lead' => $topic->user?->name,
                ...Arr::except($health, ['delayed', 'has_schedule']),
                'has_schedule' => $health['has_schedule']]);
        }
        $reported = $projectRows->filter(fn (array $row): bool => $row['percentage'] !== null);
        $facultyUsers = User::whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['faculty', 'faculty_researcher']))
            ->get(['id', 'name', 'college', 'email', 'email_verified_at']);
        $currentFaculty = $this->facultyRows($currentParticipants, $facultyUsers);
        $eventTopics = $this->topics();
        $achievements = $periodAvailable ? $this->periodAchievements($eventTopics, $periodStart, $periodEnd, $facultyUsers) : ['projects' => null, 'faculty' => null];
        $trend = $this->monthlyTrend($this->topics($academicYear), $periodStart, $periodEnd, $periodAvailable);
        $published = ProjectJournalSubmission::whereIn('topic_id', (clone $eventTopics)->withIssuedNotice()->select('id'))->where('status', 'published');
        $undated = (clone $published)->whereNull('published_on')->count()
            + ResearchPublication::whereNotNull('confirmed_at')->whereHas('topics', fn (Builder $topics) => $topics->whereIn((new TopicProposal)->qualifyColumn('id'), (clone $eventTopics)->select('id')))->distinct()->count('fingerprint');
        $publications = $periodAvailable ? (clone $published)->whereBetween('published_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get(['manuscript_title', 'publication_url'])->unique(fn ($publication): string => Str::lower(Str::squish($publication->manuscript_title)))->count() : null;
        $targetMatches = $target && $periodStart->isSameDay($target->starts_on) && $periodEnd->isSameDay($target->ends_on);

        return ['kpis' => [...$counts, 'faculty' => $currentFaculty->count()], 'faculty' => $currentFaculty, 'pipeline' => collect($pipeline)->values(),
            'trend' => $trend, 'trendMax' => max(1, (int) $trend->max(fn (array $month): int => max($month['new'], $month['revision']))),
            'trendStart' => $periodStart->toDateString(), 'trendEnd' => $periodEnd->toDateString(), 'periodAvailable' => $periodAvailable,
            'projects' => $projectRows->sortBy('title')->values(),
            'projectStatuses' => collect(['ongoing' => 'Ongoing', 'delayed' => 'Delayed / overdue', 'awaiting' => 'Awaiting required report / review', 'completed' => 'Completed'])
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'count' => $projectRows->where('status', $key)->count()])->values(),
            'attention' => $attention->sortByDesc(fn (array $issue): int => $issue['days'] ?? -1)->values(),
            'budget' => ['reported' => $reported->count(), 'total' => $projectRows->count()],
            'targets' => collect([
                ['label' => 'Research projects started', 'actual' => $achievements['projects'], 'target' => $targetMatches ? $target->projects_target : null],
                ['label' => 'Published journal outputs', 'actual' => $publications, 'target' => $targetMatches ? $target->publications_target : null],
                ['label' => 'Faculty research participation', 'actual' => $achievements['faculty'], 'target' => $targetMatches ? $target->faculty_target : null],
            ]), 'target' => $target, 'targetMatches' => $targetMatches, 'undatedPublications' => $undated,
            'unknownSchedules' => $unknownSchedules];
    }

    /** @return array{projects: int, faculty: int} */
    private function periodAchievements(Builder $topics, CarbonImmutable $start, CarbonImmutable $end, Collection $facultyUsers): array
    {
        $issued = (clone $topics)->withIssuedNotice();
        $projects = (clone $issued)->whereBetween('notice_to_proceed_issued_at', [$start, $end])->count();
        $participants = collect();
        foreach ($issued->where('notice_to_proceed_issued_at', '<=', $end)->with('collaborators')->lazyById(100) as $topic) {
            $hasSchedule = $this->projectHealth->hasSchedule($topic);
            $issuedInPeriod = $topic->notice_to_proceed_issued_at->gte($start);
            if (! $issuedInPeriod) {
                $window = $hasSchedule ? $this->quarters->reportingWindow($topic) : null;
                if (! $window || $window['end']->lt($start) || $window['start']->gt($end)) {
                    continue;
                }
            }
            $participants->push(['id' => $topic->user_id]);
            $participants = $participants->merge($topic->collaborators
                ->filter(fn ($member): bool => $member->accepted_at !== null && $member->accepted_at->lte($end) && ! $member->isProjectSecretary())
                ->map(fn ($member): array => ['id' => $member->user_id, 'email' => $member->email]));
        }

        return ['projects' => $projects, 'faculty' => $this->facultyRows($participants, $facultyUsers)->count()];
    }

    /** @return Collection<int, array{id: int, name: string, college: ?string, projects: array<int, array{id: int, title: string, role: string}>}> */
    private function facultyRows(Collection $participants, Collection $users): Collection
    {
        $usersById = $users->keyBy('id');
        $usersByEmail = $users->filter(fn (User $user): bool => $user->email_verified_at !== null)
            ->keyBy(fn (User $user): string => Str::lower(trim($user->email)));
        $rows = collect();
        foreach ($participants as $participant) {
            $user = ! empty($participant['id']) ? $usersById->get($participant['id'])
                : $usersByEmail->get(Str::lower(trim($participant['email'] ?? '')));
            if (! $user) {
                continue;
            }
            $row = $rows->get($user->id, ['id' => $user->id, 'name' => $user->name, 'college' => $user->college, 'projects' => []]);
            if (isset($participant['project'])) {
                $row['projects'][$participant['project']['id']] ??= $participant['project'];
            }
            $rows->put($user->id, $row);
        }

        return $rows->sortBy('name')->map(fn (array $row): array => [...$row, 'projects' => array_values($row['projects'])])->values();
    }

    private function issue(TopicProposal $topic, string $type, string $label, mixed $since, string $basis, string $anchor): array
    {
        return ['id' => $topic->id, 'title' => $topic->title, 'type' => $type, 'issue' => $label,
            'status' => $anchor === 'proposal-review' ? (self::PIPELINE[$this->stage($topic)] ?? $topic->status) : Str::headline($topic->project_status ?? 'ongoing'),
            'days' => $since ? (int) max(0, CarbonImmutable::parse($since)->diffInDays(now(), false)) : null,
            'basis' => $basis, 'url' => route('topics.show', $topic).'#'.$anchor];
    }

    private function monthlyTrend(Builder $topics, CarbonImmutable $start, CarbonImmutable $end, bool $available): Collection
    {
        if (! $available || $end->lt($start)) {
            return collect();
        }
        $events = ProposalVersion::whereIn('topic_id', (clone $topics)->select('id'))->whereBetween('created_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(DISTINCT CASE WHEN submission_type = 'initial' AND version_number = 1 THEN topic_id END) as new_count, SUM(CASE WHEN submission_type = 'revision' THEN 1 ELSE 0 END) as revision_count")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")->get()->keyBy('month');
        $months = collect();
        for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
            $event = $events->get($month->format('Y-m'));
            $months->push(['key' => $month->format('Y-m'), 'label' => $month->format('M Y'), 'new' => (int) ($event?->new_count ?? 0), 'revision' => (int) ($event?->revision_count ?? 0)]);
        }

        return $months;
    }
}
