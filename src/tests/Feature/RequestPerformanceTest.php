<?php

use App\Models\TopicProposal;
use App\Models\User;
use App\Services\WorkspaceActionItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'faculty_researcher', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
});

test('workspace search and project pages record timings without private request contents', function () {
    Log::spy();
    Log::shouldReceive('channel')->with('performance')->andReturnSelf();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $responses = [
        $this->postJson(route('workspace.store'), ['workspace' => 'research_head']),
        $this->get(route('search.index', ['q' => 'PrivateSearchTerm'])),
        $this->get(route('research_head.projects.index')),
    ];
    foreach ($responses as $response) {
        $response->assertSuccessful()->assertHeader('Server-Timing');
        expect($response->headers->get('Server-Timing'))->toMatch('/app;dur=[0-9.]+, db;dur=[0-9.]+;desc="[0-9]+ queries"/');
    }
    Log::shouldHaveReceived('channel')->with('performance')->times(3);
    Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
        return $message === 'ATHENA request performance' && isset($context['route'], $context['duration_ms'], $context['query_count'], $context['database_ms'])
            && ! str_contains(json_encode($context), 'PrivateSearchTerm') && ! isset($context['url'], $context['bindings'], $context['sql']);
    })->times(3);
});

test('public pages and authentication redirects do not publish private performance metrics', function () {
    Log::spy();
    $this->get('/')->assertOk()->assertHeaderMissing('Server-Timing');
    $this->get(route('search.index'))->assertRedirect(route('login'))->assertHeaderMissing('Server-Timing');
    Log::shouldNotHaveReceived('info');
});

test('header actions avoid loading every proposal file for proposals that do not need signature checks', function () {
    foreach (range(1, 12) as $index) {
        $topic = TopicProposal::create(['user_id' => $this->head->id, 'title' => 'Queued proposal '.$index, 'status' => 'pending']);
        $topic->versions()->create(['submitted_by' => $this->head->id, 'version_number' => 1, 'submission_type' => 'initial',
            'title' => $topic->title, 'file_path' => 'pending.pdf', 'original_filename' => 'pending.pdf', 'mime_type' => 'application/pdf']);
    }
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    DB::enableQueryLog();
    $items = app(WorkspaceActionItems::class)->for($this->head);
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    if (getenv('ATHENA_PERFORMANCE_BASELINE') === '1') {
        fwrite(STDERR, 'Header action queries: '.$queries->count().' / database ms: '.$queries->sum('time').PHP_EOL);
    }
    expect($items)->toHaveCount(12);
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'from `proposal_version_files`')))->toHaveCount(0);

    TopicProposal::where('user_id', $this->head->id)->update(['status' => TopicProposal::STATUS_READY_FOR_SIGNATURE]);
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(WorkspaceActionItems::class)->for($this->head);
    $signatureQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from `proposal_version_files`'));
    DB::disableQueryLog();
    expect($signatureQueries)->toHaveCount(1);
});

test('the notification page calculates workspace actions only once and refreshes them on the next request', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    $actions = $this->mock(WorkspaceActionItems::class);
    $actions->shouldReceive('for')->withArgs(fn (User $user): bool => $user->is($this->head))->twice()->andReturn([]);
    $this->get(route('notifications.index'))->assertOk();
    $this->get(route('notifications.index'))->assertOk();
});
