<?php

use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head', 'research_coordinator', 'research_secretary', 'expert'] as $role) {
        Role::findOrCreate($role, 'web');
    }
    $this->makeTopic = fn (User $owner, array $attributes = []): TopicProposal => TopicProposal::create([
        'user_id' => $owner->id, 'title' => 'Context for '.$owner->id,
        'status' => 'approved', 'project_status' => 'completed', ...$attributes,
    ]);
});

test('ordinary pages defer the full assistant project list', function (string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($workspace);
    $category = ResearchCategory::create(['name' => 'Deferred context category']);
    $call = ResearchCall::create([
        'title' => 'Deferred context call', 'academic_year' => '2026-2027', 'status' => 'open',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(),
    ]);
    foreach (range(1, 24) as $number) {
        $topic = TopicProposal::create([
            'user_id' => $user->id, 'title' => 'Unopened assistant project '.$number,
            'status' => 'approved', 'project_status' => 'completed',
            'research_category_id' => $category->id, 'research_call_id' => $call->id,
        ]);
        $topic->versions()->create([
            'submitted_by' => $user->id, 'version_number' => 1, 'submission_type' => 'initial',
            'file_path' => 'context.pdf', 'original_filename' => 'context.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 100, 'checksum' => str_repeat('a', 64), 'title' => $topic->title,
            'estimated_budget' => 1000, 'estimated_duration_months' => 12,
        ]);
    }
    $loadedTopics = 0;
    TopicProposal::retrieved(function () use (&$loadedTopics): void {
        $loadedTopics++;
    });
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $response = $this->get(route('profile.edit'))->assertOk();
        $contextQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '`research_categories`')
            || str_contains($query['query'], 'max(`proposal_versions`.`id`)'));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
    expect($loadedTopics)->toBe(0)->and($contextQueries)->toHaveCount(0);
    $response->assertSee('window.athenaResearchAssistantContexts = []', false)
        ->assertSee('data-research-assistant-contexts-url', false)
        ->assertDontSee('Unopened assistant project 1');
})->with(['faculty', 'faculty_researcher', 'research_head']);

test('assistant context lists preserve ownership collaboration visibility and complete older projects', function (string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($workspace);
    $other = User::factory()->create();
    $owned = collect(range(1, 12))->map(fn (int $number): TopicProposal => ($this->makeTopic)($user, [
        'title' => 'Owned context '.$number, 'created_at' => now()->subDays($number),
    ]));
    $private = ($this->makeTopic)($other, ['title' => 'Private project']);
    $shared = ($this->makeTopic)($other, ['title' => 'Accepted project staff']);
    $shared->collaborators()->create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'accepted_at' => now()]);
    $pending = ($this->makeTopic)($other, ['title' => 'Unaccepted invitation']);
    $pending->collaborators()->create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
    $proposal = ($this->makeTopic)($user, ['title' => 'Proposal before implementation', 'status' => 'pending', 'project_status' => null]);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace]);

    $response = $this->getJson('/research-support/contexts')->assertSuccessful()
        ->assertJsonPath('workspace', $workspace)->assertJsonPath('account_id', $user->id)
        ->assertHeader('Cache-Control', 'no-store, private');
    $ids = collect($response->json('contexts'))->pluck('id');
    foreach ($owned->push($shared) as $topic) {
        expect($ids)->toContain($topic->id);
    }
    if ($workspace === User::WORKSPACE_RESEARCH_HEAD) {
        expect($ids)->toContain($private->id, $pending->id);
    } else {
        expect($ids)->not->toContain($private->id, $pending->id);
    }
    expect($ids->contains($proposal->id))->toBe($workspace !== User::WORKSPACE_FACULTY_RESEARCHER);
    $first = $owned->first();
    $this->getJson('/research-support/contexts?active_topic_id='.$first->id)->assertSuccessful()->assertJsonPath('contexts.0.id', $first->id);
})->with(['faculty', 'faculty_researcher', 'research_head']);

test('context refresh removes revoked projects and ignores unauthorized or missing active selections', function () {
    $user = User::factory()->create();
    $user->assignRole('faculty');
    $owner = User::factory()->create();
    $shared = ($this->makeTopic)($owner);
    $membership = $shared->collaborators()->create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'accepted_at' => now()]);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY]);
    $this->getJson('/research-support/contexts?active_topic_id='.$shared->id)->assertSuccessful()->assertJsonPath('contexts.0.id', $shared->id);
    $membership->delete();
    $this->getJson('/research-support/contexts?active_topic_id='.$shared->id)->assertSuccessful()->assertJsonPath('contexts', []);
    $this->getJson('/research-support/contexts?active_topic_id=999999')->assertSuccessful()->assertJsonPath('contexts', []);
    $this->getJson('/research-support/contexts?active_topic_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('active_topic_id');
});

test('context refresh applies the current workspace after switching from Head to faculty', function () {
    $user = User::factory()->create();
    $user->assignRole('research_head');
    $owned = ($this->makeTopic)($user);
    $private = ($this->makeTopic)(User::factory()->create());
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $head = $this->getJson('/research-support/contexts')->assertSuccessful()->assertJsonPath('workspace', User::WORKSPACE_RESEARCH_HEAD);
    expect(collect($head->json('contexts'))->pluck('id')->all())->toContain($owned->id, $private->id);
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->getJson('/research-support/contexts?active_topic_id='.$private->id)->assertSuccessful()
        ->assertJsonPath('workspace', User::WORKSPACE_FACULTY)->assertJsonCount(1, 'contexts')->assertJsonPath('contexts.0.id', $owned->id);
});

test('email invitations expose contexts only to verified accounts', function (bool $verified) {
    $user = User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    $user->assignRole('faculty');
    $topic = ($this->makeTopic)(User::factory()->create());
    $topic->collaborators()->create(['name' => $user->name, 'email' => $user->email, 'accepted_at' => now()]);
    $response = $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_FACULTY])
        ->getJson('/research-support/contexts?active_topic_id='.$topic->id)->assertSuccessful();
    expect(collect($response->json('contexts'))->pluck('id')->all())->toBe($verified ? [$topic->id] : []);
})->with([true, false]);

test('assistant contexts require an allowed active workspace', function (string $role, string $workspace) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => $workspace])
        ->getJson('/research-support/contexts')->assertForbidden();
})->with([
    ['research_coordinator', User::WORKSPACE_RESEARCH_OFFICE],
    ['research_secretary', User::WORKSPACE_RESEARCH_SECRETARY],
    ['expert', 'expert'],
]);

test('assistant contexts require authentication', function () {
    $this->getJson('/research-support/contexts')->assertUnauthorized();
});
