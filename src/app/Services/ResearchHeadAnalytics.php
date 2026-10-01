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
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
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

    public function __construct(private readonly MonitoringQuarterService $quarters) {}

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
            'collaborators:id,topic_id,user_id,email,accepted_at,project_role', 'latestProgressReport', 'progressReports', 'narrativeReports'])
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

            $participants = collect([$topic->user_id])->merge($topic->collaborators
                ->filter(fn ($member): bool => $member->accepted_at !== null && ! $member->isProjectSecretary())
                ->map(fn ($member): array => ['id' => $member->user_id, 'email' => $member->email])->all());
            $completed = $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETED;
            $counts[$completed ? 'completed' : 'active']++;
            if (! $completed) {
                $currentParticipants = $currentParticipants->merge($participants);
            }
            $hasSchedule = $this->hasSchedule($topic);
            $window = $hasSchedule ? $this->quarters->reportingWindow($topic) : null;
            if (! $hasSchedule && ! $completed) {
                $unknownSchedules++;
            }
            $latest = $topic->latestProgressReport;
            $completionPending = ! $completed && ((int) $latest?->progress_percentage >= 100 || $topic->project_status === TopicProposal::PROJECT_STATUS_COMPLETION_PENDING);
            $ended = $window && $window['end']->isPast();
            $delaySince = $ended ? $window['end']->addDay()->startOfDay() : null;
            $milestones = collect($topic->progressReports)->sortBy(fn ($report): string => $report->reporting_date->format('Y-m-d').sprintf('-%05d-%010d', $report->version_number, $report->id))->flatMap(fn ($report) => collect($report->work_plan ?? [])
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
                $counts['delayed']++;
                $attention->push($this->issue($topic, 'delayed', $delaySince ? 'Project deadline / reported milestone passed' : 'Project marked delayed', $delaySince, $delaySince ? 'overdue' : 'delay start date not recorded', 'project-monitoring'));
            }
            $awaiting = $completionPending;
            if ($completed) {
                $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
                if (! $terminal) {
                    $attention->push($this->issue($topic, 'terminal', 'Completed project has no terminal report recorded', null, 'completion date not recorded', 'project-monitoring'));
                } elseif ($terminal->review_status !== 'reviewed') {
                    $attention->push($this->issue($topic, 'terminal_review', 'Terminal report '.($terminal->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $terminal->submitted_at ?? $terminal->created_at, 'waiting', 'project-monitoring'));
                }
            }
            if (! $completed) {
                $rows = $hasSchedule ? $this->quarters->summaryRows($topic->progressReports, $topic) : collect();
                $missing = $rows->filter(fn (array $row): bool => $row['state'] === 'not_submitted');
                if ($missing->isNotEmpty()) {
                    $awaiting = true;
                    $attention->push($this->issue($topic, 'quarterly', 'Missing quarterly reports: '.$missing->pluck('label')->join(', '), $missing->first()['opens_at'], 'since reporting opened', 'project-monitoring'));
                }
                $reviewRows = $hasSchedule ? $rows : $this->quarters->summaryRows($topic->progressReports);
                foreach ($reviewRows as $row) {
                    if ($row['report'] && in_array($row['report']->review_status, ['pending', 'revision_requested'], true)) {
                        $awaiting = true;
                        $attention->push([...$this->issue($topic, 'report_review', $row['label'].' report '.($row['report']->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $row['report']->submitted_at ?? $row['report']->created_at, 'waiting', 'project-monitoring'),
                            'review_status' => $row['report']->review_status, 'report_id' => $row['report']->id,
                            'url' => route('topics.show', $topic).'#monitoring-tool-'.$row['report']->id]);
                    }
                }
                $terminal = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
                if ($ended && ! $terminal) {
                    $awaiting = true;
                    $attention->push($this->issue($topic, 'terminal', 'Missing terminal report', $window['end']->addDay()->startOfDay(), 'since reporting opened', 'project-monitoring'));
                }
                if ($terminal && $terminal->review_status !== 'reviewed') {
                    $awaiting = true;
                    $attention->push([...$this->issue($topic, 'terminal_review', 'Terminal report '.($terminal->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $terminal->submitted_at ?? $terminal->created_at, 'waiting', 'project-monitoring'),
                        'review_status' => $terminal->review_status, 'report_id' => $terminal->id,
                        'url' => route('topics.show', $topic).'#narrative-report-'.$terminal->id]);
                }
                foreach ($topic->narrativeReports->where('report_type', 'progress')->where('review_status', '!=', 'reviewed') as $narrative) {
                    $awaiting = true;
                    $attention->push([...$this->issue($topic, 'narrative_review', 'Progress report '.($narrative->review_status === 'pending' ? 'awaiting review' : 'needs corrections'), $narrative->submitted_at ?? $narrative->created_at, 'waiting', 'project-monitoring'),
                        'review_status' => $narrative->review_status, 'report_id' => $narrative->id,
                        'url' => route('topics.show', $topic).'#narrative-report-'.$narrative->id]);
                }
                if ((int) $latest?->progress_percentage >= 100) {
                    $attention->push($this->issue($topic, 'completion', 'Reported 100% — completion decision needed', $latest->submitted_at ?? $latest->created_at, 'waiting', 'project-monitoring'));
                }
            }
            $entries = collect($latest?->budget_utilization ?? [])->filter(fn ($entry): bool => is_array($entry)
                && (is_numeric($entry['actual_amount'] ?? null) || is_numeric($entry['utilized'] ?? null)));
            $utilized = (float) $entries->sum(fn (array $entry): float => (float) (is_numeric($entry['actual_amount'] ?? null) ? $entry['actual_amount'] : $entry['utilized']));
            $budget = $topic->estimated_budget === null ? null : (float) $topic->estimated_budget;
            $percentage = $entries->isNotEmpty() && $budget > 0 ? round(100 * $utilized / $budget, 1) : null;
            if (($ended || $completed) && $percentage !== null && $percentage <= 50) {
                $attention->push($this->issue($topic, 'budget', 'At least 50% remaining in latest reported budget snapshot', $latest->submitted_at ?? $latest->created_at, 'since report', 'project-monitoring'));
            }
            $projectRows->push(['id' => $topic->id, 'title' => $topic->title, 'lead' => $topic->user?->name,
                'status' => $completed ? 'completed' : ($delayed ? 'delayed' : ($awaiting ? 'awaiting' : 'ongoing')),
                'progress' => $latest?->progress_percentage, 'deadline' => $window ? $window['end']->toDateString() : null,
                'budget' => $budget, 'utilized' => $entries->isNotEmpty() ? $utilized : null, 'percentage' => $percentage]);
        }
        $reported = $projectRows->filter(fn (array $row): bool => $row['percentage'] !== null);
        $budgetTotal = (float) $reported->sum('budget');
        $utilizedTotal = (float) $reported->sum('utilized');
        $facultyUsers = User::whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['faculty', 'faculty_researcher']))
            ->get(['id', 'email', 'email_verified_at']);
        $currentFaculty = $this->facultyCount($currentParticipants, $facultyUsers);
        $eventTopics = $this->topics($academicYear);
        $achievements = $periodAvailable ? $this->periodAchievements($eventTopics, $periodStart, $periodEnd, $facultyUsers) : ['projects' => null, 'faculty' => null];
        $trend = $this->monthlyTrend($eventTopics, $periodStart, $periodEnd, $periodAvailable);
        $published = ProjectJournalSubmission::whereIn('topic_id', (clone $eventTopics)->withIssuedNotice()->select('id'))->where('status', 'published');
        $undated = (clone $published)->whereNull('published_on')->count()
            + ResearchPublication::whereNotNull('confirmed_at')->whereHas('topics', fn (Builder $topics) => $topics->whereIn((new TopicProposal)->qualifyColumn('id'), (clone $eventTopics)->select('id')))->distinct()->count('fingerprint');
        $publications = $periodAvailable ? (clone $published)->whereBetween('published_on', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get(['manuscript_title', 'publication_url'])->unique(fn ($publication): string => Str::lower(Str::squish($publication->manuscript_title)))->count() : null;
        $targetMatches = $target && $periodStart->isSameDay($target->starts_on) && $periodEnd->isSameDay($target->ends_on);

        return ['kpis' => [...$counts, 'faculty' => $currentFaculty], 'pipeline' => collect($pipeline)->values(),
            'trend' => $trend, 'trendMax' => max(1, (int) $trend->max(fn (array $month): int => max($month['new'], $month['revision']))),
            'trendStart' => $periodStart->toDateString(), 'trendEnd' => $periodEnd->toDateString(), 'periodAvailable' => $periodAvailable,
            'projects' => $projectRows->sortBy('title')->values(),
            'projectStatuses' => collect(['ongoing' => 'Ongoing', 'delayed' => 'Delayed / overdue', 'awaiting' => 'Awaiting required report / review', 'completed' => 'Completed'])
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'count' => $projectRows->where('status', $key)->count()])->values(),
            'attention' => $attention->sortByDesc(fn (array $issue): int => $issue['days'] ?? -1)->values(),
            'budget' => ['utilized' => $utilizedTotal, 'budget' => $budgetTotal, 'percentage' => $budgetTotal > 0 ? round(100 * $utilizedTotal / $budgetTotal, 1) : null,
                'reported' => $reported->count(), 'total' => $projectRows->count()],
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
            $hasSchedule = $this->hasSchedule($topic);
            $issuedInPeriod = $topic->notice_to_proceed_issued_at->gte($start);
            if (! $issuedInPeriod && (! $hasSchedule || $this->quarters->reportingWindow($topic)['end']->lt($start))) {
                continue;
            }
            $participants->push($topic->user_id);
            $participants = $participants->merge($topic->collaborators
                ->filter(fn ($member): bool => $member->accepted_at !== null && $member->accepted_at->lte($end) && ! $member->isProjectSecretary())
                ->map(fn ($member): array => ['id' => $member->user_id, 'email' => $member->email]));
        }

        return ['projects' => $projects, 'faculty' => $this->facultyCount($participants, $facultyUsers)];
    }

    private function hasSchedule(TopicProposal $topic): bool
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

    private function facultyCount(Collection $participants, Collection $users): int
    {
        $ids = $participants->filter(fn ($member): bool => is_numeric($member))->map(fn ($id): int => (int) $id)
            ->merge($participants->filter(fn ($member): bool => is_array($member))->pluck('id')->filter());
        $emails = $participants->filter(fn ($member): bool => is_array($member))->filter(fn (array $member): bool => empty($member['id']))->pluck('email')
            ->filter()->map(fn (string $email): string => Str::lower(trim($email)));

        return $users->filter(fn (User $user): bool => $ids->contains($user->id)
            || ($user->email_verified_at && $emails->contains(Str::lower($user->email))))->count();
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
