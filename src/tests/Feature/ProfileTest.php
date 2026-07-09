<?php

use App\Models\User;

test('authenticated users can view their Google managed profile', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'name' => 'Red Spartan Faculty',
        'email' => 'faculty@g.batstate-u.edu.ph',
        'google_id' => 'google-user-123',
    ]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Red Spartan Faculty')
        ->assertSee('faculty@g.batstate-u.edu.ph')
        ->assertSee('BatStateU Google Workspace')
        ->assertSee('managed by Batangas State University')
        ->assertSee('Account activity')
        ->assertSee('Access and permissions')
        ->assertSee('Recent proposals')
        ->assertSee('Sign out securely')
        ->assertSee('Profile')
        ->assertSee(route('profile.edit'), false);
});

test('profile credentials cannot be changed or deleted locally', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [])->assertMethodNotAllowed();
    $this->actingAs($user)->delete('/profile')->assertMethodNotAllowed();
});
