<?php

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('a research head can choose research head or faculty workspaces', function () {
    $head = User::factory()->create(['name' => 'Research Head Tester']);
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->get(route('workspace.select'))
        ->assertOk()
        ->assertSee('Choose your workspace')
        ->assertSee('max-w-4xl')
        ->assertSee('grid grid-cols-1 gap-4 sm:grid-cols-2')
        ->assertDontSee('md:grid-cols-3')
        ->assertSee('Research Head')
        ->assertSee('Faculty')
        ->assertDontSee('Research Projects')
        ->assertDontSee('Expert Evaluator')
        ->assertSee('Current workspace')
        ->assertSee('Enter workspace')
        ->assertSee('Back to current workspace')
        ->assertSee(route('dashboard'), false)
        ->assertSee('data-confirm-title="Log out of ATHENA?"', false);
});

test('workspace switches open the dashboard and do not accept per-project destinations', function () {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);
    $other = User::factory()->create();
    $private = TopicProposal::create([
        'user_id' => $other->id, 'title' => 'Private research', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
    ]);
    $unreleased = TopicProposal::create(['user_id' => $user->id, 'title' => 'Unreleased proposal', 'status' => 'pending']);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach ([$private, $unreleased] as $topic) {
        $this->post(route('workspace.store'), ['workspace' => 'faculty_researcher', 'research_topic_id' => $topic->id])
            ->assertRedirect(route('faculty.dashboard'))->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);
    }
    $this->get(route('research.show', $private))->assertForbidden();
    $this->post(route('workspace.store'), ['workspace' => 'faculty', 'research_topic_id' => $private->id])
        ->assertRedirect(route('faculty.dashboard'))->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);
});

test('three workspaces use clear task names without shrinking the two-workspace layout', function () {
    $user = User::factory()->create();
    $user->assignRole(['research_head', 'faculty_researcher']);

    $this->actingAs($user)
        ->get(route('workspace.select'))
        ->assertOk()
        ->assertSee('max-w-5xl')
        ->assertSee('grid grid-cols-1 gap-4 md:grid-cols-3')
        ->assertSee('Research Head')
        ->assertSee('Research Projects')
        ->assertSee('Faculty')
        ->assertSee('Prepare proposals, submit research papers, and respond to review feedback.')
        ->assertDontSee('Continue as')
        ->assertDontSee('Faculty Researcher')
        ->assertDontSee('Your assigned system roles are unchanged.');
});

test('a faculty researcher can also enter the regular faculty workspace', function () {
    $facultyResearcher = User::factory()->create();
    $facultyResearcher->assignRole('faculty_researcher');

    $this->actingAs($facultyResearcher)
        ->get(route('workspace.select'))
        ->assertOk()
        ->assertSee('Research Projects')
        ->assertSee('Faculty')
        ->assertDontSee('Research Head');
});

test('the chooser marks only the selected workspace and keeps the existing form values', function (string $currentWorkspace) {
    $user = User::factory()->create();
    $user->assignRole(['research_head', 'faculty_researcher']);

    $response = $this->actingAs($user)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $currentWorkspace])
        ->get(route('workspace.select'))
        ->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-current-workspace-badge]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-workspace-option="'.$currentWorkspace.'"][@data-current-workspace="true"]//*[@data-current-workspace-badge]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-workspace-option="'.$currentWorkspace.'"]//button[@type="submit"]')->item(0)->textContent)->toBe('Continue in workspace')
        ->and($xpath->query('//*[@data-workspace-option]//form[@method="POST"][@action="'.route('workspace.store').'"]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-workspace-option]//input[@name="_token"]')->length)->toBe(3)
        ->and($xpath->query('//*[@data-workspace-option]//input[@name="workspace"][@value="faculty_researcher"]')->length)->toBe(1)
        ->and($user->fresh()->getRoleNames()->sort()->values()->all())->toBe(['faculty_researcher', 'research_head']);
})->with(['research_head', 'faculty', 'faculty_researcher']);

test('single workspace accounts continue directly to their dashboard', function (string $role, string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('workspace.select'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
})->with([
    'faculty' => ['faculty', 'faculty'],
]);

test('a research head can work as faculty without retaining research head route access', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->post(route('workspace.store'), ['workspace' => 'faculty'])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty');

    $this->get(route('faculty.dashboard'))->assertOk();
    $this->get(route('faculty.proposal-drafts.index'))->assertOk();
    $this->get(route('research_head.dashboard'))->assertForbidden();
});

test('switching back restores research head access and removes faculty access', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
        ->post(route('workspace.store'), ['workspace' => 'research_head'])
        ->assertRedirect(route('research_head.dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'research_head');

    $this->get(route('research_head.dashboard'))->assertOk();
    $this->get(route('faculty.dashboard'))->assertForbidden();
});

test('a research head cannot enter the faculty researcher workspace', function () {
    $head = User::factory()->create(['name' => 'Workspace Tester']);
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->post(route('workspace.store'), ['workspace' => 'faculty_researcher'])
        ->assertSessionHasErrors('workspace')
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
});

test('users cannot select a workspace their assigned roles do not permit', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->post(route('workspace.store'), ['workspace' => 'research_head'])
        ->assertSessionHasErrors('workspace')
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
});

test('the shared dashboard redirect honors the selected workspace', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
        ->get(route('dashboard'))
        ->assertRedirect(route('faculty.dashboard'));

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->get(route('dashboard'))
        ->assertRedirect(route('research_head.dashboard'));
});

test('fast workspace switches return only the authorized dashboard and save the selected workspace', function (string $role, string $workspace, string $dashboardRoute) {
    Role::firstOrCreate(['name' => $role]);
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user)->withSession(['url.intended' => 'https://example.com/untrusted']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->postJson(route('workspace.store'), [
        'workspace' => $workspace,
        'redirect' => 'https://example.com/untrusted',
    ]);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()
        ->assertExactJson(['redirect' => route($dashboardRoute)])
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace)
        ->assertSessionHas('status', 'You are now using the '.$user->activeWorkspaceLabel().' workspace.')
        ->assertSessionMissing('url.intended');

    expect(strlen($response->getContent()))->toBeLessThan(250)
        ->and($queries->contains(fn (array $query): bool => str_contains($query['query'], '`topics`') || str_contains($query['query'], 'notifications')))->toBeFalse();
})->with([
    'Faculty' => ['faculty', 'faculty', 'faculty.dashboard'],
    'Research Projects' => ['faculty_researcher', 'faculty_researcher', 'faculty.dashboard'],
    'Research Head' => ['research_head', 'research_head', 'research_head.dashboard'],
    'Research Office' => ['research_coordinator', 'research_office', 'research_coordinator.dashboard'],
    'Research Secretary' => ['research_secretary', 'research_secretary', 'research_secretary.dashboard'],
]);

test('fast switching rejects an unavailable workspace without changing the session', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
        ->postJson(route('workspace.store'), ['workspace' => 'research_head'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workspace')
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty');
});

test('fast switching keeps the legacy role in sync and enforces workspace route access', function () {
    Role::firstOrCreate(['name' => 'research_coordinator']);
    $user = User::factory()->create(['college' => 'CICS']);
    $user->assignRole(['research_coordinator', 'faculty_researcher']);

    $this->actingAs($user)->postJson(route('workspace.store'), ['workspace' => 'research_office'])
        ->assertOk()->assertSessionHas('active_role', 'research_coordinator');
    $this->get(route('faculty.dashboard'))->assertForbidden();

    $this->postJson(route('workspace.store'), ['workspace' => 'faculty_researcher'])
        ->assertOk()->assertSessionHas('active_role', 'faculty');
    $this->get(route('research_coordinator.dashboard'))->assertForbidden();
    $this->get(route('faculty.dashboard'))->assertOk();
});
