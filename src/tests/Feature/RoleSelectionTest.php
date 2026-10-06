<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->dualRoleUser = User::factory()->create(['name' => 'Dual Role Faculty']);
    $this->dualRoleUser->assignRole(['faculty', 'research_coordinator']);
});

test('dual role users are asked which workspace they want to use', function () {
    $this->actingAs($this->dualRoleUser)
        ->get(route('dashboard'))
        ->assertRedirect(route('role-selection.show'));

    $this->actingAs($this->dualRoleUser)
        ->get(route('role-selection.show'))
        ->assertOk()
        ->assertSee('Choose your workspace')
        ->assertSee('Faculty')
        ->assertSee('Research Office')
        ->assertSee('Enter workspace')
        ->assertDontSee('Continue as')
        ->assertSee('Back to current workspace')
        ->assertSee(route('dashboard'), false)
        ->assertSee('data-confirm-title="Log out of ATHENA?"', false);
});

test('the legacy chooser displays the current role using the shared workspace design', function (string $activeRole, string $workspace) {
    $response = $this->actingAs($this->dualRoleUser)
        ->withSession(['active_role' => $activeRole, User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route('role-selection.show'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-workspace-selector]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-current-workspace-badge]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-workspace-option="'.$activeRole.'"][@data-current-workspace="true"]//*[@data-current-workspace-badge]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-workspace-option]//form[@action="'.route('role-selection.store').'"]')->length)->toBe(2)
        ->and($xpath->query('//*[@data-workspace-option]//input[@name="role"][@value="research_coordinator"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-workspace-option]//input[@name="workspace"]')->length)->toBe(0);
})->with([
    'Faculty' => ['faculty', User::WORKSPACE_FACULTY],
    'Research Office' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
]);

test('dual role users can open workspace switching from the account menu', function () {
    $this->actingAs($this->dualRoleUser)
        ->withSession(['active_role' => 'faculty'])
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Switch Workspace')
        ->assertSee(route('role-selection.show'), false);
});

test('users can continue as faculty for the current session', function () {
    $this->actingAs($this->dualRoleUser)
        ->post(route('role-selection.store'), ['role' => 'faculty'])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHas('active_role', 'faculty')
        ->assertSessionHas('active_workspace', User::WORKSPACE_FACULTY);

    $this->get(route('dashboard'))
        ->assertRedirect(route('faculty.dashboard'));
});

test('users can continue as research coordinator for the current session', function () {
    $this->actingAs($this->dualRoleUser)
        ->post(route('role-selection.store'), ['role' => 'research_coordinator'])
        ->assertRedirect(route('research_coordinator.dashboard'))
        ->assertSessionHas('active_role', 'research_coordinator')
        ->assertSessionHas('active_workspace', User::WORKSPACE_RESEARCH_OFFICE);

    $this->get(route('dashboard'))
        ->assertRedirect(route('research_coordinator.dashboard'));
});

test('an unsupported role cannot be selected', function () {
    $this->actingAs($this->dualRoleUser)
        ->from(route('role-selection.show'))
        ->post(route('role-selection.store'), ['role' => 'research_head'])
        ->assertRedirect(route('role-selection.show'))
        ->assertSessionHasErrors('role');
});

test('users without both roles cannot access role selection', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('role-selection.show'))
        ->assertRedirect(route('dashboard'));

    $this->post(route('role-selection.store'), ['role' => 'faculty'])
        ->assertForbidden();
});

test('the legacy role switch supports fast navigation with the same session permissions', function (string $role, string $workspace, string $dashboardRoute) {
    $this->actingAs($this->dualRoleUser)
        ->postJson(route('role-selection.store'), ['role' => $role])
        ->assertOk()
        ->assertExactJson(['redirect' => route($dashboardRoute)])
        ->assertSessionHas('active_role', $role)
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);

    $this->get(route('dashboard'))->assertRedirect(route($dashboardRoute));
})->with([
    'Faculty' => ['faculty', 'faculty', 'faculty.dashboard'],
    'Research Office' => ['research_coordinator', 'research_office', 'research_coordinator.dashboard'],
]);

test('fast legacy switching rejects unsupported roles and unauthorized accounts', function () {
    $this->actingAs($this->dualRoleUser)
        ->postJson(route('role-selection.store'), ['role' => 'research_head'])
        ->assertUnprocessable()->assertJsonValidationErrors('role')->assertSessionMissing('active_role');

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->postJson(route('role-selection.store'), ['role' => 'faculty'])->assertForbidden();
});
