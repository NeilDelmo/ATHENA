<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'research_head', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->researchHead = User::factory()->create(['name' => 'Directory Administrator']);
    $this->researchHead->assignRole('research_head');
});

test('research heads can view every user in the faculty directory', function () {
    $cicsMember = User::factory()->create([
        'name' => 'CICS Faculty Member',
        'email' => 'cics@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
    ]);
    User::factory()->create([
        'name' => 'Member Without College',
        'email' => 'unset@g.batstate-u.edu.ph',
        'college' => null,
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk()
        ->assertSee('Faculty Directory')
        ->assertSee('placeholder="Search faculty"', false)
        ->assertSee('x-on:input.debounce.500ms', false)
        ->assertSee('Set as Research Coordinator?')
        ->assertSeeInOrder(['All', 'CICS', 'CTE', 'CABEIHM', 'CCJE', 'CAS', 'CHS'])
        ->assertSee($cicsMember->name)
        ->assertSee($cicsMember->email)
        ->assertSee(User::COLLEGES['CICS'])
        ->assertSee('Member Without College')
        ->assertSee('Not set');
});

test('college tabs show only users from the selected college', function () {
    User::factory()->create([
        'name' => 'Visible CICS Member',
        'college' => User::COLLEGES['CICS'],
    ]);
    User::factory()->create([
        'name' => 'Hidden CTE Member',
        'college' => User::COLLEGES['CTE'],
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index', ['college' => 'CICS']))
        ->assertOk()
        ->assertSee('Visible CICS Member')
        ->assertDontSee('Hidden CTE Member')
        ->assertDontSee('>College</th>', false);
});

test('members can be searched by name or email within a college', function () {
    User::factory()->create([
        'name' => 'Searchable Faculty',
        'email' => 'unique.faculty@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
    ]);
    User::factory()->create([
        'name' => 'Other Faculty',
        'email' => 'other@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index', ['college' => 'CICS', 'search' => 'unique.faculty']))
        ->assertOk()
        ->assertSee('Searchable Faculty')
        ->assertDontSee('Other Faculty')
        ->assertSee('value="unique.faculty"', false);
});

test('research heads can assign and remove the research coordinator role', function () {
    $member = User::factory()->create([
        'name' => 'Coordinator Candidate',
        'college' => User::COLLEGES['CICS'],
    ]);

    $this->actingAs($this->researchHead)
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Coordinator Candidate is now a Research Coordinator.');

    expect($member->refresh()->hasRole('research_coordinator'))->toBeTrue();

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk()
        ->assertSee('Research Coordinator');

    $this->actingAs($this->researchHead)
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'remove'])
        ->assertRedirect()
        ->assertSessionHas('status', 'Coordinator Candidate is no longer a Research Coordinator.');

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse();
});

test('assigning a coordinator replaces the existing coordinator from the same college only', function () {
    $existingCicsCoordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $existingCicsCoordinator->assignRole('research_coordinator');
    $existingCteCoordinator = User::factory()->create(['college' => User::COLLEGES['CTE']]);
    $existingCteCoordinator->assignRole('research_coordinator');
    $newCicsCoordinator = User::factory()->create(['college' => User::COLLEGES['CICS']]);

    $this->actingAs($this->researchHead)
        ->patch(route('research_head.faculty-directory.coordinator', $newCicsCoordinator), ['action' => 'assign'])
        ->assertRedirect();

    expect($existingCicsCoordinator->refresh()->hasRole('research_coordinator'))->toBeFalse()
        ->and($newCicsCoordinator->refresh()->hasRole('research_coordinator'))->toBeTrue()
        ->and($existingCteCoordinator->refresh()->hasRole('research_coordinator'))->toBeTrue();
});

test('a member needs a college before becoming a research coordinator', function () {
    $member = User::factory()->create(['college' => null]);

    $this->actingAs($this->researchHead)
        ->from(route('research_head.faculty-directory.index'))
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertRedirect(route('research_head.faculty-directory.index'))
        ->assertSessionHasErrors('action');

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse();
});

test('faculty cannot change research coordinator assignments', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $member = User::factory()->create();

    $this->actingAs($faculty)
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertForbidden();

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse();
});

test('faculty directory is restricted to research heads', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->get(route('research_head.faculty-directory.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($faculty)
        ->get(route('research_head.faculty-directory.index'))
        ->assertForbidden();
});
