<?php

namespace App\Http\Controllers\Auth;

use App\Actions\LinkProposalDraftMemberships;
use App\Actions\LinkTopicCollaborators;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\InstitutionalEmail;
use App\Support\WorkspacePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;

class ProviderController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')
            ->with([
                'hd' => config('services.google.allowed_domains.0'),
                'prompt' => 'select_account',
            ])
            ->redirect();
    }

    public function handleGoogleCallback(
        Request $request,
        LinkProposalDraftMemberships $linkProposalDraftMemberships,
        LinkTopicCollaborators $linkTopicCollaborators,
    ): RedirectResponse {
        try {
            $googleUser = Socialite::driver('google')->user();

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

            try {
                $linkProposalDraftMemberships->handle($user);
                $linkTopicCollaborators->handle($user);
            } catch (\Throwable $exception) {
                report($exception);
            }

            Auth::login($user, (bool) config('services.google.remember_login', false));
            $request->session()->regenerate();

            // An intended URL may require another workspace or belong to a previous account.
            $request->session()->forget([
                'url.intended',
                'active_role',
                User::ACTIVE_WORKSPACE_SESSION_KEY,
                User::WORKSPACE_RECOVERY_SESSION_KEY,
            ]);

            if (WorkspacePreference::restore($request)) {
                return redirect()->route('dashboard');
            }

            if ($user->hasMultipleWorkspaces()) {
                return redirect()->route('workspace.select');
            }

            if ($workspace = $user->availableWorkspaceKeys()[0] ?? null) {
                $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
            }

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
