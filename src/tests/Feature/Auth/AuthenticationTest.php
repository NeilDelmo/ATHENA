<?php

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use App\Support\WorkspacePreference;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as GoogleResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    config()->set('services.google.remember_login', false);
});

function mockGoogleUser(string $email, string $avatar = 'https://example.com/avatar.jpg'): void
{
    $googleUser = Mockery::mock(SocialiteUser::class);
    $googleUser->shouldReceive('getEmail')->andReturn($email);
    $googleUser->shouldReceive('getName')->andReturn('Red Spartan Faculty');
    $googleUser->shouldReceive('getId')->andReturn('google-user-123');
    $googleUser->shouldReceive('getAvatar')->andReturn($avatar);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);

    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
}

dataset('persistent account workspaces', [
    'Faculty only' => [['faculty'], 'faculty', 'faculty.dashboard'],
    'Faculty with researcher role' => [['faculty_researcher'], 'faculty', 'faculty.dashboard'],
    'Faculty Researcher' => [['faculty_researcher'], 'faculty_researcher', 'faculty.dashboard'],
    'Coordinator only' => [['research_coordinator'], 'research_office', 'research_coordinator.dashboard'],
    'Coordinator' => [['research_coordinator', 'faculty'], 'research_office', 'research_coordinator.dashboard'],
    'Coordinator using Faculty' => [['research_coordinator', 'faculty'], 'faculty', 'faculty.dashboard'],
    'Coordinator using Faculty Researcher' => [['research_coordinator', 'faculty_researcher'], 'faculty_researcher', 'faculty.dashboard'],
    'Research Head' => [['research_head'], 'research_head', 'research_head.dashboard'],
    'Research Head using Faculty' => [['research_head'], 'faculty', 'faculty.dashboard'],
    'Secretary only' => [['research_secretary'], 'research_secretary', 'research_secretary.dashboard'],
    'Secretary' => [['research_secretary', 'research_coordinator'], 'research_secretary', 'research_secretary.dashboard'],
]);

test('login screen presents institutional Google sign in and theme controls', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Continue with BatStateU Google')
        ->assertSee('@g.batstate-u.edu.ph')
        ->assertSee('auth-theme-toggle', false)
        ->assertDontSee('Forgot password?')
        ->assertDontSee('or local user');
});

test('Google sign in asks the user to choose an account', function () {
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);

    $location = $this->get('/auth/google')
        ->assertRedirect()
        ->headers->get('Location');

    expect($location)
        ->toContain('accounts.google.com')
        ->toContain('prompt=select_account')
        ->toContain('hd=g.batstate-u.edu.ph');
});

test('a Google callback with invalid state cannot authenticate or provision an account', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    $googleUser = (new Laravel\Socialite\Two\User)->map([
        'id' => 'invalid-state-google-user',
        'name' => 'Unverified callback',
        'email' => 'invalid-state@g.batstate-u.edu.ph',
        'avatar' => null,
    ]);
    $attempts = 0;
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturnUsing(function () use (&$attempts, $googleUser) {
        if ($attempts++ === 0) {
            throw new InvalidStateException;
        }

        return $googleUser;
    });
    $provider->shouldReceive('stateless')->andReturnSelf();
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
    expect(User::where('email', 'invalid-state@g.batstate-u.edu.ph')->exists())->toBeFalse();
});

test('a BatStateU Google account is provisioned as faculty and authenticated', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('faculty@g.batstate-u.edu.ph');

    $response = $this->get('/auth/google/callback');

    $response
        ->assertRedirect('/dashboard')
        ->assertCookieMissing(Auth::guard('web')->getRecallerName());
    $this->assertAuthenticated();

    $user = User::where('email', 'faculty@g.batstate-u.edu.ph')->firstOrFail();
    expect($user->google_id)->toBe('google-user-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('faculty'))->toBeTrue()
        ->and($user->getRememberToken())->toBeEmpty();
});

test('Google account switching uses the selected account even if the previous login is restored', function (bool $restored, bool $remember) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    $previous = User::factory()->create(['name' => 'Previous Research Head']);
    $previous->assignRole('research_head');
    $selected = User::factory()->create(['email' => 'selected.faculty@g.batstate-u.edu.ph']);
    $selected->assignRole('faculty');
    Auth::login($previous, remember: true);
    $previousRememberToken = $previous->fresh()->getRememberToken();
    $rememberCookieName = Auth::guard('web')->getRecallerName();
    $previousRememberCookie = app('cookie')->queued($rememberCookieName)->getValue();
    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head',
        'url.intended' => route('research_head.dashboard'),
        'workspace_resume' => ['user_id' => $previous->id, 'workspace' => 'research_head', 'url' => route('research_head.dashboard')],
        'auth.password_confirmed_at' => now()->timestamp,
    ]);
    if (! $restored) {
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    } else {
        $this->withCookie($rememberCookieName, $previousRememberCookie);
    }
    $sessionId = session()->getId();
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    config()->set('services.google.remember_login', $remember);
    mockGoogleUser($selected->email);

    $response = $this->get(route('auth.google.callback'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty')
        ->assertSessionMissing('url.intended')
        ->assertSessionMissing('workspace_resume')
        ->assertSessionMissing('auth.password_confirmed_at');

    $this->assertAuthenticatedAs($selected);
    expect(session()->getId())->not->toBe($sessionId)
        ->and($previous->fresh()->getRememberToken())->not->toBe($previousRememberToken);
    if ($remember) {
        expect(explode('|', $response->getCookie($rememberCookieName)->getValue())[0])->toBe((string) $selected->id);
    } elseif ($restored) {
        $response->assertCookieExpired($rememberCookieName);
    } else {
        $response->assertCookieMissing($rememberCookieName);
    }
    $this->get(route('faculty.dashboard'))->assertOk()
        ->assertDontSee('Previous Research Head');
    $this->get(route('research_head.dashboard'))->assertForbidden();
    $this->assertAuthenticatedAs($selected);
})->with(['logged out' => false, 'restored previous session' => true])
    ->with(['remember selected account' => true, 'session only' => false]);

test('an invalid Google callback cannot replace an existing signed-in account', function () {
    $previous = User::factory()->create();
    $this->actingAs($previous);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

    $this->assertAuthenticatedAs($previous);
});

test('switching to another multi-role account does not inherit the previous head workspace', function () {
    $this->withoutVite();
    foreach (['research_head', 'faculty', 'faculty_researcher', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $previous = User::factory()->create();
    $previous->assignRole('research_head');
    $selected = User::factory()->create(['email' => 'selected.secretary@g.batstate-u.edu.ph']);
    $selected->assignRole(['faculty', 'faculty_researcher', 'research_secretary']);
    $this->actingAs($previous)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->withCookie(WorkspacePreference::COOKIE_NAME, $previous->id.':research_head');
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser($selected->email);

    $this->get(route('auth.google.callback'))->assertRedirect(route('workspace.select'))
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);
    $this->assertAuthenticatedAs($selected);
    $this->postJson(route('workspace.store'), ['workspace' => 'research_secretary'])->assertOk();
    $this->get(route('research_secretary.dashboard'))->assertOk();
    $this->get(route('research_head.dashboard'))->assertForbidden();
    $this->get(route('dashboard'))->assertRedirect(route('research_secretary.dashboard'));
    $this->assertAuthenticatedAs($selected);
});

test('Google sign in restores the account workspace without a browser preference cookie', function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $user = User::factory()->create(['email' => 'faculty.preference@g.batstate-u.edu.ph']);
    $user->assignRole('research_head');
    $user->forceFill(['last_workspace' => 'faculty'])->save();
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser($user->email);

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, 'faculty');
    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertRedirect(route('faculty.dashboard'));
    $this->get(route('research_head.dashboard'))->assertForbidden();
});

test('Google first sign in links a proposal invitation without accepting it', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Optional project invitation']);
    $membership = $draft->members()->create([
        'name' => 'Invited faculty', 'email' => 'invited.faculty@g.batstate-u.edu.ph', 'accepted_at' => null,
    ]);
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser($membership->email);

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));
    $invitee = User::where('email', $membership->email)->sole();
    $this->assertAuthenticatedAs($invitee);
    expect($membership->fresh()->user_id)->toBe($invitee->id)
        ->and($membership->fresh()->accepted_at)->toBeNull();
    $this->get(route('faculty.proposal-drafts.show', $draft))->assertForbidden();
    $this->get(route('notifications.proposal-invitations.show', $membership))
        ->assertOk()->assertSee('Accept invitation')->assertSee('Decline invitation');
    $this->post(route('notifications.proposal-invitations.decline', $membership))->assertRedirect();
    $this->assertModelMissing($membership);
});

test('a late-signing accepted collaborator is linked and promoted for an active project', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'faculty_researcher']);

    $owner = User::factory()->create(['email' => 'owner@g.batstate-u.edu.ph']);
    $owner->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Late collaborator call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
        'created_by' => $owner->id,
    ]);
    $topic = TopicProposal::create([
        'user_id' => $owner->id,
        'research_call_id' => $call->id,
        'title' => 'Late collaborator project',
        'status' => 'approved',
        'notice_to_proceed_issued_at' => now(),
        'project_status' => 'ongoing',
    ]);
    TopicCollaborator::create([
        'topic_id' => $topic->id,
        'user_id' => null,
        'name' => 'Invited Collaborator',
        'email' => 'late.collaborator@g.batstate-u.edu.ph',
        'accepted_at' => now(),
    ]);

    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('late.collaborator@g.batstate-u.edu.ph');

    $this->get('/auth/google/callback')->assertRedirect(route('workspace.select'));

    $collaborator = User::where('email', 'late.collaborator@g.batstate-u.edu.ph')->firstOrFail();

    expect($collaborator->hasRole('faculty_researcher'))->toBeTrue()
        ->and($topic->collaborators()->sole()->user_id)->toBe($collaborator->id);
});

test('Google sign in remembers the user when persistence is enabled', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    config()->set('services.google.remember_login', true);
    mockGoogleUser('remembered@g.batstate-u.edu.ph');

    $response = $this->get('/auth/google/callback');

    $response
        ->assertRedirect('/dashboard')
        ->assertCookie(Auth::guard('web')->getRecallerName())
        ->assertCookieNotExpired(Auth::guard('web')->getRecallerName());
    $this->assertAuthenticated();

    expect(User::where('email', 'remembered@g.batstate-u.edu.ph')->firstOrFail()->getRememberToken())
        ->not->toBeNull();
});

test('a remembered Google user is restored after their session is lost', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    config()->set('services.google.remember_login', true);
    mockGoogleUser('returning@g.batstate-u.edu.ph');

    $rememberCookieName = Auth::guard('web')->getRecallerName();
    $callbackResponse = $this->get('/auth/google/callback');
    $rememberCookie = $callbackResponse->getCookie($rememberCookieName);

    expect($rememberCookie)->not->toBeNull();

    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookie($rememberCookieName, $rememberCookie->getValue())
        ->get('/dashboard')
        ->assertRedirect(route('faculty.dashboard'));

    $this->assertAuthenticated();
    expect(Auth::viaRemember())->toBeTrue();
});

test('remembered users stay in their selected workspace after session loss', function (array $roles, string $workspace, string $dashboard) {
    $this->withoutVite();
    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $user = User::factory()->create(['email' => 'returning.user@g.batstate-u.edu.ph']);
    $user->assignRole($roles);
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    config()->set('services.google.remember_login', true);
    mockGoogleUser($user->email);

    $rememberCookieName = Auth::guard('web')->getRecallerName();
    $rememberCookie = $this->get('/auth/google/callback')
        ->assertRedirect(route($user->hasMultipleWorkspaces() ? 'workspace.select' : 'dashboard'))
        ->getCookie($rememberCookieName);
    $preferenceCookie = $this->post(route('workspace.store'), ['workspace' => $workspace])
        ->assertRedirect(route($dashboard))
        ->getCookie(WorkspacePreference::COOKIE_NAME);

    expect($rememberCookie)->not->toBeNull()
        ->and($preferenceCookie)->not->toBeNull();

    $this->flushSession();
    Auth::forgetGuards();

    $this->withCookies([
        $rememberCookieName => $rememberCookie->getValue(),
        WorkspacePreference::COOKIE_NAME => $preferenceCookie->getValue(),
    ])->withCredentials()->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('workspace', $workspace)
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);

    $this->assertAuthenticatedAs($user);
    expect(Auth::viaRemember())->toBeTrue();
    Auth::forgetGuards();
    $this->get(route($dashboard))->assertOk()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
    $this->get(route('dashboard'))->assertRedirect(route($dashboard));
    $otherDashboard = in_array($workspace, ['faculty', 'faculty_researcher'], true)
        ? 'research_head.dashboard' : 'faculty.dashboard';
    $this->get(route($otherDashboard))->assertForbidden()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
    $this->get(route('dashboard'))->assertRedirect(route($dashboard));
})->with('persistent account workspaces');

test('Google sign in restores the same account workspace preference after logout', function (array $roles, string $workspace, string $dashboard) {
    $this->withoutVite();
    foreach ($roles as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $user = User::factory()->create(['email' => 'returning.user@g.batstate-u.edu.ph']);
    $user->assignRole($roles);
    $preferenceCookie = $this->actingAs($user)
        ->post(route('workspace.store'), ['workspace' => $workspace])
        ->getCookie(WorkspacePreference::COOKIE_NAME);

    expect($preferenceCookie)->not->toBeNull();

    $logoutResponse = $this->withCookie(WorkspacePreference::COOKIE_NAME, $preferenceCookie->getValue())
        ->post('/logout')->assertRedirect('/');
    expect($logoutResponse->getCookie(WorkspacePreference::COOKIE_NAME)?->isCleared() ?? false)->toBeFalse();
    $this->assertGuest();
    $this->flushSession();
    Auth::forgetGuards();
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser($user->email);

    $this->withSession([
        User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head',
        'active_role' => 'research_coordinator',
        'url.intended' => route('research_head.dashboard'),
    ])->get('/auth/google/callback')
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace)
        ->assertSessionMissing('url.intended');

    if ($user->hasRole('research_coordinator') && $user->hasAnyRole(['faculty', 'faculty_researcher'])) {
        expect(session('active_role'))->toBe($workspace === 'research_office' ? 'research_coordinator' : 'faculty');
    } else {
        expect(session('active_role'))->toBeNull();
    }
    $this->assertAuthenticatedAs($user);
    $this->get(route('dashboard'))->assertRedirect(route($dashboard));
    $this->get(route($dashboard))->assertOk()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
})->with('persistent account workspaces');

test('Google sign in ignores foreign malformed and unavailable workspace preferences', function (bool $foreignAccount, string $workspace) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'research_head']);
    $head = User::factory()->create(['email' => 'head.preference@g.batstate-u.edu.ph']);
    $head->assignRole('research_head');
    $accountId = $foreignAccount ? User::factory()->create()->id : $head->id;
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser($head->email);

    $this->withCookie(WorkspacePreference::COOKIE_NAME, $accountId.':'.$workspace)
        ->get('/auth/google/callback')
        ->assertRedirect(route('workspace.select'))
        ->assertSessionMissing(User::ACTIVE_WORKSPACE_SESSION_KEY);

    $this->assertAuthenticatedAs($head);
    $this->get(route('faculty.dashboard'))->assertForbidden();
    expect($head->fresh()->getRoleNames()->all())->toBe(['research_head']);
})->with([
    'another account' => [true, 'faculty'],
    'malformed payload' => [false, 'faculty:unexpected'],
    'unavailable workspace' => [false, 'faculty_researcher'],
]);

test('a Google avatar URL longer than 255 characters is stored', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    $avatar = 'https://lh3.googleusercontent.com/a/'.str_repeat('a', 300).'=s96-c';
    mockGoogleUser('long-avatar@g.batstate-u.edu.ph', $avatar);

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    expect(strlen($avatar))->toBeGreaterThan(255)
        ->and(User::where('email', 'long-avatar@g.batstate-u.edu.ph')->firstOrFail()->avatar)
        ->toBe($avatar);
});

test('Google accounts outside the institutional domain are rejected', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('personal@gmail.com');

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'personal@gmail.com']);
});

test('an existing institutional user keeps their assigned system role', function () {
    Role::firstOrCreate(['name' => 'research_head']);
    $head = User::factory()->create(['email' => 'head@g.batstate-u.edu.ph']);
    $head->assignRole('research_head');

    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('head@g.batstate-u.edu.ph');

    $this->get('/auth/google/callback')->assertRedirect(route('workspace.select'));

    expect($head->fresh()->hasRole('research_head'))->toBeTrue()
        ->and($head->fresh()->google_id)->toBe('google-user-123');
});

test('Google sign in ignores a saved page for another role', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create(['email' => 'faculty@g.batstate-u.edu.ph']);
    $faculty->assignRole('faculty');

    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('faculty@g.batstate-u.edu.ph');

    $this->withSession(['url.intended' => route('research_head.dashboard')])
        ->get('/auth/google/callback')
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('url.intended');
});

test('users can logout', function () {
    $user = User::factory()->create();
    Auth::login($user, remember: true);

    $rememberToken = $user->fresh()->getRememberToken();
    $rememberCookieName = Auth::guard('web')->getRecallerName();
    $rememberCookieValue = app('cookie')->queued($rememberCookieName)->getValue();

    $this->withCookie($rememberCookieName, $rememberCookieValue)
        ->post('/logout')
        ->assertRedirect('/')
        ->assertCookieExpired($rememberCookieName);

    $this->assertGuest();
    expect($user->fresh()->getRememberToken())->not->toBe($rememberToken);
});

test('local account registration and password endpoints are unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
    $this->post('/login')->assertMethodNotAllowed();
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password')->assertNotFound();
    $this->get('/reset-password/token')->assertNotFound();
    $this->put('/password')->assertNotFound();
    $this->get('/verify-email')->assertNotFound();
});

test('a failed Google account change shows the error and current identity without signing in another account', function (string $failure) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $current = User::factory()->create(['name' => 'Current signed-in person']);
    $current->assignRole('faculty');
    $this->actingAs($current);
    if ($failure === 'outside institution') {
        config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
        mockGoogleUser('personal@gmail.com');
    } else {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andThrow($failure === 'invalid state' ? new InvalidStateException : new RuntimeException($failure));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    $this->followingRedirects()->get(route('auth.google.callback'))
        ->assertOk()->assertSee('Continue with BatStateU Google')
        ->assertSee('Signed in as')->assertSee($current->name)
        ->assertSee($failure === 'outside institution' ? 'Use your official' : 'Google sign-in could not be completed');
    $this->assertAuthenticatedAs($current);
})->with(['invalid state', 'access denied', 'provider unavailable', 'outside institution']);

test('a signed-in user can start a new Google account choice without changing identity yet', function () {
    $current = User::factory()->create();
    $this->actingAs($current);
    config()->set('services.google.client_id', 'google-client-id');
    config()->set('services.google.client_secret', 'google-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    $location = $this->get(route('auth.google.redirect'))->assertRedirect()->headers->get('Location');

    expect($location)->toContain('accounts.google.com', 'prompt=select_account');
    $this->assertAuthenticatedAs($current);
    expect(session('state'))->not->toBeEmpty();
});

test('an older Google callback preserves the newer sign-in and a replay cannot replace its account', function (mixed $olderState) {
    $this->withoutVite();
    $latestState = str_repeat('n', 40);
    $requests = [];
    $handler = HandlerStack::create(new MockHandler([
        new GoogleResponse(200, [], json_encode(['access_token' => 'test-token', 'expires_in' => 3600, 'token_type' => 'Bearer'])),
        new GoogleResponse(200, [], json_encode(['sub' => 'latest-google-account', 'name' => 'Latest selected person', 'email' => 'latest@g.batstate-u.edu.ph', 'email_verified' => true])),
    ]));
    $handler->push(Middleware::history($requests));
    $client = new Client(['handler' => $handler]);
    Socialite::shouldReceive('driver')->with('google')->andReturnUsing(fn () => (new GoogleProvider(request(), 'client-id', 'client-secret', route('auth.google.callback')))->setHttpClient($client));
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);

    $this->withSession(['state' => $latestState])
        ->get(route('auth.google.callback', ['state' => $olderState]))
        ->assertRedirect(route('login'))->assertSessionHas('state', $latestState);
    $this->assertGuest();
    expect($requests)->toBeEmpty();
    $this->get(route('auth.google.callback', ['state' => $latestState, 'code' => 'latest-code']))->assertRedirect(route('dashboard'));
    $selected = User::where('email', 'latest@g.batstate-u.edu.ph')->firstOrFail();
    $this->assertAuthenticatedAs($selected);
    expect($requests)->toHaveCount(2);
    $this->get(route('auth.google.callback', ['state' => $latestState, 'code' => 'latest-code']))->assertRedirect(route('login'));
    $this->assertAuthenticatedAs($selected);
    expect($requests)->toHaveCount(2);
})->with(['older state' => ['older-state'], 'missing state' => [null], 'malformed state' => [['unexpected']]]);

test('session expiry after switching accounts restores only the new remember cookie', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $previous = User::factory()->create();
    $previous->assignRole('faculty');
    Auth::login($previous, remember: true);
    $cookieName = Auth::guard('web')->getRecallerName();
    $oldCookie = app('cookie')->queued($cookieName)->getValue();
    $this->withCookie($cookieName, $oldCookie)->post(route('logout'))->assertRedirect('/');
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    config()->set('services.google.remember_login', true);
    mockGoogleUser('new.remembered@g.batstate-u.edu.ph');
    $newCookie = $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'))->getCookie($cookieName)->getValue();
    $selected = User::where('email', 'new.remembered@g.batstate-u.edu.ph')->firstOrFail();

    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($cookieName, $newCookie)->get(route('dashboard'))->assertRedirect(route('faculty.dashboard'));
    $this->assertAuthenticatedAs($selected);
    $this->flushSession();
    Auth::forgetGuards();
    $this->withCookie($cookieName, $oldCookie)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the dashboard can privately verify the current account and workspace for an older browser tab', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $current = User::factory()->create();
    $current->assignRole('faculty');
    $this->actingAs($current)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
        ->getJson(route('dashboard'))->assertOk()->assertJsonPath('user_id', $current->id)
        ->assertJsonPath('workspace', 'faculty')->assertHeader('Cache-Control', 'no-store, private');
    $this->post(route('logout'))->assertRedirect('/');
    $this->getJson(route('dashboard'))->assertUnauthorized()->assertJsonMissingPath('user_id');
});

test('a Google callback holds the session lock while resolving the selected account', function () {
    $googleUser = (new Laravel\Socialite\Two\User)->map(['id' => 'locked-callback-user', 'name' => 'Selected account', 'email' => 'locked@g.batstate-u.edu.ph']);
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturnUsing(function () use ($googleUser) {
        $otherRequest = Cache::lock('session:'.request()->session()->getId(), 60);
        $acquired = $otherRequest->get();
        if ($acquired) {
            $otherRequest->release();
        }
        expect($acquired)->toBeFalse();

        return $googleUser;
    });
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);

    $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));
    foreach (['auth.google.redirect', 'auth.google.callback', 'logout'] as $name) {
        expect(Route::getRoutes()->getByName($name)->locksFor())->toBe(60);
    }
    expect(config('services.google.guzzle.timeout'))->toBeGreaterThan(0)
        ->toBeLessThan(Route::getRoutes()->getByName('auth.google.callback')->locksFor() / 2)
        ->and(config('services.google.guzzle.connect_timeout'))->toBeGreaterThan(0)
        ->toBeLessThan(config('services.google.guzzle.timeout'));
});

test('two accounts in the same workspace cannot reuse the same persisted sidebar', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $keys = [];
    foreach (User::factory()->count(2)->create() as $user) {
        $user->assignRole('faculty');
        $html = $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
            ->get(route('faculty.dashboard'))->assertOk()->getContent();
        expect(preg_match('/x-persist="(app-sidebar-[^"]+)"/u', $html, $matches))->toBe(1);
        $keys[] = $matches[1];
    }
    expect($keys[0])->not->toBe($keys[1]);
});
