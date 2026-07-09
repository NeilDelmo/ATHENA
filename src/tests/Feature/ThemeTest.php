<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('the authentication page and main application share one persistent theme preference', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertSee('athena-theme', false)
        ->assertSee('auth-theme-toggle', false);

    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->actingAs($faculty)
        ->get('/faculty/dashboard')
        ->assertOk()
        ->assertSee('data-app-shell', false)
        ->assertSee('app-theme-toggle', false)
        ->assertSee('athena-theme', false);
});

test('the shared application theme toggle is rendered for the research head', function () {
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'research_head']);
    Role::firstOrCreate(['name' => 'expert']);
    $head = User::factory()->create();
    $head->assignRole('research_head');

    $this->actingAs($head)
        ->get('/research-head/dashboard')
        ->assertOk()
        ->assertSee('app-theme-toggle', false);
});

test('the landing page uses the shared theme controller', function () {
    $this->withoutVite();

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme-toggle', false)
        ->assertSee('athena-theme', false)
        ->assertSee('prefers-color-scheme: dark', false)
        ->assertDontSee('localStorage.getItem("theme")', false);
});
