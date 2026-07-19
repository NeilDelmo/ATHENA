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
        ->assertSee('Continue as Faculty')
        ->assertSee('Continue as Research Coordinator')
        ->assertSee('Open workspace');
});

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
        ->assertSessionHas('active_role', 'faculty');

    $this->get(route('dashboard'))
        ->assertRedirect(route('faculty.dashboard'));
});

test('users can continue as research coordinator for the current session', function () {
    $this->actingAs($this->dualRoleUser)
        ->post(route('role-selection.store'), ['role' => 'research_coordinator'])
        ->assertRedirect(route('research_coordinator.dashboard'))
        ->assertSessionHas('active_role', 'research_coordinator');

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
