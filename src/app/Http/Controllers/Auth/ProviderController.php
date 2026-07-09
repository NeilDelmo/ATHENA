<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InstitutionalEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;

class ProviderController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->with(['hd' => config('services.google.allowed_domains.0')])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            try {
                $googleUser = Socialite::driver('google')->user();
            } catch (InvalidStateException $e) {
                $googleUser = Socialite::driver('google')->stateless()->user();
            }

            $email = strtolower(trim((string) $googleUser->getEmail()));

            if (! InstitutionalEmail::isAllowed($email, config('services.google.allowed_domains', []))) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'google' => 'Use your official @g.batstate-u.edu.ph Google account to access ATHENA.',
                    ]);
            }

            $user = $this->findOrCreateGoogleUser($googleUser, $email);

            if (! $user->roles()->exists()) {
                $user->assignRole(Role::firstOrCreate(['name' => 'faculty']));
            }

            Auth::login($user);
            $request->session()->regenerate();

            // Always let the application choose the correct dashboard for the
            // authenticated user's role. An intended URL may point to a page
            // protected by a different role and would otherwise cause a 403
            // immediately after a successful sign-in.
            $request->session()->forget('url.intended');

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            Log::warning('Google authentication failed.', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return redirect()
                ->route('login')
                ->withErrors(['google' => 'Google sign-in could not be completed. Please try again.']);
        }
    }

    private function findOrCreateGoogleUser(SocialiteUser $googleUser, string $email): User
    {
        return User::updateOrCreate([
            'email' => $email,
        ], [
            'name' => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
        ]);
    }
}
