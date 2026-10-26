<?php

use App\Models\ProposalDraft;
use App\Models\ResearchOutcome;
use App\Models\TopicCollaborator;
use App\Models\TopicProposal;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use App\Services\SidebarAttentionService;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'faculty_researcher', 'research_coordinator', 'research_head', 'research_secretary'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->leader = User::factory()->create(['college' => 'CICS']);
    $this->leader->assignRole('faculty_researcher');
    $this->coordinator = User::factory()->create(['college' => 'CICS']);
    $this->coordinator->assignRole('research_coordinator');
    $this->topic = TopicProposal::create([
        'user_id' => $this->leader->id, 'title' => 'Completed school research',
        'status' => 'approved', 'project_status' => 'completed', 'estimated_budget' => 150000,
    ]);
    $this->actingAs($this->leader)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY_RESEARCHER]);
    Notification::fake();
});

test('completed projects appear automatically without writing records on read', function () {
    $this->get(route('research-outcomes.index'))->assertOk()->assertSee($this->topic->title)->assertSee('School-funded');
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk()
        ->assertSee('Publication')->assertSee('Presentation')->assertSee('Patent')
        ->assertSee('Commercialized')->assertSee('Utilization')->assertSee('Save labels');
    expect(ResearchOutcome::count())->toBe(0);
});

test('leaders save multiple labels and notify only college coordinators without changing the archive', function () {
    $other = User::factory()->create(['college' => 'CTE']);
    $other->assignRole('research_coordinator');
    $before = $this->topic->fresh()->getAttributes();
    $this->put(route('research-outcomes.projects.update', $this->topic), [
        'labels' => ['publication', 'commercialized', 'utilization'], 'lock_version' => 0,
    ])->assertSessionHasNoErrors()->assertRedirect(route('research-outcomes.projects.show', $this->topic));
    $outcome = ResearchOutcome::firstOrFail();
    expect($outcome->labels)->toBe(['publication', 'commercialized', 'utilization'])
        ->and($outcome->lock_version)->toBe(1)->and($outcome->funding_source)->toBe('school')
        ->and($this->topic->fresh()->getAttributes())->toBe($before);
    Notification::assertSentTo($this->coordinator, ProposalActivityNotification::class, function ($notification) {
        return $notification->workspace === User::WORKSPACE_RESEARCH_OFFICE
            && $notification->url === route('research-outcomes.projects.show', $this->topic)
            && str_contains($notification->message, 'Commercialized');
    });
    Notification::assertNotSentTo($other, ProposalActivityNotification::class);
});

test('unchanged saves do not notify again and all labels can be cleared', function () {
    $url = route('research-outcomes.projects.update', $this->topic);
    $this->put($url, ['labels' => ['patent'], 'lock_version' => 0])->assertSessionHasNoErrors();
    $this->put($url, ['labels' => ['patent'], 'lock_version' => 1])->assertSessionHasNoErrors();
    expect(ResearchOutcome::firstOrFail()->lock_version)->toBe(1);
    Notification::assertSentToTimes($this->coordinator, ProposalActivityNotification::class, 1);
    $this->put($url, ['lock_version' => 1])->assertSessionHasNoErrors();
    expect(ResearchOutcome::firstOrFail()->labels)->toBe([]);
    Notification::assertSentToTimes($this->coordinator, ProposalActivityNotification::class, 2);
});

test('stale forms cannot overwrite labels including simultaneous first saves', function () {
    $url = route('research-outcomes.projects.update', $this->topic);
    $this->put($url, ['labels' => ['patent'], 'lock_version' => 0])->assertSessionHasNoErrors();
    $this->put($url, ['labels' => ['publication'], 'lock_version' => 0])->assertSessionHasErrors('lock_version');
    expect(ResearchOutcome::firstOrFail()->labels)->toBe(['patent']);
    Notification::assertSentToTimes($this->coordinator, ProposalActivityNotification::class, 1);
});

test('invalid labels and versions are rejected', function (array $data, string $error) {
    $this->put(route('research-outcomes.projects.update', $this->topic), $data)->assertSessionHasErrors($error);
    expect(ResearchOutcome::count())->toBe(0);
})->with([
    [['labels' => ['unknown'], 'lock_version' => 0], 'labels.0'],
    [['labels' => ['patent', 'patent'], 'lock_version' => 0], 'labels.0'],
    [['labels' => 'publication', 'lock_version' => 0], 'labels'],
    [['labels' => ['bad' => 'patent'], 'lock_version' => 0], 'labels'],
    [['labels' => [null], 'lock_version' => 0], 'labels.0'],
    [['labels' => ['patent']], 'lock_version'],
]);

test('accepted members can view but only the leader can update', function () {
    $member = User::factory()->create(['college' => 'CICS']);
    $member->assignRole('faculty_researcher');
    TopicCollaborator::factory()->create(['topic_id' => $this->topic->id, 'user_id' => $member->id, 'accepted_at' => now()]);
    $this->actingAs($member)->get(route('research-outcomes.projects.show', $this->topic))->assertOk()->assertDontSee('Save labels');
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => ['patent'], 'lock_version' => 0])->assertForbidden();
});

test('coordinators have read only access restricted to their college', function () {
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->get(route('research-outcomes.index'))->assertOk()->assertSee($this->topic->title);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk()->assertDontSee('Save labels');
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => [], 'lock_version' => 0])->assertForbidden();
    $this->leader->update(['college' => 'CTE']);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertForbidden();
    $this->get(route('research-outcomes.index'))->assertOk()->assertDontSee($this->topic->title);
});

test('unrelated researchers and wrong workspaces cannot access labels', function () {
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->get(route('research-outcomes.projects.show', $this->topic))->assertForbidden();
    $this->get(route('research-outcomes.index'))->assertOk()->assertDontSee($this->topic->title);
    $this->actingAs($this->leader)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->get(route('research-outcomes.index'))->assertForbidden();
});

test('only completed approved projects enter this module', function (string $status, string $projectStatus) {
    $this->topic->update(['status' => $status, 'project_status' => $projectStatus]);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertForbidden();
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => [], 'lock_version' => 0])->assertForbidden();
    $this->get(route('research-outcomes.index'))->assertOk()->assertDontSee($this->topic->title);
})->with([['approved', 'ongoing'], ['pending', 'completed'], ['approved', 'completion_pending']]);

test('leaders can register other completed research and school projects remain first', function () {
    $this->get(route('research-outcomes.create'))->assertOk();
    $this->post(route('research-outcomes.store'), [
        'title' => 'Other completed research', 'team_members' => 'Ana Reyes, Juan Cruz',
        'funding_source' => 'external', 'labels' => ['presentation'],
    ])->assertSessionHasNoErrors();
    $outcome = ResearchOutcome::firstOrFail();
    expect($outcome->topic_id)->toBeNull()->and($outcome->leader_id)->toBe($this->leader->id);
    $this->get(route('research-outcomes.index'))->assertOk()->assertSeeInOrder([$this->topic->title, $outcome->title]);
    $this->get(route('research-outcomes.show', $outcome))->assertOk()->assertSee('Ana Reyes, Juan Cruz');
    $this->put(route('research-outcomes.update', $outcome), [
        'title' => $outcome->title, 'team_members' => $outcome->team_members,
        'funding_source' => 'unfunded', 'labels' => ['presentation', 'utilization'], 'lock_version' => 1,
    ])->assertSessionHasNoErrors();
    expect($outcome->fresh()->labels)->toBe(['presentation', 'utilization']);
    Notification::assertSentToTimes($this->coordinator, ProposalActivityNotification::class, 2);
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->get(route('research-outcomes.show', $outcome))->assertOk()->assertDontSee('Save labels');
    $this->get(route('research-outcomes.create'))->assertForbidden();
});

test('other research cannot forge leadership a funded project or access another team', function () {
    $this->post(route('research-outcomes.store'), ['title' => 'Other research', 'funding_source' => 'school'])->assertSessionHasErrors('funding_source');
    $this->post(route('research-outcomes.store'), [
        'title' => 'Other research', 'funding_source' => 'unfunded', 'leader_id' => $this->coordinator->id, 'topic_id' => $this->topic->id,
    ])->assertSessionHasNoErrors();
    $outcome = ResearchOutcome::firstOrFail();
    expect($outcome->leader_id)->toBe($this->leader->id)->and($outcome->topic_id)->toBeNull();
    $other = User::factory()->create();
    $other->assignRole('faculty_researcher');
    $this->actingAs($other)->get(route('research-outcomes.show', $outcome))->assertForbidden();
    $this->put(route('research-outcomes.update', $outcome), ['lock_version' => 1])->assertForbidden();
});

test('research heads can read all outcomes but cannot edit team labels', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->get(route('research-outcomes.index'))->assertOk()->assertSee($this->topic->title);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk()->assertDontSee('Save labels');
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => [], 'lock_version' => 0])->assertForbidden();
});

test('coordinator receives a real inbox entry and sidebar attention for the new module', function () {
    Notification::swap(new ChannelManager($this->app));
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => ['publication'], 'lock_version' => 0])
        ->assertSessionHasNoErrors();
    $notification = $this->coordinator->notifications()->sole();
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $attention = app(SidebarAttentionService::class);
    expect($attention->countsFor($this->coordinator)['research_outcomes'])->toBe(1);
    $this->getJson(route('notifications.index'))->assertOk()->assertJsonPath('notifications.0.category', 'projects')
        ->assertJsonPath('notifications.0.data.url', route('research-outcomes.projects.show', $this->topic));
    $this->post(route('sidebar-attention.open', 'research_outcomes'))->assertRedirect(route('research-outcomes.index'));
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('linked records cannot bypass the project routes and standalone edits reject stale versions', function () {
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => ['patent'], 'lock_version' => 0])->assertSessionHasNoErrors();
    $linked = ResearchOutcome::where('topic_id', $this->topic->id)->sole();
    $this->get(route('research-outcomes.show', $linked))->assertNotFound();
    $this->put(route('research-outcomes.update', $linked), ['lock_version' => 1])->assertForbidden();
    $manual = ResearchOutcome::factory()->create(['leader_id' => $this->leader->id, 'lock_version' => 1]);
    $this->put(route('research-outcomes.update', $manual), [
        'title' => $manual->title, 'funding_source' => 'unfunded', 'labels' => ['utilization'], 'lock_version' => 0,
    ])->assertSessionHasErrors('lock_version');
    expect($manual->fresh()->labels)->toBe([]);
});

test('missing colleges never expose projects or notify coordinators from another college', function () {
    $this->leader->update(['college' => null]);
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => ['patent'], 'lock_version' => 0])->assertSessionHasNoErrors()
        ->assertSessionHas('success', 'Labels saved. No coordinator is assigned to your college.');
    Notification::assertNothingSent();
    $this->coordinator->update(['college' => null]);
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $this->get(route('research-outcomes.index'))->assertOk()->assertDontSee($this->topic->title);
    $this->get(route('research-outcomes.projects.show', $this->topic))->assertForbidden();
});

test('validation restores only the correct project form and preserves the original version', function () {
    $url = route('research-outcomes.projects.show', $this->topic);
    $this->withCookie(config('session.cookie'), session()->getId());
    $this->from($url)->put(route('research-outcomes.projects.update', $this->topic), [
        'labels' => ['publication', 'invalid'], 'lock_version' => 0,
    ])->assertSessionHasErrors('labels.1')->assertRedirect($url);
    $response = $this->get($url)->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//input[@value="publication" and @checked]')->length)->toBe(1);
    $other = TopicProposal::create(['user_id' => $this->leader->id, 'title' => 'Another completed project', 'status' => 'approved', 'project_status' => 'completed']);
    $response = $this->get(route('research-outcomes.projects.show', $other))->assertOk();
    @$dom->loadHTML($response->getContent());
    expect((new DOMXPath($dom))->query('//input[@value="publication" and @checked]')->length)->toBe(0);
});

test('post implementation pages render the leader and coordinator desktop forms', function () {
    $this->leader->update(['name' => 'Maria Santos']);
    $this->put(route('research-outcomes.projects.update', $this->topic), ['labels' => ['publication', 'patent'], 'lock_version' => 0])->assertSessionHasNoErrors();
    $responses = [
        'index' => $this->get(route('research-outcomes.index'))->assertOk(),
        'leader' => $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk(),
        'create' => $this->get(route('research-outcomes.create'))->assertOk(),
    ];
    $this->actingAs($this->coordinator)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_OFFICE]);
    $responses['coordinator'] = $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk();
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $responses['head'] = $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk();
    if (getenv('ATHENA_EXPORT_RESEARCH_OUTCOMES') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        foreach ($responses as $name => $response) {
            File::put(storage_path('framework/testing/research-outcomes-'.$name.'.html'), $response->getContent());
        }
    }
});

test('post implementation shows accepted team members once using their current names', function () {
    $member = User::factory()->create(['name' => 'Current member name']);
    $draft = ProposalDraft::create(['user_id' => $this->leader->id, 'topic_id' => $this->topic->id, 'project_title' => $this->topic->title]);
    foreach ([$this->topic->collaborators(), $draft->members()] as $members) {
        $members->create(['user_id' => $member->id, 'name' => 'Old invitation name', 'email' => $member->email, 'accepted_at' => now()]);
        $members->create(['name' => 'Pending invitation', 'email' => 'pending@example.com', 'accepted_at' => null]);
        $members->create(['name' => 'Project secretary', 'email' => 'secretary@example.com', 'accepted_at' => now(), 'project_role' => 'secretary']);
    }
    $this->topic->collaborators()->create(['name' => 'Accepted external member', 'email' => 'external@example.com', 'accepted_at' => now()]);
    $response = $this->get(route('research-outcomes.projects.show', $this->topic))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $team = (new DOMXPath($dom))->query('//p[starts-with(normalize-space(.), "Other team members:")]')->item(0)?->textContent;
    expect($team)->toBe('Other team members: Accepted external member, Current member name');
});
