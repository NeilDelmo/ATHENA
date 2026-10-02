<?php

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Js;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
});

test('faculty workspace separates submissions and calendar from its dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('faculty');
    TopicProposal::create(['user_id' => $otherUser->id, 'title' => 'Private submission', 'status' => 'pending']);
    for ($index = 1; $index <= 13; $index++) {
        TopicProposal::create(['user_id' => $user->id, 'title' => 'Own submission '.$index, 'status' => 'pending']);
    }

    $this->actingAs($user)->get(route('faculty.dashboard'))->assertOk()
        ->assertSee(route('faculty.submissions'), false)
        ->assertSee(route('faculty.calendar'), false)
        ->assertSee('Draft proposals')
        ->assertDontSee('submitted-proposals-heading', false)
        ->assertDontSee('data-calendar-compact', false)
        ->assertDontSee('Submission history');

    $this->get(route('faculty.submissions'))->assertOk()
        ->assertSee('data-faculty-submissions', false)
        ->assertSee('View proposal')
        ->assertDontSee('Private submission')
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 13 && $topics->count() === 12);
    $this->get(route('faculty.submissions', ['page' => 2]))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->count() === 1);
});

test('each faculty workspace can open its full calendar', function (string $workspace) {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])->actingAs($user)
        ->get(route('faculty.calendar'))->assertOk()
        ->assertSee('data-faculty-calendar', false)
        ->assertSee('Research deadlines, scheduled activities, and your personal reminders.')
        ->assertDontSee('data-calendar-compact', false);
})->with([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]);

test('submitted proposals remain restricted to the faculty workspace', function () {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->actingAs($user)->get(route('faculty.submissions'))->assertForbidden();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)->get(route('faculty.calendar'))->assertForbidden();
});

test('faculty sidebars group accessible shortcuts by workspace', function (string $workspace) {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);

    $response = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->actingAs($user)->get(route('faculty.dashboard'))->assertOk();

    $html = $response->getContent();
    preg_match('/<aside\b.*?<\/aside>/s', $html, $matches);
    $sidebar = $matches[0];

    expect($sidebar)->toContain('data-faculty-navigation')
        ->toContain('aria-label="Overview"')
        ->not->toContain('aria-label="Research"')
        ->not->toContain('aria-label="Planning"')
        ->toContain('aria-label="Resources"')
        ->toContain('href="'.route('faculty.calendar').'"')
        ->toContain('href="'.route('research-support.index').'#shared-literature-library"')
        ->toContain('Saved literature')
        ->not->toContain('href="'.route('research_head.analytics').'"');

    $dom = new DOMDocument;
    @$dom->loadHTML($sidebar);
    $xpath = new DOMXPath($dom);
    $navigation = '//nav[@data-faculty-navigation]';
    $headings = [];
    foreach ($xpath->query($navigation.'/section/h2') as $heading) {
        $headings[] = trim($heading->textContent);
    }
    expect($headings)->toBe($workspace === User::WORKSPACE_FACULTY
        ? ['Overview', 'Submission', 'Review', 'Resources']
        : ['Overview', 'Monitoring', 'Completion', 'Resources'])
        ->and($xpath->query($navigation.'//button | '.$navigation.'//details')->length)->toBe(0);
    $expectedGroups = $workspace === User::WORKSPACE_FACULTY ? [
        'Overview' => ['Dashboard', 'Calendar'],
        'Submission' => ['Research calls', 'New proposal', 'Draft proposals'],
        'Review' => ['Submitted proposals'],
        'Resources' => ['Saved literature', 'Literature search', 'Turnitin'],
    ] : [
        'Overview' => ['Dashboard', 'Calendar'],
        'Monitoring' => ['My Projects', 'Active projects', 'Awaiting release'],
        'Completion' => ['Completed projects'],
        'Resources' => ['Saved literature', 'Literature search', 'Turnitin', 'Journal Finder'],
    ];
    foreach ($expectedGroups as $group => $labels) {
        $links = [];
        foreach ($xpath->query($navigation.'/section[@aria-label="'.$group.'"]//a') as $link) {
            $links[] = $link->getAttribute('title');
            expect($link->getAttribute('class'))->toContain('text-sm', 'min-h-[44px]')
                ->and($link->getAttribute('x-show'))->toBe('');
        }
        expect($links)->toBe($labels);
    }

    if ($workspace === User::WORKSPACE_FACULTY) {
        expect($sidebar)->toContain('aria-label="Submission"')
            ->toContain('aria-label="Review"')
            ->not->toContain('aria-label="Monitoring"')
            ->toContain('href="'.route('faculty.proposal-drafts.create').'"')
            ->toContain('href="'.route('faculty.submissions').'"')
            ->toContain('href="'.route('research-calls.index').'"')
            ->not->toContain('href="'.route('research.index').'"');
    } else {
        expect($sidebar)->toContain('aria-label="Monitoring"')
            ->toContain('aria-label="Completion"')
            ->not->toContain('aria-label="Submission"')
            ->not->toContain('aria-label="Review"')
            ->toContain('href="'.route('research.index').'"')
            ->toContain('href="'.route('research.index', ['status' => 'active']).'"')
            ->toContain('href="'.route('research.index', ['status' => 'waiting']).'"')
            ->toContain('href="'.route('research.index', ['status' => 'completed']).'"')
            ->not->toContain('href="'.route('faculty.proposal-drafts.create').'"')
            ->not->toContain('href="'.route('research-calls.index').'"');
    }
})->with([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]);

test('project shortcuts initialize the selected status and reject unknown filters', function (string $status, string $expected) {
    $user = User::factory()->create();
    $user->assignRole('faculty_researcher');

    $this->actingAs($user)->get(route('research.index', ['status' => $status]))
        ->assertOk()->assertSee('filter: '.Js::from($expected)->toHtml(), false);
})->with([
    ['active', 'active'],
    ['waiting', 'waiting'],
    ['completed', 'completed'],
    ['unknown', 'all'],
]);

test('saved literature shortcut initializes and follows the library tab', function (string $workspace) {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->actingAs($user)->get(route('research-support.index'))->assertOk()
        ->assertSee("workspaceMode: window.location.hash === '#shared-literature-library' ? 'library' : 'find'", false)
        ->assertSee('@hashchange.window="workspaceMode =', false)
        ->assertSee('id="shared-literature-library"', false);
})->with([User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]);
