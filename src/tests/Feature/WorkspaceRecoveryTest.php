<?php

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('workspace choices are saved in a persistent encrypted account scoped cookie', function (string $role, string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)->postJson(route('workspace.store'), ['workspace' => $workspace])
        ->assertOk()->assertCookie('athena_workspace', $user->id.':'.$workspace);

    $cookie = $response->getCookie('athena_workspace');
    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(time() + 86400)
        ->and($response->getCookie('athena_workspace', false)->getValue())->not->toBe($cookie->getValue());
})->with([
    ['research_head', 'faculty'],
    ['research_head', 'research_head'],
    ['faculty_researcher', 'faculty_researcher'],
    ['research_coordinator', 'research_office'],
    ['research_secretary', 'research_secretary'],
]);

test('a saved workspace restores the shared dashboard after session selection is lost', function (string $workspace, string $dashboard) {
    $user = User::factory()->create();
    $user->assignRole(['research_head', 'faculty_researcher', 'research_coordinator', 'research_secretary']);

    $this->actingAs($user)->withCookie('athena_workspace', $user->id.':'.$workspace)
        ->get(route('dashboard'))->assertRedirect(route($dashboard))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
    expect($user->activeWorkspace())->toBe($workspace);
})->with([
    ['faculty', 'faculty.dashboard'],
    ['faculty_researcher', 'faculty.dashboard'],
    ['research_head', 'research_head.dashboard'],
    ['research_office', 'research_coordinator.dashboard'],
    ['research_secretary', 'research_secretary.dashboard'],
]);

test('an explicit workspace selection takes priority over a saved preference', function () {
    $user = User::factory()->create();
    $user->assignRole('research_head');

    $this->actingAs($user)->withCookie('athena_workspace', $user->id.':faculty')
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->get(route('research_head.dashboard'))->assertOk()
        ->assertCookie('athena_workspace', $user->id.':research_head');
    $this->get(route('faculty.dashboard'))->assertForbidden();
});

test('the latest explicit workspace switch remains selected after session loss', function (string $workspace, string $dashboard) {
    $user = User::factory()->create();
    $user->assignRole(['research_head', 'faculty_researcher', 'research_coordinator', 'research_secretary']);
    Auth::login($user, remember: true);
    $recallerName = Auth::guard('web')->getRecallerName();
    $recaller = app('cookie')->queued($recallerName)->getValue();
    $previousWorkspace = $workspace === 'research_head' ? 'faculty' : 'research_head';

    $this->postJson(route('workspace.store'), ['workspace' => $previousWorkspace])
        ->assertOk()->assertCookie('athena_workspace', $user->id.':'.$previousWorkspace);
    $preference = $this->withCookie('athena_workspace', $user->id.':'.$previousWorkspace)
        ->postJson(route('workspace.store'), ['workspace' => $workspace])
        ->assertOk()->assertCookie('athena_workspace', $user->id.':'.$workspace)
        ->getCookie('athena_workspace')->getValue();

    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookies([$recallerName => $recaller, 'athena_workspace' => $preference])
        ->get(route('dashboard'))->assertRedirect(route($dashboard))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
})->with([
    'Faculty' => ['faculty', 'faculty.dashboard'],
    'Faculty Researcher' => ['faculty_researcher', 'faculty.dashboard'],
    'Research Head' => ['research_head', 'research_head.dashboard'],
    'Coordinator' => ['research_office', 'research_coordinator.dashboard'],
    'Secretary' => ['research_secretary', 'research_secretary.dashboard'],
]);

test('legacy role choices also take priority over a saved workspace', function () {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'research_coordinator']);

    $this->actingAs($user)->withCookie('athena_workspace', $user->id.':research_office')
        ->withSession(['active_role' => 'faculty'])
        ->get(route('dashboard'))->assertRedirect(route('faculty.dashboard'))
        ->assertCookie('athena_workspace', $user->id.':faculty');
});

test('the shared dashboard follows the selected workspace when the legacy role is stale', function (string $workspace, ?string $legacyRole, string $dashboard) {
    $user = User::factory()->create();
    $user->assignRole(['research_coordinator', 'faculty_researcher']);

    $this->actingAs($user)->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace,
        'active_role' => $legacyRole,
    ])->get(route('dashboard'))->assertRedirect(route($dashboard))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
})->with([
    'Faculty Researcher with old Coordinator choice' => ['faculty_researcher', 'research_coordinator', 'faculty.dashboard'],
    'Coordinator with old Faculty choice' => ['research_office', 'faculty', 'research_coordinator.dashboard'],
    'Faculty with no legacy choice' => ['faculty', null, 'faculty.dashboard'],
]);

test('restoring faculty does not expose another users proposal through research head access', function () {
    $user = User::factory()->create();
    $user->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => User::factory()->create()->id, 'title' => 'Private proposal', 'status' => 'pending']);

    $this->actingAs($user)->withCookie('athena_workspace', $user->id.':faculty')
        ->get(route('topics.show', $topic))->assertForbidden()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty');
});

test('malformed and foreign account preferences cannot choose a workspace', function (string $preference) {
    $user = User::factory()->create();
    $user->assignRole('research_head');
    $value = str_replace('{id}', (string) $user->id, $preference);

    $this->actingAs($user)->withCookie('athena_workspace', $value)
        ->get(route('faculty.dashboard'))->assertForbidden()
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
})->with(['invalid', '0:faculty', '{id}:invalid', '{id}:faculty:extra']);

test('a saved preference cannot restore a workspace after its role is removed', function () {
    $user = User::factory()->create();
    $user->assignRole('faculty');

    $this->actingAs($user)->withCookie('athena_workspace', $user->id.':research_head')
        ->get(route('research_head.dashboard'))->assertForbidden()
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
});

test('a remembered login can recover an unambiguous page before a preference has been saved', function () {
    $user = User::factory()->create();
    $user->assignRole('research_head');
    Auth::login($user, remember: true);
    $recallerName = Auth::guard('web')->getRecallerName();
    $recaller = app('cookie')->queued($recallerName)->getValue();
    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($recallerName, $recaller)->get(route('faculty.proposal-drafts.index'))
        ->assertOk()->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty')
        ->assertCookie('athena_workspace', $user->id.':faculty');
    expect(Auth::viaRemember())->toBeTrue();
});

test('a remembered login with no saved preference chooses among ambiguous workspaces', function (string $route) {
    $user = User::factory()->create();
    $user->assignRole(['research_head', 'faculty_researcher']);
    Auth::login($user, remember: true);
    $recallerName = Auth::guard('web')->getRecallerName();
    $recaller = app('cookie')->queued($recallerName)->getValue();
    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($recallerName, $recaller)->get(route($route))
        ->assertRedirect(route('workspace.select'))
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
})->with(['dashboard', 'faculty.dashboard']);

test('a workspace preference alone cannot authenticate a signed out user', function () {
    $user = User::factory()->create();
    $user->assignRole('research_head');

    $this->withCookie('athena_workspace', $user->id.':faculty')
        ->get(route('faculty.dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('background requests preserve unresolved recovery without falling back to research head', function (string $destination) {
    $user = User::factory()->create();
    $user->assignRole('research_head');
    $topic = TopicProposal::create(['user_id' => User::factory()->create()->id, 'title' => 'Another users proposal', 'status' => 'pending']);
    Auth::login($user, remember: true);
    $recallerName = Auth::guard('web')->getRecallerName();
    $recaller = app('cookie')->queued($recallerName)->getValue();
    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($recallerName, $recaller)->withCredentials()
        ->getJson(route('topics.live-status', $topic))->assertForbidden();
    expect(Auth::viaRemember())->toBeTrue()
        ->and($user->activeWorkspace())->toBeNull();

    Auth::forgetGuards();
    if ($destination === 'dashboard') {
        $this->get(route('dashboard'))->assertRedirect(route('workspace.select'));
    } else {
        $this->get(route('faculty.proposal-drafts.index'))->assertOk()
            ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty')
            ->assertCookie('athena_workspace', $user->id.':faculty');
    }
    expect(Auth::viaRemember())->toBeFalse();
})->with(['dashboard', 'faculty.proposal-drafts.index']);
