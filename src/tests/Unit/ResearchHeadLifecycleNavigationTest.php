<?php

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

uses(TestCase::class);

test('Research Head sidebar separates lifecycle destinations and exposes a report review badge', function () {
    $html = Blade::render('<x-research-head-navigation :report-review-count="4" />');
    $labels = ['Overview', 'Dashboard', 'Calendar', 'Analytics', 'Submission', 'Research calls', 'Received submissions', 'Review', 'Proposal reviews', 'Report reviews', 'Monitoring', 'Research projects', 'Completed projects', 'Faculty directory', 'Resources', 'Signatories'];
    $offset = 0;
    foreach ($labels as $label) {
        $position = strpos($html, $label, $offset);
        expect($position)->not->toBeFalse();
        $offset = $position + strlen($label);
    }
    expect($html)->toContain('4 reports awaiting review', 'wire:current.exact', 'whitespace-nowrap')
        ->not->toContain('Knowledge base', 'Administration', 'assistant-knowledge', 'Templates', 'research-head/proposal-templates');
    foreach (['received-submissions', 'report-reviews', 'completed-projects'] as $destination) {
        $route = Route::getRoutes()->getByName('research_head.'.$destination.'.index');
        expect($route)->not->toBeNull()->and($route->gatherMiddleware())->toContain('auth', 'workspace:research_head');
        expect($html)->toContain(e(route($route->getName())));
    }
    if (getenv('ATHENA_EXPORT_REPORT_LAYOUT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/head-lifecycle-sidebar.html'), $html);
    }
});

test('report review queue renders specific report links and honest empty states', function (bool $populated) {
    $rows = $populated ? collect(['quarterly', 'progress', 'terminal'])->map(fn ($type, $index) => (object) [
        'id' => $index + 10, 'topic_id' => 29, 'title' => 'Coastal resilience and community research', 'faculty_name' => 'Faculty Example', 'report_type' => $type, 'review_status' => 'pending', 'received_at' => '2026-09-25 12:00:00', 'report_date' => '2026-09-24',
    ]) : collect();
    $html = view('research_head.report-reviews.queue', [
        'reports' => new LengthAwarePaginator($rows, $rows->count(), 15), 'pendingByType' => collect(['quarterly' => $populated ? 1 : 0, 'progress' => $populated ? 1 : 0, 'terminal' => $populated ? 1 : 0]), 'search' => '', 'status' => 'pending', 'type' => '',
    ])->render();
    expect($html)->toContain('Reports awaiting review', 'Oldest submissions first.', 'All report types', 'Filter', 'Reset')->not->toContain('target="_blank"');
    if ($populated) {
        expect($html)->toContain('#monitoring-tool-10', '#narrative-report-11', '#narrative-report-12', 'Review report');
    } else {
        expect($html)->toContain('No reports awaiting review')->not->toContain('Review report</a>');
    }
    if (getenv('ATHENA_EXPORT_REPORT_LAYOUT') === '1') {
        File::put(storage_path('framework/testing/head-reports-'.($populated ? 'populated' : 'empty').'.html'), $html);
    }
})->with([true, false]);
