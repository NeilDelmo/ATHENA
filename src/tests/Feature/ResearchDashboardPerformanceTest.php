<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-15 12:00:00'));
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $this->researcher = User::factory()->create();
    $this->researcher->assignRole('faculty_researcher');
    $this->body = str_repeat('Full report contents outside the dashboard. ', 1000);
    $this->project = fn (array $attributes = []): TopicProposal => TopicProposal::create([
        'user_id' => $this->researcher->id, 'title' => 'Research dashboard project',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        'estimated_duration_months' => 12, 'estimated_budget' => 10000,
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31', 'approved_budget' => 10000],
        ...$attributes,
    ]);
    $this->monitoring = fn (TopicProposal $topic, array $attributes = []): ProjectProgressReport => $topic->progressReports()->create([
        'submitted_by' => $this->researcher->id, 'reporting_date' => '2026-03-31',
        'reporting_year' => 2026, 'reporting_quarter' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31',
        'version_number' => 1, 'progress_percentage' => 40, 'accomplishments' => $this->body, 'issues' => $this->body,
        'budget_utilization' => [['actual_amount' => 2000, 'remarks' => $this->body]],
        'review_status' => 'reviewed', 'submitted_at' => '2026-10-01 12:00:00',
        ...$attributes,
    ]);
    $this->narrative = fn (TopicProposal $topic, array $attributes = []): ProjectNarrativeReport => $topic->narrativeReports()->create([
        'submitted_by' => $this->researcher->id, 'report_type' => 'progress', 'reporting_date' => '2026-03-31',
        'reporting_quarter' => 1, 'period_start' => '2026-01-01', 'period_end' => '2026-03-31', 'version_number' => 1,
        'submission_date' => '2026-04-01', 'researchers' => $this->researcher->name,
        'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31', 'budget' => 10000,
        'funding_agency' => 'University', 'accomplishment_summary' => $this->body,
        'introduction' => $this->body, 'objectives' => $this->body, 'methodology' => $this->body,
        'results_discussion' => $this->body, 'photos' => [['caption' => $this->body]], 'terminal_data' => ['abstract' => $this->body],
        'review_status' => 'reviewed', 'submitted_at' => '2026-10-01 12:00:00',
        ...$attributes,
    ]);
});

test('researcher dashboards load report summaries only for active projects and retain required actions', function () {
    $active = ($this->project)([
        'notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-06-30', 'approved_budget' => 10000],
    ]);
    $milestone = ['source_work_plan_index' => 0, 'objective' => 'Measure water quality', 'activity' => 'Collect water samples',
        'physical_target' => 'Sample inventory', 'target_completion_date' => '2026-02-28', 'percent_weight' => 100, 'accomplished_percentage' => 40];
    $monitoring = ($this->monitoring)($active, [
        'review_status' => 'revision_requested', 'review_stage' => 'secretary', 'secretary_reviewed_at' => '2026-10-10 12:00:00',
        'work_plan' => [$milestone],
    ]);
    $narrative = ($this->narrative)($active, [
        'review_status' => 'revision_requested', 'review_stage' => 'head', 'reviewed_at' => '2026-10-09 12:00:00',
    ]);
    ($this->monitoring)($active, [
        'reporting_date' => '2026-06-30', 'reporting_quarter' => 2, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'submission_status' => 'prepared', 'review_status' => 'pending', 'progress_percentage' => 99,
    ]);
    ($this->narrative)($active, [
        'reporting_date' => '2026-06-30', 'reporting_quarter' => 2, 'period_start' => '2026-04-01', 'period_end' => '2026-06-30',
        'submission_status' => 'prepared', 'review_status' => 'pending',
    ]);
    $waiting = ($this->project)(['title' => 'Awaiting release', 'notice_to_proceed_issued_at' => null]);
    $completed = ($this->project)(['title' => 'Completed project', 'project_status' => 'completed']);
    $rejected = ($this->project)(['title' => 'Rejected proposal', 'status' => 'rejected', 'notice_to_proceed_issued_at' => null]);
    $revision = ($this->project)(['title' => 'Proposal requiring revisions', 'status' => 'revision_requested', 'notice_to_proceed_issued_at' => null]);
    foreach ([$waiting, $completed, $rejected, $revision] as $inactive) {
        ($this->monitoring)($inactive);
        ($this->narrative)($inactive);
    }

    $retrieved = [];
    foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
        Event::listen('eloquent.retrieved: '.$model, function (Model $report) use (&$retrieved): void {
            $retrieved[] = $report->getAttributes();
        });
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $response = $this->actingAs($this->researcher)
            ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
            ->get(route('faculty.dashboard'))->assertOk();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        foreach ([ProjectProgressReport::class, ProjectNarrativeReport::class] as $model) {
            Event::forget('eloquent.retrieved: '.$model);
        }
    }
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Researcher dashboard report bytes: '.strlen(json_encode($retrieved, JSON_THROW_ON_ERROR)).' / queries: '.count($queries).PHP_EOL);
    }

    $response->assertViewHas('averageProgress', 40)
        ->assertViewHas('activeProjects', fn ($projects): bool => $projects->modelKeys() === [$active->id])
        ->assertViewMissing('awaitingProjects')
        ->assertViewHas('completedProjects', fn ($projects): bool => $projects->modelKeys() === [$completed->id])
        ->assertSee('Missing Monitoring Tools: Q2')->assertSee('Missing Progress Reports: Q2')
        ->assertSee('Q1 Monitoring Tool · Corrections needed')->assertSee('Q1 Progress Report · Corrections needed')
        ->assertSee('Missing terminal report')->assertSee('At least 50% remaining in latest reported budget snapshot');
    $health = $response->viewData('projectHealth')[$active->id];
    expect($health['progress'])->toBe(40)
        ->and($health['utilized'])->toBe(2000.0)
        ->and($health['percentage'])->toBe(20.0)
        ->and($health['status'])->toBe('delayed')
        ->and($health['deadline'])->toBe('2026-06-30')
        ->and($health['delay_days'])->toBe(228)
        ->and($health['issues']->pluck('type')->all())->toEqualCanonicalizing([
            'delayed', 'quarterly', 'progress_quarterly', 'report_review', 'terminal', 'narrative_review', 'budget',
        ])
        ->and($health['issues']->firstWhere('type', 'report_review')['days'])->toBe(5)
        ->and($health['issues']->firstWhere('type', 'narrative_review')['days'])->toBe(6);
    $project = $response->viewData('activeProjects')->sole();
    expect($project->progressReports->modelKeys())->toBe([$monitoring->id])
        ->and($project->narrativeReports->modelKeys())->toBe([$narrative->id])
        ->and($project->progressReports->sole()->work_plan)->toEqual([$milestone]);
    foreach ($project->progressReports as $report) {
        expect($report->getAttributes())->not->toHaveKeys(['accomplishments', 'issues', 'budget_utilization']);
    }
    $project = $response->viewData('completedProjects')->sole();
    expect($project->relationLoaded('progressReports'))->toBeFalse()
        ->and($project->relationLoaded('narrativeReports'))->toBeFalse()
        ->and($project->relationLoaded('latestProgressReport'))->toBeFalse();
    expect($retrieved)->not->toBeEmpty();
    foreach ($retrieved as $attributes) {
        expect($attributes['topic_id'])->toBe($active->id);
        expect($attributes)->not->toHaveKeys([
            'accomplishments', 'issues', 'accomplishment_summary', 'introduction', 'objectives', 'methodology',
            'results_discussion', 'photos', 'terminal_data', 'research_head_remarks', 'secretary_remarks',
        ]);
    }
});

test('dashboard report summaries retain review routing dates and exact correction links', function (array $attributes, string $label, int $days, string $basis) {
    $topic = ($this->project)();
    $attributes = ['review_status' => 'pending', ...$attributes];
    $reports = [
        'report_review' => [($this->monitoring)($topic, $attributes), 'Monitoring Tool', 'monitoring-tool-'],
        'narrative_review' => [($this->narrative)($topic, $attributes), 'Progress Report', 'narrative-report-'],
    ];
    if (isset($attributes['created_at'])) {
        foreach ($reports as [$report]) {
            $report->forceFill(['created_at' => $attributes['created_at']])->saveQuietly();
        }
    }
    $response = $this->actingAs($this->researcher)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('faculty.dashboard'))->assertOk();
    $health = $response->viewData('projectHealth')[$topic->id];
    foreach ($reports as $type => [$report, $reportLabel, $anchor]) {
        $issue = $health['issues']->firstWhere('type', $type);
        expect($issue['issue'])->toBe('Q1 '.$reportLabel.' · '.$label)
            ->and($issue['days'])->toBe($days)
            ->and($issue['basis'])->toBe($basis)
            ->and($issue['review_stage'])->toBe($attributes['review_stage'] ?? 'head')
            ->and($issue['report_id'])->toBe($report->id)
            ->and($issue['url'])->toBe(route('topics.show', $topic).'#'.$anchor.$report->id);
    }
    expect($health['actions']->whereIn('type', ['report_review', 'narrative_review']))
        ->toHaveCount($attributes['review_status'] === 'revision_requested' ? 2 : 0);
})->with([
    'forwarded Head review' => [['review_stage' => 'head', 'forwarded_at' => '2026-10-12 12:00:00'], 'Head review', 3, 'with Research Head'],
    'initial Office review' => [['review_stage' => 'secretary'], 'Office review', 14, 'with Research Office Secretary'],
    'returned Office review' => [['review_stage' => 'secretary', 'reviewed_at' => '2026-10-13 12:00:00'], 'Office review', 2, 'with Research Office Secretary'],
    'secretary corrections' => [['review_stage' => 'secretary', 'review_status' => 'revision_requested', 'secretary_reviewed_at' => '2026-10-10 12:00:00'], 'Corrections needed', 5, 'since corrections requested'],
    'legacy Head corrections' => [['review_stage' => null, 'review_status' => 'revision_requested', 'reviewed_at' => '2026-10-09 12:00:00'], 'Corrections needed', 6, 'since corrections requested'],
    'legacy creation fallback' => [['review_stage' => 'head', 'submitted_at' => null, 'created_at' => '2026-10-08 12:00:00'], 'Head review', 7, 'with Research Head'],
]);
