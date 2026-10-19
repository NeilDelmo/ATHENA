<?php

use App\Jobs\CheckResearchManualGuidance;
use App\Models\ProposalDraft;
use App\Models\ResearchManual;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchManualIndexer;
use App\Services\ResearchManualLibrary;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->activeIndexPath = Storage::disk('local')->path('research-manual/index.json');
    config(['research_assistant.manual.index_path' => $this->activeIndexPath]);
    Process::preventStrayProcesses();
    Process::fake([Process::result(output: "1. Research Policy\n\nEthics approval is required before participant recruitment.\f")]);
    Role::findOrCreate('research_head', 'web');
    Role::findOrCreate('faculty', 'web');
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->sourcePath = Storage::disk('local')->path('source.pdf');
    File::put($this->sourcePath, "%PDF-1.4\nTest manual fixture");
});

function manualUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('manual.pdf', "%PDF-1.4\nTest manual fixture");
}

test('manual uploads stage private editions without changing the active manual', function () {
    $library = app(ResearchManualLibrary::class);
    $first = $library->upload($this->sourcePath, 'Research Manual', '2026', '2026-01-01', $this->head);
    expect($library->active())->toBeNull()
        ->and(is_file($this->activeIndexPath))->toBeFalse()
        ->and(is_file($first->indexPath()))->toBeTrue()
        ->and(File::get($first->pdfPath()))->toBe(File::get($this->sourcePath));

    $library->activate($first);
    $beforeIndex = File::get($this->activeIndexPath);
    File::put($this->sourcePath, "%PDF-1.4\nSecond edition");
    $second = $library->upload($this->sourcePath, 'Research Manual', '2027', '2026-01-01', $this->head);

    expect($second->id)->not->toBe($first->id)
        ->and($library->active()->id)->toBe($first->id)
        ->and(File::get($this->activeIndexPath))->toBe($beforeIndex)
        ->and($second->activated_at)->toBeNull();
});

test('legacy adoption preserves original files and pins existing unassigned proposals before activation', function () {
    $index = app(ResearchManualIndexer::class)->index($this->sourcePath, 'Legacy Research Manual');
    $legacyPdf = File::get(dirname($this->activeIndexPath).'/manual.pdf');
    $draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Existing draft']);
    $deletedDraft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Recoverable existing draft']);
    $deletedDraft->delete();
    $topic = TopicProposal::create(['user_id' => $this->faculty->id, 'title' => 'Existing project', 'description' => 'Existing research']);
    File::put($this->sourcePath, "%PDF-1.4\nReplacement edition");
    $library = app(ResearchManualLibrary::class);
    $replacement = $library->upload($this->sourcePath, 'New Research Manual', '2026', '2026-01-01', $this->head);
    $legacy = ResearchManual::where('version', $index['version'])->firstOrFail();
    $library->activate($replacement);

    expect($draft->fresh()->research_manual_id)->toBe($legacy->id)
        ->and(ProposalDraft::withTrashed()->findOrFail($deletedDraft->id)->research_manual_id)->toBe($legacy->id)
        ->and($topic->fresh()->research_manual_id)->toBe($legacy->id)
        ->and(File::get($legacy->pdfPath()))->toBe($legacyPdf)
        ->and(json_decode(File::get($legacy->indexPath()), true)['version'])->toBe($index['version'])
        ->and($library->active()->id)->toBe($replacement->id);
});

test('failed or duplicate imports preserve the active edition', function () {
    $library = app(ResearchManualLibrary::class);
    $first = $library->upload($this->sourcePath, 'Research Manual', '2026', '2026-01-01', $this->head);
    $library->activate($first);
    $beforeIndex = File::get($this->activeIndexPath);
    $beforePdf = File::get($first->pdfPath());
    expect(fn () => $library->upload($this->sourcePath, 'Research Manual', '2026', '2026-01-01', $this->head))
        ->toThrow(ValidationException::class);
    File::put($this->sourcePath, "%PDF-1.4\nUnreadable scan");
    Process::fake([Process::result(output: "\f")]);
    expect(fn () => $library->upload($this->sourcePath, 'Scanned Manual', '2027', '2026-01-01', $this->head))
        ->toThrow(ValidationException::class);
    expect(ResearchManual::count())->toBe(1)
        ->and(File::get($this->activeIndexPath))->toBe($beforeIndex)
        ->and(File::get($first->pdfPath()))->toBe($beforePdf);
});

test('faculty cannot manage or download manuals even with a forged workspace', function () {
    $manual = ResearchManual::factory()->create();
    $this->actingAs($this->faculty)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => 'research_head']);
    $this->get(route('research-manuals.index', ['role' => 'research_head']))->assertForbidden();
    $this->get(route('research-manuals.show', $manual))->assertForbidden();
    $this->get(route('research-manuals.download', $manual))->assertForbidden();
    $this->post(route('research-manuals.store'), [])->assertForbidden();
    $this->post(route('research-manuals.activate', $manual), ['review_confirmation' => '1'])->assertForbidden();
});

test('research heads can upload preview and privately download staged editions', function () {
    $this->actingAs($this->head)->post(route('research-manuals.store'), [
        'title' => 'Research Manual', 'edition' => '2026', 'effective_date' => '2026-01-01', 'manual' => manualUpload(),
    ])->assertRedirect();
    $manual = ResearchManual::sole();
    $this->get(route('research-manuals.index'))->assertOk()->assertSee('Research Manual')->assertSee(route('research-manuals.index'));
    $this->get(route('research-manuals.show', $manual))->assertOk()
        ->assertViewHas('manualReader', fn (array $reader): bool => $reader['version'] === $manual->version
            && $reader['guidance_url'] === route('research-manuals.guidance-check', $manual));
    $this->get(route('research-manuals.download', $manual))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(app(ResearchManualLibrary::class)->active())->toBeNull();
});

test('uploads validate metadata pdf type and size', function () {
    $this->actingAs($this->head)->post(route('research-manuals.store'), [
        'title' => '', 'edition' => '', 'effective_date' => 'invalid',
        'manual' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
    ])->assertSessionHasErrors(['title', 'edition', 'effective_date', 'manual']);
    $this->post(route('research-manuals.store'), [
        'title' => 'Research Manual', 'edition' => '2026', 'effective_date' => '2026-01-01',
        'manual' => UploadedFile::fake()->create('manual.pdf', 25601, 'application/pdf'),
    ])->assertSessionHasErrors('manual');
    expect(ResearchManual::count())->toBe(0);
});

test('progress uploads retain a single private manual and one check job when a saved response is retried', function () {
    config(['cache.default' => 'array']);
    Cache::flush();
    Queue::fake();
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->withCookie(config('session.cookie'), app('session')->getId());
    $headers = ['Accept' => 'application/json', 'X-Athena-Upload-ID' => (string) Str::uuid(), 'X-Athena-Upload-Form' => '1'];
    $data = fn (): array => ['title' => 'Research Manual', 'edition' => '2026', 'effective_date' => '2026-01-01', 'manual' => manualUpload()];
    $this->post(route('research-manuals.store'), $data(), $headers)->assertOk();
    $manual = ResearchManual::sole();
    $this->post(route('research-manuals.store'), $data(), [...$headers, 'X-Athena-Upload-Retry' => '1'])
        ->assertOk()->assertJson(['redirect' => route('research-manuals.show', $manual)]);
    expect(ResearchManual::count())->toBe(1)
        ->and(app(ResearchManualLibrary::class)->active())->toBeNull();
    Queue::assertPushed(CheckResearchManualGuidance::class, 1);
    $this->head->removeRole('research_head');
    $this->post(route('research-manuals.store'), $data(), [...$headers, 'X-Athena-Upload-Retry' => '1'])->assertForbidden();
});

test('progress manual uploads return validation JSON before saving an invalid edition', function () {
    $this->actingAs($this->head)->withSession([User::ACTIVE_WORKSPACE_SESSION_KEY => User::WORKSPACE_RESEARCH_HEAD]);
    $this->post(route('research-manuals.store'), [
        'title' => '', 'edition' => '2026', 'effective_date' => '2026-01-01', 'manual' => manualUpload(),
    ], ['Accept' => 'application/json', 'X-Athena-Upload-ID' => (string) Str::uuid(), 'X-Athena-Upload-Form' => '1'])
        ->assertUnprocessable()->assertJsonValidationErrors('title');
    expect(ResearchManual::count())->toBe(0);
});

test('activation requires review and acknowledgment of incomplete extraction', function () {
    Process::fake([Process::result(output: "1. Research Policy\n\nEthics approval is required.\f\f")]);
    $manual = app(ResearchManualLibrary::class)->upload($this->sourcePath, 'Research Manual', '2026', '2026-01-01', $this->head);
    $this->actingAs($this->head)->post(route('research-manuals.activate', $manual), [])->assertSessionHasErrors('review_confirmation');
    $this->post(route('research-manuals.activate', $manual), ['review_confirmation' => '1'])->assertSessionHasErrors('acknowledge_unindexed_pages');
    expect(app(ResearchManualLibrary::class)->active())->toBeNull();
    $this->post(route('research-manuals.activate', $manual), [
        'review_confirmation' => '1', 'acknowledge_unindexed_pages' => '1',
    ])->assertRedirect(route('research-manuals.show', $manual));
    expect(app(ResearchManualLibrary::class)->active()->id)->toBe($manual->id);
});

test('future effective editions cannot be activated', function () {
    $manual = app(ResearchManualLibrary::class)->upload($this->sourcePath, 'Research Manual', '2099', '2099-01-01', $this->head);
    $this->actingAs($this->head)->post(route('research-manuals.activate', $manual), ['review_confirmation' => '1'])
        ->assertSessionHasErrors('effective_date');
    expect(app(ResearchManualLibrary::class)->active())->toBeNull();
});

test('opening the library does not adopt a legacy active manual', function () {
    app(ResearchManualIndexer::class)->index($this->sourcePath, 'Legacy Manual');
    $this->actingAs($this->head)->get(route('research-manuals.index'))->assertOk()->assertSee('Legacy Manual');
    expect(ResearchManual::count())->toBe(0);
});

test('failed publishing restores the active files and rolls back activation', function () {
    $library = app(ResearchManualLibrary::class);
    $previous = $library->upload($this->sourcePath, 'Previous Manual', '2025', '2025-01-01', $this->head);
    $library->activate($previous);
    $next = $library->upload($this->sourcePath, 'Next Manual', '2026', '2026-01-01', $this->head);
    $oldIndex = File::get($this->activeIndexPath);
    $oldPdf = File::get(dirname($this->activeIndexPath).'/manual.pdf');
    $filesystem = new Filesystem;
    $failed = false;
    File::partialMock()->shouldReceive('replace')->andReturnUsing(function (string $path, string $content) use ($filesystem, &$failed): void {
        if ($path === $this->activeIndexPath && ! $failed) {
            $failed = true;
            throw new RuntimeException('Simulated index publication failure');
        }
        $filesystem->replace($path, $content);
    });

    expect(fn () => $library->activate($next))->toThrow(RuntimeException::class, 'publication failure');
    expect($next->fresh()->activated_at)->toBeNull()
        ->and($library->active()->id)->toBe($previous->id)
        ->and(File::get($this->activeIndexPath))->toBe($oldIndex)
        ->and(File::get(dirname($this->activeIndexPath).'/manual.pdf'))->toBe($oldPdf);
});

test('invalid saved index metadata cannot be previewed or activated', function (array $invalid) {
    $library = app(ResearchManualLibrary::class);
    $manual = $library->upload($this->sourcePath, 'Research Manual', '2026', '2026-01-01', $this->head);
    $index = $library->index($manual);
    File::put($manual->indexPath(), json_encode([...$index, ...$invalid], JSON_THROW_ON_ERROR));

    expect(fn () => $library->index($manual))->toThrow(RuntimeException::class)
        ->and(fn () => $library->activate($manual))->toThrow(RuntimeException::class)
        ->and(is_file($this->activeIndexPath))->toBeFalse();
})->with([
    'invalid passage' => [['chunks' => [['content' => []]]]],
    'invalid page' => [['unindexed_pages' => ['two']]],
    'unknown schema' => [['schema_version' => 99]],
]);
