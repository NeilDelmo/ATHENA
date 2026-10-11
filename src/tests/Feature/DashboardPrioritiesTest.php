<?php

use App\Livewire\ResearchHeadDashboard;
use App\Models\ProjectProgressReport;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(Carbon::parse('2026-10-07 12:00:00', 'Asia/Manila'));
});

test('dashboard deadlines distinguish approaching dates and overdue times in Philippine time', function (string $date, string $label, string $absolute) {
    $html = Blade::render('<x-dashboard-deadline :date="$date" />', ['date' => Carbon::parse($date)]);

    expect($html)->toContain($label, $absolute, '<time', 'datetime=');
})->with([
    ['2026-10-10 23:59:59+08:00', 'Due in 3 days', 'Oct 10, 2026'],
    ['2026-10-08 23:59:59+08:00', 'Due tomorrow', 'Oct 8, 2026'],
    ['2026-10-07 23:59:59+08:00', 'Due today', 'Oct 7, 2026'],
    ['2026-10-07 10:00:00+08:00', 'Overdue today', 'Oct 7, 2026'],
    ['2026-10-02 23:59:59+08:00', '5 days overdue', 'Oct 2, 2026'],
    ['2026-10-07 18:00:00+00:00', 'Due tomorrow', 'Oct 8, 2026'],
]);

test('office and secretary dashboards show accessible notices oldest first with the next release action', function (string $role, string $workspace, string $route, string $fixture) {
    Role::firstOrCreate(['name' => $role]);
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole($role);
    $owner = User::factory()->create(['college' => 'CICS']);
    $outsideOwner = User::factory()->create(['college' => 'CTE']);
    $older = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Older cleared laboratory research project',
        'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'notice_to_proceed_data' => ['resolution_number' => '01']]);
    $older->forceFill(['status_started_at' => now()->subDays(5)])->saveQuietly();
    TopicProposal::create(['user_id' => $owner->id, 'title' => 'Newer cleared project', 'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
    TopicProposal::create(['user_id' => $outsideOwner->id, 'title' => 'Private other college project', 'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);

    $response = $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route($route))->assertOk()
        ->assertSeeInOrder(['Awaiting release', 'Notices to Proceed', $older->title, 'Upload signed documents', 'Newer cleared project', 'Prepare Notice to Proceed'])
        ->assertSee('5 days waiting')->assertDontSee('Private other college project')
        ->assertSee('href="#notice-release-queue"', false);

    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/dashboard-'.$fixture.'.html'), $response->getContent());
    }
})->with([
    ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, 'research_coordinator.dashboard', 'office'],
    ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, 'research_secretary.dashboard', 'secretary'],
]);

test('office review queue distinguishes actionable LREC reviews from proposals waiting on others', function () {
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole('research_coordinator');
    $owner = User::factory()->create(['college' => 'CICS']);
    $review = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Committee comments required', 'status' => TopicProposal::STATUS_LREC_REVIEW, 'review_stage' => 'lrec']);
    $review->forceFill(['status_started_at' => now()->subDays(4)])->saveQuietly();
    TopicProposal::create(['user_id' => $owner->id, 'title' => 'Faculty correction pending', 'status' => 'revision_requested', 'review_stage' => 'lrec']);

    $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE])
        ->get(route('research_coordinator.dashboard'))->assertOk()
        ->assertSeeInOrder(['LREC proposals', 'Committee comments required', '4 days in this stage', 'Record comments', 'Faculty correction pending', 'View review status'])
        ->assertSee('href="#office-lrec-proposals"', false);
});

test('secretary budget queue shows incomplete budgets before the assigned portfolio and excludes confirmed reports', function () {
    Role::firstOrCreate(['name' => 'research_secretary']);
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole('research_secretary');
    $owner = User::factory()->create(['college' => 'CICS']);
    $project = TopicProposal::create(['user_id' => $owner->id, 'research_secretary_id' => $operator->id,
        'title' => 'Laboratory project awaiting financial entry', 'status' => 'approved', 'notice_to_proceed_issued_at' => now()]);
    $pending = ProjectProgressReport::create(['topic_id' => $project->id, 'submitted_by' => $owner->id,
        'submission_status' => 'prepared', 'reporting_date' => '2026-09-30', 'progress_percentage' => 25,
        'accomplishments' => 'Quarter completed', 'prepared_at' => now()->subDays(3)]);
    ProjectProgressReport::create(['topic_id' => $project->id, 'submitted_by' => $owner->id,
        'submission_status' => 'prepared', 'reporting_date' => '2026-06-30', 'progress_percentage' => 15,
        'accomplishments' => 'Quarter completed', 'budget_prepared_by' => $operator->id, 'budget_prepared_at' => now()]);

    $response = $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_SECRETARY])
        ->get(route('research_secretary.dashboard'))->assertOk()
        ->assertSeeInOrder(['Awaiting budget', 'Budgets to complete', $project->title, '3 days waiting', 'Complete budget', 'Assigned projects']);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $budgetQueue = $xpath->query('//*[@id="budget-worklist"]')->item(0);
    expect($budgetQueue)->not->toBeNull()
        ->and($xpath->query('.//a', $budgetQueue))->toHaveCount(1)
        ->and($xpath->query('.//a', $budgetQueue)->item(0)->getAttribute('href'))->toBe(route('project-budget.edit', [$project, $pending]))
        ->and($xpath->query('//details[@id="assigned-projects" and not(@open)]'))->toHaveCount(1);

    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::put(storage_path('framework/testing/dashboard-secretary.html'), $response->getContent());
    }
});

test('research head overview keeps reporting behind analytics links and exposes proposal review actions', function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    TopicProposal::create(['user_id' => $faculty->id, 'title' => 'New proposal requiring review', 'status' => 'pending']);
    TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Revised proposal requiring review', 'status' => 'resubmitted']);

    Livewire::actingAs($head)->test(ResearchHeadDashboard::class, ['overview' => true])
        ->assertSee('Review proposal')->assertSee('Review revision')
        ->assertSee('Review stage')->assertSee('View analytics')
        ->assertSeeHtml('wire:model.live="pipeline"')
        ->assertDontSeeHtml('data-dashboard-activity-chart')
        ->assertDontSeeHtml('data-dashboard-pipeline-chart')
        ->assertDontSee('Submission activity')
        ->call('setPipeline', 'lrec')->assertSet('pipeline', 'lrec');
});

test('faculty revisions prioritize recorded deadlines and omit inapplicable LREC deadlines', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    foreach ([
        ['Later faculty revision', '2026-10-10', 'initial', '2026-10-06'],
        ['Overdue faculty revision', '2026-10-02', 'initial', '2026-10-01'],
        ['LREC revision without a deadline', '2026-10-01', 'lrec', '2026-10-05'],
    ] as [$title, $deadline, $stage, $created]) {
        $call = ResearchCall::create(['title' => $title.' call', 'academic_year' => '2026-2027', 'status' => 'open', 'opens_at' => '2026-09-01',
            'closes_at' => '2026-10-31', 'paper_revisions_end_date' => $deadline]);
        TopicProposal::create(['user_id' => $faculty->id, 'research_call_id' => $call->id,
            'title' => $title, 'status' => 'revision_requested', 'review_stage' => $stage, 'created_at' => $created]);
    }
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('faculty.dashboard'))->assertOk()
        ->assertSeeInOrder(['Revisions to address', 'Overdue faculty revision', '5 days overdue', 'Later faculty revision', 'Due in 3 days', 'LREC revision without a deadline'])
        ->assertDontSee('6 days overdue');
});

test('release queues do not present proposal creation as an unrecorded clearance date', function (string $role, string $workspace, string $route) {
    Role::firstOrCreate(['name' => $role]);
    $operator = User::factory()->create(['college' => 'CICS']);
    $operator->assignRole($role);
    $owner = User::factory()->create(['college' => 'CICS']);
    $topic = TopicProposal::create(['user_id' => $owner->id, 'title' => 'Legacy cleared project',
        'status' => TopicProposal::STATUS_READY_FOR_SIGNATURE, 'created_at' => now()->subDays(5)]);
    $topic->forceFill(['status_started_at' => null])->saveQuietly();

    $this->actingAs($operator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route($route))->assertOk()->assertSee('Clearance date unavailable')->assertDontSee('5 days waiting');
})->with([
    ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, 'research_coordinator.dashboard'],
    ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, 'research_secretary.dashboard'],
]);
