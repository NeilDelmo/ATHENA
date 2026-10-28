<?php

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(9, 0));
    Role::firstOrCreate(['name' => 'research_secretary']);
    $this->secretary = User::factory()->create(['name' => 'Andrea Reyes', 'college' => 'CICS']);
    $this->secretary->assignRole('research_secretary');
    $this->owner = User::factory()->create(['name' => 'Gabriel Santos', 'college' => 'CICS']);
    $this->project = TopicProposal::create([
        'user_id' => $this->owner->id, 'research_secretary_id' => $this->secretary->id,
        'title' => 'Community health information systems', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01', 'estimated_budget' => 150000,
    ]);
    $this->project->collaborators()->create(['user_id' => $this->secretary->id, 'name' => $this->secretary->name,
        'email' => $this->secretary->email, 'accepted_at' => now(), 'project_role' => 'secretary']);
    $this->budget = ProjectProgressReport::create(['topic_id' => $this->project->id, 'submitted_by' => $this->owner->id,
        'submission_status' => 'prepared', 'reporting_date' => '2026-09-30', 'progress_percentage' => 25,
        'accomplishments' => 'Fieldwork completed', 'prepared_at' => now()->subDays(3)]);
    $cleared = TopicProposal::create(['user_id' => $this->owner->id, 'title' => 'Coastal resource monitoring',
        'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
    $cleared->forceFill(['status_started_at' => now()->subDays(4)])->saveQuietly();
    foreach ([1, 2, 3] as $quarter) {
        ProjectNarrativeReport::create(['topic_id' => $this->project->id, 'submitted_by' => $this->owner->id,
            'report_type' => 'progress', 'reporting_date' => '2026-'.str_pad((string) ($quarter * 3), 2, '0', STR_PAD_LEFT).'-28',
            'reporting_quarter' => $quarter, 'period_start' => '2026-01-01', 'period_end' => '2026-09-30',
            'submission_status' => 'submitted', 'submitted_at' => now()->subDays(4 - $quarter),
            'review_stage' => 'secretary', 'review_status' => 'pending', 'version_number' => 1,
            'submission_date' => '2026-10-27', 'researchers' => $this->owner->name,
            'implementation_start' => '2026-01-01', 'implementation_end' => '2026-12-31',
            'budget' => 150000, 'funding_agency' => 'University', 'accomplishment_summary' => 'Fieldwork completed.',
            'accomplishments' => [], 'photos' => [], 'introduction' => 'Project background.', 'objectives' => 'Assess community outcomes.',
            'methodology' => 'Field observations.', 'results_discussion' => 'Implementation is ongoing.',
            'research_head_remarks' => $quarter === 1 ? 'Verify the supporting evidence.' : null]);
    }
    $this->actingAs($this->secretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY]);
});

test('the secretary has a separate dashboard and focused work pages with working navigation', function () {
    expect(route('research_secretary.dashboard'))->toEndWith('/research-secretary/dashboard')
        ->and(route('research_secretary.projects.index'))->toEndWith('/research-secretary/projects');
    foreach (['dashboard', 'projects.index', 'reports.index', 'notices.index', 'budgets.index', 'targets.index'] as $page) {
        $response = $this->get(route('research_secretary.'.$page))->assertOk()
            ->assertSee('data-secretary-workspace', false)->assertSee('data-research-secretary-navigation', false)
            ->assertDontSee('secretary-page-index')->assertDontSee('secretary-project-monogram')
            ->assertDontSee('secretary-page-intro')
            ->assertDontSeeText([
                'Your office at a glance', 'Ready for the next step', 'Up next for review',
                'Research office monitoring', 'Start with report reviews',
                'Research office / Notices to Proceed', 'Research office / Planning',
                'Review submissions, release cleared documents, and monitor project budgets.',
            ]);
        foreach (['dashboard', 'reports.index', 'notices.index', 'budgets.index', 'targets.index'] as $destination) {
            $response->assertSee(route('research_secretary.'.$destination), false);
        }
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        expect($xpath->query('//nav[@data-research-secretary-navigation]//a[@href="'.route('research_secretary.projects.index').'"]')->length)->toBe(0);
        if (getenv('ATHENA_EXPORT_SECRETARY_WORKSPACE') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing'));
            File::put(storage_path('framework/testing/secretary-'.str_replace('.index', '', $page).'.html'), $response->getContent());
        }
    }
    $this->get(route('research_secretary.dashboard'))->assertOk()->assertSeeText('Secretary dashboard')
        ->assertSeeText('Review reports')->assertSeeText('need attention')
        ->assertDontSee(route('research_secretary.projects.index'), false)
        ->assertDontSee('data-research-target-board', false)->assertDontSee('id="assigned-projects"', false);
    $projectsResponse = $this->get(route('research_secretary.projects.index'))->assertOk()->assertSeeText('Projects')
        ->assertDontSeeText('Project record')
        ->assertDontSee('data-secretary-report-queue', false)->assertDontSee('data-notice-to-proceed-queue', false)
        ->assertDontSee('data-research-target-board', false)->assertSee($this->project->title);
    $document = new DOMDocument;
    @$document->loadHTML($projectsResponse->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-secretary-workspace="projects"]//a[@href="'.route('topics.show', $this->project).'"]')->length)->toBe(0);
    $this->get(route('research_secretary.reports.index'))->assertOk()->assertSeeText('Report reviews')
        ->assertSee('data-secretary-report-queue', false)->assertDontSee('data-notice-to-proceed-queue', false)
        ->assertSeeText('Submitted reports')->assertSeeText('Returned by Research Head')
        ->assertDontSee('One queue. A clear next step.')->assertDontSee('Submission queue');
});

test('report review filters identify the selected view using the existing tab format', function (string $type, string $label) {
    $response = $this->get(route('research_secretary.reports.index', $type ? ['type' => $type] : []))->assertOk()
        ->assertDontSee('secretary-filter-tabs');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $selected = $xpath->query('//nav[@data-report-review-filters]//a[@aria-current="page"]');
    expect($selected->length)->toBe(1)
        ->and(trim($selected->item(0)->textContent))->toBe($label)
        ->and($selected->item(0)->getAttribute('href'))->toBe(route('research_secretary.reports.index', $type ? ['type' => $type] : []));
    if (getenv('ATHENA_EXPORT_SECRETARY_WORKSPACE') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/secretary-reports-'.($type ?: 'all').'.html'), $response->getContent());
    }
})->with([
    ['', 'All pending'], ['monitoring', 'Monitoring Tools'], ['progress', 'Progress Reports'], ['returned', 'Returned by Research Head'],
]);

test('project and review filters keep college and assignment access boundaries', function () {
    $outsider = User::factory()->create(['college' => 'CTE']);
    TopicProposal::create(['user_id' => $outsider->id, 'title' => 'Private outside project', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);
    $this->get(route('research_secretary.projects.index', ['q' => 'Community']))->assertOk()->assertSee($this->project->title)->assertDontSee('Private outside project');
    $this->get(route('research_secretary.projects.index', ['q' => 'No matching project']))->assertOk()->assertSeeText('No projects match your search.')->assertDontSee($this->project->title);
    $this->get(route('research_secretary.reports.index', ['type' => 'returned']))->assertOk()
        ->assertViewHas('reportReviewTasks', fn ($reports): bool => $reports->count() === 1)->assertSeeText('Returned by Research Head');
    $this->getJson(route('research_secretary.projects.index', ['q' => ['invalid']]))->assertUnprocessable();
});

test('other workspaces cannot open any secretary work page', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach (['dashboard', 'projects.index', 'reports.index', 'notices.index', 'budgets.index', 'targets.index'] as $page) {
        $this->getJson(route('research_secretary.'.$page))->assertForbidden();
    }
});

test('the office secretary monitors project budgets across colleges without budget entry', function () {
    $outsideOwner = User::factory()->create(['college' => 'CTE']);
    $outsideProject = TopicProposal::create(['user_id' => $outsideOwner->id, 'title' => 'Engineering budget monitoring',
        'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'estimated_budget' => 90000, 'notice_to_proceed_data' => ['approved_budget' => '80000.00']]);
    ProjectProgressReport::create(['topic_id' => $outsideProject->id, 'submitted_by' => $outsideOwner->id,
        'submission_status' => 'submitted', 'reporting_date' => '2026-09-30', 'submitted_at' => now(),
        'progress_percentage' => 25, 'accomplishments' => 'Reported fieldwork',
        'budget_utilization' => [['type' => 'Cash Advance', 'actual_amount' => 12500]]]);
    foreach (['dashboard', 'projects.index', 'budgets.index'] as $page) {
        $this->get(route('research_secretary.'.$page))->assertOk()
            ->assertDontSee('Complete budget')->assertDontSee('Budgets to complete')->assertDontSee('Budget entry');
    }
    $this->get(route('research_secretary.budgets.index'))->assertOk()->assertSeeText('Budget monitoring')
        ->assertSeeText($outsideProject->title)->assertSeeText('80,000.00')->assertSeeText('12,500.00');
    $this->get(route('research_secretary.projects.index'))->assertOk()->assertSeeText($outsideProject->title);
    $this->get(route('project-budget.edit', [$this->project, $this->budget]))->assertNotFound();
    $this->put(route('project-budget.update', [$this->project, $this->budget]), [])->assertForbidden();
    $this->get(route('research_secretary.projects.budget.edit', [$this->project, $this->budget]))->assertNotFound();
    $this->put(route('research_secretary.projects.budget.update', [$this->project, $this->budget]), [])->assertForbidden();
    $projectSecretary = User::factory()->create();
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $projectSecretary->assignRole('faculty_researcher');
    $this->project->update(['research_secretary_id' => $projectSecretary->id]);
    $this->actingAs($projectSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->get(route('project-budget.edit', [$this->project, $this->budget]))->assertOk()->assertSeeText('Confirm budget utilization');
});
