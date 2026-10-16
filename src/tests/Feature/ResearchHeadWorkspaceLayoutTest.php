<?php

use App\Livewire\ResearchTargetBoard;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['research_head', 'faculty'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
});

test('the daily dashboard links to planning tools and shows review queues before assistant suggestions', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk()
        ->assertSeeLivewire(ResearchTargetBoard::class)
        ->assertSeeHtml('data-dashboard-target-summary');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//header//a[@href="'.route('research_head.targets.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//header//a[@href="'.route('research_head.rms-quarterly-reports.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-dashboard-compact-queues]/following-sibling::*[@data-dashboard-assistant-suggestions]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-dashboard-reporting-links]//a[@href="'.route('research_head.quarterly-reports.index').'"]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-dashboard-reporting-links]//a[@href="'.route('research_head.targets.index').'"]')->length)->toBe(1);
});

test('research targets have a dedicated management page within the research head workspace', function () {
    $this->get(route('research_head.targets.index'))->assertOk()->assertSeeLivewire(ResearchTargetBoard::class)
        ->assertSee('Set annual research goals')->assertSee('Back to dashboard');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $this->actingAs($faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('research_head.targets.index'))->assertForbidden();
});

test('dashboard shortcuts have icons and readable names without text arrows', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//header//a | //*[@data-dashboard-reporting-links]//a');

    expect($links->length)->toBe(8);
    foreach ($links as $link) {
        expect($xpath->query('.//*[local-name()="svg"]', $link)->length)->toBeGreaterThan(0)
            ->and(trim($link->textContent))->not->toBeEmpty()->not->toContain('→', '->');
    }
});

test('research head navigation groups projects separately from planning and reporting', function () {
    $response = $this->get(route('research_head.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ([
        'Research workflow' => ['research-calls.index', 'research_head.received-submissions.index', 'research_head.proposal-submissions.index', 'research_head.report-reviews.index'],
        'Projects' => ['research_head.projects.index', 'research_head.completed-projects.index'],
        'Planning & reports' => ['research_head.targets.index', 'research_head.quarterly-reports.index', 'research_head.rms-quarterly-reports.index', 'research_head.analytics'],
        'Resources' => ['research_head.faculty-directory.index', 'research-manuals.index', 'signatories.index'],
    ] as $group => $routes) {
        foreach ($routes as $routeName) {
            expect($xpath->query('//nav[@data-research-head-navigation]/section[@aria-label="'.$group.'"]//a[@href="'.route($routeName).'"]')->length)->toBe(1);
        }
    }
});
