<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class WorkspacePreference
{
    public const COOKIE_NAME = 'athena_workspace';

    public static function restore(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        $savedWorkspace = $user->last_workspace;
        if (is_string($savedWorkspace) && $user->canUseWorkspace($savedWorkspace)) {
            if ($user->selectedWorkspace() === $savedWorkspace) {
                return false;
            }
            self::select($request, $savedWorkspace);

            return true;
        }
        $preference = $request->cookie(self::COOKIE_NAME);
        if ($user->selectedWorkspace() !== null || ! is_string($preference)) {
            return false;
        }
        $parts = explode(':', $preference, 2);
        if (count($parts) !== 2 || $parts[0] !== (string) $user->getAuthIdentifier() || ! $user->canUseWorkspace($parts[1])) {
            return false;
        }

        self::select($request, $parts[1]);

        return true;
    }

    public static function select(Request $request, string $workspace, bool $persist = false): void
    {
        $user = $request->user();
        abort_unless($user?->canUseWorkspace($workspace), 403);

        if ($persist && $user->last_workspace !== $workspace) {
            $user->forceFill(['last_workspace' => $workspace])->save();
        }
        $request->session()->put(User::ACTIVE_WORKSPACE_SESSION_KEY, $workspace);
        $request->session()->forget(User::WORKSPACE_RECOVERY_SESSION_KEY);
        if ($user->hasRole('research_coordinator') && $user->hasAnyRole(['faculty', 'faculty_researcher'])
            && in_array($workspace, [User::WORKSPACE_RESEARCH_OFFICE, User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER], true)) {
            $request->session()->put('active_role', $workspace === User::WORKSPACE_RESEARCH_OFFICE ? 'research_coordinator' : 'faculty');
        }
    }

    public static function remember(Request $request): void
    {
        $user = $request->user();
        $workspace = $user?->selectedWorkspace();
        if ($workspace === null) {
            return;
        }
        $request->session()->forget(User::WORKSPACE_RECOVERY_SESSION_KEY);
        $preference = $user->getAuthIdentifier().':'.$workspace;
        if ($request->cookie(self::COOKIE_NAME) !== $preference) {
            Cookie::queue(Cookie::forever(self::COOKIE_NAME, $preference));
        }
    }
}
