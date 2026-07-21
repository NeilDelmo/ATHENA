<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

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

test('research coordinators must be removed before changing their college', function () {
    Role::firstOrCreate(['name' => 'research_coordinator']);

    $user = User::factory()->create([
        'college' => User::COLLEGES['CICS'],
    ]);
    $user->assignRole('research_coordinator');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.college.update'), ['college' => User::COLLEGES['CTE']])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors([
            'college' => 'Remove your Research Coordinator assignment before changing your college.',
        ]);

    expect($user->refresh()->college)->toBe(User::COLLEGES['CICS']);

    $user->removeRole('research_coordinator');

    $this->actingAs($user)
        ->patch(route('profile.college.update'), ['college' => User::COLLEGES['CTE']])
        ->assertRedirect()
        ->assertSessionHas('status', 'college-updated');

    expect($user->refresh()->college)->toBe(User::COLLEGES['CTE']);
});

test('research coordinators see that their college is locked', function () {
    $this->withoutVite();

    Role::firstOrCreate(['name' => 'research_coordinator']);

    $user = User::factory()->create([
        'college' => User::COLLEGES['CICS'],
    ]);
    $user->assignRole('research_coordinator');

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('data-college-locked', false)
        ->assertSee('College is locked while you are a Research Coordinator.')
        ->assertSee('Ask the Research Head to remove the coordinator assignment before changing it.')
        ->assertDontSee('action="'.route('profile.college.update').'"', false);
});

test('guests cannot update a college', function () {
    $this->patch(route('profile.college.update'), [
        'college' => 'College of Teacher Education',
    ])->assertRedirect(route('login'));
});
