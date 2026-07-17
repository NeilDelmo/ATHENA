<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('a research head can choose research head faculty researcher or faculty workspaces', function () {
    $head = User::factory()->create(['name' => 'Research Head Tester']);
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->get(route('workspace.select'))
        ->assertOk()
        ->assertSee('Choose your workspace')
        ->assertSee('Continue as Research Head')
        ->assertSee('Continue as Faculty Researcher')
        ->assertSee('Continue as Faculty')
        ->assertDontSee('Continue as Expert Evaluator');
});

test('a faculty researcher can also enter the regular faculty workspace', function () {
    $facultyResearcher = User::factory()->create();
    $facultyResearcher->assignRole('faculty_researcher');

    $this->actingAs($facultyResearcher)
        ->get(route('workspace.select'))
        ->assertOk()
        ->assertSee('Continue as Faculty Researcher')
        ->assertSee('Continue as Faculty')
        ->assertDontSee('Continue as Research Head');
});

test('single workspace accounts continue directly to their dashboard', function (string $role, string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('workspace.select'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
})->with([
    'faculty' => ['faculty', 'faculty'],
    'expert' => ['expert', 'expert'],
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

test('a research head in faculty researcher mode receives researcher access and identity', function () {
    $head = User::factory()->create(['name' => 'Workspace Tester']);
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->post(route('workspace.store'), ['workspace' => 'faculty_researcher'])
        ->assertRedirect(route('faculty.dashboard'));

    $this->get(route('research.index'))->assertOk();
    $this->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Faculty Researcher Workspace')
        ->assertDontSee('Research Proposal Workspace');
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
