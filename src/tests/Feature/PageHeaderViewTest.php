<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

test('page header standardizes workspace title subtitle and responsive actions', function () {
    $html = Blade::render(<<<'BLADE'
        <x-page-header title="Research Calls" subtitle="Manage published schedules.">
            <x-slot name="actions"><button type="button">Create new call</button></x-slot>
        </x-page-header>
        BLADE);

    expect($html)
        ->toContain('data-page-header-variant="simple"', '<h1', 'text-2xl font-black tracking-tight text-slate-950 dark:text-white', 'Manage published schedules.', 'sm:flex-row sm:flex-wrap', 'Create new call')
        ->not->toContain('data-workspace-header-banner', 'border-l-4', 'uppercase');
});

test('hero headers keep dashboard spacing and typography with transparent content and existing actions', function () {
    $html = Blade::render(<<<'BLADE'
        <x-page-header container>
            <x-page-header variant="hero" eyebrow="Faculty workspace" title="Faculty research" subtitle="Welcome back.">
                <x-slot:actions><a href="/existing-action">New proposal</a></x-slot:actions>
            </x-page-header>
        </x-page-header>
        BLADE);

    expect($html)->toContain('data-page-header-variant="hero"', 'border-l-4 border-[#800000]',
        '!bg-transparent', 'dark:!bg-transparent', 'px-5 py-4', 'text-[10px] font-bold uppercase tracking-[0.2em]',
        'text-xl font-bold tracking-tight text-slate-950 sm:text-2xl', 'Welcome back.', 'href="/existing-action"');
});

test('page header container preserves padding and places all content above the decoration', function () {
    $html = Blade::render(<<<'BLADE'
        <x-page-header container>
            <x-page-header title="Research Calls" subtitle="Manage published schedules.">
                <x-slot name="actions"><button type="button">Create new call</button></x-slot>
            </x-page-header>
        </x-page-header>
        BLADE);

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//header[@data-page-header-container]')->length)->toBe(1)
        ->and($xpath->query('//header/div[contains(@class, "relative z-[1]")]//h1')->length)->toBe(1)
        ->and($xpath->query('//header/div[contains(@class, "relative z-[1]")]//button')->length)->toBe(1)
        ->and($html)->toContain('relative overflow-hidden', 'py-6 px-4 sm:px-6 lg:px-8', 'bg-white', 'dark:bg-slate-900');
});

test('banner headings retain their typography attributes and actions through the shared component', function () {
    $html = Blade::render(<<<'BLADE'
        <x-workspace-header-banner class="!pt-0" data-custom-header eyebrow="Research Head" title="Dashboard" description="Your review queue.">
            <x-slot name="actions"><a href="/existing-action">Existing action</a></x-slot>
        </x-workspace-header-banner>
        BLADE);

    expect($html)->toContain('data-workspace-header-banner', 'data-custom-header', '!pt-0', 'border-l-4 border-[#800000]',
        'text-xl font-bold tracking-tight text-slate-950 sm:text-2xl', 'Research Head', 'Dashboard', 'Your review queue.',
        'href="/existing-action"', 'Existing action');
});

test('every workspace dashboard renders one decorated page header beneath the untouched topbar', function (string $workspace, string $role, string $routeName, string $title) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => $role]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route($routeName));

    $response->assertOk()->assertSee($title)->assertSee('data-page-header-container', false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->query('//header[@data-page-header-container]')->length)->toBe(1)
        ->and($xpath->query('//nav[@data-app-topbar][contains(@class, "athena-page-header")]')->length)->toBe(0)
        ->and($xpath->query('//header[@data-page-header-container]/preceding-sibling::nav[@data-app-topbar]')->length)->toBe(1)
        ->and($xpath->query('//header[@data-page-header-container]/div[contains(@class, "relative z-[1]")]//*[@data-page-header-variant="hero"]')->length)->toBe(1)
        ->and($xpath->query('//header[@data-page-header-container]//*[@data-page-header-variant="simple"]')->length)->toBe(0);
})->with([
    'Research Head' => ['research_head', 'research_head', 'research_head.dashboard', 'Dashboard'],
    'Research Office' => ['research_office', 'research_coordinator', 'research_coordinator.dashboard', 'Research Office Dashboard'],
    'Faculty' => ['faculty', 'faculty', 'faculty.dashboard', 'Faculty research'],
    'Faculty Researcher' => ['faculty_researcher', 'faculty_researcher', 'faculty.dashboard', 'Your research at a glance'],
]);

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
        'research_head/topics/files.blade.php',
        'research_head/analytics.blade.php',
        'research_head/calendar.blade.php',
        'research_coordinator/faculty-members.blade.php',
        'research_calls/index.blade.php',
        'research_calls/faculty-index.blade.php',
        'research_calls/calendar-detail.blade.php',
        'faculty/topics/create.blade.php',
        'faculty/calendar.blade.php',
        'faculty/proposal-drafts/index.blade.php',
        'faculty/submissions.blade.php',
        'notifications/index.blade.php',
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
        expect(File::get(resource_path('views/'.$view)))
            ->toContain('<x-page-header')
            ->not->toContain('<x-workspace-header-banner', 'variant="hero"', 'variant="banner"');
    }

    foreach (['faculty/proposal-drafts/index.blade.php', 'faculty/submissions.blade.php'] as $view) {
        expect(File::get(resource_path('views/'.$view)))
            ->toContain('<x-page-header')
            ->not->toContain('<x-faculty-proposal-navigation');
    }

    $researchCalls = File::get(resource_path('views/research_calls/index.blade.php'));

    expect(str_contains($researchCalls, 'Research Office'))->toBeFalse();
});

test('converted workspace pages render simple decorated headers without hero labels or accents', function (string $workspace, string $role, string $routeName, string $title) {
    $this->withoutVite();
    Role::firstOrCreate(['name' => $role]);
    $user = User::factory()->create();
    $user->assignRole($role);

    $response = $this->actingAs($user)
        ->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->get(route($routeName));

    $response->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $header = $xpath->query('//header[@data-page-header-container]')->item(0);

    expect($header)->not->toBeNull()
        ->and($xpath->query('.//*[@data-page-header-variant="simple"]', $header)->length)->toBe(1)
        ->and($xpath->query('.//h1', $header)->item(0)->textContent)->toBe($title)
        ->and($document->saveHTML($header))->not->toContain('data-workspace-header-banner', 'border-l-4', 'uppercase');
})->with([
    'Research Head Analytics' => ['research_head', 'research_head', 'research_head.analytics', 'Analytics'],
    'Research Head Calendar' => ['research_head', 'research_head', 'research_head.calendar', 'Calendar'],
    'Faculty Calendar' => ['faculty', 'faculty', 'faculty.calendar', 'Calendar'],
    'Faculty Researcher Calendar' => ['faculty_researcher', 'faculty_researcher', 'faculty.calendar', 'Calendar'],
    'Faculty drafts' => ['faculty', 'faculty', 'faculty.proposal-drafts.index', 'Draft proposals'],
    'Faculty submissions' => ['faculty', 'faculty', 'faculty.submissions', 'Submitted proposals'],
    'Research Head inbox' => ['research_head', 'research_head', 'notifications.index', 'Notification inbox'],
    'Research Office inbox' => ['research_office', 'research_coordinator', 'notifications.index', 'Notification inbox'],
    'Faculty inbox' => ['faculty', 'faculty', 'notifications.index', 'Notification inbox'],
    'Faculty Researcher inbox' => ['faculty_researcher', 'faculty_researcher', 'notifications.index', 'Notification inbox'],
]);
