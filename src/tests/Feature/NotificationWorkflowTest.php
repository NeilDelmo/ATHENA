<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ResearchCall;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
});

test('the notification menu lists and marks proposal notifications as read', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $faculty->notify(new ProposalActivityNotification(
        'Revision requested',
        'Please update the proposal work plan.',
        route('faculty.dashboard'),
        'warning',
        42,
        route('notifications.proposal-invitations.accept', 1),
        [
            'proposal_title' => 'Shared Coastal Research',
            'inviter_name' => 'Workspace Owner',
        ],
    ));

    $notification = $faculty->notifications()->firstOrFail();

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('data-notification-menu', false)
        ->assertSee('aria-label="Close notifications"', false)
        ->assertSee('Revision requested')
        ->assertSee('Please update the proposal work plan.')
        ->assertSee('Review invitation')
        ->assertSee('View notification inbox')
        ->assertSee('bg-gray-100/90', false)
        ->assertSee('shadow-[inset_3px_0_0_0_#dc2626]', false)
        ->assertSee('>New</span>', false)
        ->assertSee('x-text="unreadCount > 99 ? \'99+\' : unreadCount"', false)
        ->assertDontSee("x-show=\"unreadCount > 0\"\n            x-text=\"unreadCount > 99 ? '99+' : unreadCount\"", false);

    $this->actingAs($faculty)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.id', $notification->id);

    $this->actingAs($faculty)
        ->patchJson(route('notifications.read', $notification->id))
        ->assertOk()
        ->assertJson(['read' => true]);

    $this->actingAs($faculty)
        ->get(route('faculty.dashboard'))
        ->assertOk()
        ->assertSee('x-text="`${unreadCount} unread`"', false)
        ->assertDontSee('You are all caught up', false);

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('faculty');

    $owner->notify(new ProposalActivityNotification(
        'Private proposal update',
        'Only the proposal owner should receive this.',
        route('faculty.dashboard'),
    ));

    $notification = $owner->notifications()->firstOrFail();

    $this->actingAs($otherUser)
        ->patchJson(route('notifications.read', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('workspace-targeted notifications only appear in the matching workspace', function () {
    Role::firstOrCreate(['name' => 'research_head']);

    $head = User::factory()->create();
    $head->assignRole(['faculty', 'research_head']);
    $head->notify(new ProposalActivityNotification(
        title: 'New proposal submitted',
        message: 'A proposal is ready for review.',
        url: route('research_head.dashboard'),
        workspace: User::WORKSPACE_RESEARCH_HEAD,
    ));
    $head->notify(new ProposalActivityNotification(
        title: 'Proposal revision submitted',
        message: 'A revised proposal is ready for review.',
        url: route('research_head.dashboard'),
    ));

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->actingAs($head)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread_count', 0);

    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD])
        ->actingAs($head)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonCount(2, 'notifications')
        ->assertJsonPath('unread_count', 2)
        ->assertJsonFragment(['workspace' => User::WORKSPACE_RESEARCH_HEAD]);
});

test('accepting an invitation keeps the notification but removes its review action', function () {
    $owner = User::factory()->create();
    $owner->assignRole('faculty');
    $collaborator = User::factory()->create();
    $collaborator->assignRole('faculty');
    $researchCall = ResearchCall::create([
        'title' => 'Collaboration Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'status' => 'open',
    ]);
    $draft = ProposalDraft::create([
        'user_id' => $owner->id,
        'research_call_id' => $researchCall->id,
        'project_title' => 'Shared Research Draft',
        'duration_months' => 3,
        'planned_start' => '2026-08-01',
        'planned_end' => '2026-10-31',
        'project_leader' => $owner->name,
    ]);
    $membership = ProposalDraftMember::create([
        'proposal_draft_id' => $draft->id,
        'user_id' => $collaborator->id,
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'accepted_at' => null,
    ]);
    $collaborator->notify(new ProposalActivityNotification(
        'Proposal workspace invitation',
        'You were invited to collaborate.',
        route('faculty.proposal-drafts.show', $draft),
        'info',
        null,
        route('notifications.proposal-invitations.accept', $membership),
    ));
    $notification = $collaborator->notifications()->firstOrFail();
    $this->actingAs($collaborator);
    $response = $this->postJson(
        route('notifications.proposal-invitations.accept', $membership),
        [
            'notification_id' => $notification->id,
        ],
    );
    $response->assertOk();

    $notification->refresh();

    expect($notification->data)->toMatchArray([
        'title' => 'Proposal workspace invitation',
        'action_completed' => true,
    ])
        ->and($notification->data)->not->toHaveKey('action_url')
        ->and($notification->data)->not->toHaveKey('action_data')
        ->and($notification->read_at)->not->toBeNull()
        ->and($collaborator->notifications()->count())->toBe(1);
});
test('the notification inbox segregates activity and marks an opened item as read', function () {
    $this->withoutVite();

    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $notifications = [
        ['Proposal workspace invitation', 'A proposal owner invited you to collaborate.', ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE, route('notifications.proposal-invitations.accept', 1)],
        ['Collaborator accepted invitation', 'A faculty member joined your proposal workspace.', ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_WORKSPACE, null],
        ['Proposal submitted for review', 'A collaborative proposal is ready for review.', ProposalActivityNotification::SIDEBAR_AREA_PROPOSAL_SUBMISSIONS, null],
        ['Research call updated', 'The call schedule and requirements changed.', null, null],
        ['Progress report submitted', 'A quarterly progress report is ready.', ProposalActivityNotification::SIDEBAR_AREA_PROJECT_MONITORING, null],
    ];

    foreach ($notifications as [$title, $message, $sidebarArea, $actionUrl]) {
        $faculty->notify(new ProposalActivityNotification(
            title: $title,
            message: $message,
            url: route('faculty.dashboard'),
            actionUrl: $actionUrl,
            sidebarArea: $sidebarArea,
        ));
    }

    $response = $this->actingAs($faculty)->get(route('notifications.index'));

    $response
        ->assertOk()
        ->assertSee('Notification inbox')
        ->assertSee('Search notifications')
        ->assertSee('Invitations')
        ->assertSee('Collaboration')
        ->assertSee('Reviews')
        ->assertSee('Research calls')
        ->assertSee('Projects')
        ->assertSee('data-notification-category="invitations"', false)
        ->assertSee('data-notification-category="collaboration"', false)
        ->assertSee('data-notification-category="reviews"', false)
        ->assertSee('data-notification-category="research_calls"', false)
        ->assertSee('data-notification-category="projects"', false);

    $reviewNotification = $faculty->notifications()->firstWhere('data->title', 'Proposal submitted for review');

    $this->actingAs($faculty)
        ->post(route('notifications.open', $reviewNotification))
        ->assertRedirect(route('faculty.dashboard'));

    expect($reviewNotification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($faculty)
        ->patch(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'));

    expect($faculty->unreadNotifications()->count())->toBe(0);
});
