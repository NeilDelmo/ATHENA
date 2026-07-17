<?php

namespace App\Http\Middleware;

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

        abort_unless(
            $request->user()?->isUsingWorkspace($allowedWorkspaces),
            403,
        );

        return $next($request);
    }
}
