<?php

use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\SidebarAttentionService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->withoutVite();
});

test('Research Head review notifications stay unread on sidebar navigation but can be explicitly read', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $head->notify(new ProposalActivityNotification(
        title: 'New proposal submitted',
        message: 'A proposal is ready for review.',
        url: route('research_head.proposal-submissions.index'),
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS,
    ));
    $head->notify(new ProposalActivityNotification(
        title: 'Progress report submitted',
        message: 'A project report is ready for review.',
        url: route('research_head.projects.index'),
        workspace: User::WORKSPACE_RESEARCH_HEAD,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING,
    ));
    $head->notify(new ProposalActivityNotification(
        title: 'General update',
        message: 'This remains in the full activity feed.',
        url: route('research_head.dashboard'),
        workspace: User::WORKSPACE_RESEARCH_HEAD,
    ));

    $proposalNotification = $head->notifications()->where('data->title', 'New proposal submitted')->sole();
    $monitoringNotification = $head->notifications()->where('data->title', 'Progress report submitted')->sole();
    $generalNotification = $head->notifications()->where('data->title', 'General update')->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->get(route('research_head.dashboard'))
        ->assertOk()
        ->assertSee(route('sidebar-attention.open', 'proposal_submissions'), false)
        ->assertSee(route('sidebar-attention.open', 'project_monitoring'), false)
        ->assertSee('data-sidebar-attention-url="'.route('sidebar-attention.open', 'proposal_submissions').'"', false)
        ->assertSee('wire:navigate', false)
        ->assertDontSee('<form method="POST" action="'.route('sidebar-attention.open', 'proposal_submissions').'"', false)
        ->assertSee('1 unread update')
        ->assertSee('x-show="!$store.sidebar.open"', false)
        ->assertSee('dark:ring-slate-950');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->post(route('sidebar-attention.open', 'proposal_submissions'))
        ->assertRedirect(route('research_head.proposal-submissions.index'));

    expect($proposalNotification->fresh()->read_at)->toBeNull()
        ->and($monitoringNotification->fresh()->read_at)->toBeNull()
        ->and($generalNotification->fresh()->read_at)->toBeNull();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->post(route('notifications.open', $proposalNotification))
        ->assertRedirect(route('research_head.proposal-submissions.index'));

    expect($proposalNotification->fresh()->read_at)->not->toBeNull();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->patchJson(route('notifications.read', $proposalNotification))
        ->assertOk()
        ->assertJsonPath('read', true);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->patchJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(0, 'preserved_ids');

    expect($proposalNotification->fresh()->read_at)->not->toBeNull()
        ->and($monitoringNotification->fresh()->read_at)->not->toBeNull()
        ->and($generalNotification->fresh()->read_at)->not->toBeNull();
});

test('faculty navigation preserves the selected workspace even when researcher access is available', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);
    $faculty->notify(new ProposalActivityNotification(
        title: 'Signed Notice to Proceed issued',
        message: 'Project monitoring is now open.',
        url: route('faculty.dashboard'),
        workspace: [User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER],
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
    ));
    $faculty->notify(new ProposalActivityNotification(
        title: 'Team member accepted invitation',
        message: 'A team member joined the draft proposal.',
        url: route('faculty.proposal-drafts.index'),
        workspace: User::WORKSPACE_FACULTY,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
    ));

    $projectNotification = $faculty->notifications()->where('data->title', 'Signed Notice to Proceed issued')->sole();
    $proposalNotification = $faculty->notifications()->where('data->title', 'Team member accepted invitation')->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Faculty Dashboard')
        ->assertSee('data-sidebar-attention-url="'.route('sidebar-attention.open', 'proposal_workspace').'"', false)
        ->assertDontSee('<form method="POST" action="'.route('sidebar-attention.open', 'proposal_workspace').'"', false)
        ->assertDontSee(route('sidebar-attention.open', 'my_projects'), false)
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->post(route('sidebar-attention.open', 'my_projects'))
        ->assertNotFound()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);

    expect($projectNotification->fresh()->read_at)->toBeNull()
        ->and($proposalNotification->fresh()->read_at)->toBeNull();

    $this->post(route('sidebar-attention.open', 'proposal_workspace'))
        ->assertRedirect(route('faculty.proposal-drafts.index'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);

    $this->get(route('faculty.proposal-drafts.index'))
        ->assertOk()
        ->assertSee('Faculty Dashboard')
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);

    $this->get(route('research.index'))
        ->assertForbidden()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY);

    expect($projectNotification->fresh()->read_at)->toBeNull()
        ->and($proposalNotification->fresh()->read_at)->not->toBeNull();
});

test('My Projects opens after explicitly selecting the faculty researcher workspace', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);
    $faculty->notify(new ProposalActivityNotification(
        title: 'Signed Notice to Proceed issued',
        message: 'Project monitoring is now open.',
        url: route('faculty.dashboard'),
        workspace: [User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER],
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_MY_PROJECTS,
    ));
    $projectNotification = $faculty->notifications()->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->post(route('workspace.store'), ['workspace' => User::WORKSPACE_FACULTY_RESEARCHER])
        ->assertRedirect(route('faculty.dashboard'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);

    $this->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('Faculty Researcher Dashboard')
        ->assertSee('data-sidebar-attention-url="'.route('sidebar-attention.open', 'my_projects').'"', false)
        ->assertDontSee('<form method="POST" action="'.route('sidebar-attention.open', 'my_projects').'"', false)
        ->assertDontSee(route('sidebar-attention.open', 'proposal_workspace'), false);

    $this->post(route('sidebar-attention.open', 'my_projects'))
        ->assertRedirect(route('research.index'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);

    $this->get(route('research.index'))
        ->assertOk()
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);

    expect($projectNotification->fresh()->read_at)->not->toBeNull();
});

test('legacy Research Head review notifications also stay unread when their sidebar area is opened', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $head->notify(new ProposalActivityNotification(
        title: 'Proposal revision submitted',
        message: 'A revised proposal is ready for review.',
        url: route('research_head.proposal-submissions.index'),
        workspace: User::WORKSPACE_RESEARCH_HEAD,
    ));

    $notification = $head->notifications()->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->post(route('sidebar-attention.open', 'proposal_submissions'))
        ->assertRedirect(route('research_head.proposal-submissions.index'));

    expect($notification->fresh()->read_at)->toBeNull();
});

test('a user cannot open a sidebar destination outside their workspace access', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->post(route('sidebar-attention.open', 'my_projects'))
        ->assertNotFound();
});

test('sidebar attention navigation returns a Livewire destination without a browser redirect', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $faculty->notify(new ProposalActivityNotification(
        title: 'Team member accepted invitation',
        message: 'A team member joined the draft proposal.',
        url: route('faculty.proposal-drafts.index'),
        workspace: User::WORKSPACE_FACULTY,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
    ));

    $notification = $faculty->notifications()->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->postJson(route('sidebar-attention.open', 'proposal_workspace'))
        ->assertOk()
        ->assertJson([
            'url' => route('faculty.proposal-drafts.index'),
            'clear_attention' => true,
        ]);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('submitted proposal updates appear on their own sidebar item and clear independently from draft invitations', function (?string $area, string $title, ?int $topicId) {
    $faculty = User::factory()->create();
    $faculty->assignRole(['faculty', 'faculty_researcher']);
    $faculty->notify(new ProposalActivityNotification(
        title: 'Proposal workspace invitation',
        message: 'Join a draft proposal.',
        url: route('faculty.proposal-drafts.index'),
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
    ));
    $faculty->notify(new ProposalActivityNotification(
        title: $title,
        message: 'An update to a submitted proposal.',
        url: route('faculty.submissions'),
        topicId: $topicId,
        workspace: [User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER],
        sidebarArea: $area,
    ));
    $draftNotification = $faculty->notifications()->where('data->title', 'Proposal workspace invitation')->sole();
    $submittedNotification = $faculty->notifications()->where('data->title', $title)->sole();

    $response = $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)->get(route('faculty.dashboard'))->assertOk();

    expect(app(SidebarAttentionService::class)->countsFor($faculty))->toBe([
        'proposal_workspace' => 1,
        'submitted_proposals' => 1,
    ]);

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $submittedLink = $xpath->query('//aside//a[@data-sidebar-attention-url="'.route('sidebar-attention.open', 'submitted_proposals').'"]')->item(0);
    $draftLink = $xpath->query('//aside//a[@data-sidebar-attention-url="'.route('sidebar-attention.open', 'proposal_workspace').'"]')->item(0);
    expect($submittedLink)->not->toBeNull()
        ->and($submittedLink->getAttribute('href'))->toBe(route('faculty.submissions'))
        ->and($submittedLink->textContent)->toContain('Submitted proposals')
        ->and($draftLink->textContent)->toContain('Draft proposals')
        ->and($xpath->query('.//*[@aria-label="1 unread update"]', $submittedLink)->length)->toBe(2);

    $this->postJson(route('sidebar-attention.open', 'submitted_proposals'))
        ->assertOk()->assertJson(['url' => route('faculty.submissions'), 'clear_attention' => true]);
    expect($submittedNotification->fresh()->read_at)->not->toBeNull()
        ->and($draftNotification->fresh()->read_at)->toBeNull()
        ->and(app(SidebarAttentionService::class)->countsFor($faculty))->toBe([
            'proposal_workspace' => 1,
            'submitted_proposals' => 0,
        ]);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->get(route('faculty.dashboard'))->assertOk()
        ->assertDontSee(route('sidebar-attention.open', 'submitted_proposals'), false);
    $this->post(route('sidebar-attention.open', 'submitted_proposals'))->assertNotFound();
})->with([
    'current submission' => ['submitted_proposals', 'Proposal submitted for review', 123],
    'older submission' => ['proposal_workspace', 'Proposal submitted for review', 123],
    'older revision' => ['proposal_workspace', 'Revision requested', 123],
    'older LREC update' => ['proposal_workspace', 'Queued for LREC', null],
    'older topic update' => ['proposal_workspace', 'Proposal stage changed', 123],
    'untagged submission' => [null, 'Proposal submitted for review', 123],
]);
