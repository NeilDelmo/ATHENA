<?php

use App\Livewire\ResearchTargetBoard;
use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['research_head', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
});

test('the daily dashboard exposes target management and review queues', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk()
        ->assertSeeLivewire(ResearchTargetBoard::class)
        ->assertSeeHtml('data-research-target-board')
        ->assertSee('Set targets');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//header//a[@href="#research-targets"]')->length)->toBe(1)
        ->and($xpath->query('//header//a[@href="'.route('research_head.rms-quarterly-reports.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-dashboard-assistant-suggestions]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-dashboard-reporting-links]//a[@href="'.route('research_head.quarterly-reports.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-dashboard-reporting-links]//a[@href="'.route('research_head.targets.index').'"]')->length)->toBe(1);
});

test('research targets have a dedicated management page within the research head workspace', function () {
    $this->get(route('research_head.targets.index'))->assertOk()->assertSeeLivewire(ResearchTargetBoard::class)
        ->assertSee('Set annual research goals')->assertSee('Back to dashboard');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('research_head.targets.index'))->assertForbidden();
});

test('dashboard shortcuts have icons and readable names without text arrows', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//header//a | //*[@data-dashboard-reporting-links]//a');

    expect($links->length)->toBe(8);
    foreach ($links as $link) {
        expect($xpath->query('.//*[local-name()="svg"]', $link)->length)->toBeGreaterThan(0)
            ->and(trim($link->textContent))->not->toBeEmpty()->not->toContain('→', '->');
    }
});

test('research head navigation groups projects separately from planning and reporting', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ([
        'Research workflow' => ['research-calls.index', 'research_head.received-submissions.index', 'research_head.proposal-submissions.index', 'research_head.report-reviews.index'],
        'Projects' => ['research_head.projects.index', 'research_head.budgets.index', 'research_head.completed-projects.index'],
        'Planning & reports' => ['research_head.targets.index', 'research_head.quarterly-reports.index', 'research_head.rms-quarterly-reports.index', 'research_head.analytics'],
        'Resources' => ['research_head.faculty-directory.index', 'research-manuals.index', 'signatories.index'],
    ] as $group => $routes) {
        foreach ($routes as $routeName) {
            expect($xpath->query('//nav[@data-research-head-navigation]/section[@aria-label="'.$group.'"]//a[@href="'.route($routeName).'"]')->length)->toBe(1);
        }
    }
});

test('research head budget monitoring shows issued budgets and only forwarded report spending', function () {
    $owner = User::factory()->create(['name' => 'Budget project leader']);
    $project = TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Coastal budget monitoring', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => '2026-01-01',
        'estimated_budget' => 50000, 'notice_to_proceed_data' => ['approved_budget' => '60000.00'],
    ]);
    foreach ([['2026-06-30', 'head', 12000], ['2026-09-30', 'secretary', 23000]] as [$date, $stage, $amount]) {
        ProjectProgressReport::create([
            'topic_id' => $project->id, 'submitted_by' => $owner->id, 'submission_status' => 'submitted',
            'submitted_at' => now(), 'reporting_date' => $date, 'review_stage' => $stage,
            'progress_percentage' => 25, 'accomplishments' => 'Reported fieldwork',
            'budget_utilization' => [['actual_amount' => $amount]],
        ]);
    }
    TopicProposal::create(['user_id' => $owner->id, 'title' => 'Unissued budget proposal', 'status' => 'pending']);

    $this->get('/research-head/budgets')->assertOk()->assertSeeText('Budget monitoring')
        ->assertSeeText($project->title)->assertSeeText('60,000.00')->assertSeeText('12,000.00')
        ->assertDontSeeText('23,000.00')->assertDontSeeText('Unissued budget proposal')
        ->assertDontSee('data-analytics-layout')->assertDontSee('data-secretary-report-queue');
    $this->get('/research-head/budgets?q=Coastal')->assertOk()->assertSeeText($project->title);
    $this->get('/research-head/budgets?q=No+match')->assertOk()->assertSeeText('No projects match your search.')
        ->assertDontSeeText($project->title);
    $this->getJson('/research-head/budgets?q[]=invalid')->assertUnprocessable();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get('/research-head/budgets')->assertForbidden();
});
