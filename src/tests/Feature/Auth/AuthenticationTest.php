<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

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

test('a BatStateU Google account is provisioned as faculty and authenticated', function () {
    config()->set('services.google.allowed_domains', ['g.batstate-u.edu.ph']);
    mockGoogleUser('faculty@g.batstate-u.edu.ph');

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated();

    $user = User::where('email', 'faculty@g.batstate-u.edu.ph')->firstOrFail();
    expect($user->google_id)->toBe('google-user-123')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('faculty'))->toBeTrue();
});

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

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

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

    $this->actingAs($user)->post('/logout')->assertRedirect('/');

    $this->assertGuest();
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
