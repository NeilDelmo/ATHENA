<?php

use App\Jobs\CheckResearchManualGuidance;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\ResearchManualChecks;
use App\Services\ResearchManualLibrary;
use App\Services\ResearchManualRetriever;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    Http::preventStrayRequests();
    config([
        'research_assistant.manual.index_path' => Storage::disk('local')->path('research-manual/index.json'),
        'research_assistant.manual.enabled' => true,
        'research_assistant.manual.minimum_score' => 0.1,
        'research_assistant.manual.retrieval_cache_seconds' => 0,
        'services.openrouter.key' => 'test-key',
        'services.openrouter.model' => 'test-model',
        'services.openrouter.base_url' => 'https://manual-checks.test/v1',
        'services.gemini.key' => '',
    ]);
    Role::findOrCreate('research_head', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
});

function automaticManualFixture(): ResearchManual
{
    $manual = ResearchManual::factory()->create(['page_count' => 3, 'unindexed_pages' => [3]]);
    File::ensureDirectoryExists(dirname($manual->indexPath()));
    File::put($manual->pdfPath(), "%PDF-1.4\nSaved candidate PDF");
    File::put($manual->indexPath(), json_encode([
        'schema_version' => 1, 'version' => $manual->version, 'title' => $manual->title,
        'page_count' => 3, 'unindexed_pages' => [3],
        'chunks' => [[
            'id' => 'ethics', 'section' => '1.1 Ethics approval',
            'content' => 'Ethics approval requires signed consent before participant recruitment.',
            'page_start' => 2, 'page_end' => 2,
        ]],
    ], JSON_THROW_ON_ERROR));

    return $manual;
}

function finishAutomaticManualChecks(ResearchManual $manual): ResearchManual
{
    $checks = app(ResearchManualChecks::class);
    $checks->queue($manual);
    for ($attempt = 0; $attempt < 6 && in_array($manual->fresh()->guidance_check_status, ['pending', 'running'], true); $attempt++) {
        (new CheckResearchManualGuidance($manual->id))->handle($checks);
    }

    return $manual->fresh();
}

test('uploading an indexed candidate automatically queues checks without activating it', function () {
    Process::preventStrayProcesses();
    Process::fake([Process::result(output: "1.1 Ethics approval\n\nEthics approval requires signed consent before participant recruitment.\f")]);
    $source = Storage::disk('local')->path('source.pdf');
    File::put($source, "%PDF-1.4\nUploaded candidate PDF");

    $manual = app(ResearchManualLibrary::class)->upload($source, 'Candidate manual', '2026', '2026-01-01', $this->head);

    expect($manual->fresh()->guidance_check_status)->toBe('pending')
        ->and($manual->activated_at)->toBeNull()
        ->and(app(ResearchManualLibrary::class)->active())->toBeNull();
    Queue::assertPushed(CheckResearchManualGuidance::class, fn ($job): bool => $job->manualId === $manual->id && $job->connection === 'database' && $job->queue === 'research-manual-checks');
    Http::assertNothingSent();
});

test('automatic checks persist generated questions candidate answers sources missing evidence and unreadable pages', function () {
    $manual = automaticManualFixture();
    Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => 'Signed consent is required [ATHENA 1].']]]])]);

    $manual = finishAutomaticManualChecks($manual);
    $results = $manual->guidance_check_results;
    $answered = collect($results['checks'])->where('status', 'checked');

    expect($manual->guidance_check_status)->toBe('completed')
        ->and($manual->guidance_check_started_at)->not->toBeNull()
        ->and($manual->guidance_check_completed_at)->not->toBeNull()
        ->and($results['version'])->toBe($manual->version)
        ->and($results['unindexed_pages'])->toBe([3])
        ->and(count($results['checks']))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(5)
        ->and(array_column($results['checks'], 'question'))->toContain('What does section 1.1 require in this edition?')
        ->and(array_column($results['checks'], 'status'))->toContain('no_evidence')
        ->and($answered)->not->toBeEmpty()
        ->and($answered->first()['reply'])->toBe('Signed consent is required [ATHENA 1].')
        ->and($answered->first()['sources'][0]['document_version'])->toBe($manual->version)
        ->and($answered->first()['sources'][0]['page_start'])->toBe(2)
        ->and($manual->activated_at)->toBeNull();
    Http::assertSent(fn ($request): bool => str_contains(json_encode($request['messages']), $manual->edition));
});

test('each queue invocation persists progress and makes at most one bounded answer request', function () {
    $manual = automaticManualFixture();
    Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => 'Consent [ATHENA 1].']]]])]);
    $checks = app(ResearchManualChecks::class);
    $checks->queue($manual);
    $checks->queue($manual);
    Queue::assertPushed(CheckResearchManualGuidance::class, 1);

    (new CheckResearchManualGuidance($manual->id))->handle($checks);

    expect($manual->fresh()->guidance_check_status)->toBe('running')
        ->and(collect($manual->fresh()->guidance_check_results['checks'])->where('status', 'pending')->count())->toBeGreaterThan(0);
    expect(Http::recorded()->count())->toBeLessThanOrEqual(1);
    Queue::assertPushed(CheckResearchManualGuidance::class, 2);
});

test('answer checks report missing and invented citations without certifying answer correctness', function (string $reply) {
    $manual = automaticManualFixture();
    Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => $reply]]]])]);

    $manual = finishAutomaticManualChecks($manual);

    expect(array_column($manual->guidance_check_results['checks'], 'status'))->toContain('invalid_references');
    $this->actingAs($this->head)->get(route('research-manuals.show', $manual))->assertOk()
        ->assertSee('Automated guidance checks')->assertSee('Reference check needs attention')
        ->assertSee('do not certify that every answer is correct');
})->with(['no citations' => 'Consent is required.', 'unknown citation' => 'Consent [ATHENA 99].']);

test('provider unavailability preserves retrieval checks and reports that answers were not checked', function () {
    $manual = automaticManualFixture();
    config(['services.openrouter.key' => '']);

    $manual = finishAutomaticManualChecks($manual);

    expect($manual->guidance_check_status)->toBe('completed')
        ->and(array_column($manual->guidance_check_results['checks'], 'status'))->toContain('unavailable', 'no_evidence');
    Http::assertNothingSent();
    $this->actingAs($this->head)->get(route('research-manuals.show', $manual))->assertOk()
        ->assertSee('Answer service unavailable')->assertSee('Activate edition');
});

test('missing candidate files stop automated checking without falling back to the active edition', function () {
    $manual = automaticManualFixture();
    app(ResearchManualChecks::class)->queue($manual);
    File::delete($manual->indexPath());

    (new CheckResearchManualGuidance($manual->id))->handle(app(ResearchManualChecks::class));

    expect($manual->fresh()->guidance_check_status)->toBe('failed')
        ->and($manual->fresh()->guidance_check_results['message'])->toContain('unavailable');
    Http::assertNothingSent();
});

test('review automatically queues preexisting candidates once and displays pending state', function () {
    $manual = automaticManualFixture();
    $this->actingAs($this->head)->get(route('research-manuals.show', $manual))->assertOk()
        ->assertSee('Checks queued automatically')->assertSee('No questions or expected answers are needed from you.');
    $this->get(route('research-manuals.show', $manual))->assertOk();

    Queue::assertPushed(CheckResearchManualGuidance::class, 1);
    expect($manual->fresh()->guidance_check_status)->toBe('pending');

    if (getenv('ATHENA_EXPORT_MANUAL_CHECKS') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/manual-checks-pending.html'), $this->get(route('research-manuals.show', $manual))->getContent());
        Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => 'Consent is required [ATHENA 1].']]]])]);
        $completed = finishAutomaticManualChecks($manual);
        File::put(storage_path('framework/testing/manual-checks-completed.html'), $this->get(route('research-manuals.show', $completed))->getContent());

    }
});

test('queue failure is persisted and duplicate completed deliveries do not generate more answers', function () {
    $manual = automaticManualFixture();
    Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => 'Consent [ATHENA 1].']]]])]);
    $manual = finishAutomaticManualChecks($manual);
    $sent = Http::recorded()->count();
    (new CheckResearchManualGuidance($manual->id))->handle(app(ResearchManualChecks::class));
    expect(Http::recorded()->count())->toBe($sent);

    $other = automaticManualFixture();
    app(ResearchManualChecks::class)->queue($other);
    (new CheckResearchManualGuidance($other->id))->failed(new RuntimeException('Private connection information'));
    expect($other->fresh()->guidance_check_status)->toBe('failed')
        ->and(json_encode($other->fresh()->guidance_check_results))->not->toContain('Private connection information');
});

test('automated checks reject foreign or damaged retrieved references before requesting an answer', function (string $damage) {
    $manual = automaticManualFixture();
    $source = [
        'chunk_id' => 'ethics', 'document_version' => $manual->version,
        'section' => '1.1 Ethics approval', 'content' => 'Ethics approval requires signed consent before participant recruitment.',
        'page_start' => 2, 'page_end' => 2,
        'url' => route('research-manuals.read', $manual->version).'#page=2',
    ];
    match ($damage) {
        'edition' => $source['document_version'] = str_repeat('f', 64),
        'pages' => $source['page_end'] = 99,
        'passage' => $source['chunk_id'] = 'unknown',
        'content' => $source['content'] = 'Unsupported replacement evidence.',
        'section' => $source['section'] = '8.2 Funding',
        'link' => $source['url'] = 'https://untrusted.test/manual.pdf#page=2',
    };
    $this->mock(ResearchManualRetriever::class)->shouldReceive('retrieve')->andReturn([$source]);

    $manual = finishAutomaticManualChecks($manual);

    expect(array_unique(array_column($manual->guidance_check_results['checks'], 'status')))->toBe(['invalid_references']);
    Http::assertNothingSent();
})->with(['edition', 'pages', 'passage', 'content', 'section', 'link']);

test('automatic check status is private and its saved answers are escaped', function () {
    $manual = automaticManualFixture();
    Http::fake(['manual-checks.test/*' => Http::response(['choices' => [['message' => ['content' => '<script>alert(1)</script> [ATHENA 1]']]]])]);
    $manual = finishAutomaticManualChecks($manual);
    $faculty = User::factory()->create();
    $this->actingAs($faculty)->getJson(route('research-manuals.show', $manual))->assertForbidden();
    $response = $this->actingAs($this->head)->getJson(route('research-manuals.show', $manual))->assertOk()
        ->assertJsonPath('status', 'completed')->assertHeader('Cache-Control', 'no-store, private');
    expect($response->json('html'))->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
