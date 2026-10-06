<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

uses(TestCase::class);

test('Research Head overview shows truthful activity and project-health summaries with working drilldowns', function (bool $hasData) {
    $emptyRows = fn () => new LengthAwarePaginator(collect(), 0, 4);
    $analytics = [
        'kpis' => ['review' => 0, 'delayed' => $hasData ? 1 : 0, 'active' => $hasData ? 3 : 0, 'completed' => $hasData ? 1 : 0, 'faculty' => 0],
        'pipeline' => collect([['key' => 'awaiting_review', 'label' => 'Awaiting review', 'count' => 0], ['key' => 'lrec', 'label' => 'LREC', 'count' => 0]]),
        'trend' => collect(range(4, 9))->map(fn ($month) => ['key' => sprintf('2026-%02d', $month), 'label' => sprintf('2026-%02d', $month), 'new' => $hasData ? $month - 3 : 0, 'revision' => $hasData ? 1 : 0]),
        'periodAvailable' => true, 'targetMatches' => false,
        'projectStatuses' => collect([
            ['key' => 'ongoing', 'label' => 'Ongoing', 'count' => $hasData ? 2 : 0],
            ['key' => 'delayed', 'label' => 'Delayed / overdue', 'count' => $hasData ? 1 : 0],
            ['key' => 'awaiting', 'label' => 'Awaiting required report / review', 'count' => 0],
            ['key' => 'completed', 'label' => 'Completed', 'count' => $hasData ? 1 : 0],
        ]),
        'budget' => ['percentage' => null],
    ];
    $html = view('livewire.research-head-overview', [
        'academicYear' => '2026-2027', 'fromDate' => '', 'toDate' => '', 'pipeline' => 'awaiting_review',
        'errors' => new ViewErrorBag, 'academicYears' => collect(['2026-2027']), 'analytics' => $analytics, 'topics' => $emptyRows(),
        'attentionItems' => $emptyRows(), 'reportItems' => $emptyRows(), 'deadlines' => collect(),
    ])->render();
    expect($html)->toContain('Submission activity', 'Project health', 'View analytics', 'View all proposals')
        ->toContain(e(route('research_head.analytics', ['academicYear' => '2026-2027', 'projectStatus' => 'delayed'])))
        ->not->toContain('NAN', 'INF', 'packages');
    if ($hasData) {
        expect($html)->toContain('27 submissions.', 'Revision submissions', '4 issued projects.')
            ->toContain(e(route('research_head.analytics', ['academicYear' => '2026-2027', 'submissionMonth' => '2026-09'])))
            ->toContain('stroke-dasharray="50 50"');
    } else {
        expect($html)->toContain('No submission activity recorded', 'Projects appear here once')
            ->not->toContain('data-dashboard-activity-chart');
    }
    if (getenv('ATHENA_EXPORT_DASHBOARD_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-overview-'.($hasData ? 'populated' : 'empty').'.html'), $html);
    }
})->with([true, false]);
