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
