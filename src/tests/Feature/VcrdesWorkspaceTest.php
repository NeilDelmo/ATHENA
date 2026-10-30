<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['vcrdes', 'research_head', 'faculty'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->executive = User::factory()->create(['name' => 'VCRDES Officer', 'college' => 'CAS']);
    $this->executive->assignRole('vcrdes');
    $this->actingAs($this->executive)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'vcrdes']);
});

test('vcrdes opens its own monitoring workspace and can switch back from other assigned roles', function () {
    expect($this->executive->availableWorkspaceKeys())->toBe(['vcrdes']);
    $this->get(route('dashboard'))->assertRedirect('/vcrdes/dashboard');
    $this->executive->assignRole('research_head');
    $this->get(route('workspace.select'))->assertOk()->assertSee('VCRDES');
    $this->postJson(route('workspace.store'), ['workspace' => 'vcrdes'])
        ->assertOk()->assertJsonPath('redirect', url('/vcrdes/dashboard'));
});

test('vcrdes monitors issued projects across colleges using only forwarded progress and spending', function () {
    $owner = User::factory()->create(['college' => 'CICS']);
    $project = TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Coastal sensor network', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_data' => ['approved_budget' => '60000.00'],
    ]);
    foreach ([['2026-06-30', 'head', 42, 12000], ['2026-09-30', 'secretary', 85, 23000]] as [$date, $stage, $progress, $amount]) {
        ProjectProgressReport::create([
            'topic_id' => $project->id, 'submitted_by' => $owner->id, 'submission_status' => 'submitted',
            'submitted_at' => now(), 'reporting_date' => $date, 'review_stage' => $stage,
            'progress_percentage' => $progress, 'accomplishments' => 'Fieldwork recorded',
            'budget_utilization' => [['actual_amount' => $amount]],
        ]);
    }
    TopicProposal::create(['user_id' => $owner->id, 'title' => 'Unissued confidential proposal']);

    $response = $this->get('/vcrdes/dashboard')->assertOk()
        ->assertViewHas('budgetPercentage', fn ($value): bool => $value === 20.0)
        ->assertSee('Coastal sensor network')->assertSee('42%')->assertSee('60,000.00')->assertSee('12,000.00')
        ->assertDontSee('23,000.00')->assertDontSee('Unissued confidential proposal')
        ->assertSeeHtml('data-vcrdes-navigation')->assertSeeHtml('data-vcrdes-dashboard');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//nav[@data-vcrdes-navigation]//a');
    expect($links->length)->toBe(5);
    foreach ($links as $link) {
        expect($link->getAttribute('aria-label'))->not->toBeEmpty()
            ->and($link->getAttribute('title'))->not->toBeEmpty()
            ->and(trim($link->textContent))->toBeEmpty()
            ->and($xpath->query('.//*[local-name()="svg"]', $link)->length)->toBe(1);
    }
    $this->get('/vcrdes/dashboard?college=CAS')->assertOk()->assertDontSeeText($project->title);
    $this->get('/vcrdes/dashboard?college=CICS')->assertOk()->assertSeeText($project->title)
        ->assertViewHas('colleges', fn ($colleges): bool => $colleges['CICS']['total'] === 1);
    $this->get('/vcrdes/dashboard?q=Coastal')->assertOk()->assertSeeText($project->title);
    $this->get('/vcrdes/dashboard?status=completed')->assertOk()
        ->assertViewHas('projects', fn ($projects): bool => $projects->isEmpty());
});

test('unknown budgets and spending remain unavailable rather than zero', function () {
    TopicProposal::create([
        'user_id' => $this->executive->id, 'title' => 'Project awaiting budget records', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
    ]);
    $this->get('/vcrdes/dashboard')->assertOk()->assertViewHas('budgetPercentage', fn ($value): bool => $value === null)
        ->assertSee('No budgets recorded')->assertSee('Not reported');
});

test('partial budget coverage does not produce a misleading utilization percentage', function () {
    foreach ([60000, null] as $budget) {
        $project = TopicProposal::create([
            'user_id' => $this->executive->id, 'title' => 'Research budget coverage', 'status' => 'approved',
            'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(), 'estimated_budget' => $budget,
        ]);
        ProjectProgressReport::create([
            'topic_id' => $project->id, 'submitted_by' => $this->executive->id,
            'submission_status' => 'submitted', 'reporting_date' => now(), 'review_stage' => 'head',
            'progress_percentage' => 20, 'accomplishments' => 'Fieldwork', 'budget_utilization' => [['actual_amount' => 12000]],
        ]);
    }
    $this->get('/vcrdes/dashboard')->assertOk()->assertSee('Known approved / estimated')
        ->assertSee('1 of 2 budgets recorded')->assertViewHas('budgetPercentage', fn ($value): bool => $value === null);
});

test('monitoring navigation can return to dashboard sections from the profile page', function () {
    $this->get(route('profile.edit'))->assertOk()
        ->assertSee('href="'.url('/vcrdes/dashboard').'#projects"', false);
});

test('monitoring filters reject malformed values and empty portfolios give a useful state', function () {
    $this->get('/vcrdes/dashboard')->assertOk()->assertSee('No issued projects yet');
    foreach (['q[]=invalid', 'college=invalid', 'status=invalid'] as $query) {
        $this->getJson('/vcrdes/dashboard?'.$query)->assertUnprocessable();
    }
});

test('vcrdes access does not grant research head editing and other roles cannot enter it', function () {
    $this->get('/research-head/dashboard')->assertForbidden();
    foreach (['research_head', 'faculty'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $role])
            ->get('/vcrdes/dashboard')->assertForbidden();
        $this->postJson(route('workspace.store'), ['workspace' => 'vcrdes'])->assertUnprocessable();
    }
    auth()->logout();
    $this->get('/vcrdes/dashboard')->assertRedirect(route('login'));
});
