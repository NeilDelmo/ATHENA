<?php

use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('upgrading preserves office access without assigning an office role to the project secretary', function () {
    $this->withoutVite();
    $legacyRole = Role::findOrCreate('research_secretary', 'web');
    $permission = Permission::findOrCreate('verify signed release', 'web');
    $legacyRole->givePermissionTo($permission);
    $officer = User::factory()->create(['college' => 'CICS', 'last_workspace' => 'research_secretary']);
    $officer->assignRole($legacyRole);
    expect($officer->hasPermissionTo($permission))->toBeTrue();

    $leader = User::factory()->create(['college' => 'CICS']);
    $teamSecretary = User::factory()->create(['college' => 'CICS']);
    $teamSecretary->assignRole(Role::findOrCreate('faculty_researcher', 'web'));
    $topic = TopicProposal::create([
        'user_id' => $leader->id,
        'research_secretary_id' => $teamSecretary->id,
        'title' => 'Project budget assignment',
        'status' => 'approved',
        'project_status' => 'ongoing',
        'notice_to_proceed_issued_at' => now(),
    ]);
    $member = TopicCollaborator::factory()->create([
        'topic_id' => $topic->id,
        'user_id' => $teamSecretary->id,
        'project_role' => 'secretary',
        'accepted_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_11_24_211333_rename_research_office_secretary_role.php');
    $migration->up();

    $officer = $officer->fresh();
    expect($officer->roles()->sole()->id)->toBe($legacyRole->id)
        ->and($officer->hasRole('research_office_secretary'))->toBeTrue()
        ->and($officer->hasPermissionTo($permission))->toBeTrue()
        ->and($officer->last_workspace)->toBe('research_secretary')
        ->and($officer->availableWorkspaceKeys())->toBe(['research_secretary']);

    $this->actingAs($officer)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_secretary'])
        ->get(route('research_secretary.dashboard'))->assertOk()->assertSee('Research Office Secretary');

    $this->actingAs($teamSecretary)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('research_secretary.dashboard'))->assertForbidden();
    expect($member->fresh()->isProjectSecretary())->toBeTrue()
        ->and($topic->fresh()->research_secretary_id)->toBe($teamSecretary->id)
        ->and($topic->canPrepareMonitoringBudget($teamSecretary))->toBeTrue()
        ->and($teamSecretary->fresh()->hasRole('research_office_secretary'))->toBeFalse();

    $migration->down();
    $this->assertDatabaseHas('roles', ['id' => $legacyRole->id, 'name' => 'research_secretary']);
    expect($officer->fresh()->hasPermissionTo($permission))->toBeTrue();
});

test('existing office roles merge their assignments and permissions without duplicates', function () {
    $legacyRole = Role::findOrCreate('research_secretary', 'web');
    $officeRole = Role::findOrCreate('research_office_secretary', 'web');
    $sharedPermission = Permission::findOrCreate('view signing queue', 'web');
    $releasePermission = Permission::findOrCreate('record document release', 'web');
    $legacyRole->givePermissionTo($sharedPermission, $releasePermission);
    $officeRole->givePermissionTo($sharedPermission);
    $existingOfficer = User::factory()->create();
    $existingOfficer->assignRole($legacyRole);
    $officerWithBothRoles = User::factory()->create();
    $officerWithBothRoles->assignRole($legacyRole, $officeRole);

    $migration = require database_path('migrations/2026_11_24_211333_rename_research_office_secretary_role.php');
    $migration->up();
    $migration->up();

    $this->assertDatabaseMissing('roles', ['name' => 'research_secretary', 'guard_name' => 'web']);
    expect($existingOfficer->fresh()->roles()->sole()->id)->toBe($officeRole->id)
        ->and($officerWithBothRoles->fresh()->roles()->sole()->id)->toBe($officeRole->id)
        ->and($officeRole->fresh()->permissions)->toHaveCount(2)
        ->and($existingOfficer->fresh()->hasPermissionTo($releasePermission))->toBeTrue();
});
