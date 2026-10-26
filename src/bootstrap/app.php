<?php

use App\Http\Middleware\EnsureWorkspace;
use App\Http\Middleware\HandleFileUploads;
use App\Http\Middleware\MeasureRequestPerformance;
use App\Http\Middleware\RememberWorkspacePage;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [MeasureRequestPerformance::class], append: [RememberWorkspacePage::class, HandleFileUploads::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, RememberWorkspacePage::class);
        $middleware->appendToPriorityList(SubstituteBindings::class, EnsureWorkspace::class);
        $middleware->appendToPriorityList([EnsureWorkspace::class, SubstituteBindings::class, Authorize::class], HandleFileUploads::class);
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'workspace' => EnsureWorkspace::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->expectsJson()
                || $request->is('api/*')
                || $request->routeIs('research-support.chat')
                || $request->routeIs('research-manuals.guidance-check')
                || $request->routeIs('research-support.proposal-changes.resolve')
                || $request->routeIs('research-support.report-changes.resolve')
                || $request->routeIs('research-support.history', 'research-support.history.*', 'research-support.contexts')
                || $request->routeIs(
                    'research-support.literature-*',
                    'research-support.conference-search',
                    'research-support.journal-search',
                    'faculty.proposal-drafts.literature-sources.*',
                    'faculty.proposal-drafts.literature-drafts.*',
                    'faculty.proposal-drafts.literature-evidence.*',
                    'faculty.proposal-drafts.literature-evidence-assistance',
                )
                || $request->routeIs('faculty.work-plans.*')
                || $request->routeIs(
                    'faculty.proposal-drafts.work-plan.preview',
                    'faculty.proposal-drafts.work-plan.download',
                ),
        );
    })->create();
