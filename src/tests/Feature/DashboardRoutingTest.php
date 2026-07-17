<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator', 'expert'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('each account role is sent to its own dashboard', function (string $role, string $route) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($route));
})->with([
    'faculty' => ['faculty', 'faculty.dashboard'],
    'faculty researcher' => ['faculty_researcher', 'faculty.dashboard'],
    'research head' => ['research_head', 'research_head.dashboard'],
    'research coordinator' => ['research_coordinator', 'research_coordinator.dashboard'],
    'expert' => ['expert', 'expert.dashboard'],
]);

test('the shared faculty dashboard uses the correct workspace identity for each role', function () {
    $this->withoutVite();

    $faculty = User::factory()->create(['name' => 'Regular Faculty']);
    $faculty->assignRole('faculty');

    $facultyResearcher = User::factory()->create(['name' => 'Faculty Researcher']);
    $facultyResearcher->assignRole('faculty_researcher');

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Research Proposal Workspace')
        ->assertSee('Submit and track your research proposals.')
        ->assertDontSee('Faculty Researcher Workspace')
        ->assertDontSee('Manage and track your institutional research submissions.');

    $this->actingAs($facultyResearcher)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Faculty Researcher Workspace')
        ->assertSee('Manage and track your institutional research submissions.')
        ->assertDontSee('Research Proposal Workspace');
});

test('the forbidden response uses the friendly error page', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get(route('research_head.dashboard'))
        ->assertForbidden()
        ->assertSee("This page isn't for your account.")
        ->assertSee('Go to my dashboard');
});

test('missing pages use the friendly error design', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('We could not find that page.')
        ->assertSee('Return home');
});

test('common error views share the ATHENA design', function (string $code, string $heading) {
    $this->view("errors.{$code}")
        ->assertSee("Error {$code}")
        ->assertSee($heading)
        ->assertSee('ATHENA');
})->with([
    'expired session' => ['419', 'Your session has expired.'],
    'too many requests' => ['429', 'Please slow down for a moment.'],
    'server error' => ['500', 'Something went wrong on our side.'],
    'maintenance' => ['503', 'ATHENA is taking a short break.'],
]);

test('an account without a role is signed out instead of being sent to a forbidden page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
});
