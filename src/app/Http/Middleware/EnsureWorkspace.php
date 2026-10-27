<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\WorkspacePreference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspace
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $workspaces): Response
    {
        $allowedWorkspaces = explode('|', $workspaces);
        $user = $request->user();
        if ($user && $user->selectedWorkspace() === null
            && $request->session()->get(User::WORKSPACE_RECOVERY_SESSION_KEY) === $user->getAuthIdentifier()
            && $request->isMethod('GET') && ! $request->expectsJson()) {
            $available = array_values(array_intersect($allowedWorkspaces, $user->availableWorkspaceKeys()));
            if (count($available) === 1) {
                WorkspacePreference::select($request, $available[0]);
            } elseif (count($available) > 1) {
                return redirect()->route('workspace.select');
            }
        }

        abort_unless(
            $user?->isUsingWorkspace($allowedWorkspaces),
            403,
        );

        return $next($request);
    }
}
