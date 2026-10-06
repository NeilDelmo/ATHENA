<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchAssistantConversation;
use App\Models\User;
use App\Notifications\ProposalActivityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

test('shared pages avoid loading unrelated account histories', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    ResearchAssistantConversation::factory()->count(40)->create([
        'user_id' => $faculty->id,
        'title' => 'Saved conversation outside page loading',
        'messages' => array_fill(0, 100, ['role' => 'user', 'content' => str_repeat('Research context ', 100)]),
    ]);
    $faculty->notifications()->insert(collect(range(1, 200))->map(fn (int $number): array => [
        'id' => (string) Str::uuid(),
        'type' => ProposalActivityNotification::class,
        'notifiable_type' => $faculty->getMorphClass(),
        'notifiable_id' => $faculty->id,
        'data' => json_encode(['title' => 'Research update '.$number, 'message' => 'A saved update.', 'workspace' => User::WORKSPACE_FACULTY], JSON_THROW_ON_ERROR),
        'read_at' => $number <= 180 ? now() : null,
        'created_at' => now()->subSeconds(201 - $number),
        'updated_at' => now(),
    ])->all());

    $this->actingAs($faculty);

    foreach (['faculty.dashboard', 'faculty.submissions', 'faculty.calendar', 'profile.edit', 'faculty.proposal-drafts.index', 'research-support.index'] as $route) {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $started = hrtime(true);
        $response = $this->get(route($route));
        $elapsed = (hrtime(true) - $started) / 1_000_000;
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertDontSee('Saved conversation outside page loading');
        expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'research_assistant_conversations')))->toHaveCount(0);
        $notificationReads = $queries->filter(fn (array $query): bool => str_starts_with($query['query'], 'select * from `notifications`'));
        expect($notificationReads)->toHaveCount(2);
        expect($notificationReads->every(fn (array $query): bool => str_contains($query['query'], 'limit 15') || str_contains($query['query'], '`read_at` is null')))->toBeTrue();

        if (getenv('ATHENA_PAGE_BENCHMARK') === '1') {
            fwrite(STDERR, sprintf("%s: %.1f ms, %d queries, %d response bytes\n", $route, $elapsed, $queries->count(), strlen($response->getContent())));
        }
    }

    $this->getJson(route('notifications.index'))->assertOk()->assertJsonCount(15, 'notifications')->assertJsonPath('unread_count', 20);
    $this->getJson(route('research-support.history'))->assertOk()->assertJsonCount(40, 'conversations');
});

test('dashboard feedback stays available without loading attachment histories or hidden drafts', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = $faculty->proposals()->create(['title' => 'Proposal needing revision', 'status' => 'revision_requested']);
    $topic->reviews()->create([
        'reviewer_id' => $faculty->id,
        'decision' => 'revision_requested',
        'comment' => 'Clarify the sampling plan.',
    ]);
    foreach (range(1, 5) as $number) {
        $version = $topic->versions()->create([
            'submitted_by' => $faculty->id,
            'version_number' => $number,
            'submission_type' => $number === 1 ? 'initial' : 'revision',
            'title' => $topic->title,
            'file_path' => "proposals/version-{$number}.pdf",
            'original_filename' => 'proposal.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);
        $version->files()->create([
            'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
            'file_path' => $version->file_path,
            'original_filename' => 'proposal.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'source_data' => ['narrative' => str_repeat('Prior proposal narrative. ', 1000)],
        ]);
    }
    foreach (range(1, 5) as $number) {
        ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Draft '.$number, 'updated_at' => now()->subMinutes($number)]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->actingAs($faculty)->get(route('faculty.dashboard'));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()->assertSee('Clarify the sampling plan.')->assertSee('Draft 1')->assertSee('Draft 2')->assertDontSee('Draft 3');
    $response->assertViewHas('proposalDraftCount', 5)->assertViewHas('recentProposalDrafts', fn ($drafts): bool => $drafts->count() === 2);
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'proposal_version_files')))->toHaveCount(0);
});

test('notification queries preserve global and workspace visibility before applying limits', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $dataByTitle = [
        'Global missing workspace' => [],
        'Global null workspace' => ['workspace' => null],
        'Faculty only' => ['workspace' => User::WORKSPACE_FACULTY],
        'Both workspaces' => ['workspace' => [User::WORKSPACE_FACULTY, User::WORKSPACE_FACULTY_RESEARCHER]],
        'No workspaces' => ['workspace' => []],
        'New proposal submitted' => [],
        'Proposal revision submitted' => ['workspace' => User::WORKSPACE_FACULTY],
        'Faculty sidebar overrides workspace' => ['sidebar_area' => ProposalActivityNotification::SIDEBAR_AREA_SUBMITTED_PROPOSALS, 'workspace' => User::WORKSPACE_RESEARCH_HEAD],
    ];
    foreach ($dataByTitle as $title => $data) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ProposalActivityNotification::class,
            'data' => ['title' => $title, ...$data],
        ]);
    }
    $otherUser->notifications()->create(['id' => (string) Str::uuid(), 'type' => ProposalActivityNotification::class, 'data' => ['title' => 'Private to another user']]);

    expect($user->visibleNotifications(User::WORKSPACE_FACULTY)->pluck('data.title')->all())->toEqualCanonicalizing([
        'Global missing workspace', 'Global null workspace', 'Faculty only', 'Both workspaces', 'Proposal revision submitted', 'Faculty sidebar overrides workspace',
    ]);
    expect($user->visibleNotifications(User::WORKSPACE_FACULTY_RESEARCHER)->pluck('data.title')->all())->toEqualCanonicalizing([
        'Global missing workspace', 'Global null workspace', 'Both workspaces',
    ]);
    expect($user->visibleNotifications(User::WORKSPACE_RESEARCH_HEAD)->pluck('data.title')->all())->toEqualCanonicalizing([
        'Global missing workspace', 'Global null workspace', 'New proposal submitted',
    ]);
});
