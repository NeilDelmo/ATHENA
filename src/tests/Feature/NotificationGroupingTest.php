<?php

use App\Models\ProjectProgressReport;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\ResearchCall;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Notifications\ProposalWorkspaceInvitation;
use App\Notifications\ResearchCallDeadlineReminderNotification;
use App\Notifications\ResearchCallOpeningReminderNotification;
use App\Notifications\ResearchCallPublishedNotification;
use App\Notifications\ResearchCallUpdatedNotification;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->withoutVite();
    config(['broadcasting.default' => 'null']);
    $this->travelTo('2026-08-25 09:00:00');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole(['faculty', 'faculty_researcher']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])->actingAs($this->faculty);
});

function groupedInboxNotice(User $user, array $data = [], int $minutesAgo = 0): DatabaseNotification
{
    return $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'data' => [
            'title' => 'Revision requested', 'message' => 'Please revise the proposal.',
            'url' => route('faculty.dashboard'), 'workspace' => User::WORKSPACE_FACULTY,
            ...$data,
        ],
        'created_at' => now()->subMinutes($minutesAgo),
    ]);
}

test('related project updates show the latest link with all history and accurate unread notifications', function () {
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Grouped research', 'status' => 'revision_requested']);
    $old = groupedInboxNotice($this->faculty, ['topic_id' => $topic->id, 'message' => 'Earlier review feedback.'], 5);
    $latest = groupedInboxNotice($this->faculty, ['topic_id' => $topic->id, 'message' => 'Latest review feedback.', 'url' => route('faculty.dashboard').'?latest=1']);
    $latest->markAsRead();

    $response = $this->get(route('notifications.index'))->assertOk()
        ->assertSee('View all 2 updates')->assertSee('Earlier review feedback.')->assertSee('Latest review feedback.')
        ->assertSee('Unread notifications')->assertSee('data-notification-history', false)
        ->assertSee('Address proposal revisions');

    $item = $response->viewData('notificationItems')->sole();
    expect($item['id'])->toBe($latest->id)->and($item['update_count'])->toBe(2)
        ->and($item['unread_count'])->toBe(1)->and($item['read_at'])->toBeNull()->and($item['needs_action'])->toBeTrue();

    if (getenv('ATHENA_EXPORT_NOTIFICATION_GROUPS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/notification-groups.html'), $response->getContent());
    }

    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.data.url', route('faculty.dashboard').'?latest=1')
        ->assertJsonPath('notifications.0.update_count', 2)->assertJsonPath('unread_count', 1);
    $topic->update(['status' => 'resubmitted']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.needs_action', false);
    expect($old->fresh()->read_at)->toBeNull()->and($this->faculty->notifications()->count())->toBe(2);
});

test('group reads intersect displayed ids with the recipients visible entity and leave new updates unread', function (string $operation) {
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Grouped project', 'status' => 'revision_requested']);
    $data = ['topic_id' => $topic->id];
    $old = groupedInboxNotice($this->faculty, $data, 5);
    $latest = groupedInboxNotice($this->faculty, [...$data, 'url' => route('faculty.dashboard').'?latest=1'], 1);
    $displayedIds = $this->getJson(route('notifications.index'))->json('notifications.0.notification_ids');
    $new = groupedInboxNotice($this->faculty, $data);
    $otherEntity = groupedInboxNotice($this->faculty, ['topic_id' => $topic->id + 1]);
    $otherWorkspace = groupedInboxNotice($this->faculty, [...$data, 'workspace' => User::WORKSPACE_FACULTY_RESEARCHER]);
    $otherUser = groupedInboxNotice(User::factory()->create(), $data);
    $ids = [...$displayedIds, $otherEntity->id, $otherWorkspace->id, $otherUser->id];

    if ($operation === 'open') {
        $this->post(route('notifications.open', $latest), ['notification_ids' => $ids])
            ->assertRedirect(route('faculty.dashboard').'?latest=1');
    } else {
        $this->patchJson(route('notifications.read', $latest), ['notification_ids' => $ids])
            ->assertOk()->assertJsonPath('unread_count', 2);
    }
    expect($old->fresh()->read_at)->not->toBeNull()->and($latest->fresh()->read_at)->not->toBeNull()
        ->and($new->fresh()->read_at)->toBeNull()->and($otherEntity->fresh()->read_at)->toBeNull()
        ->and($otherWorkspace->fresh()->read_at)->toBeNull()->and($otherUser->fresh()->read_at)->toBeNull();

    $this->patchJson(route('notifications.read', $otherUser), ['notification_ids' => [$old->id]])->assertNotFound();
    $this->post(route('notifications.open', $otherWorkspace), ['notification_ids' => [$old->id]])->assertNotFound();
})->with(['open', 'read']);

test('opening historical activity only marks that individual notification read', function () {
    $old = groupedInboxNotice($this->faculty, ['topic_id' => 42], 5);
    $latest = groupedInboxNotice($this->faculty, ['topic_id' => 42]);
    $this->post(route('notifications.open', $old))->assertRedirect(route('faculty.dashboard'));
    expect($old->fresh()->read_at)->not->toBeNull()->and($latest->fresh()->read_at)->toBeNull();
});

test('group read requests reject invalid and oversized batches', function () {
    $notice = groupedInboxNotice($this->faculty);
    $this->patchJson(route('notifications.read', $notice), ['notification_ids' => ['not-a-uuid']])->assertUnprocessable();
    $this->patchJson(route('notifications.read', $notice), ['notification_ids' => array_map(fn () => (string) Str::uuid(), range(1, 51))])->assertUnprocessable();
    expect($notice->fresh()->read_at)->toBeNull();
});

test('large project history remains visible in bounded groups without losing unread totals', function () {
    foreach (range(1, 51) as $minute) {
        groupedInboxNotice($this->faculty, ['topic_id' => 42], $minute);
    }
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(2, 'notifications')
        ->assertJsonPath('notifications.0.update_count', 50)->assertJsonCount(50, 'notifications.0.notification_ids')
        ->assertJsonPath('notifications.1.update_count', 1)->assertJsonPath('unread_count', 51);
});

test('the header limits loaded history while preserving the full unread count and inbox history', function () {
    $notice = groupedInboxNotice($this->faculty, ['topic_id' => 42]);
    $rows = [];
    foreach (range(1, 750) as $minute) {
        $rows[] = [...$notice->getAttributes(), 'id' => (string) Str::uuid(), 'created_at' => now()->subMinutes($minute)->toDateTimeString()];
    }
    $this->faculty->notifications()->insert($rows);

    DB::enableQueryLog();
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(15, 'notifications')
        ->assertJsonPath('unread_count', 751);
    $historyQuery = collect(DB::getQueryLog())->first(fn (array $query): bool => str_starts_with($query['query'], 'select * from `notifications`'));
    DB::disableQueryLog();
    expect($historyQuery['query'])->toContain('limit 750');

    $response = $this->get(route('notifications.index'))->assertOk();
    expect($response->viewData('notificationItems')->sum('update_count'))->toBe(751)
        ->and($response->viewData('unreadCount'))->toBe(751);
});

test('new updates from another project appear before an older batch of a large history', function () {
    foreach (range(1, 51) as $minute) {
        groupedInboxNotice($this->faculty, ['topic_id' => 42], $minute);
    }
    $otherProject = groupedInboxNotice($this->faculty, ['topic_id' => 43], 2);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(3, 'notifications')
        ->assertJsonPath('notifications.0.update_count', 50)
        ->assertJsonPath('notifications.1.id', $otherProject->id)
        ->assertJsonPath('notifications.2.update_count', 1);
});

test('separate invitations and changed deadline stages do not collapse into one project group', function () {
    foreach ([1, 2] as $membership) {
        groupedInboxNotice($this->faculty, ['title' => 'Project staff invitation', 'topic_id' => 42,
            'action_url' => route('notifications.proposal-invitations.accept', $membership)]);
    }
    foreach ([['2026-08-27T09:00:00+08:00', 'approaching'], ['2026-08-27T09:00:00+08:00', 'final'], ['2026-08-28T09:00:00+08:00', 'approaching']] as [$deadline, $stage]) {
        groupedInboxNotice($this->faculty, ['title' => 'Research call deadline approaching', 'research_call_id' => 10, 'deadline_at' => $deadline, 'deadline_notification_stage' => $stage]);
    }
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(5, 'notifications')->assertJsonPath('unread_count', 5);
});

test('resolved invitations keep their history unread and lose the action presented in the menu', function () {
    $owner = User::factory()->create();
    $draft = ProposalDraft::create(['user_id' => $owner->id, 'project_title' => 'Invited project']);
    $member = ProposalDraftMember::create(['proposal_draft_id' => $draft->id, 'user_id' => $this->faculty->id,
        'name' => $this->faculty->name, 'email' => $this->faculty->email, 'accepted_at' => null]);
    $notice = groupedInboxNotice($this->faculty, ['title' => 'Project staff invitation',
        'url' => route('notifications.proposal-invitations.show', $member),
        'action_url' => route('notifications.proposal-invitations.accept', $member)]);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.needs_action', true);
    $member->update(['accepted_at' => now()]);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.needs_action', false)
        ->assertJsonMissingPath('notifications.0.data.action_url')->assertJsonPath('unread_count', 1);
    expect($notice->fresh()->read_at)->toBeNull()->and($notice->fresh()->data)->toHaveKey('action_url');
});

test('superseded report corrections are information when the latest version no longer needs the researcher', function () {
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Report project', 'status' => 'approved', 'project_status' => 'ongoing', 'notice_to_proceed_issued_at' => now()]);
    $attributes = ['topic_id' => $topic->id, 'submitted_by' => $this->faculty->id, 'reporting_date' => now(),
        'period_start' => now()->startOfMonth(), 'progress_percentage' => 25, 'accomplishments' => 'Official progress'];
    $returned = ProjectProgressReport::create([...$attributes, 'version_number' => 1, 'review_status' => 'revision_requested']);
    $notice = groupedInboxNotice($this->faculty, ['title' => 'Monitoring report corrections requested',
        'topic_id' => $topic->id, 'workspace' => User::WORKSPACE_FACULTY_RESEARCHER, 'sidebar_area' => 'my_projects']);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.needs_action', true);
    ProjectProgressReport::create([...$attributes, 'version_number' => 2, 'supersedes_report_id' => $returned->id, 'review_status' => 'pending']);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.needs_action', false)
        ->assertJsonPath('unread_count', 1);
    expect($notice->fresh()->read_at)->toBeNull();
});

test('opening a notification rejects unsafe redirect destinations', function (string $destination) {
    $notice = groupedInboxNotice($this->faculty, ['url' => $destination]);
    $this->post(route('notifications.open', $notice))->assertRedirect(route('notifications.index'));
})->with(['//external.example/path', '/\\external.example/path', 'https://external.example/path', "/\x09/external.example"]);

test('queued call notifications check current records again before database delivery', function () {
    $call = ResearchCall::create(['title' => 'Current research call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(2), 'status' => 'open']);
    $reminder = new ResearchCallDeadlineReminderNotification($call->id, $call->title, $call->closes_at->copy(), 'approaching', route('faculty.dashboard'));
    $job = new SendQueuedNotifications([$this->faculty], $reminder, ['database']);
    expect($reminder->shouldSend($this->faculty, 'database'))->toBeTrue();
    $call->update(['closes_at' => now()->addDays(3)]);
    $job->handle(app(ChannelManager::class));
    expect($this->faculty->notifications()->count())->toBe(0);
    $call->update(['closes_at' => $reminder->closesAt]);
    $call->update(['status' => 'closed']);
    expect($reminder->shouldSend($this->faculty, 'broadcast'))->toBeFalse()
        ->and((new ResearchCallPublishedNotification($call->id, $call->title, '/'))->shouldSend($this->faculty, 'database'))->toBeFalse();
    $call->delete();
    expect((new ResearchCallUpdatedNotification($call->id, $call->title, '/'))->shouldSend($this->faculty, 'database'))->toBeFalse();
});

test('opening reminders stop when the call opens or is rescheduled and outdated warning stages stop', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $call = ResearchCall::create(['title' => 'Scheduled call', 'academic_year' => '2026-2027',
        'opens_at' => now()->addHours(12), 'closes_at' => now()->addDays(2), 'status' => 'open']);
    $opening = new ResearchCallOpeningReminderNotification($call->id, $call->title, $call->opens_at->copy(), '/');
    expect($opening->shouldSend($head, 'database'))->toBeTrue();
    $call->update(['opens_at' => now()->addDays(3)]);
    expect($opening->shouldSend($head, 'database'))->toBeFalse();
    $call->update(['opens_at' => now()->subHour(), 'closes_at' => now()->addHours(12)]);
    expect($opening->shouldSend($head, 'broadcast'))->toBeFalse();
    $outdated = new ResearchCallDeadlineReminderNotification($call->id, $call->title, $call->closes_at->copy(), 'approaching', '/');
    expect($outdated->shouldSend($this->faculty, 'database'))->toBeFalse();
    $this->travel(13)->hours();
    expect((new ResearchCallDeadlineReminderNotification($call->id, $call->title, $call->closes_at, 'final', '/'))->shouldSend($this->faculty, 'database'))->toBeFalse();
});

test('queued invitation mail stops after acceptance or removal', function () {
    $draft = ProposalDraft::create(['user_id' => User::factory()->create()->id, 'project_title' => 'Invited project']);
    $member = ProposalDraftMember::create(['proposal_draft_id' => $draft->id, 'user_id' => $this->faculty->id,
        'name' => $this->faculty->name, 'email' => $this->faculty->email, 'accepted_at' => null]);
    $mail = new ProposalWorkspaceInvitation($this->faculty->name, 'Leader', $draft->project_title, $this->faculty->email,
        route('notifications.proposal-invitations.show', $member), true, true);
    expect($mail->shouldSend($this->faculty, 'mail'))->toBeTrue();
    $member->update(['accepted_at' => now()]);
    expect($mail->shouldSend($this->faculty, 'mail'))->toBeFalse();
    $member->delete();
    expect($mail->shouldSend($this->faculty, 'mail'))->toBeFalse();
});
