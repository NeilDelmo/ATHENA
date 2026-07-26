<?php

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
        ->assertSee('Review invitation');

    $this->actingAs($faculty)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.id', $notification->id);

    $this->actingAs($faculty)
        ->patchJson(route('notifications.read', $notification->id))
        ->assertOk()
        ->assertJson(['read' => true]);

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
