<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->coordinator = User::factory()->create([
        'name' => 'CICS Coordinator',
        'college' => User::COLLEGES['CICS'],
    ]);
    $this->coordinator->assignRole(['faculty', 'research_coordinator']);
});

test('research coordinators see only other members from their college', function () {
    $sameCollegeMember = User::factory()->create([
        'name' => 'Visible CICS Faculty',
        'email' => 'visible.cics@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
    ]);
    User::factory()->create([
        'name' => 'Hidden CTE Faculty',
        'email' => 'hidden.cte@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CTE'],
    ]);

    $this->actingAs($this->coordinator)
        ->get(route('research_coordinator.dashboard'))
        ->assertOk()
        ->assertSee('Research Coordinator Dashboard')
        ->assertSee('Faculty Members')
        ->assertSee(User::COLLEGES['CICS'])
        ->assertSee($sameCollegeMember->name)
        ->assertSee($sameCollegeMember->email)
        ->assertDontSee('Hidden CTE Faculty')
        ->assertDontSee('>'.$this->coordinator->email.'</td>', false);
});

test('research coordinator accounts with faculty access are asked to choose a workspace', function () {
    $this->actingAs($this->coordinator)
        ->get(route('dashboard'))
        ->assertRedirect(route('role-selection.show'));
});

test('other roles cannot open the research coordinator dashboard', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('research_coordinator.dashboard'))
        ->assertForbidden();
});
