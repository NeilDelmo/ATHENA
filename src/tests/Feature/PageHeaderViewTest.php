<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

test('page header standardizes workspace title subtitle and responsive actions', function () {
    $html = Blade::render(<<<'BLADE'
        <x-page-header title="Research Calls" subtitle="Manage published schedules.">
            <x-slot name="actions"><button type="button">Create new call</button></x-slot>
        </x-page-header>
        BLADE);

    expect($html)
        ->toContain('<h1', 'text-2xl font-black tracking-tight text-slate-950 dark:text-white', 'Manage published schedules.', 'sm:flex-row sm:flex-wrap', 'Create new call');
});

test('regular workspace pages use the shared page header', function () {
    $views = [
        'announcement_images/index.blade.php',
        'topics/show.blade.php',
        'topics/file-annotations.blade.php',
        'profile/edit.blade.php',
        'research_secretary/projects/budget.blade.php',
        'research_head/signatories.blade.php',
        'research_head/proposal-submissions/index.blade.php',
        'research_head/projects/index.blade.php',
        'research_head/faculty-directory.blade.php',
        'research_head/proposal_templates/index.blade.php',
        'research_head/assistant_knowledge/index.blade.php',
        'research_head/topics/files.blade.php',
        'research_coordinator/faculty-members.blade.php',
        'research_calls/index.blade.php',
        'research_calls/faculty-index.blade.php',
        'research_calls/calendar-detail.blade.php',
        'faculty/topics/create.blade.php',
        'faculty/proposal-drafts/index.blade.php',
        'faculty/proposal-drafts/create.blade.php',
        'faculty/proposal-drafts/show.blade.php',
        'faculty/proposal-drafts/review.blade.php',
        'faculty/proposal-drafts/history.blade.php',
        'faculty/proposal-drafts/signatories.blade.php',
        'faculty/proposal-drafts/details/edit.blade.php',
        'faculty/proposal-drafts/work-plan/edit.blade.php',
        'faculty/proposal-drafts/detailed-proposal/edit.blade.php',
        'faculty/proposal-drafts/line-item-budget/edit.blade.php',
        'faculty/proposal-drafts/expense-breakdown/edit.blade.php',
        'faculty/proposal-drafts/curriculum-vitae/edit.blade.php',
        'faculty/proposal-drafts/papers/edit.blade.php',
        'faculty/proposal-drafts/gad-checklist/show.blade.php',
        'faculty/proposal-drafts/initial-screening-form/show.blade.php',
        'faculty/research_support/index.blade.php',
        'faculty/monitoring-tools/create.blade.php',
        'faculty/progress-reports/create.blade.php',
        'research/index.blade.php',
        'research/show.blade.php',
        'research/dissemination.blade.php',
    ];

    foreach ($views as $view) {
        expect(File::get(resource_path('views/'.$view)))->toContain('<x-page-header');
    }

    $researchCalls = File::get(resource_path('views/research_calls/index.blade.php'));

    expect(str_contains($researchCalls, 'Research Office'))->toBeFalse();
});
