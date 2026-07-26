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
        ->get(route('research_coordinator.members.index'))
        ->assertOk()
        ->assertSee('Faculty Members')
        ->assertSee(User::COLLEGES['CICS'])
        ->assertSee('1 member')
        ->assertSee($sameCollegeMember->name)
        ->assertSee($sameCollegeMember->email)
        ->assertDontSee('Hidden CTE Faculty')
        ->assertDontSee('>'.$this->coordinator->email.'</td>', false);
});

test('research coordinators still see their dashboard when their college has no other members', function () {
    $this->actingAs($this->coordinator)
        ->get(route('research_coordinator.dashboard'))
        ->assertOk()
        ->assertSee('Research Coordinator Dashboard')
        ->assertSee('View faculty members assigned to '.User::COLLEGES['CICS'].'.')
        ->assertSee('College members')
        ->assertSee('>0</span>', false)
        ->assertSee('Dashboard')
        ->assertSee('M15.75 6.75a3.75 3.75 0 1 1-7.5 0', false)
        ->assertDontSee('No faculty members found yet');
});

test('research coordinators see an empty state on the faculty members page', function () {
    $this->actingAs($this->coordinator)
        ->get(route('research_coordinator.members.index'))
        ->assertOk()
        ->assertSee('Faculty Members')
        ->assertDontSee('inline-flex items-center self-start rounded-xl border', false)
        ->assertSee('No faculty members found yet')
        ->assertSee('currently has 0 registered members');
});

test('research coordinator accounts with faculty access are asked to choose a workspace', function () {
    $this->actingAs($this->coordinator)
        ->get(route('dashboard'))
        ->assertRedirect(route('role-selection.show'));
});

test('other roles cannot open the research coordinator dashboard', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    foreach (['research_coordinator.dashboard', 'research_coordinator.members.index'] as $route) {
        $this->actingAs($faculty)
            ->get(route($route))
            ->assertForbidden();
    }
});
