<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\WorkspaceLocation;
use App\Support\WorkspacePreference;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RememberWorkspacePage
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        WorkspacePreference::restore($request);
        $user = $request->user();
        if ($user && $user->selectedWorkspace() === null && $user->hasMultipleWorkspaces() && Auth::viaRemember()) {
            $request->session()->put(User::WORKSPACE_RECOVERY_SESSION_KEY, $user->getAuthIdentifier());
        }
        if ($request->isMethod('GET') && ! $request->expectsJson() && $request->routeIs('dashboard')
            && $user && $user->selectedWorkspace() === null
            && $request->session()->get(User::WORKSPACE_RECOVERY_SESSION_KEY) === $user->getAuthIdentifier()) {
            return redirect()->route('workspace.select');
        }
        $pending = $request->session()->get('workspace_resume');
        $resuming = $request->isMethod('GET') && is_array($pending)
            && ($pending['user_id'] ?? null) === $request->user()?->id
            && strtok($pending['url'] ?? '', '#') === $request->fullUrl();
        if ($resuming) {
            $request->session()->forget('workspace_resume');
        }
        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            if (! $resuming || ! ($exception instanceof AuthorizationException || $exception instanceof ModelNotFoundException
                || ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [403, 404], true)))) {
                throw $exception;
            }

            return $this->fallback($request, $pending['workspace']);
        }
        if ($resuming && in_array($response->getStatusCode(), [403, 404], true)) {
            return $this->fallback($request, $pending['workspace']);
        }
        $workspace = $request->user()?->activeWorkspace();
        $route = $request->route();
        $isWorkspacePage = $route && collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_starts_with($middleware, 'workspace:'));
        if ($workspace && $isWorkspacePage && $request->isMethod('GET') && ! $request->expectsJson()
            && $response->isSuccessful() && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->boolean('revision_embed') && ! preg_match('/(?:preview|download|edit-state|\.files\.|\.photos\.)/', (string) $route->getName())) {
            $request->session()->put(WorkspaceLocation::key($request->user()->id, $workspace), ['url' => $request->fullUrl(), 'saved_at' => time()]);
        }

        WorkspacePreference::remember($request);

        return $response;
    }

    private function fallback(Request $request, string $workspace): Response
    {
        $request->session()->forget(WorkspaceLocation::key($request->user()->id, $workspace));

        return redirect()->route($request->user()->dashboardRouteName($workspace))
            ->with('status', 'Your previous page is no longer available. Your workspace dashboard is open.');
    }
}
