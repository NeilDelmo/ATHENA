<?php

use App\Models\ResearchAssistantConversation;
use App\Models\ResearchManual;
use App\Models\User;
use App\Services\ResearchManualLibrary;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $diskName = 'manual-reader-'.getmypid();
    Storage::fake($diskName);
    Storage::set('local', Storage::disk($diskName));
    $this->activeIndexPath = Storage::disk('local')->path('research-manual/index.json');
    config([
        'research_assistant.manual.index_path' => $this->activeIndexPath,
        'research_assistant.manual.enabled' => true,
        'research_assistant.manual.minimum_score' => 0.1,
        'research_assistant.manual.retrieval_cache_seconds' => 0,
        'services.openrouter.key' => 'test-key',
        'services.openrouter.model' => 'test-model',
        'services.openrouter.base_url' => 'https://manual-reader.test/v1',
        'services.gemini.key' => '',
    ]);
    Http::preventStrayRequests();
    Role::findOrCreate('research_head', 'web');
    Role::findOrCreate('faculty', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
});

function readerManualPdf(string $text): string
{
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R 5 0 R 7 0 R] /Count 3 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> >> /Contents 4 0 R >>',
        '',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> >> /Contents 6 0 R >>',
        '',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 9 0 R >> >> /Contents 8 0 R >>',
        '',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    foreach ([3, 5, 7] as $index) {
        $stream = 'BT /F1 16 Tf 54 730 Td ('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text).' - Page '.(($index - 1) / 2).') Tj ET';
        $objects[$index] = '<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 10\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
    }

    return $pdf."trailer\n<< /Size 10 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
}

function readerManualFixture(User $uploader, string $edition = '2026', bool $activated = false): ResearchManual
{
    $manual = ResearchManual::factory()->create([
        'title' => 'Research Manual '.$edition, 'edition' => $edition, 'uploaded_by' => $uploader->id,
        'page_count' => 3, 'unindexed_pages' => [3], 'activated_at' => $activated ? now() : null,
    ]);
    File::ensureDirectoryExists(dirname($manual->indexPath()));
    File::put($manual->pdfPath(), readerManualPdf('Research Manual '.$edition));
    File::put($manual->indexPath(), json_encode([
        'schema_version' => 1, 'version' => $manual->version, 'title' => $manual->title,
        'page_count' => 3, 'unindexed_pages' => [3],
        'chunks' => [[
            'id' => 'ethics-'.$edition, 'section' => '1.1 Ethics approval',
            'content' => 'Ethics approval for edition '.$edition.' requires signed consent before participant recruitment.',
            'page_start' => 2, 'page_end' => 2,
        ]],
    ], JSON_THROW_ON_ERROR));

    return $manual;
}

test('reader and PDF require authentication', function () {
    $manual = readerManualFixture($this->head, activated: true);
    $this->get(route('research-manuals.read', $manual->version))->assertRedirect(route('login'));
    $this->get(route('research-manuals.file', $manual->version))->assertRedirect(route('login'));
});

test('authenticated faculty can read activated editions outside the research head workspace', function () {
    $manual = readerManualFixture($this->head, activated: true);
    $this->actingAs($this->faculty)->get(route('research-manuals.read', $manual->version))->assertOk()
        ->assertViewHas('manualReader', fn (array $reader): bool => $reader['version'] === $manual->version
            && $reader['edition'] === '2026' && $reader['guidance_url'] === null
            && $reader['page_count'] === 3 && $reader['unindexed_pages'] === [3]);
});

test('staged editions require the research head role and selected workspace', function () {
    $manual = readerManualFixture($this->head);
    $this->actingAs($this->faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    $this->get(route('research-manuals.read', $manual->version))->assertForbidden();
    $this->get(route('research-manuals.file', ['version' => $manual->version, 'download' => 1]))->assertForbidden();
    $this->head->assignRole('faculty');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty']);
    $this->get(route('research-manuals.read', $manual->version))->assertForbidden();
    $this->get(route('research-manuals.file', $manual->version))->assertForbidden();
    $this->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    $this->get(route('research-manuals.read', $manual->version))->assertOk();
});

test('reader PDF is private inline and the same authorized file can be downloaded', function () {
    $manual = readerManualFixture($this->head, activated: true);
    $inline = $this->actingAs($this->faculty)->get(route('research-manuals.file', $manual->version))
        ->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff');
    expect($inline->headers->get('content-disposition'))->toStartWith('inline;')
        ->and($inline->headers->get('cache-control'))->toContain('private', 'no-store')
        ->and($inline->baseResponse->getFile()->getPathname())->toBe($manual->pdfPath());
    $download = $this->get(route('research-manuals.file', ['version' => $manual->version, 'download' => 1]))
        ->assertOk()->assertDownload();
    expect($download->baseResponse->getFile()->getPathname())->toBe($manual->pdfPath());
});

test('reader links keep opening the old PDF after a new edition is activated', function () {
    $library = app(ResearchManualLibrary::class);
    $previous = readerManualFixture($this->head, '2025');
    $library->activate($previous);
    $next = readerManualFixture($this->head, '2026');
    $library->activate($next);
    $response = $this->actingAs($this->faculty)->get(route('research-manuals.file', $previous->version))->assertOk();
    expect(File::get($response->baseResponse->getFile()->getPathname()))->toBe(readerManualPdf('Research Manual 2025'))
        ->and($library->active()->id)->toBe($next->id);
});

test('legacy active edition can be read without adopting it', function () {
    $manual = readerManualFixture($this->head);
    File::ensureDirectoryExists(dirname($this->activeIndexPath));
    File::copy($manual->indexPath(), $this->activeIndexPath);
    File::copy($manual->pdfPath(), dirname($this->activeIndexPath).'/manual.pdf');
    $version = $manual->version;
    $manual->delete();
    $indexBefore = File::get($this->activeIndexPath);
    $this->actingAs($this->faculty)->get(route('research-manuals.read', $version))->assertOk()
        ->assertViewHas('manualReader', fn (array $reader): bool => $reader['edition'] === null && $reader['guidance_url'] === null);
    $this->get(route('research-manuals.file', $version))->assertOk();
    expect(ResearchManual::count())->toBe(0)->and(File::get($this->activeIndexPath))->toBe($indexBefore);
});

test('a legacy PDF response keeps its original bytes when activation publishes another edition', function () {
    $manual = readerManualFixture($this->head, '2025');
    File::ensureDirectoryExists(dirname($this->activeIndexPath));
    File::copy($manual->indexPath(), $this->activeIndexPath);
    File::copy($manual->pdfPath(), dirname($this->activeIndexPath).'/manual.pdf');
    $version = $manual->version;
    $manual->delete();
    $response = $this->actingAs($this->faculty)->get(route('research-manuals.file', $version))->assertOk();
    $replacement = readerManualFixture($this->head, '2026');
    app(ResearchManualLibrary::class)->activate($replacement);

    expect($response->getContent())->toBe(readerManualPdf('Research Manual 2025'));
});

test('reader rejects unknown and malformed version identifiers', function () {
    $this->actingAs($this->faculty)->get(route('research-manuals.read', str_repeat('a', 64)))->assertNotFound();
    $this->get('/research-manuals/../../../.env/file')->assertNotFound();
    $this->get('/research-manuals/123/file')->assertNotFound();
});

test('reader fails closed when saved PDF or index metadata is unavailable', function (string $damage) {
    $manual = readerManualFixture($this->head, activated: true);
    match ($damage) {
        'PDF' => File::delete($manual->pdfPath()),
        'index' => File::delete($manual->indexPath()),
        'malformed' => File::put($manual->indexPath(), '{broken'),
        'version' => File::put($manual->indexPath(), str_replace($manual->version, str_repeat('a', 64), File::get($manual->indexPath()))),
    };
    $this->actingAs($this->faculty)->get(route('research-manuals.read', $manual->version))->assertServiceUnavailable();
    $this->get(route('research-manuals.file', $manual->version))->assertServiceUnavailable();
})->with(['PDF', 'index', 'malformed', 'version']);

test('manual reader pages expose complete PDF configuration without adopting the legacy manual', function () {
    $manual = readerManualFixture($this->head);
    $review = $this->actingAs($this->head)->get(route('research-manuals.show', $manual))->assertOk()
        ->assertSee('data-submitted-report-paper', false)
        ->assertSee('data-research-manual-preview-modal', false)
        ->assertDontSee('manual-reader-frame', false)
        ->assertViewHas('manualReader', fn (array $reader): bool => $reader['guidance_url'] === route('research-manuals.guidance-check', $manual)
            && $reader['read_url'] === route('research-manuals.read', $manual->version)
            && $reader['pdf_url'] === route('research-manuals.file', $manual->version));
    app(ResearchManualLibrary::class)->activate($manual);
    $this->get(route('research-manuals.index'))->assertOk()->assertViewHas('manualReader', fn (array $reader): bool => $reader['version'] === $manual->version);
    $reader = $this->get(route('research-manuals.read', $manual->version))->assertOk();
    $manual->delete();
    $index = $this->get(route('research-manuals.index'))->assertOk()
        ->assertViewHas('manualReader', fn (array $reader): bool => $reader['version'] === $manual->version && $reader['guidance_url'] === null);
    if (getenv('ATHENA_EXPORT_MANUAL_READER') === '1') {
        $directory = storage_path('framework/testing');
        File::ensureDirectoryExists($directory);
        File::put($directory.'/manual-reader-index.html', $index->getContent());
        File::put($directory.'/manual-reader-review.html', $review->getContent());
        File::put($directory.'/manual-reader-reader.html', $reader->getContent());
        File::copy($manual->pdfPath(), $directory.'/manual-reader.pdf');
    }
});

test('reader reports a friendly unavailable status when the library cannot be locked', function () {
    $manual = readerManualFixture($this->head, activated: true);
    File::ensureDirectoryExists($this->activeIndexPath.'.lock');
    $this->actingAs($this->faculty)->get(route('research-manuals.file', $manual->version))->assertServiceUnavailable();
});

test('guidance uses only the selected staged manual and returns page citations without saving chat history', function () {
    $active = readerManualFixture($this->head, '2025');
    $library = app(ResearchManualLibrary::class);
    $library->activate($active);
    $staged = readerManualFixture($this->head, '2026');
    $activeIndexBefore = File::get($this->activeIndexPath);
    $conversationCount = ResearchAssistantConversation::count();
    Http::fake(['manual-reader.test/*' => Http::response(['choices' => [['message' => ['content' => 'Signed consent is required before recruitment [ATHENA 1].']]]])]);
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $staged), ['question' => 'What does section 1.1 require?'])
        ->assertOk()->assertJsonPath('edition', '2026')->assertJsonPath('version', $staged->version)->assertJsonPath('check_type', 'ai_response')
        ->assertJsonPath('sources.0.document_version', $staged->version)
        ->assertJsonPath('sources.0.url', route('research-manuals.read', $staged->version).'#page=2')
        ->assertJsonPath('sources.0.reference', 'ATHENA 1')
        ->assertJsonPath('reply', 'Signed consent is required before recruitment [ATHENA 1].');
    Http::assertSent(function (Request $request): bool {
        $messages = json_encode($request['messages']);

        return str_contains($messages, 'edition 2026') && ! str_contains($messages, 'edition 2025');
    });
    expect($library->active()->id)->toBe($active->id)
        ->and($staged->fresh()->activated_at)->toBeNull()
        ->and(File::get($this->activeIndexPath))->toBe($activeIndexBefore)
        ->and(ResearchAssistantConversation::count())->toBe($conversationCount);
});

test('guidance without matching evidence answers honestly without calling a provider', function () {
    $manual = readerManualFixture($this->head);
    Http::fake();
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What is the submarine allowance?'])
        ->assertOk()->assertJsonPath('sources', [])->assertJsonPath('edition', '2026')->assertJsonPath('check_type', 'no_evidence')
        ->assertJson(fn (AssertableJson $json) => $json->where('reply', fn (string $reply): bool => str_contains($reply, 'not find'))->etc());
    Http::assertNothingSent();
});

test('edition review compares current and uploaded passages with escaped highlights and exact PDF links without activating', function () {
    $active = readerManualFixture($this->head, '2025');
    $library = app(ResearchManualLibrary::class);
    $library->activate($active);
    $staged = readerManualFixture($this->head, '2026');
    $stagedIndex = $library->index($staged);
    $stagedIndex['chunks'][0]['content'] = 'Ethics approval for edition 2026 requires signed consent before participant recruitment. <script>alert(1)</script>';
    File::put($staged->indexPath(), json_encode($stagedIndex, JSON_THROW_ON_ERROR));
    $before = File::get($this->activeIndexPath);
    $review = $this->actingAs($this->head)->get(route('research-manuals.show', $staged))->assertOk()
        ->assertSee('Compare with the current manual')->assertSee('Changed extracted text')
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee(route('research-manuals.read', $active->version).'#page=2', false)
        ->assertSee(route('research-manuals.read', $staged->version).'#page=2', false)
        ->assertSee('Unreadable pages are excluded from the comparison.')
        ->assertViewHas('currentReader', fn (array $reader): bool => $reader['version'] === $active->version && $reader['guidance_url'] === null && $reader['read_hash'] === false)
        ->assertViewHas('comparisonChanges', fn ($changes): bool => $changes->total() === 1 && $changes->first()['type'] === 'changed')
        ->assertViewHas('manualReader', fn (array $reader): bool => in_array('What does section 1.1 require in this edition?', $reader['guidance_questions'], true) && count($reader['guidance_questions']) <= 5);
    expect(File::get($this->activeIndexPath))->toBe($before)
        ->and($staged->fresh()->activated_at)->toBeNull()
        ->and($library->active()->id)->toBe($active->id);

    if (getenv('ATHENA_EXPORT_MANUAL_READER') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/manual-comparison.html'), $review->getContent());
    }
});

test('edition review handles a first manual and a legacy current edition without adopting or changing it', function () {
    $staged = readerManualFixture($this->head, '2026');
    $this->actingAs($this->head)->get(route('research-manuals.show', $staged))->assertOk()
        ->assertSee('There is no current manual to compare.');
    $active = readerManualFixture($this->head, '2025');
    app(ResearchManualLibrary::class)->activate($active);
    $active->delete();
    $before = File::get($this->activeIndexPath);
    $this->get(route('research-manuals.show', $staged))->assertOk()
        ->assertViewHas('currentReader', fn (array $reader): bool => $reader['version'] === $active->version && $reader['edition'] === null);
    expect(ResearchManual::count())->toBe(1)->and(File::get($this->activeIndexPath))->toBe($before);
});

test('edition comparison paginates changes and reports an unavailable current index honestly', function () {
    $active = readerManualFixture($this->head, '2025');
    app(ResearchManualLibrary::class)->activate($active);
    $staged = readerManualFixture($this->head, '2026');
    $index = app(ResearchManualLibrary::class)->index($staged);
    $index['chunks'][0]['content'] = implode("\n\n", array_map(fn (int $number): string => 'Updated ethics passage '.$number.'.', range(1, 12)));
    File::put($staged->indexPath(), json_encode($index, JSON_THROW_ON_ERROR));
    $this->actingAs($this->head)->get(route('research-manuals.show', $staged))->assertOk()
        ->assertViewHas('comparisonChanges', fn ($changes): bool => $changes->count() === 8 && $changes->total() === 12);
    $this->get(route('research-manuals.show', [$staged, 'comparison_page' => 2]))->assertOk()
        ->assertViewHas('comparisonChanges', fn ($changes): bool => $changes->count() === 4 && $changes->currentPage() === 2);
    File::put($this->activeIndexPath, '{unreadable');
    $this->get(route('research-manuals.show', $staged))->assertOk()->assertSee('Comparison is unavailable:')
        ->assertViewHas('comparison', null)->assertSee('Preview manual');
});

test('guidance never falls back to the active edition when the selected index is missing', function () {
    $active = readerManualFixture($this->head, '2025');
    app(ResearchManualLibrary::class)->activate($active);
    $staged = readerManualFixture($this->head, '2026');
    File::delete($staged->indexPath());
    Http::fake();

    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $staged), ['question' => 'What does section 1.1 require?'])
        ->assertServiceUnavailable()->assertJsonStructure(['message']);
    Http::assertNothingSent();
});

test('guidance does not call a provider when no evidence fits the context budget', function () {
    $manual = readerManualFixture($this->head);
    config(['research_assistant.manual.maximum_context_tokens' => 1]);
    Http::fake();
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])
        ->assertOk()->assertJsonPath('sources', []);
    Http::assertNothingSent();
});

test('guidance requires a research head actively using that workspace', function () {
    $manual = readerManualFixture($this->head, activated: true);
    $this->actingAs($this->faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head'])
        ->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])->assertForbidden();
    $this->head->assignRole('faculty');
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'faculty'])
        ->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])->assertForbidden();
});

test('guidance validates the question', function (mixed $question) {
    $manual = readerManualFixture($this->head);
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => $question])
        ->assertUnprocessable()->assertJsonValidationErrors('question');
})->with([
    'missing' => [null], 'empty' => [''], 'array' => [['unexpected']], 'too long' => [str_repeat('x', 1501)],
]);

test('guidance returns a friendly unavailable response for an unconfigured provider', function () {
    $manual = readerManualFixture($this->head);
    config(['services.openrouter.key' => '']);
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])
        ->assertServiceUnavailable()->assertJsonStructure(['message']);
});

test('guidance handles provider connection failures', function () {
    $manual = readerManualFixture($this->head);
    Http::fake(['manual-reader.test/*' => Http::failedConnection()]);
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])
        ->assertServiceUnavailable()->assertJsonStructure(['message']);
});

test('guidance handles rejected and empty provider responses', function (array $body, int $status) {
    $manual = readerManualFixture($this->head);
    Http::fake(['manual-reader.test/*' => Http::response($body, $status)]);
    $this->actingAs($this->head)->postJson(route('research-manuals.guidance-check', $manual), ['question' => 'What does section 1.1 require?'])
        ->assertStatus(502)->assertJsonStructure(['message']);
})->with([
    'rejected' => [['error' => ['message' => 'Private provider details']], 500],
    'empty' => [['choices' => [['message' => ['content' => '']]]], 200],
    'invalid content' => [['choices' => [['message' => ['content' => ['unexpected']]]]], 200],
]);
