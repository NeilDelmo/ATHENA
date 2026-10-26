<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\MonitoringQuarterService;
use App\Services\ProjectMonitoringHealth;
use App\Services\ResearchHeadAnalytics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 2)->setTime(12, 0));
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole(['faculty', 'faculty_researcher']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->call = ResearchCall::create(['title' => 'Call for Proposals — August 2026 Implementation', 'academic_year' => '2026-2027',
        'opens_at' => '2026-08-01', 'closes_at' => '2026-12-31', 'status' => 'open',
        'paper_revisions_end_date' => '2026-10-12', 'reference_image_path' => 'dashboard-test-poster.png']);
    $this->project = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => 'Predictive maintenance and equipment availability in university laboratories',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-04-01',
        'estimated_duration_months' => 9, 'estimated_budget' => 100000, ...$attributes,
    ]);
    $this->monitoring = fn (TopicProposal $topic, int $quarter, array $attributes = []) => ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $topic->user_id,
        'reporting_date' => $quarter === 1 ? '2026-06-30' : '2026-09-30',
        'reporting_quarter' => $quarter, 'version_number' => 1,
        'progress_percentage' => 40, 'accomplishments' => 'Equipment records validated.',
        'review_status' => 'reviewed', 'submitted_at' => '2026-10-01', ...$attributes,
    ]);
    $this->narrative = function (TopicProposal $topic, int $quarter, array $attributes = []): ProjectNarrativeReport {
        $period = app(MonitoringQuarterService::class)->projectPeriods($topic)->firstWhere('quarter', $quarter);

        return ProjectNarrativeReport::create([
            'topic_id' => $topic->id, 'submitted_by' => $topic->user_id, 'report_type' => 'progress',
            'reporting_quarter' => $quarter, 'period_start' => $period['start'], 'period_end' => $period['end'],
            'submission_date' => $period['end']->addDay(), 'researchers' => $this->faculty->name,
            'implementation_start' => '2026-04-01', 'implementation_end' => '2026-12-31',
            'budget' => 100000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Dataset validated.',
            'introduction' => 'Laboratory equipment reliability.', 'objectives' => 'Reduce equipment downtime.',
            'methodology' => 'Maintenance history analysis.', 'results_discussion' => 'Pilot completed.',
            'photos' => [], 'review_status' => 'reviewed', 'submitted_at' => '2026-10-01', ...$attributes,
        ]);
    };
    $this->dashboard = fn () => $this->actingAs($this->faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('faculty.dashboard'));
    $this->exportDashboard = function (string $name, string $html): void {
        if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing'));
            File::put(storage_path('framework/testing/dashboard-'.$name.'.html'), $html);
        }
    };
});

test('completion averages use submitted reported progress without treating missing reports as zero', function () {
    $reported = ($this->project)();
    ($this->monitoring)($reported, 2, ['progress_percentage' => 80]);
    ($this->monitoring)($reported, 2, ['progress_percentage' => 99, 'version_number' => 2, 'submission_status' => 'prepared']);
    ($this->project)(['title' => 'Unreported active project']);
    ($this->project)(['user_id' => $this->head->id, 'title' => 'Outside this faculty portfolio']);
    $response = ($this->dashboard)()->assertOk()->assertViewHas('averageProgress', 80)
        ->assertViewHas('reportedProjects', fn ($projects): bool => $projects->count() === 1)
        ->assertSee('1 of 2 projects reported')->assertDontSee('Outside this faculty portfolio');
    ($this->exportDashboard)('researcher', $response->getContent());
});

test('an earlier dated submitted correction replaces superseded progress and budget snapshots', function () {
    $project = ($this->project)();
    $old = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-30', 'progress_percentage' => 100,
        'budget_utilization' => [['actual_amount' => 90000]], 'review_stage' => 'head',
    ]);
    $correction = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-25', 'version_number' => 2, 'supersedes_report_id' => $old->id,
        'progress_percentage' => 40, 'budget_utilization' => [['actual_amount' => 20000]], 'review_stage' => 'head',
    ]);

    expect($project->fresh()->latestProgressReport?->id)->toBe($correction->id)
        ->and($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($correction->id);
    foreach ([false, true] as $forHead) {
        $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: $forHead);
        expect($health['report_id'])->toBe($correction->id)->and($health['progress'])->toBe(40)
            ->and($health['utilized'])->toBe(20000.0)
            ->and($health['issues']->pluck('type'))->not->toContain('completion');
    }
    ($this->dashboard)()->assertOk()->assertViewHas('averageProgress', 40);
    expect(TopicProposal::query()->completionPending()->whereKey($project)->exists())->toBeFalse();
});

test('a prepared earlier dated correction does not replace the submitted monitoring snapshot', function () {
    $project = ($this->project)();
    $submitted = ($this->monitoring)($project, 2, ['review_stage' => 'head', 'progress_percentage' => 40]);
    ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-25', 'version_number' => 2, 'supersedes_report_id' => $submitted->id,
        'submission_status' => 'prepared', 'progress_percentage' => 100, 'review_stage' => 'head',
    ]);
    expect($project->fresh()->latestProgressReport?->id)->toBe($submitted->id)
        ->and($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($submitted->id);
});

test('head snapshots keep legacy forwarded values until a correction is forwarded including repeated corrections', function () {
    $project = ($this->project)();
    $old = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-30', 'progress_percentage' => 60, 'review_stage' => null,
        'review_status' => 'revision_requested', 'budget_utilization' => [['actual_amount' => 30000]],
    ]);
    $correction = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-25', 'version_number' => 2, 'supersedes_report_id' => $old->id,
        'progress_percentage' => 40, 'review_stage' => 'secretary', 'budget_utilization' => [['actual_amount' => 20000]],
    ]);
    $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);
    expect($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($old->id)
        ->and($health['report_id'])->toBe($old->id)->and($health['progress'])->toBe(60)->and($health['utilized'])->toBe(30000.0);
    $correction->update(['review_stage' => 'head']);
    expect($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($correction->id);
    $correction->update(['review_stage' => 'secretary', 'review_status' => 'revision_requested']);
    $third = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-24', 'version_number' => 3, 'supersedes_report_id' => $correction->id,
        'progress_percentage' => 20, 'review_stage' => 'head', 'budget_utilization' => [['actual_amount' => 10000]],
    ]);
    $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);
    expect($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($third->id)
        ->and($health['report_id'])->toBe($third->id)->and($health['progress'])->toBe(20)->and($health['utilized'])->toBe(10000.0);
});

test('earlier dated corrected milestones do not leave false overdue warnings', function () {
    $project = ($this->project)();
    $milestone = ['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 50];
    $old = ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-30', 'work_plan' => [[...$milestone, 'accomplished_percentage' => 0]],
    ]);
    ($this->monitoring)($project, 2, [
        'reporting_date' => '2026-09-25', 'version_number' => 2, 'supersedes_report_id' => $old->id,
        'work_plan' => [[...$milestone, 'accomplished_percentage' => 50]],
    ]);
    foreach ([false, true] as $forHead) {
        $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: $forHead);
        expect($health['delayed'])->toBeFalse()->and($health['issues']->pluck('type'))->not->toContain('delayed');
    }
});

test('late corrections to a legacy year do not replace a later year monitoring snapshot', function () {
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2025-01-01', 'estimated_duration_months' => 24]);
    $old = ($this->monitoring)($project, 2, ['reporting_date' => '2025-06-30', 'reporting_year' => null, 'progress_percentage' => 30]);
    $latest = ($this->monitoring)($project, 2, ['reporting_date' => '2026-06-30', 'reporting_year' => null, 'progress_percentage' => 80]);
    ($this->monitoring)($project, 2, [
        'reporting_date' => '2025-06-25', 'reporting_year' => null,
        'version_number' => 2, 'supersedes_report_id' => $old->id, 'progress_percentage' => 40,
    ]);
    expect($project->fresh()->latestProgressReport?->id)->toBe($latest->id)
        ->and($project->fresh()->latestProgressReportForResearchHead?->id)->toBe($latest->id);
});

test('Head health and analytics use forwarded progress and budgets while retaining secretary review status', function () {
    $project = ($this->project)();
    $forwarded = ($this->monitoring)($project, 1, [
        'review_stage' => 'head', 'budget_utilization' => [['actual_amount' => 20000]],
    ]);
    $waiting = ($this->monitoring)($project, 2, [
        'review_stage' => 'secretary', 'review_status' => 'pending', 'progress_percentage' => 100,
        'budget_utilization' => [['actual_amount' => 90000]],
        'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 50, 'accomplished_percentage' => 1]],
    ]);
    ($this->narrative)($project, 1);
    ($this->narrative)($project, 2, ['review_stage' => 'secretary', 'review_status' => 'pending']);

    $headHealth = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);
    $facultyHealth = app(ProjectMonitoringHealth::class)->assess($project->fresh());
    $analytics = app(ResearchHeadAnalytics::class)->summarize()['projects']->sole();

    expect($headHealth['progress'])->toBe(40)
        ->and($headHealth['utilized'])->toBe(20000.0)
        ->and($headHealth['report_id'])->toBe($forwarded->id)
        ->and($headHealth['delayed'])->toBeFalse()
        ->and($headHealth['issues']->pluck('type'))->not->toContain('quarterly', 'progress_quarterly', 'delayed', 'completion')
        ->and($headHealth['issues']->pluck('issue')->join(' '))->toContain('Q2 Monitoring Tool · Office review', 'Q2 Progress Report · Office review')
        ->and($facultyHealth['progress'])->toBe(100)
        ->and($facultyHealth['utilized'])->toBe(90000.0)
        ->and($analytics['progress'])->toBe(40)
        ->and($analytics['utilized'])->toBe(20000.0)
        ->and($analytics['report_id'])->toBe($forwarded->id);

    $waiting->update(['review_stage' => 'head']);
    $forwardedHealth = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);
    expect($forwardedHealth['progress'])->toBe(100)
        ->and($forwardedHealth['utilized'])->toBe(90000.0)
        ->and($forwardedHealth['issues']->pluck('type'))->toContain('completion');
});

test('Head health leaves reported values unavailable when every report is with the secretary', function () {
    $project = ($this->project)();
    foreach ([1, 2] as $quarter) {
        ($this->monitoring)($project, $quarter, [
            'review_stage' => 'secretary', 'review_status' => 'pending', 'progress_percentage' => 100,
            'budget_utilization' => [['actual_amount' => 90000]],
        ]);
        ($this->narrative)($project, $quarter, ['review_stage' => 'secretary', 'review_status' => 'pending']);
    }

    $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);
    $analytics = app(ResearchHeadAnalytics::class)->summarize();
    expect($health['progress'])->toBeNull()
        ->and($health['utilized'])->toBeNull()
        ->and($health['percentage'])->toBeNull()
        ->and($health['report_id'])->toBeNull()
        ->and($health['report_date'])->toBeNull()
        ->and($health['issues']->pluck('type'))->not->toContain('quarterly', 'progress_quarterly', 'completion')
        ->and($health['issues']->pluck('issue')->join(' '))->toContain('Office review')
        ->and($analytics['projects']->sole()['progress'])->toBeNull()
        ->and($analytics['projects']->sole()['utilized'])->toBeNull()
        ->and($analytics['budget']['reported'])->toBe(0);
});

test('monitoring review issues identify the quarter and measure the current review stage', function (string $reportType, array $attributes, string $status, int $days, string $basis) {
    $project = ($this->project)();
    $attributes = ['review_status' => 'pending', 'submitted_at' => '2026-09-01 12:00:00', ...$attributes];
    $report = $reportType === 'monitoring'
        ? ($this->monitoring)($project, 1, $attributes)
        : ($this->narrative)($project, 1, $attributes);
    $type = $reportType === 'monitoring' ? 'report_review' : 'narrative_review';
    $label = $reportType === 'monitoring' ? 'Monitoring Tool' : 'Progress Report';
    $anchor = $reportType === 'monitoring' ? 'monitoring-tool-' : 'narrative-report-';
    $issue = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true)['issues']->firstWhere('type', $type);

    expect($issue['issue'])->toBe('Q1 '.$label.' · '.$status)
        ->and($issue['days'])->toBe($days)
        ->and($issue['basis'])->toBe($basis)
        ->and($issue['review_stage'])->toBe($attributes['review_stage'] ?? 'head')
        ->and($issue['review_status'])->toBe($attributes['review_status'])
        ->and($issue['report_id'])->toBe($report->id)
        ->and($issue['url'])->toBe(route('topics.show', $project).'#'.$anchor.$report->id);
})->with(['Monitoring Tool' => 'monitoring', 'Progress Report' => 'progress'])->with([
    'Head review starts when the secretary forwards' => [['review_stage' => 'head', 'forwarded_at' => '2026-09-30 12:00:00'], 'Head review', 2, 'with Research Head'],
    'initial Office review starts at submission' => [['review_stage' => 'secretary'], 'Office review', 31, 'with Research Office Secretary'],
    'returned concerns restart Office review' => [['review_stage' => 'secretary', 'reviewed_at' => '2026-10-01 12:00:00'], 'Office review', 1, 'with Research Office Secretary'],
    'secretary corrections start at the correction request' => [['review_stage' => 'secretary', 'review_status' => 'revision_requested', 'secretary_reviewed_at' => '2026-09-29 12:00:00'], 'Corrections needed', 3, 'since corrections requested'],
    'legacy corrections use the Head decision date' => [['review_stage' => null, 'review_status' => 'revision_requested', 'reviewed_at' => '2026-09-28 12:00:00'], 'Corrections needed', 4, 'since corrections requested'],
    'legacy Head review falls back to submission' => [['review_stage' => 'head', 'forwarded_at' => null], 'Head review', 31, 'with Research Head'],
]);

test('reported full progress flags closeout without claiming a completion decision is ready', function () {
    $project = ($this->project)();
    ($this->monitoring)($project, 2, ['progress_percentage' => 100, 'submitted_at' => '2026-10-01 12:00:00']);
    $health = app(ProjectMonitoringHealth::class)->assess($project->fresh(), forResearchHead: true);

    expect($health['status'])->toBe('awaiting')
        ->and($health['progress'])->toBe(100)
        ->and($health['delayed'])->toBeFalse()
        ->and($health['issues']->firstWhere('type', 'completion'))->toMatchArray([
            'issue' => 'Project closeout pending', 'basis' => 'since 100% report', 'days' => 1,
        ])
        ->and($health['issues']->pluck('type'))->toContain('quarterly', 'progress_quarterly');
});

test('Head monitoring lists and completion filters use the latest forwarded reports', function () {
    $project = ($this->project)();
    $visible = ($this->monitoring)($project, 1, ['review_stage' => 'head', 'progress_percentage' => 40]);
    $waiting = ($this->monitoring)($project, 2, ['review_stage' => 'secretary', 'review_status' => 'pending', 'progress_percentage' => 100]);
    $visibleNarrative = ($this->narrative)($project, 1, ['review_stage' => 'head']);
    ($this->narrative)($project, 2, ['review_stage' => 'secretary', 'review_status' => 'pending']);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->get(route('research_head.projects.index', ['status' => 'ongoing']))->assertOk()
        ->assertViewHas('summary', fn (array $summary): bool => $summary['ongoing'] === 1 && $summary['completion_pending'] === 0)
        ->assertViewHas('projects', fn ($projects): bool => $projects->count() === 1
            && $projects->first()->latestProgressReport?->id === $visible->id
            && $projects->first()->latestNarrativeReport?->id === $visibleNarrative->id)
        ->assertViewHas('workspaceProjects', fn (array $projects): bool => $projects[0]['progress'] === 40);
    $this->get(route('research_head.projects.index', ['status' => 'completion_pending']))->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => $projects->isEmpty());
    $this->patch(route('research_head.projects.update-status', $project), [
        'project_status' => 'completed', 'completion_confirmed' => '1',
    ])->assertSessionHasErrors(['project_status' => 'A project can only be completed when its latest monitoring tool shows 100% progress.']);
    expect($project->fresh()->project_status)->toBe('ongoing');

    $waiting->update(['review_stage' => 'head']);
    $this->get(route('research_head.projects.index', ['status' => 'completion_pending']))->assertOk()
        ->assertViewHas('summary', fn (array $summary): bool => $summary['completion_pending'] === 1 && $summary['ongoing'] === 0)
        ->assertViewHas('projects', fn ($projects): bool => $projects->first()->latestProgressReport?->id === $waiting->id);
});

test('project completion waits for current secretary reviews and permits resolved version history', function (string $reportType) {
    Notification::fake();
    Storage::fake('local');
    $project = ($this->project)(['notice_to_proceed_data' => ['approved_end_date' => '2026-09-30']]);
    foreach ([1, 2] as $quarter) {
        $monitoring = ($this->monitoring)($project, $quarter, ['review_stage' => 'head', 'progress_percentage' => 100]);
        $progress = ($this->narrative)($project, $quarter, ['review_stage' => 'head']);
    }
    $waiting = $reportType === 'monitoring' ? $monitoring : $progress;
    $waiting->update(['review_stage' => 'secretary', 'review_status' => 'pending']);
    Storage::disk('local')->put('signed-terminal.pdf', '%PDF-signed');
    ($this->narrative)($project, 2, [
        'report_type' => 'terminal', 'terminal_data' => ['signed_copy' => ['path' => 'signed-terminal.pdf', 'checksum' => hash('sha256', '%PDF-signed')]],
    ]);

    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->patch(route('research_head.projects.update-status', $project), ['project_status' => 'completed', 'completion_confirmed' => '1'])
        ->assertSessionHasErrors('project_status');
    expect(session('errors')->first('project_status'))->toContain('Research Office Secretary')
        ->and($project->fresh()->project_status)->toBe('ongoing');

    $replacement = $waiting->replicate()->fill([
        'review_stage' => 'head', 'review_status' => 'reviewed', 'version_number' => 2,
    ]);
    if ($replacement instanceof ProjectProgressReport) {
        $replacement->supersedes_report_id = $waiting->id;
    }
    $replacement->save();
    $this->patch(route('research_head.projects.update-status', $project), ['project_status' => 'completed', 'completion_confirmed' => '1'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($project->fresh()->project_status)->toBe('completed');
})->with(['Monitoring Tool' => 'monitoring', 'Progress Report' => 'progress']);

test('unreported and empty portfolios have honest progress and attention states', function (bool $hasProject) {
    if ($hasProject) {
        ($this->project)(['notice_to_proceed_issued_at' => '2026-10-01']);
    }
    $response = ($this->dashboard)()->assertOk()->assertViewHas('averageProgress', null)
        ->assertViewHas('attentionProjects', fn ($projects): bool => $projects->isEmpty())
        ->assertSee('No actions currently flagged')->assertDontSee('currently on track');
    ($this->exportDashboard)($hasProject ? 'unreported' : 'empty', $response->getContent());
})->with([true, false]);

test('both dashboards detect later missing Monitoring Tools and Progress Reports independently', function () {
    $project = ($this->project)();
    ($this->monitoring)($project, 1);
    ($this->narrative)($project, 1);
    foreach (range(1, 4) as $index) {
        ($this->project)(['title' => 'Project '.$index.' with quarterly reports still to submit']);
    }
    $response = ($this->dashboard)()->assertOk()->assertSee('Missing Monitoring Tools: Q2')
        ->assertSee('Missing Progress Reports: Q2')->assertSee('Show 1 more projects')
        ->assertViewHas('attentionProjects', fn ($projects): bool => $projects->contains('id', $project->id));
    ($this->exportDashboard)('actions', $response->getContent());
    $headDashboard = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertViewHas('analytics', fn ($data): bool => $data['attention']->where('id', $project->id)->pluck('type')->contains('progress_quarterly'));
    ($this->exportDashboard)('head', $headDashboard->html());
    ($this->monitoring)($project, 2);
    $issues = app(ResearchHeadAnalytics::class)->summarize()['attention']->where('id', $project->id)->pluck('type');
    expect($issues)->toContain('progress_quarterly')->not->toContain('quarterly');
    ($this->narrative)($project, 2);
    ($this->dashboard)()->assertViewHas('attentionProjects', fn ($projects): bool => ! $projects->contains('id', $project->id));
});

test('dashboards flag missing quarterly reports only after their deadline', function (string $today, bool $overdue, bool $prepared = false) {
    $this->travelTo(CarbonImmutable::parse($today));
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2026-01-01']);
    if ($prepared) {
        ($this->narrative)($project, 1, ['submission_status' => 'prepared', 'submitted_at' => null]);
    }
    $issues = app(ProjectMonitoringHealth::class)->assess($project)['issues']->whereIn('type', ['quarterly', 'progress_quarterly']);

    expect($issues)->toHaveCount($overdue ? 2 : 0);
    if ($overdue) {
        expect($issues->pluck('basis')->unique()->all())->toBe(['overdue'])
            ->and($issues->pluck('days')->unique()->all())->toBe([0]);
    }
})->with([
    'before the final week' => ['2026-03-24 23:59:59', false],
    'during the final week' => ['2026-03-25 00:00:00', false],
    'on the deadline' => ['2026-03-31 23:59:59', false],
    'after the deadline' => ['2026-04-01 00:00:00', true],
    'prepared but not submitted' => ['2026-04-01 00:00:00', true, true],
]);

test('faculty correction actions point to the exact reports and skip reviews awaiting the head', function () {
    $project = ($this->project)();
    ($this->monitoring)($project, 1);
    ($this->narrative)($project, 1);
    $monitoring = ($this->monitoring)($project, 2, ['review_status' => 'revision_requested']);
    $narrative = ($this->narrative)($project, 2, ['review_status' => 'revision_requested']);
    ($this->dashboard)()->assertOk()->assertSee('Correct Monitoring Tool')
        ->assertSee(route('topics.show', $project).'#monitoring-tool-'.$monitoring->id, false)
        ->assertSee(route('topics.show', $project).'#narrative-report-'.$narrative->id, false);
    $monitoring->update(['review_status' => 'pending']);
    $narrative->update(['review_status' => 'pending']);
    ($this->dashboard)()->assertViewHas('attentionProjects', fn ($projects): bool => $projects->isEmpty());
});

test('faculty proposal cards open matching scoped submission lists including shared projects', function () {
    $revision = ($this->project)(['status' => 'revision_requested', 'notice_to_proceed_issued_at' => null, 'project_status' => null]);
    $review = ($this->project)(['status' => 'pending', 'notice_to_proceed_issued_at' => null, 'project_status' => null]);
    $shared = ($this->project)(['user_id' => $this->head->id, 'title' => 'Accepted shared proposal']);
    $shared->collaborators()->create(['user_id' => $this->faculty->id, 'email' => $this->faculty->email,
        'name' => $this->faculty->name, 'accepted_at' => now()]);
    ($this->project)(['user_id' => $this->head->id, 'title' => 'Unrelated proposal']);
    $this->actingAs($this->faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->get(route('faculty.dashboard'))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->count() === 3)
        ->assertSee(route('faculty.submissions', ['category' => 'all']), false);
    $this->get(route('faculty.submissions', ['category' => 'all']))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 3)->assertDontSee('Unrelated proposal');
    $this->get(route('faculty.submissions', ['category' => 'active', 'status' => 'review']))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1 && $topics->first()->id === $review->id)
        ->assertSee('Clear filter');
    $this->get(route('faculty.submissions', ['category' => 'active', 'status' => 'revision_requested']))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1 && $topics->first()->id === $revision->id);
});

test('faculty announcements lead the overview revisions and drafts with applicable deadlines', function () {
    ($this->project)(['status' => 'revision_requested', 'notice_to_proceed_issued_at' => null, 'project_status' => null]);
    ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'project_title' => 'Laboratory equipment maintenance proposal draft']);
    $response = $this->actingAs($this->faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('faculty.dashboard'))->assertOk()
        ->assertSeeInOrder(['Research Office announcements', 'Proposal overview', 'Revisions to address', 'Papers ready'])
        ->assertSee('Due in 10 days')->assertSee('Oct 12, 2026')->assertDontSee('Project completion')->assertDontSee('You can submit anytime');
    ($this->exportDashboard)('faculty', $response->getContent());
});
