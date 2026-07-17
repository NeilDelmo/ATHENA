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

test('users can save their college from their profile', function () {
    $user = User::factory()->create();
    $college = 'College of Informatics and Computing Sciences';

    $this->actingAs($user)
        ->patch(route('profile.college.update'), ['college' => $college])
        ->assertRedirect()
        ->assertSessionHas('status', 'college-updated');

    expect($user->refresh()->college)->toBe($college);

    $this->withoutVite();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Set your college')
        ->assertSee('value="'.$college.'" selected', false);
});

test('college must be one of the supported colleges', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.college.update'), ['college' => 'Unsupported College'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('college');

    expect($user->refresh()->college)->toBeNull();
});

test('guests cannot update a college', function () {
    $this->patch(route('profile.college.update'), [
        'college' => 'College of Teacher Education',
    ])->assertRedirect(route('login'));
});
