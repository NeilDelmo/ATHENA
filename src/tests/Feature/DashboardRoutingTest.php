<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head', 'expert'] as $role) {
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
    'expert' => ['expert', 'expert.dashboard'],
]);

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
