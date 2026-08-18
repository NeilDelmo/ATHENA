<?php

use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $this->withoutVite();
});

test('a Research Head sidebar area clears only its matching unread notifications', function () {
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
        ->assertSee('1 unread update')
        ->assertSee('x-show="!sidebarOpen"', false)
        ->assertSee('dark:ring-slate-950');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->post(route('sidebar-attention.open', 'proposal_submissions'))
        ->assertRedirect(route('research_head.proposal-submissions.index'));

    expect($proposalNotification->fresh()->read_at)->not->toBeNull()
        ->and($monitoringNotification->fresh()->read_at)->toBeNull()
        ->and($generalNotification->fresh()->read_at)->toBeNull();
});

test('a Faculty user can open My Projects and is switched into the researcher workspace', function () {
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
        title: 'Revision requested',
        message: 'A proposal revision is needed.',
        url: route('faculty.proposal-drafts.index'),
        workspace: User::WORKSPACE_FACULTY,
        sidebarArea: ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE,
    ));

    $projectNotification = $faculty->notifications()->where('data->title', 'Signed Notice to Proceed issued')->sole();
    $proposalNotification = $faculty->notifications()->where('data->title', 'Revision requested')->sole();

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('My Projects')
        ->assertSee(route('sidebar-attention.open', 'my_projects'), false);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->post(route('sidebar-attention.open', 'my_projects'))
        ->assertRedirect(route('research.index'))
        ->assertSessionHas(User::ACTIVE_WORKSPACE_SESSION_KEY, User::WORKSPACE_FACULTY_RESEARCHER);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER])
        ->actingAs($faculty)
        ->get(route('research.index'))
        ->assertOk()
        ->assertSee(route('sidebar-attention.open', 'my_projects'), false)
        ->assertDontSee(route('sidebar-attention.open', 'proposal_workspace'), false);

    expect($projectNotification->fresh()->read_at)->not->toBeNull()
        ->and($proposalNotification->fresh()->read_at)->toBeNull();
});

test('legacy unread notification titles are grouped without adding sidebar metadata', function () {
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

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot open a sidebar destination outside their workspace access', function () {
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($faculty)
        ->post(route('sidebar-attention.open', 'my_projects'))
        ->assertNotFound();
});
