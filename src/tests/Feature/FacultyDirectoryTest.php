<?php

use App\Actions\UpdateResearchCoordinatorAction;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    foreach (['faculty', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->researchHead = User::factory()->create(['name' => 'Directory Administrator']);
    $this->researchHead->assignRole('research_head');
});

test('research heads can view every user in the user directory', function () {
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
        ->assertSee('User Directory')
        ->assertSee('placeholder="Search users"', false)
        ->assertSee('x-model.debounce.250ms="search"', false)
        ->assertSee('window.history.replaceState', false)
        ->assertSee('data-faculty-directory', false)
        ->assertSee('data-college-tab="CICS"', false)
        ->assertSee('x-on:click="setCollege', false)
        ->assertSee("'bg-gray-900 text-white shadow-sm'", false)
        ->assertDontSee('collegeDotClasses', false)
        ->assertSee('Assign Research Coordinator')
        ->assertSee('aria-label="Assign Research Coordinator to CICS Faculty Member"', false)
        ->assertSee('aria-label="Assign Research Office Secretary to CICS Faculty Member"', false)
        ->assertSee('Assign Research Office Secretary')
        ->assertDontSee('Coordinator rules:')
        ->assertSeeInOrder(['All', 'CICS', 'CTE', 'CABEIHM', 'CCJE', 'CAS', 'CHS'])
        ->assertSee($cicsMember->name)
        ->assertSee($cicsMember->email)
        ->assertSee(User::COLLEGES['CICS'])
        ->assertSee('Member Without College')
        ->assertSee($this->researchHead->name)
        ->assertSee('Not set')
        ->assertSee('data-coordinator-ineligible', false)
        ->assertSee('College required');
});

test('college tabs load members once for client-side switching', function () {
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
        ->assertSee('Hidden CTE Member')
        ->assertSee('data-initial-college="CICS"', false)
        ->assertSee('data-college="CICS"', false)
        ->assertSee('data-college="CTE"', false)
        ->assertSee('x-show="selectedCollege === \'all\'"', false);
});

test('members can be searched by name or email without another request', function () {
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
        ->assertSee('Other Faculty')
        ->assertSee('data-initial-search="unique.faculty"', false)
        ->assertSee('member.search.includes(query)', false)
        ->assertDontSee('x-ref="searchForm"', false);
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
        ->assertSee('Research Office');

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

test('research heads cannot be assigned as coordinators', function () {
    $member = User::factory()->create([
        'name' => 'CICS Research Head',
        'college' => User::COLLEGES['CICS'],
    ]);
    $member->assignRole('research_head');

    $existingOfficeMember = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $existingOfficeMember->assignRole('research_coordinator');

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk()
        ->assertSee('CICS Research Head')
        ->assertSee('Unavailable')
        ->assertSee('title="Research Heads cannot be assigned as Research Coordinators."', false);

    $this->actingAs($this->researchHead)
        ->from(route('research_head.faculty-directory.index'))
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertRedirect(route('research_head.faculty-directory.index'))
        ->assertSessionHasErrors(['action' => 'Research Heads cannot be assigned as Research Coordinators.']);

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse()
        ->and($existingOfficeMember->refresh()->hasRole('research_coordinator'))->toBeTrue();

    expect(fn () => app(UpdateResearchCoordinatorAction::class)->handle($member, 'assign'))
        ->toThrow(InvalidArgumentException::class, 'Research Heads cannot be assigned as Research Coordinators.');
});

test('an existing research office assignment can be removed from a research head', function () {
    $member = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $member->assignRole(['research_head', 'research_coordinator']);

    $this->actingAs($this->researchHead)
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'remove'])
        ->assertRedirect();

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse()
        ->and($member->hasRole('research_head'))->toBeTrue();
});

test('members without a college have a disabled coordinator control', function () {
    User::factory()->create([
        'name' => 'Unassigned Faculty',
        'college' => null,
    ]);

    $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk()
        ->assertSee('Unassigned Faculty')
        ->assertSee('data-coordinator-ineligible', false)
        ->assertSee('Set this member\'s college before assigning the Research Coordinator role.');
});

test('the directory exposes secretary and coordinator roles independently', function () {
    $member = User::factory()->create(['name' => 'Office Secretary', 'college' => User::COLLEGES['CICS']]);
    $member->assignRole(['research_secretary', 'research_coordinator']);

    $response = $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk()
        ->assertSee('User directory');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $row = $xpath->query('//tr[@data-member-row][.//span[text()="Office Secretary"]]')->item(0);

    expect($row)->not->toBeNull()
        ->and($xpath->query('.//div[@data-member-roles]', $row)->item(0)->textContent)->toContain('Research Coordinator', 'Research Office Secretary')
        ->and($xpath->query('.//button[@aria-label="Remove Research Coordinator from Office Secretary"]', $row)->length)->toBe(1)
        ->and($xpath->query('.//button[@aria-label="Remove Research Office Secretary from Office Secretary"]', $row)->length)->toBe(1);
});

test('directory separates member roles from college status and keeps assignment actions consistent', function () {
    $member = User::factory()->create([
        'name' => 'Member Awaiting College',
        'email' => 'awaiting.college@g.batstate-u.edu.ph',
        'college' => null,
    ]);
    $member->assignRole('research_secretary');
    $coordinator = User::factory()->create([
        'name' => 'College Research Coordinator',
        'email' => 'coordinator@g.batstate-u.edu.ph',
        'college' => User::COLLEGES['CICS'],
    ]);
    $coordinator->assignRole('research_coordinator');

    $response = $this->actingAs($this->researchHead)
        ->get(route('research_head.faculty-directory.index'))
        ->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $row = $xpath->query('//tr[@data-member-row][.//span[text()="Member Awaiting College"]]')->item(0);
    $identity = $xpath->query('./td[1]', $row)->item(0);
    $college = $xpath->query('./td[3]', $row)->item(0);

    expect($identity->textContent)->not->toContain('College required')
        ->and($college->textContent)->toContain('College required')
        ->and($xpath->query('.//div[@data-member-roles]', $identity)->item(0)->textContent)->toContain('Research Office Secretary');

    foreach ($xpath->query('//tr[@data-member-row]/td[position() >= 4]/button') as $button) {
        expect($button->getAttribute('class'))->toContain('w-full', 'min-h-11')
            ->and(trim($button->textContent))->toBeIn(['Assign', 'Remove', 'Unavailable']);
    }

    if (getenv('ATHENA_EXPORT_USER_DIRECTORY') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/user-directory.html'), $response->getContent());
    }
});

test('secretary assignments preserve coordinator and faculty roles', function () {
    $member = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $member->assignRole(['faculty', 'research_coordinator']);
    $otherSecretary = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $otherSecretary->assignRole('research_secretary');

    $this->actingAs($this->researchHead)
        ->patch('/research-head/faculty-directory/'.$member->id.'/secretary', ['action' => 'assign'])
        ->assertRedirect()->assertSessionHasNoErrors()
        ->assertSessionHas('status', $member->name.' is now a Research Office Secretary.');

    expect($member->refresh()->hasAllRoles(['faculty', 'research_coordinator', 'research_secretary']))->toBeTrue()
        ->and($otherSecretary->refresh()->hasRole('research_secretary'))->toBeTrue();

    $this->patch('/research-head/faculty-directory/'.$member->id.'/secretary', ['action' => 'remove'])
        ->assertRedirect()->assertSessionHasNoErrors()
        ->assertSessionHas('status', $member->name.' is no longer a Research Office Secretary.');

    expect($member->refresh()->hasRole('research_secretary'))->toBeFalse()
        ->and($member->hasAllRoles(['faculty', 'research_coordinator']))->toBeTrue()
        ->and($otherSecretary->refresh()->hasRole('research_secretary'))->toBeTrue();
});

test('replacing and removing coordinators preserves secretary roles', function () {
    $previous = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $previous->assignRole(['research_coordinator', 'research_secretary']);
    $member = User::factory()->create(['college' => User::COLLEGES['CICS']]);
    $member->assignRole('research_secretary');

    $this->actingAs($this->researchHead)
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($previous->refresh()->hasRole('research_coordinator'))->toBeFalse()
        ->and($previous->hasRole('research_secretary'))->toBeTrue()
        ->and($member->refresh()->hasAllRoles(['research_coordinator', 'research_secretary']))->toBeTrue();

    $this->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'remove'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse()
        ->and($member->hasRole('research_secretary'))->toBeTrue();
});

test('secretary assignments require the research head workspace', function (string $role, string $workspace) {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    $member = User::factory()->create();

    $this->actingAs($actor)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->patch('/research-head/faculty-directory/'.$member->id.'/secretary', ['action' => 'assign'])
        ->assertForbidden();

    expect($member->refresh()->hasRole('research_secretary'))->toBeFalse();
})->with([
    'faculty' => ['faculty', User::WORKSPACE_FACULTY],
    'coordinator' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    'secretary' => ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY],
    'research head working as faculty' => ['research_head', User::WORKSPACE_FACULTY],
]);

test('secretary assignments reject invalid actions without changing roles', function () {
    $member = User::factory()->create();
    $member->assignRole('research_secretary');

    $this->actingAs($this->researchHead)
        ->from(route('research_head.faculty-directory.index'))
        ->patch('/research-head/faculty-directory/'.$member->id.'/secretary', ['action' => 'replace'])
        ->assertRedirect(route('research_head.faculty-directory.index'))->assertSessionHasErrors('action');

    expect($member->refresh()->hasRole('research_secretary'))->toBeTrue();
});

test('secretaries can be assigned without a coordinator college requirement', function () {
    $member = User::factory()->create(['college' => null]);
    $member->assignRole('faculty');

    $this->actingAs($this->researchHead)
        ->patch('/research-head/faculty-directory/'.$member->id.'/secretary', ['action' => 'assign'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($member->refresh()->hasAllRoles(['faculty', 'research_secretary']))->toBeTrue()
        ->and($member->hasRole('research_coordinator'))->toBeFalse();
});

test('coordinator assignments require the research head workspace', function (string $role, string $workspace) {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    $member = User::factory()->create();

    $this->actingAs($actor)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->patch(route('research_head.faculty-directory.coordinator', $member), ['action' => 'assign'])
        ->assertForbidden();

    expect($member->refresh()->hasRole('research_coordinator'))->toBeFalse();
})->with([
    'faculty' => ['faculty', User::WORKSPACE_FACULTY],
    'research office' => ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    'research head working as faculty' => ['research_head', User::WORKSPACE_FACULTY],
]);

test('user directory is restricted to research heads', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->get(route('research_head.faculty-directory.index'))
        ->assertRedirect(route('login'));

    $this->actingAs($faculty)
        ->get(route('research_head.faculty-directory.index'))
        ->assertForbidden();
});
