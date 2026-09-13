<?php

use App\Http\Middleware\EnsureWorkspace;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
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
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'workspace' => EnsureWorkspace::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->routeIs('research-support.chat')
                || ($request->expectsJson() && $request->routeIs('research.dissemination.*'))
                || $request->routeIs(
                    'research-support.literature-*',
                    'research-support.conference-search',
                    'faculty.proposal-drafts.literature-sources.*',
                    'faculty.proposal-drafts.literature-drafts.*',
                )
                || $request->routeIs('faculty.work-plans.*')
                || $request->routeIs(
                    'faculty.proposal-drafts.work-plan.preview',
                    'faculty.proposal-drafts.work-plan.download',
                )
                || ($request->expectsJson() && $request->routeIs(
                    'topics.versions.files.annotations.store',
                    'topics.versions.files.annotations.update',
                    'topics.versions.files.annotations.destroy',
                    'faculty.proposal-drafts.details.update',
                    'faculty.proposal-drafts.revision-files.store',
                    'faculty.proposal-drafts.detailed-proposal.download',
                    'faculty.proposal-drafts.curriculum-vitae.download',
                    'faculty.proposal-drafts.detailed-proposal.update',
                    'faculty.proposal-drafts.work-plan.update',
                    'faculty.proposal-drafts.line-item-budget.update',
                    'faculty.proposal-drafts.line-item-budget.preview',
                    'faculty.proposal-drafts.line-item-budget.download',
                    'faculty.proposal-drafts.expense-breakdown.update',
                    'faculty.proposal-drafts.expense-breakdown.preview',
                    'faculty.proposal-drafts.expense-breakdown.download',
                    'faculty.proposal-drafts.curriculum-vitae.update',
                    'project-progress.draft',
                    'project-narrative-reports.draft',
                    'project-narrative-reports.preview',
                    'project-progress.preview',
                    'research_head.topics.notice-to-proceed.store',
                )),
        );
    })->create();
