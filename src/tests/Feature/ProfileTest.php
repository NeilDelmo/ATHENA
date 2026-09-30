<?php

use App\Models\ResearchCall;
use App\Models\TopicProposal;
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
        ->assertSee('data-confirm-title="Log out of ATHENA?"', false)
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

test('users can save their contact number from their profile', function () {
    $user = User::factory()->create();
    $contactNumber = '09171234567';

    $this->actingAs($user)
        ->patch(route('profile.contact-number.update'), ['contact_number' => $contactNumber])
        ->assertRedirect()
        ->assertSessionHas('status', 'contact-number-updated');

    expect($user->refresh()->contact_number)->toBe($contactNumber);

    $this->withoutVite();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('id="contact_number"', false)
        ->assertSee('value="'.$contactNumber.'"', false);
});

test('users can save their college and contact number together from their profile', function () {
    $user = User::factory()->create(['college' => null, 'contact_number' => null]);
    $college = 'College of Informatics and Computing Sciences';
    $contactNumber = '09788978768';

    $this->actingAs($user)
        ->patch(route('profile.details.update'), [
            'college' => $college,
            'contact_number' => $contactNumber,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'profile-details-updated');

    expect($user->refresh()->college)->toBe($college)
        ->and($user->contact_number)->toBe($contactNumber);
});

test('contact number must contain exactly 11 digits when provided', function () {
    $user = User::factory()->create(['contact_number' => null]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.contact-number.update'), ['contact_number' => '0917ABCDEFG'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('contact_number');

    expect($user->refresh()->contact_number)->toBeNull();
});

test('contact number can be cleared from the profile', function () {
    $user = User::factory()->create(['contact_number' => '09171234567']);

    $this->actingAs($user)
        ->patch(route('profile.contact-number.update'), ['contact_number' => null])
        ->assertRedirect()
        ->assertSessionHas('status', 'contact-number-updated');

    expect($user->refresh()->contact_number)->toBeNull();
});

test('guests cannot update a contact number', function () {
    $this->patch(route('profile.contact-number.update'), [
        'contact_number' => '09171234567',
    ])->assertRedirect(route('login'));
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
            'college' => 'Remove your Research Office assignment before changing your college.',
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
        ->assertSee('College is locked while you are part of the Research Office.')
        ->assertSee('Ask the Research Head to remove your Research Office assignment before changing it.')
        ->assertDontSee('action="'.route('profile.college.update').'"', false);
});

test('guests cannot update a college', function () {
    $this->patch(route('profile.college.update'), [
        'college' => 'College of Teacher Education',
    ])->assertRedirect(route('login'));
});

test('long account identities and unavailable avatars remain visible', function () {
    $this->withoutVite();

    $user = User::factory()->create([
        'name' => str_repeat('Long Institutional Name ', 10),
        'email' => str_repeat('long-account-', 12).'@g.batstate-u.edu.ph',
        'avatar' => 'https://example.com/unavailable-profile-photo.jpg',
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee('data-header-account-menu', false)
        ->assertSee('data-user-avatar', false)
        ->assertSee('x-on:error="avatarFailed = true"', false)
        ->assertSee('break-words', false)
        ->assertSee('max-w-[calc(100vw-6rem)]', false);
});

test('account profiles render recent proposals across workspaces with optional research calls', function (string $role, string $workspace, bool $hasResearchCall) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => $role]);
    $user = User::factory()->create();
    $user->assignRole($role);
    $call = $hasResearchCall ? ResearchCall::create([
        'title' => 'Profile research call',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'academic_year' => '2026-2027',
        'status' => 'open',
    ]) : null;
    $proposal = TopicProposal::create([
        'user_id' => $user->id,
        'research_call_id' => $call?->id,
        'category_id' => null,
        'title' => 'My profile proposal',
        'status' => 'approved',
    ]);
    $otherUser = User::factory()->create();
    TopicProposal::create([
        'user_id' => $otherUser->id,
        'title' => 'Another account private proposal',
        'status' => 'approved',
    ]);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->actingAs($user)->get(route('profile.edit'))->assertOk()
        ->assertSee('Account Profile')
        ->assertSee($proposal->title)
        ->assertSee($hasResearchCall ? 'Profile research call' : 'Independent submission')
        ->assertViewHas('recentProposals', fn ($items): bool => $items->count() === 1 && $items->first()->id === $proposal->id);
})->with([
    'faculty independent' => ['faculty', User::WORKSPACE_FACULTY, false],
    'faculty call' => ['faculty', User::WORKSPACE_FACULTY, true],
    'researcher independent' => ['faculty_researcher', User::WORKSPACE_FACULTY_RESEARCHER, false],
    'researcher call' => ['faculty_researcher', User::WORKSPACE_FACULTY_RESEARCHER, true],
    'head independent' => ['research_head', User::WORKSPACE_RESEARCH_HEAD, false],
    'head call' => ['research_head', User::WORKSPACE_RESEARCH_HEAD, true],
    'office independent' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, false],
    'office call' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE, true],
    'secretary independent' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, false],
    'secretary call' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY, true],
]);
