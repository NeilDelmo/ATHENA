<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectJournalSubmission;
use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\ResearchAnnualTarget;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchHeadAnalytics;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(12, 0));
    foreach (['faculty', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->call = ResearchCall::create(['title' => 'Analytics verification', 'academic_year' => '2026-2027', 'opens_at' => '2026-08-01', 'closes_at' => '2026-10-31', 'status' => 'open']);
    $this->topic = fn (array $attributes = []) => TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $this->call->id,
        'title' => fake()->unique()->sentence(), 'status' => 'pending', ...$attributes,
    ]);
    $this->project = fn (array $attributes = []) => ($this->topic)([
        'status' => 'approved', 'notice_to_proceed_issued_at' => '2026-08-01', 'project_status' => 'ongoing',
        'estimated_duration_months' => 12, 'estimated_budget' => 100000, ...$attributes,
    ]);
    $this->version = function (TopicProposal $topic, int $number, string $type, string $date): void {
        $version = $topic->versions()->make(['submitted_by' => $this->faculty->id, 'version_number' => $number,
            'submission_type' => $type, 'file_path' => 'analytics.pdf', 'original_filename' => 'analytics.pdf',
            'title' => $topic->title, 'estimated_budget' => 1000, 'estimated_duration_months' => 6]);
        $version->created_at = $date;
        $version->save();
    };
    $this->report = fn (TopicProposal $topic, array $attributes = []) => ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => '2026-08-31',
        'progress_percentage' => 25, 'accomplishments' => 'Recorded progress', 'review_status' => 'reviewed', ...$attributes,
    ]);
});

test('overview prioritizes reviews and preserves compact independently paginated lists', function () {
    foreach (range(1, 9) as $index) {
        $priorityProposal = ($this->topic)(['title' => 'Priority proposal '.$index]);
        ($this->version)($priorityProposal, 1, 'initial', '2026-09-01');
        ($this->project)(['title' => 'Delayed project '.$index, 'project_status' => 'delayed']);
    }
    ($this->topic)(['title' => 'Committee review', 'status' => 'lrec_queued']);

    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertSet('overview', true)->assertSet('pipeline', 'awaiting_review')
        ->assertSee('Proposal reviews')->assertSee('View all proposals')->assertSee('9 / 0')
        ->assertSeeInOrder(['data-dashboard-priority-kpis', 'Overview filters', 'id="received-proposals"', 'id="dashboard-report-reviews"', 'id="needs-attention"', 'Upcoming deadlines', 'data-dashboard-visual-summary'], false)
        ->assertDontSeeHtml('<table')->assertDontSee('Monthly submission trend')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 9 && $items->count() === 4)
        ->assertViewHas('attentionItems', fn ($items): bool => $items->total() === 9 && $items->count() === 4);

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-dashboard-review-workspace]//*[@id="received-proposals"]'))->toHaveCount(1)
        ->and($xpath->query('//aside[@aria-label="Research priorities"]//*[@id="needs-attention"]'))->toHaveCount(1)
        ->and($xpath->query('//details[@data-dashboard-date-filters and not(@open)]'))->toHaveCount(1);

    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-dashboard-overview.html'), $component->html());
        File::put(storage_path('framework/testing/head-dashboard-shell.html'), $this->actingAs($this->head)->get(route('research_head.dashboard'))->getContent());
        File::put(storage_path('framework/testing/head-calendar-shell.html'), $this->get(route('research_head.calendar'))->getContent());
    }

    $component->call('nextPage', 'attentionPage')
        ->assertViewHas('attentionItems', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 4)
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 1)
        ->call('nextPage')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 4)
        ->call('nextPage')->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 3 && $items->count() === 1)
        ->set('search', 'Priority proposal 1')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 1)
        ->set('search', '')->call('setPipeline', 'lrec')
        ->assertSeeHtml('aria-pressed="true" aria-label="Show LREC proposals: 1"')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 1 && $items->first()->title === 'Committee review')
        ->call('showReviewQueue')->assertSet('pipeline', 'awaiting_review')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 9)
        ->call('showReviewQueue')->assertSet('pipeline', 'awaiting_review');
});

test('dashboard report reviews include older progress submissions and keep pagination and scope independent', function () {
    $project = ($this->project)(['title' => 'Reports requiring attention']);
    $monitoring = ($this->report)($project, ['review_status' => 'pending']);
    $narrativeData = [
        'topic_id' => $project->id, 'submitted_by' => $this->faculty->id,
        'report_type' => 'progress', 'submission_date' => '2026-08-20',
        'researchers' => $this->faculty->name, 'implementation_start' => '2026-08-01', 'implementation_end' => '2027-07-31',
        'budget' => 100000, 'funding_agency' => 'Institution', 'accomplishment_summary' => 'Reported accomplishments',
        'introduction' => 'Background', 'objectives' => 'Objectives',
        'methodology' => 'Methodology', 'results_discussion' => 'Results', 'photos' => [],
        'review_status' => 'pending', 'submitted_at' => '2026-08-20',
    ];
    $olderReports = collect(range(1, 3))->map(fn ($index) => ProjectNarrativeReport::create([
        ...$narrativeData, 'submitted_at' => '2026-08-2'.$index,
    ]));
    ProjectNarrativeReport::create([...$narrativeData, 'review_status' => 'reviewed', 'submitted_at' => '2026-09-01']);
    ProjectNarrativeReport::create([...$narrativeData, 'submission_status' => 'prepared']);
    $correctionReport = ProjectNarrativeReport::create([...$narrativeData, 'review_status' => 'revision_requested']);
    ProjectNarrativeReport::create([...$narrativeData, 'report_type' => 'terminal', 'submitted_at' => '2026-09-02']);
    $latestTerminal = ProjectNarrativeReport::create([...$narrativeData, 'report_type' => 'terminal', 'submitted_at' => '2026-09-03']);
    $completed = ($this->project)(['project_status' => 'completed']);
    ProjectNarrativeReport::create([...$narrativeData, 'topic_id' => $completed->id]);
    $unissued = ($this->project)(['notice_to_proceed_issued_at' => null]);
    ProjectNarrativeReport::create([...$narrativeData, 'topic_id' => $unissued->id]);

    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertSee('Proposals awaiting review')->assertSee('Reports awaiting review')
        ->assertViewHas('reportItems', fn ($reports) => $reports->total() === 5 && $reports->count() === 4
            && $reports->first()->id === $olderReports->first()->id && $reports->first()->report_type === 'progress')
        ->assertSeeHtml('href="'.route('topics.show', $project).'#narrative-report-'.$olderReports->first()->id.'"')
        ->assertSeeHtml('href="'.route('topics.show', $project).'#narrative-report-'.$latestTerminal->id.'"');
    $component->assertViewHas('attentionItems', fn ($issues) => $issues->contains(fn ($issue) => ($issue['report_id'] ?? null) === $correctionReport->id)
        && ! $issues->contains(fn ($issue) => ($issue['review_status'] ?? null) === 'pending'));

    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-dashboard-review-work.html'), $component->html());
    }

    $component->call('nextPage', 'reportPage')
        ->assertViewHas('reportItems', fn ($reports) => $reports->currentPage() === 2 && $reports->count() === 1
            && $reports->first()->id === $monitoring->id && $reports->first()->report_type === 'quarterly')
        ->assertViewHas('topics', fn ($topics) => $topics->currentPage() === 1)
        ->set('search', 'Unrelated proposal search')
        ->assertViewHas('reportItems', fn ($reports) => $reports->currentPage() === 2 && $reports->total() === 5)
        ->set('academicYear', '2026-2027')
        ->assertViewHas('reportItems', fn ($reports) => $reports->currentPage() === 1 && $reports->total() === 5)
        ->set('academicYear', '2025-2026')
        ->assertViewHas('reportItems', fn ($reports) => $reports->total() === 0);

    $alerts = app(ResearchHeadAnalytics::class)->summarize()['attention']->where('id', $project->id)->where('type', 'narrative_review');
    expect($alerts->pluck('report_id')->all())->toEqualCanonicalizing($olderReports->pluck('id')->push($correctionReport->id)->all())
        ->and($alerts->firstWhere('report_id', $olderReports->first()->id)['url'])->toBe(route('topics.show', $project).'#narrative-report-'.$olderReports->first()->id);
});

test('dashboard orders proposals by their current waiting period and displays its duration', function () {
    $recentRecord = ($this->topic)(['title' => 'Longest waiting proposal']);
    $recentRecord->forceFill(['status_started_at' => '2026-09-01', 'created_at' => '2026-09-29'])->saveQuietly();
    $olderRecord = ($this->topic)(['title' => 'Recently returned proposal', 'status' => 'resubmitted']);
    $olderRecord->forceFill(['status_started_at' => '2026-09-28', 'created_at' => '2026-08-01'])->saveQuietly();

    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertViewHas('topics', fn ($topics) => $topics->pluck('id')->all() === [$recentRecord->id, $olderRecord->id])
        ->assertSee('29 days in this stage')->assertSee('2 days in this stage')
        ->assertSeeInOrder(['Longest waiting proposal', 'Recently returned proposal']);
});

test('calendar and analytics have separate protected routes and grouped functional navigation', function () {
    $this->actingAs($this->head)->get(route('research_head.calendar'))->assertOk()
        ->assertSeeHtml('data-research-head-calendar')->assertSee('Research calendar')
        ->assertDontSeeHtml('data-research-head-overview')->assertDontSee('Annual research targets');
    $this->get(route('research_head.analytics'))->assertOk()
        ->assertSeeHtml('data-research-head-analytics')->assertSee('Annual research targets')
        ->assertSee('Monthly submission trend')->assertDontSeeHtml('id="research-calendar"');

    $response = $this->get(route('research_head.dashboard'))->assertOk()
        ->assertSeeInOrder(['aria-label="Overview"', 'aria-label="Submission"', 'aria-label="Review"', 'aria-label="Monitoring"', 'aria-label="Resources"'], false)
        ->assertSeeHtml('data-sidebar-account')->assertSee('Account Profile')
        ->assertSeeHtml('data-sidebar-attention-url="'.route('sidebar-attention.open', 'proposal_submissions').'"')
        ->assertSeeHtml('data-sidebar-attention-url="'.route('sidebar-attention.open', 'project_monitoring').'"')
        ->assertDontSee('Coverage:')->assertDontSee('Project Monitoring')->assertDontSee('Proposal Submissions');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//nav[@data-research-head-navigation]//a');
    foreach (['research_head.dashboard', 'research_head.calendar', 'research_head.analytics', 'research-calls.index',
        'research_head.received-submissions.index', 'research_head.proposal-submissions.index', 'research_head.report-reviews.index',
        'research_head.projects.index', 'research_head.completed-projects.index', 'research_head.faculty-directory.index',
        'signatories.index'] as $destination) {
        expect($xpath->query('//nav[@data-research-head-navigation]//a[@href="'.route($destination).'"]'))->toHaveCount(1);
    }
    foreach ($links as $link) {
        $this->get($link->getAttribute('href'))->assertOk();
    }

    $this->actingAs($this->faculty)->get(route('research_head.calendar'))->assertForbidden();
    $this->get(route('research_head.analytics'))->assertForbidden();
    $this->actingAs($this->head)->withSession(['active_workspace' => User::WORKSPACE_FACULTY]);
    $this->get(route('research_head.calendar'))->assertForbidden();
    $this->get(route('research_head.analytics'))->assertForbidden();
});

test('Research Head pages use sidebar navigation without duplicate header tabs', function (string $routeName, string $activeLabel) {
    $response = $this->actingAs($this->head)->get(route($routeName))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-research-head-page-navigation]')->length)->toBe(0);
    foreach (['research_head.dashboard' => 'Dashboard', 'research_head.calendar' => 'Calendar', 'research_head.analytics' => 'Analytics'] as $destination => $label) {
        $link = $xpath->query('//nav[@data-research-head-navigation]//a[@href="'.route($destination).'"]')->item(0);
        expect($link)->not->toBeNull()->and(trim($link->textContent))->toBe($label)
            ->and($link->hasAttribute('wire:navigate'))->toBeTrue()
            ->and($link->getAttribute('class'))->toContain('min-h-[44px]');
    }
    $response->assertSee($activeLabel);
    if ($routeName === 'research_head.calendar') {
        $response->assertSeeHtml('href="'.route('research-calls.index').'"');
    }
    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-navigation-'.str($routeName)->afterLast('.').'.html'), $response->getContent());
    }
})->with([
    'dashboard' => ['research_head.dashboard', 'Dashboard'],
    'calendar' => ['research_head.calendar', 'Calendar'],
    'analytics' => ['research_head.analytics', 'Analytics'],
]);

test('the overview mode cannot be changed through a client update', function () {
    expect(fn () => Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class, ['overview' => true])->set('overview', false))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('overview carries the selected academic year and date range to its analytics links', function () {
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'projects_target' => 4, 'publications_target' => 2, 'faculty_target' => 6,
    ]);
    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertSee('Date range applied')
        ->assertSeeHtml('data-dashboard-date-filters  open')
        ->assertSee('0 / 4')->assertSee('0 / 2')->assertSee('0 / 6')
        ->assertSeeHtml('href="'.e(route('research_head.analytics', ['academicYear' => '2026-2027', 'fromDate' => '2026-08-01', 'toDate' => '2027-07-31'])).'#annual-targets"')
        ->set('fromDate', '2026-09-01')->assertDontSee('0 / 4')
        ->assertSee('select the full academic year');
});

test('the pipeline reuses workflow statuses and review KPIs exclude external review stages', function () {
    foreach (['pending', 'resubmitted', 'expert_review', 'for_final_decision', 'revision_requested', 'gad_review', 'lrec_queued', 'lrec_review', 'ready_for_signature', 'approved', 'rejected'] as $status) {
        ($this->topic)(['status' => $status]);
    }
    ($this->project)();
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['review'])->toBe(4)->and($data['kpis']['active'])->toBe(1)
        ->and($data['pipeline']->pluck('count', 'key')->all())->toBe([
            'submitted' => 1, 'head_review' => 3, 'revision_requested' => 1, 'gad_review' => 1, 'lrec' => 2, 'signing' => 2, 'approved' => 1,
        ]);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->call('setPipeline', 'lrec')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 2)
        ->call('setPipeline', 'signing')->assertViewHas('topics', fn ($topics): bool => $topics->total() === 2);
});

test('monthly trends count one new proposal separately from each revision and preserve first-submission cohorts', function () {
    $topic = ($this->topic)(['status' => 'resubmitted']);
    ($this->version)($topic, 1, 'initial', '2026-08-05');
    ($this->version)($topic, 2, 'revision', '2026-09-05');
    ($this->version)($topic, 3, 'revision', '2026-09-20');
    $data = app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-10-31');
    expect($data['trend'])->toHaveCount(3)->and($data['trend']->sum('new'))->toBe(1)
        ->and($data['trend']->sum('revision'))->toBe(2)->and($data['trend']->last()['new'])->toBe(0);
    expect(app(ResearchHeadAnalytics::class)->topics('', '2026-09-01', '2026-09-30')->count())->toBe(0);
    expect(app(ResearchHeadAnalytics::class)->summarize('', '2026-09-01', '2026-09-30')['trend']->sum('revision'))->toBe(2);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('submissionMonth', '2026-09')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1);
});

test('project analytics derive delay and missing reports from recorded schedules without treating completion percentage as completion', function () {
    $ended = ($this->project)(['notice_to_proceed_data' => ['approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-06-30'], 'notice_to_proceed_issued_at' => '2026-01-01']);
    $awaiting = ($this->project)();
    ($this->report)($awaiting, ['progress_percentage' => 100, 'review_status' => 'pending']);
    ($this->project)(['project_status' => 'completed']);
    ($this->project)(['estimated_duration_months' => null]);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['active'])->toBe(3)->and($data['kpis']['completed'])->toBe(1)->and($data['kpis']['delayed'])->toBe(1)
        ->and($data['projectStatuses']->pluck('count', 'key')->all())->toBe(['ongoing' => 1, 'delayed' => 1, 'awaiting' => 1, 'completed' => 1])
        ->and($data['unknownSchedules'])->toBe(1);
    expect($data['attention']->where('id', $ended->id)->pluck('type')->all())->toContain('delayed', 'quarterly', 'terminal');
    expect($data['attention']->where('id', $awaiting->id)->pluck('type')->all())->toContain('completion', 'report_review');
});

test('reported approved-plan milestones can flag delay before the overall project end', function () {
    $project = ($this->project)();
    ($this->report)($project, ['work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 25]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(1);
    ($this->report)($project, ['reporting_date' => '2026-09-20', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 100]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('report replacements remove missing-report alerts and only the latest terminal report is actionable', function () {
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 3]);
    ($this->report)($project, ['reporting_date' => '2026-03-31']);
    ProjectNarrativeReport::create(['topic_id' => $project->id, 'submitted_by' => $this->faculty->id,
        'report_type' => 'terminal', 'submission_date' => '2026-04-01', 'review_status' => 'reviewed',
        'researchers' => $this->faculty->name, 'implementation_start' => '2026-01-01', 'implementation_end' => '2026-03-31',
        'budget' => 100000, 'funding_agency' => 'Institution', 'accomplishment_summary' => 'Complete',
        'introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives', 'methodology' => 'Methodology', 'results_discussion' => 'Results', 'photos' => []]);
    $types = app(ResearchHeadAnalytics::class)->summarize()['attention']->pluck('type');
    expect($types)->not->toContain('quarterly', 'terminal', 'terminal_review');
});

test('budget utilization uses only usable latest submitted snapshots and does not treat missing reports as zero spend', function () {
    $reported = ($this->project)(['project_status' => 'completed']);
    ($this->report)($reported, ['budget_utilization' => [['actual_amount' => 10000]]]);
    ($this->report)($reported, ['reporting_date' => '2026-09-20', 'budget_utilization' => [['actual_amount' => 30000]]]);
    ($this->report)($reported, ['reporting_date' => '2026-09-25', 'submission_status' => 'prepared', 'budget_utilization' => [['actual_amount' => 99999]]]);
    ($this->project)();
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['budget']['percentage'])->toBe(30.0)->and($data['budget']['utilized'])->toBe(30000.0)
        ->and($data['budget']['budget'])->toBe(100000.0)->and($data['budget']['reported'])->toBe(1)->and($data['budget']['total'])->toBe(2)
        ->and($data['attention']->pluck('type'))->toContain('budget');
});

test('faculty participation deduplicates accepted faculty and excludes invitations and secretaries', function () {
    $member = User::factory()->create();
    $member->assignRole('faculty');
    $invited = User::factory()->create();
    $invited->assignRole('faculty');
    $secretary = User::factory()->create();
    $secretary->assignRole('faculty');
    foreach (range(1, 2) as $index) {
        $project = ($this->project)();
        $project->collaborators()->create(['user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'accepted_at' => now()]);
        $project->collaborators()->create(['user_id' => $invited->id, 'name' => $invited->name, 'email' => $invited->email]);
        $project->collaborators()->create(['user_id' => $secretary->id, 'name' => $secretary->name, 'email' => $secretary->email, 'accepted_at' => now(), 'project_role' => 'secretary']);
    }
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['faculty'])->toBe(2);
});

test('academic year targets are persisted securely with nullable targets and exact dates', function () {
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->set('targetForm', ['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31', 'projects_target' => 10, 'publications_target' => '', 'faculty_target' => 0])
        ->call('saveTargets')->assertHasNoErrors()->assertSet('academicYear', '2026-2027')
        ->assertSet('fromDate', '2026-08-01')->assertSee('Academic-year targets saved.');
    $target = ResearchAnnualTarget::sole();
    expect($target->publications_target)->toBeNull()->and($target->faculty_target)->toBe(0)->and($target->updated_by)->toBe($this->head->id);
    Livewire::actingAs($this->faculty)->test(ResearchHeadDashboard::class)->assertForbidden();
    $office = User::factory()->create();
    $office->assignRole('research_coordinator');
    $this->actingAs($office)->get(route('research_head.dashboard'))->assertForbidden();
});

test('year targets count dated published outputs rather than acceptances or undated publications', function () {
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    $project = ($this->project)();
    foreach ([['published', '2026-09-01', 'One output'], ['published', '2026-09-02', ' One  output '], ['accepted', null, 'Accepted only'], ['published', null, 'Undated']] as [$status, $date, $title]) {
        ProjectJournalSubmission::factory()->create(['topic_id' => $project->id, 'added_by' => $this->faculty->id, 'manuscript_title' => $title, 'status' => $status, 'published_on' => $date]);
    }
    $data = app(ResearchHeadAnalytics::class)->summarize('2026-2027');
    expect($data['targets']->pluck('actual')->all())->toBe([1, 1, 1])->and($data['undatedPublications'])->toBe(1)->and($data['targetMatches'])->toBeTrue();
    expect(app(ResearchHeadAnalytics::class)->summarize('2026-2027', '2026-09-01', '2026-09-30')['targetMatches'])->toBeFalse();
});

test('unknown year dates and empty datasets have honest no-data states', function () {
    $data = app(ResearchHeadAnalytics::class)->summarize('2026-2027');
    expect($data['periodAvailable'])->toBeFalse()->and($data['trend'])->toBeEmpty()->and($data['targets']->pluck('actual')->all())->toBe([null, null, null])
        ->and($data['budget']['percentage'])->toBeNull();
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->assertSee('No data yet')
        ->set('academicYear', '2026-2027')->assertSee('Set this academic year');
});

test('annual targets are prominent and editable without crowding the dashboard', function () {
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'projects_target' => 4, 'publications_target' => 2, 'faculty_target' => 4,
    ]);
    ($this->project)();

    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class)
        ->assertSeeHtml('data-analytics-layout="workbench"')
        ->assertSeeInOrder(['Monthly submission trend', 'Proposal pipeline', 'Annual research targets'])
        ->assertSee('of 4 target')->assertSee('25% achieved')->assertSee('3 to go')
        ->assertSee('Edit annual targets')->assertDontSeeHtml('id="target-projects_target"')
        ->assertSeeHtml('<details data-analytics-methodology')
        ->assertDontSeeHtml('<details data-analytics-methodology open')
        ->assertDontSeeHtml('<section id="research-calendar"')
        ->call('editTargets')->assertSeeHtml('id="target-projects_target"')
        ->set('targetForm.projects_target', 5)->call('saveTargets')->assertHasNoErrors()
        ->assertSee('of 5 target')->assertSee('20% achieved')
        ->call('editTargets')->assertDontSeeHtml('id="target-projects_target"');

    expect(ResearchAnnualTarget::sole()->projects_target)->toBe(5);
});

test('analytics presents readable project titles and accessible icon actions', function () {
    $title = 'Community-based monitoring of coastal habitats and long-term environmental recovery';
    ($this->project)(['title' => $title]);
    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->assertSee('Apply filters')->assertSee($title)->assertSee('Open monitoring')
        ->assertSeeHtml('rh-analytics-kpis')->assertSeeHtml('rh-analytics-review');

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-dashboard-kpi-band]/*[self::a or self::button]'))->toHaveCount(6)
        ->and($xpath->query('//form[@*[name()="wire:submit"]="applyFilters"]/button[1]/svg[@aria-hidden="true"]'))->toHaveCount(1)
        ->and($xpath->query('//button[@*[name()="wire:click"]="resetAnalyticsFilters"]/svg'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="active-projects"]//strong[@title="'.$title.'" and contains(@class,"line-clamp-2")]'))->toHaveCount(1)
        ->and($xpath->query('//details[@data-analytics-methodology]/summary/svg'))->toHaveCount(1);

});

test('annual achievement retains actual overachievement while clamping the visual bar', function () {
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'projects_target' => 2, 'publications_target' => null, 'faculty_target' => null,
    ]);
    foreach (range(1, 3) as $index) {
        ($this->project)();
    }

    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class)
        ->assertSee('of 2 target')->assertSee('150% achieved')->assertSee('Target met')
        ->assertSeeHtml('aria-valuenow="100" aria-valuetext="3 achieved against a target of 2"')
        ->set('fromDate', '2026-09-01')->assertDontSee('of 2 target')
        ->assertSee('Annual target hidden for this date range');
});

test('readable monthly charts expose a count axis and retain clickable revision drill downs', function () {
    $topic = ($this->topic)(['status' => 'resubmitted']);
    ($this->version)($topic, 1, 'initial', '2026-08-05');
    ($this->version)($topic, 2, 'revision', '2026-09-05');

    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->assertSeeHtml('data-submission-chart')
        ->assertSeeHtml('aria-label="Proposal count axis"')
        ->assertSeeHtml('class="rh-title">Monthly submission trend')
        ->set('submissionMonth', '2026-09')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1)
        ->assertSeeHtml('aria-pressed="true"')
        ->assertSee('1 revisions');
});

test('invalid dates and target values are rejected without writes', function () {
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('fromDate', 'invalid')->assertHasErrors('fromDate');
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('targetForm', [
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2026-07-31',
        'projects_target' => -1, 'publications_target' => '', 'faculty_target' => '',
    ])->call('saveTargets')->assertHasErrors(['targetForm.ends_on', 'targetForm.projects_target']);
    expect(ResearchAnnualTarget::count())->toBe(0);
});

test('analytics keeps its independent queues and exposes details outside the overview', function () {
    ResearchAnnualTarget::factory()->create([
        'academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31',
        'projects_target' => 12, 'publications_target' => 8, 'faculty_target' => 20,
    ]);
    foreach (range(1, 11) as $index) {
        $topic = ($this->topic)(['title' => 'Research queue item '.$index]);
        ($this->version)($topic, 1, 'initial', '2026-08-05');
        if ($index <= 4) {
            ($this->version)($topic, 2, 'revision', '2026-09-05');
        }
    }
    foreach (range(1, 7) as $index) {
        ($this->project)(['title' => 'Active project '.$index]);
    }

    $component = Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class)
        ->assertSeeInOrder(['data-dashboard-kpi-band', 'id="annual-targets"', 'data-dashboard-compact-queues'], false)
        ->assertDontSee('Coverage:')
        ->assertDontSeeHtml('id="research-calendar"')
        ->assertSeeHtml('data-dashboard-queue="attention"')
        ->assertSeeHtml('data-dashboard-queue="proposals"')
        ->assertDontSeeHtml('min-w-[720px]')
        ->assertDontSeeHtml('sticky top-[128px]')
        ->assertViewHas('topics', fn ($items): bool => $items->total() === 11 && $items->perPage() === 5 && $items->count() === 5)
        ->assertViewHas('attentionItems', fn ($items): bool => $items->total() === 11 && $items->perPage() === 5 && $items->count() === 5)
        ->assertViewHas('projectItems', fn ($items): bool => $items->total() === 7 && $items->perPage() === 6 && $items->count() === 6);

    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-dashboard-workbench.html'), $component->html());
    }

    $component->call('nextPage', 'attentionPage')
        ->assertViewHas('attentionItems', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 5)
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 1)
        ->call('nextPage', 'attentionPage')
        ->assertViewHas('attentionItems', fn ($items): bool => $items->currentPage() === 3 && $items->count() === 1)
        ->call('nextPage')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 5)
        ->call('nextPage')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 3 && $items->count() === 1)
        ->call('nextPage', 'projectPage')
        ->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 2 && $items->count() === 1)
        ->set('search', 'Research queue item 11')
        ->assertViewHas('topics', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 1)
        ->assertViewHas('attentionItems', fn ($items): bool => $items->currentPage() === 1)
        ->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1);
});

test('initial-screening deadlines do not incorrectly mark LREC revisions overdue', function () {
    $this->call->update(['paper_revisions_end_date' => '2026-09-01']);
    $initial = ($this->topic)(['status' => 'revision_requested', 'review_stage' => 'initial']);
    $lrec = ($this->topic)(['status' => 'revision_requested', 'review_stage' => 'lrec']);
    $items = app(ResearchHeadAnalytics::class)->summarize()['attention'];
    expect($items->firstWhere('id', $initial->id)['issue'])->toBe('Overdue faculty revision')
        ->and($items->firstWhere('id', $lrec->id)['basis'])->toBe('waiting, not an overdue calculation');
});

test('period achievements include projects whose proposal was submitted before the selected date range', function () {
    $project = ($this->project)();
    ($this->version)($project, 1, 'initial', '2026-06-01');
    ($this->version)($project, 2, 'revision', '2026-08-15');
    $data = app(ResearchHeadAnalytics::class)->summarize('', '2026-08-01', '2026-09-30');
    expect($data['kpis']['active'])->toBe(0)->and($data['targets']->first()['actual'])->toBe(1)
        ->and($data['targets']->last()['actual'])->toBe(1)->and($data['trend']->sum('revision'))->toBe(1);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->set('fromDate', '2026-08-01')->set('toDate', '2026-09-30')
        ->set('submissionMonth', '2026-08')->assertViewHas('topics', fn ($topics): bool => $topics->total() === 1)
        ->assertSee(route('topics.show', $project).'#project-monitoring', false);
});

test('analytics pagination and derived project drill-down reset properly', function () {
    foreach (range(1, 11) as $index) {
        ($this->project)(['project_status' => 'delayed']);
    }
    ($this->project)(['project_status' => 'completed']);
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)->call('showProjects', 'delayed')
        ->assertViewHas('projectItems', fn ($items): bool => $items->total() === 11 && $items->count() === 6)
        ->call('setPage', 2, 'projectPage')->assertViewHas('projectItems', fn ($items): bool => $items->count() === 5)
        ->call('showProjects', 'completed')->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 1)
        ->call('resetAnalyticsFilters')->assertViewHas('projectItems', fn ($items): bool => $items->currentPage() === 1 && $items->total() === 12);
});

test('an old quarter revision cannot overwrite a newer milestone completion observation', function () {
    $project = ($this->project)();
    ($this->report)($project, ['reporting_date' => '2026-09-15', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 100]]]);
    ($this->report)($project, ['reporting_date' => '2026-08-31', 'version_number' => 2, 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 100, 'accomplished_percentage' => 25]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('saved academic-year dates and target editor survive dashboard reloads', function () {
    ResearchAnnualTarget::factory()->create(['academic_year' => '2026-2027', 'starts_on' => '2026-08-01', 'ends_on' => '2027-07-31']);
    Livewire::actingAs($this->head)->withQueryParams(['academicYear' => '2026-2027'])->test(ResearchHeadDashboard::class)
        ->assertSet('fromDate', '2026-08-01')->assertSet('toDate', '2027-07-31')
        ->call('editTargets')->assertSet('editingTargets', true)->assertSet('targetForm.projects_target', 10)
        ->call('editTargets')->assertSet('editingTargets', false);
});

test('a project reported at 100 percent awaits completion review rather than being marked delayed implementation', function () {
    $project = ($this->project)(['notice_to_proceed_issued_at' => '2026-01-01', 'estimated_duration_months' => 3]);
    ($this->report)($project, ['reporting_date' => '2026-03-31', 'progress_percentage' => 100]);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['kpis']['delayed'])->toBe(0)->and($data['kpis']['completed'])->toBe(0)
        ->and($data['projectStatuses']->firstWhere('key', 'awaiting')['count'])->toBe(1);
});

test('a Research Head account using the faculty workspace cannot access analytics actions', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    Livewire::test(ResearchHeadDashboard::class)->assertForbidden();
});

test('milestone completion uses its assigned project weight and unknown weights are not guessed', function () {
    $project = ($this->project)();
    ($this->report)($project, ['work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 20, 'accomplished_percentage' => 20]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
    ($this->report)($project, ['reporting_date' => '2026-09-15', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'percent_weight' => 20, 'accomplished_percentage' => 10]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(1);
    ($this->report)($project, ['reporting_date' => '2026-09-20', 'work_plan' => [['source_work_plan_index' => 0, 'target_completion_date' => '2026-09-01', 'accomplished_percentage' => 10]]]);
    expect(app(ResearchHeadAnalytics::class)->summarize()['kpis']['delayed'])->toBe(0);
});

test('manually delayed projects and completed projects without terminal reports do not invent waiting dates', function () {
    $delayed = ($this->project)(['project_status' => 'delayed', 'status_started_at' => '2026-08-01']);
    $completed = ($this->project)(['project_status' => 'completed']);
    $data = app(ResearchHeadAnalytics::class)->summarize();
    expect($data['attention']->where('id', $delayed->id)->firstWhere('type', 'delayed')['days'])->toBeNull()
        ->and($data['attention']->where('id', $completed->id)->firstWhere('type', 'terminal')['days'])->toBeNull()
        ->and($data['projectStatuses']->firstWhere('key', 'completed')['count'])->toBe(1);
});

test('analytics charts distinguish actionable filters from static summaries', function () {
    ($this->project)(['title' => 'Ongoing example']);
    ($this->project)(['title' => 'Completed example', 'project_status' => 'completed']);

    $component = Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->assertDontSeeHtml('data-dashboard-section-navigation')
        ->assertSeeInOrder(['Monthly submission trend', 'Proposal pipeline', 'Annual research targets'])
        ->assertSee('View faculty directory')->assertSee('View budget breakdown')
        ->assertSeeHtml('data-project-distribution-chart')
        ->assertSeeHtml('style="width: 50%"')
        ->call('showProjects', 'completed')
        ->assertSee('Selected filter')
        ->assertViewHas('projectItems', fn ($items): bool => $items->total() === 1 && $items->first()['title'] === 'Completed example');

    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    $facultyLink = $xpath->query('//*[@data-dashboard-kpi-band]//a[@href="'.route('research_head.faculty-directory.index').'"]')->item(0);
    $selected = $xpath->query('//*[@data-project-distribution-chart]//button[@aria-pressed="true"]');

    expect($facultyLink)->not->toBeNull()
        ->and($facultyLink->hasAttribute('wire:click'))->toBeFalse()
        ->and($selected)->toHaveCount(1)
        ->and($selected->item(0)->getAttribute('aria-label'))->toContain('Completed', '1 of 2')
        ->and($xpath->query('//*[@data-project-distribution-chart]//button'))->toHaveCount(4);
});

test('empty project charts show zero shares with an explicit empty state', function () {
    Livewire::actingAs($this->head)->test(ResearchHeadDashboard::class)
        ->assertSee('No issued projects in this selection.')
        ->assertSeeHtml('data-project-distribution-chart')
        ->assertSeeHtml('style="width: 0%"')
        ->assertDontSee('NaN')->assertDontSee('INF');
});
