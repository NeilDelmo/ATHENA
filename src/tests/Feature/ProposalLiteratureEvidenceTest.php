<?php

use App\Actions\LinkLiteratureSourceToProposal;
use App\Actions\SaveLiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\AiChatCompletionService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create(['title' => 'Evidence Research Call', 'academic_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id, 'project_title' => 'Community Coastal Monitoring', 'duration_months' => 12, 'project_leader' => $this->faculty->name]);
    $saved = app(SaveLiteratureSource::class)->handle(['title' => 'Coastal Community Monitoring', 'authors' => 'Maria Santos', 'year' => 2024, 'source' => 'Manual entry'], $this->faculty);
    $this->source = app(LinkLiteratureSourceToProposal::class)->handle($this->draft, $saved['source'], $this->faculty)['link'];
    Storage::fake('local');
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Process::fake(fn (PendingProcess $process) => Process::result(output: Str::contains(is_array($process->command) ? implode(' ', $process->command) : $process->command, 'pdfinfo') ? "Pages: 2\n" : "Community monitoring improved local reporting.\fBiodiversity reporting varied across coastal villages.\f"));
    $this->actingAs($this->faculty);
});

function evidenceRoute(string $action, ProposalDraft $draft, ProposalDraftLiteratureSource $source, ?string $passageId = null): string
{
    return route('faculty.proposal-drafts.literature-evidence.'.$action, [$draft, $source, ...($passageId ? [$passageId] : [])]);
}

function uploadEvidencePdf(ProposalDraft $draft, ProposalDraftLiteratureSource $source): array
{
    return test()->postJson(evidenceRoute('document.store', $draft, $source), ['file' => UploadedFile::fake()->create('coastal-study.pdf', 50, 'application/pdf')])->assertCreated()->json();
}

function saveEvidenceQuote(ProposalDraft $draft, ProposalDraftLiteratureSource $source, string $quote = 'Community monitoring improved local reporting.', ?int $page = 1): array
{
    return test()->postJson(evidenceRoute('passages.store', $draft, $source), ['kind' => 'quote', 'quote' => $quote, 'note' => 'Relevant to coastal reporting.', 'page' => $page])->assertCreated()->json('passages.0');
}

function evidenceAssistancePayload(ProposalDraftLiteratureSource $source, string $passageId, string $mode = 'synthesize'): array
{
    return ['mode' => $mode, 'evidence' => [['source_link_id' => $source->id, 'passage_ids' => [$passageId]]], ...($mode === 'support' ? ['claim' => 'Community monitoring improves coastal reporting.'] : [])];
}

function fakeEvidenceAssistant(array $output): void
{
    $ai = mock(AiChatCompletionService::class);
    $ai->shouldReceive('isConfigured')->once()->andReturn(true);
    $ai->shouldReceive('complete')->once()->andReturn(new Illuminate\Http\Client\Response(new Response(200, ['Content-Type' => 'application/json'], json_encode(['choices' => [['message' => ['content' => json_encode($output)], 'finish_reason' => 'stop']]]))));
}

test('uploaded PDFs remain private and retain extracted page numbers for the reader', function () {
    $payload = uploadEvidencePdf($this->draft, $this->source);
    $document = $this->source->fresh()->evidence_document;
    Storage::disk('local')->assertExists($document['path']);
    expect($this->source->fresh()->toArray())->not->toHaveKey('evidence_document')->not->toHaveKey('evidence_passages');
    expect($payload['document']['pages'])->toHaveCount(2)
        ->and($payload['document']['pages'][1]['number'])->toBe(2)
        ->and($payload['document']['coverage']['total_pages'])->toBe(2)
        ->and($payload['document']['coverage']['limited'])->toBeFalse()
        ->and($payload['source']['has_uploaded_document'])->toBeTrue()
        ->and($payload['document'])->not->toHaveKey('path')
        ->and($payload['document']['url'])->not->toContain('/storage/');
    $this->get(evidenceRoute('document.show', $this->draft, $this->source))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
    $download = $this->get(evidenceRoute('document.show', $this->draft, $this->source).'?download=1')->assertOk()->assertHeader('Content-Disposition');
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment;')->toContain('coastal-study.pdf');
    $this->getJson(evidenceRoute('show', $this->draft, $this->source))->assertOk()->assertJsonPath('document.pages.1.text', 'Biodiversity reporting varied across coastal villages.');
});

test('reader extraction enforces page and aggregate character limits', function () {
    config(['literature.evidence.maximum_pdf_pages' => 2, 'literature.evidence.maximum_characters' => 65]);
    Process::fake(fn (PendingProcess $process) => Process::result(output: Str::contains(implode(' ', $process->command), 'pdfinfo') ? "Pages: 8\n" : str_repeat('a', 40)."\f".str_repeat('b', 40)."\f"));
    $payload = uploadEvidencePdf($this->draft, $this->source);
    expect($payload['document']['coverage']['characters'])->toBe(65)->and($payload['document']['coverage']['limited'])->toBeTrue()
        ->and(mb_strlen($payload['document']['pages'][1]['text']))->toBe(25);
    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command) && in_array('-l', $process->command, true) && in_array('2', $process->command, true) && ! in_array('-nopgbrk', $process->command, true));
});

test('extraction failure preserves a readable PDF and permits clearly labelled manual evidence', function () {
    Process::fake(['*' => Process::result(exitCode: 1)]);
    $payload = uploadEvidencePdf($this->draft, $this->source);
    expect($payload['document']['pages'])->toBe([])->and($payload['document']['coverage']['limited'])->toBeTrue();
    $this->get(evidenceRoute('document.show', $this->draft, $this->source))->assertOk();
    $passage = saveEvidenceQuote($this->draft, $this->source);
    expect($passage['origin'])->toBe('manual');
});

test('PDF quotation save verifies the selected extracted page and uses immutable passage identifiers', function () {
    uploadEvidencePdf($this->draft, $this->source);
    $passage = saveEvidenceQuote($this->draft, $this->source, "Community\nmonitoring improved local reporting.");
    expect(Str::isUuid($passage['id']))->toBeTrue()->and($passage['origin'])->toBe('pdf')->and($passage['page'])->toBe(1);
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'quote', 'quote' => 'This study shows a fabricated coastal finding.', 'page' => 1])->assertUnprocessable()->assertJsonValidationErrors('quote');
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'quote', 'quote' => $passage['quote'], 'page' => 2])->assertUnprocessable();
    $this->deleteJson(evidenceRoute('passages.destroy', $this->draft, $this->source, $passage['id']))->assertOk()->assertJsonPath('passages', []);
    $replacement = saveEvidenceQuote($this->draft, $this->source);
    expect($replacement['id'])->not->toBe($passage['id']);
});

test('reading notes remain labelled as researcher context and cannot establish source findings', function () {
    $note = $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'note', 'note' => 'Compare reporting methods across villages.'])->assertCreated()->json('passages.0');
    expect($note['origin'])->toBe('researcher_note')->and($note['quote'])->toBe('');
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $note['id']))->assertUnprocessable()->assertJsonValidationErrors('evidence');
    Http::assertNothingSent();
});

test('PDF replacement removes obsolete PDF quotations while retaining researcher notes', function () {
    uploadEvidencePdf($this->draft, $this->source);
    $oldPath = $this->source->fresh()->evidence_document['path'];
    $passage = saveEvidenceQuote($this->draft, $this->source);
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'note', 'note' => 'Check monitoring methods.', 'page' => 1])->assertCreated();
    $payload = uploadEvidencePdf($this->draft, $this->source);
    Storage::disk('local')->assertMissing($oldPath);
    expect($payload['passages'])->toHaveCount(1)->and($payload['passages'][0]['kind'])->toBe('note')->and($payload['passages'][0]['page'])->toBeNull();
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $passage['id']))->assertUnprocessable();
});

test('source evidence and private downloads require proposal access', function (string $operation) {
    uploadEvidencePdf($this->draft, $this->source);
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $this->actingAs($other);
    match ($operation) {
        'read' => $this->getJson(evidenceRoute('show', $this->draft, $this->source))->assertForbidden(),
        'document' => $this->get(evidenceRoute('document.show', $this->draft, $this->source))->assertForbidden(),
        'upload' => $this->postJson(evidenceRoute('document.store', $this->draft, $this->source), ['file' => UploadedFile::fake()->create('paper.pdf', 1, 'application/pdf')])->assertForbidden(),
        'passage' => $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'note', 'note' => 'An unrelated note.'])->assertForbidden(),
    };
})->with(['read', 'document', 'upload', 'passage']);

test('a source link from another proposal cannot be read through the current proposal', function () {
    $other = $this->draft->replicate();
    $other->save();
    $this->getJson(evidenceRoute('show', $other, $this->source))->assertNotFound();
    $this->postJson(evidenceRoute('passages.store', $other, $this->source), ['kind' => 'note', 'note' => 'Mismatched source.'])->assertForbidden();
});

test('source uploads reject non PDF files before extraction', function () {
    Process::fake();
    $this->postJson(evidenceRoute('document.store', $this->draft, $this->source), ['file' => UploadedFile::fake()->create('document.html', 1, 'text/html')])->assertUnprocessable()->assertJsonValidationErrors('file');
    Process::assertNothingRan();
    Storage::disk('local')->assertDirectoryEmpty('proposal-literature');
});

test('evidence assistance uses authoritative saved quotations and returns server citation associations', function () {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    $ai = mock(AiChatCompletionService::class);
    $ai->shouldReceive('isConfigured')->once()->andReturn(true);
    $ai->shouldReceive('complete')->once()->withArgs(function (array $payload) use ($quote): bool {
        $sourceData = json_decode($payload['messages'][1]['content'], true);

        return $sourceData['selected_evidence'][0]['quote'] === $quote['quote'] && $sourceData['selected_evidence'][0]['origin'] === 'manual'
            && str_contains($payload['messages'][0]['content'], 'Researcher notes and instructions provide writing context, not evidence.');
    })->andReturn(new Illuminate\Http\Client\Response(new Response(200, ['Content-Type' => 'application/json'], json_encode(['choices' => [['message' => ['content' => json_encode(['paragraph' => 'The selected quotation describes improved reporting associated with community monitoring.', 'used_passage_ids' => [$quote['id']], 'supported' => true])], 'finish_reason' => 'stop']]]))));
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $quote['id'], 'support'))->assertOk()
        ->assertJsonPath('draft.can_insert', true)->assertJsonPath('draft.evidence.0.quote', $quote['quote'])->assertJsonPath('draft.evidence.0.source_link_id', $this->source->id)
        ->assertJsonPath('draft.evidence.0.origin', 'manual');
    expect($this->source->fresh()->rrl_note)->toBeNull();
});

test('evidence assistance rejects model fabricated citation identifiers and incomplete output', function (string $failure) {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    $generated = ['paragraph' => 'The selected quotation describes community reporting.', 'used_passage_ids' => [$quote['id']], 'supported' => true];
    if ($failure === 'citation') {
        $generated['paragraph'] = 'Community monitoring improved reporting [99].';
    } elseif ($failure === 'unknown') {
        $generated['used_passage_ids'] = [(string) Str::uuid()];
    } else {
        $generated['paragraph'] = 'An unfinished fragment';
    }
    fakeEvidenceAssistant($generated);
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $quote['id']))->assertStatus(502);
})->with(['citation', 'unknown', 'fragment']);

test('unsupported claims return a review explanation that cannot be inserted', function () {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    fakeEvidenceAssistant(['paragraph' => 'This quotation does not establish a causal improvement in biodiversity.', 'used_passage_ids' => [$quote['id']], 'supported' => false]);
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $quote['id'], 'support'))->assertOk()->assertJsonPath('supported', false)->assertJsonPath('draft.can_insert', false);
});

test('unavailable AI providers leave saved evidence unchanged', function (string $failure) {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    $ai = mock(AiChatCompletionService::class);
    $ai->shouldReceive('isConfigured')->once()->andReturn($failure !== 'unconfigured');
    if ($failure !== 'unconfigured') {
        $ai->shouldReceive('complete')->once()->andReturn(new Illuminate\Http\Client\Response(new Response($failure === 'rate_limit' ? 429 : 500)));
    }
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $quote['id']))
        ->assertStatus(match ($failure) {
            'unconfigured' => 503, 'rate_limit' => 429, default => 502
        });
    expect($this->source->fresh()->evidence_passages[0]['id'])->toBe($quote['id'])->and($this->source->fresh()->rrl_note)->toBeNull();
})->with(['unconfigured', 'rate_limit', 'provider_failure']);

test('evidence selection rejects a passage from a different proposal and client evidence content', function () {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    $other = $this->draft->replicate();
    $other->save();
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $other), evidenceAssistancePayload($this->source, $quote['id']))->assertUnprocessable();
    $payload = evidenceAssistancePayload($this->source, $quote['id']);
    $payload['evidence'][0]['quote'] = 'Fabricated client evidence';
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), $payload)->assertUnprocessable()->assertJsonValidationErrors('evidence.0');
});

test('assistance synthesizes multiple sources using saved page and passage associations', function () {
    uploadEvidencePdf($this->draft, $this->source);
    $first = saveEvidenceQuote($this->draft, $this->source);
    $saved = app(SaveLiteratureSource::class)->handle(['title' => 'Coastal Biodiversity Study', 'source' => 'Manual entry'], $this->faculty);
    $secondSource = app(LinkLiteratureSourceToProposal::class)->handle($this->draft, $saved['source'], $this->faculty)['link'];
    $second = saveEvidenceQuote($this->draft, $secondSource, 'Biodiversity reporting varied across coastal villages.', null);
    $ai = mock(AiChatCompletionService::class);
    $ai->shouldReceive('isConfigured')->once()->andReturn(true);
    $ai->shouldReceive('complete')->once()->withArgs(function (array $payload) use ($first, $second): bool {
        $evidence = json_decode($payload['messages'][1]['content'], true)['selected_evidence'];

        return count($evidence) === 2 && $evidence[0]['passage_id'] === $first['id'] && $evidence[0]['page'] === 1 && $evidence[1]['passage_id'] === $second['id'] && $evidence[1]['origin'] === 'manual';
    })->andReturn(new Illuminate\Http\Client\Response(new Response(200, ['Content-Type' => 'application/json'], json_encode(['choices' => [['message' => ['content' => json_encode(['paragraph' => 'These selected quotations discuss community reporting and differences between coastal villages.', 'used_passage_ids' => [$first['id'], $second['id']], 'supported' => true])], 'finish_reason' => 'stop']]]))));
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), ['mode' => 'synthesize', 'evidence' => [
        ['source_link_id' => $this->source->id, 'passage_ids' => [$first['id']]], ['source_link_id' => $secondSource->id, 'passage_ids' => [$second['id']]],
    ]])->assertOk()->assertJsonCount(2, 'draft.evidence')->assertJsonPath('draft.evidence.0.page', 1)->assertJsonPath('draft.evidence.1.source_link_id', $secondSource->id);
});

test('assistance refuses evidence removed while the AI response is being generated', function () {
    $quote = saveEvidenceQuote($this->draft, $this->source, 'Community monitoring improved local reporting.', null);
    $ai = mock(AiChatCompletionService::class);
    $ai->shouldReceive('isConfigured')->once()->andReturn(true);
    $ai->shouldReceive('complete')->once()->andReturnUsing(function () use ($quote) {
        $this->source->update(['evidence_passages' => []]);

        return new Illuminate\Http\Client\Response(new Response(200, ['Content-Type' => 'application/json'], json_encode(['choices' => [['message' => ['content' => json_encode(['paragraph' => 'The quotation describes community monitoring.', 'used_passage_ids' => [$quote['id']], 'supported' => true])], 'finish_reason' => 'stop']]])));
    });
    $this->postJson(route('faculty.proposal-drafts.literature-evidence-assistance', $this->draft), evidenceAssistancePayload($this->source, $quote['id']))->assertUnprocessable()->assertJsonValidationErrors('evidence');
});

test('locked proposals can read their private documents but cannot replace evidence', function () {
    uploadEvidencePdf($this->draft, $this->source);
    $this->draft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);
    $this->getJson(evidenceRoute('show', $this->draft, $this->source))->assertOk();
    $this->get(evidenceRoute('document.show', $this->draft, $this->source))->assertOk();
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'note', 'note' => 'A new reading note.'])->assertForbidden();
    $this->postJson(evidenceRoute('document.store', $this->draft, $this->source), ['file' => UploadedFile::fake()->create('paper.pdf', 50, 'application/pdf')])->assertForbidden();
});

test('whitespace-only evidence and oversized requests are rejected', function () {
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'quote', 'quote' => str_repeat(' ', 20)])->assertUnprocessable();
    $this->postJson(evidenceRoute('passages.store', $this->draft, $this->source), ['kind' => 'note', 'note' => str_repeat('x', 2001)])->assertUnprocessable();
    $this->postJson(evidenceRoute('document.store', $this->draft, $this->source), ['file' => UploadedFile::fake()->create('large.pdf', 8193, 'application/pdf')])->assertUnprocessable();
});

test('DOI lookup retrieves metadata from a fixed provider for explicit review', function () {
    Http::fake(['api.crossref.org/*' => Http::response(['message' => ['title' => ['Coastal Community Monitoring'], 'author' => [['given' => 'Maria', 'family' => 'Santos']], 'issued' => ['date-parts' => [[2024]]], 'container-title' => ['Coastal Journal'], 'DOI' => '10.1234/coastal.2024']])]);
    $this->postJson(route('research-support.literature-metadata'), ['identifier' => 'https://doi.org/10.1234/coastal.2024'])->assertOk()->assertJsonPath('source.title', 'Coastal Community Monitoring')->assertJsonPath('source.authors', 'Maria Santos')->assertJsonPath('source.year', 2024);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.crossref.org/works/10.1234%2Fcoastal.2024');
});

test('metadata lookup refuses arbitrary URLs without sending network requests', function () {
    $this->postJson(route('research-support.literature-metadata'), ['identifier' => 'http://127.0.0.1/private'])->assertUnprocessable();
    Http::assertNothingSent();
});

test('a PDF-only manual source can be saved without a DOI or external URL', function () {
    $this->postJson(route('research-support.literature-library.store'), ['title' => 'Unindexed local thesis', 'source' => 'Manual entry'])->assertCreated()->assertJsonPath('source.title', 'Unindexed local thesis');
});
