<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MeasureRequestPerformance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('workspace.store', 'role-selection.store', 'search.index', 'research.index', 'research.show',
            'research_head.projects.index', 'research_head.completed-projects.index', '*.projects.workspace.documents',
            'topics.show', 'notifications.index', 'faculty.proposal-drafts.index')) {
            return $next($request);
        }

        $startedAt = hrtime(true);
        $metrics = (object) ['query_count' => 0, 'database_ms' => 0.0];
        $request->attributes->set('athena.request_performance', $metrics);
        try {
            $response = $next($request);
            if (! $request->user()) {
                return $response;
            }
            $duration = round((hrtime(true) - $startedAt) / 1000000, 2);
            $database = round($metrics->database_ms, 2);
            $timing = sprintf('app;dur=%.2f, db;dur=%.2f;desc="%d queries"', $duration, $database, $metrics->query_count);
            $existing = $response->headers->get('Server-Timing');
            $response->headers->set('Server-Timing', $existing ? $existing.', '.$timing : $timing);
            try {
                Log::channel('performance')->info('ATHENA request performance', [
                    'route' => $request->route()->getName(), 'method' => $request->method(),
                    'workspace' => $request->user()->activeWorkspace(), 'status' => $response->getStatusCode(),
                    'duration_ms' => $duration, 'database_ms' => $database, 'query_count' => $metrics->query_count,
                ]);
            } catch (Throwable) {
                // Recording timings must not interrupt the user's work.
            }

            return $response;
        } finally {
            $request->attributes->remove('athena.request_performance');
        }
    }
}
