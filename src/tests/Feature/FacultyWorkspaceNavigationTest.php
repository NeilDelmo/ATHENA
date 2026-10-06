<?php

use App\Models\ProjectProgressReport;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

test('three submission categories separate active proposals monitoring and closed records within the accessible scope', function () {
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);
    $other = User::factory()->create();
    $fixtures = [
        ['pending', null, null, 'review'],
        ['resubmitted', null, null, 'review'],
        ['gad_review', null, null, 'review'],
        ['lrec_queued', null, null, 'review'],
        ['revision_requested', null, null, 'revision'],
        ['ready_for_signature', null, null, 'signing'],
        ['approved', null, null, 'signing'],
        ['approved', 'ongoing', now(), 'monitoring'],
        ['approved', 'delayed', now(), 'monitoring'],
        ['rejected', null, null, 'closed'],
        ['approved', 'completed', now(), 'closed'],
    ];
    $expected = collect();
    foreach ($fixtures as $index => [$status, $projectStatus, $releasedAt, $category]) {
        $topic = TopicProposal::create([
            'user_id' => $user->id, 'title' => 'Categorized proposal '.$index,
            'status' => $status, 'project_status' => $projectStatus, 'notice_to_proceed_issued_at' => $releasedAt,
        ]);
        expect($topic->submissionCategory())->toBe($category);
        $expected->put($topic->id, $category);
    }
    $shared = TopicProposal::create(['user_id' => $other->id, 'title' => 'Shared review proposal', 'status' => 'pending']);
    $shared->collaborators()->create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'accepted_at' => now()]);
    $expected->put($shared->id, 'review');
    TopicProposal::create(['user_id' => $other->id, 'title' => 'Private research proposal', 'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);

    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    foreach (['active', 'monitoring', 'closed'] as $category) {
        $ids = $expected->filter(fn ($value) => $category === 'active' ? in_array($value, ['review', 'revision', 'signing'], true) : $value === $category)->keys();
        $response = $this->get(route('faculty.submissions', ['category' => $category]))->assertOk()
            ->assertSee('data-submission-categories', false)->assertDontSee('Submission history')
            ->assertDontSee('Private research proposal')
            ->assertViewHas('category', $category)
            ->assertViewHas('topics', fn ($topics): bool => $topics->pluck('id')->sort()->values()->all() === $ids->sort()->values()->all())
            ->assertViewHas('categories', fn ($categories): bool => $categories === ['active' => 'Active proposals', 'monitoring' => 'In monitoring', 'closed' => 'Closed'])
            ->assertViewHas('categoryCounts', fn ($counts): bool => $counts->all() === ['active' => 8, 'monitoring' => 2, 'closed' => 2])
            ->assertDontSee('Switch to Faculty Researcher')->assertDontSee('data-research-workspace-switch', false);
        if ($category === 'monitoring') {
            $response->assertSee('In monitoring')->assertSee('View proposal')->assertDontSee('>Project monitoring<', false);
        }
        if ($category === 'active') {
            $response->assertSee('Revise proposal')->assertSee('Research Head review')->assertSee('Final signing')
                ->assertViewHas('topics', fn ($topics): bool => $topics->first()->status === 'revision_requested');
        }
        if ($category === 'closed') {
            $response->assertSee('Research completed')->assertSee('Proposal rejected');
        }
    }
    $this->get(route('faculty.submissions', ['category' => 'unknown']))->assertOk()->assertViewHas('category', 'active');
    $this->get(route('faculty.submissions'))->assertOk()->assertViewHas('category', 'active')
        ->assertDontSee('data-submission-category="monitoring"', false)->assertDontSee('data-submission-category="closed"', false);
});

test('submission category pagination retains the selected category and empty categories offer a way back', function () {
    $user = User::factory()->create();
    $user->assignRole('faculty');
    foreach (range(1, 13) as $index) {
        TopicProposal::create(['user_id' => $user->id, 'title' => 'Review proposal '.$index, 'status' => 'pending']);
    }
    $revision = TopicProposal::create(['user_id' => $user->id, 'title' => 'Older revision needing attention', 'status' => 'revision_requested', 'created_at' => now()->subYear()]);
    $this->actingAs($user)->get(route('faculty.submissions'))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => str_contains($topics->nextPageUrl(), 'category=active') && $topics->first()->id === $revision->id);
    $this->get(route('faculty.submissions', ['category' => 'active', 'page' => 2]))->assertOk()
        ->assertViewHas('topics', fn ($topics): bool => $topics->total() === 14 && $topics->count() === 2 && ! $topics->contains('id', $revision->id));
    $this->get(route('faculty.submissions', ['category' => 'monitoring']))->assertOk()
        ->assertSee('No proposals in this category')->assertSee('View active proposals');
});

test('faculty proposal records show the project outcome and monitoring stays in the researcher workspace', function (string $projectStatus) {
    Storage::fake('local');
    $user = User::factory()->create();
    $user->assignRole(['faculty', 'faculty_researcher']);
    $topic = TopicProposal::create([
        'user_id' => $user->id, 'title' => 'Released workspace project', 'status' => 'approved',
        'project_status' => $projectStatus, 'notice_to_proceed_issued_at' => now(),
        'notice_to_proceed_path' => 'notices/workspace-release.pdf', 'notice_to_proceed_original_filename' => 'workspace-release.pdf',
        'notice_to_proceed_data' => ['approved_start_date' => '2026-10-03', 'approved_end_date' => '2027-02-03', 'approved_budget' => 60000],
    ]);
    Storage::disk('local')->put('notices/workspace-release.pdf', '%PDF signed notice');
    Storage::disk('local')->put('reports/workspace-monitoring.pdf', '%PDF monitoring');
    $report = ProjectProgressReport::create([
        'topic_id' => $topic->id, 'submitted_by' => $user->id, 'reporting_date' => now()->subDay(),
        'progress_percentage' => 60, 'accomplishments' => 'Report available only in the research workspace.',
        'official_pdf_path' => 'reports/workspace-monitoring.pdf',
    ]);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY, 'topic_tab' => 'monitoring'])
        ->get(route('topics.show', $topic))->assertOk()
        ->assertSee('data-research-workspace-handoff', false)->assertSee('Use Switch Workspace in your account menu')
        ->assertSee($projectStatus === 'completed' ? 'Research completed' : 'In monitoring')
        ->assertDontSee('Switch to Faculty Researcher')->assertDontSee('data-research-workspace-switch', false)
        ->assertDontSee('Released documents')->assertDontSee('Open researcher workspace')
        ->assertDontSee('id="notice-to-proceed-tab-button"', false)->assertDontSee('id="notice-to-proceed-tab"', false)
        ->assertSee('data-released-notice-summary', false)->assertSee('2026-10-03 to 2027-02-03')->assertSee('PHP 60,000.00')
        ->assertDontSee('id="project-monitoring-tab-button"', false)->assertDontSee('id="project-monitoring-tab"', false)
        ->assertViewHas('projectDocumentLibrary', fn ($library): bool => ! $library['documents']->contains('key', 'monitoring-report-'.$report->id));
    $this->get(route('research.show', $topic))->assertForbidden();
    $this->post(route('workspace.store'), ['workspace' => 'faculty_researcher'])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);
    $this->get(route('research.show', $topic))->assertOk()->assertSee('id="project-monitoring-tab"', false)
        ->assertSee('data-released-notice-summary', false)->assertDontSee('id="notice-to-proceed-tab"', false)
        ->assertDontSee('data-research-workspace-handoff', false)
        ->assertViewHas('projectDocumentLibrary', fn ($library): bool => $library['documents']->contains('key', 'monitoring-report-'.$report->id));
})->with(['ongoing', 'completed']);

test('faculty can see monitoring status without gaining researcher access', function () {
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $topic = TopicProposal::create([
        'user_id' => $user->id, 'title' => 'Project needing workspace access', 'status' => 'approved',
        'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now(),
    ]);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->get(route('faculty.submissions', ['category' => 'monitoring']))->assertOk()
        ->assertSee('In monitoring')->assertSee('View proposal')
        ->assertDontSee('data-research-workspace-switch', false);
    $this->post(route('workspace.store'), ['workspace' => 'faculty_researcher'])
        ->assertSessionHasErrors('workspace')->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);
});

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
