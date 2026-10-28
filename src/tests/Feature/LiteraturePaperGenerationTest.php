<?php

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\LiteratureFullTextPreviewService;
use App\Services\LiteratureSearchService;
use App\Support\LiteratureFullTextToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create(['title' => 'Paper generation fixture', 'academic_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Coastal monitoring', 'project_leader' => $this->faculty->name, 'duration_months' => 12]);
    $this->paper = $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['related_literature' => '<p>Keep the saved RRL.</p>', 'specific_objectives' => [['description' => 'Assess community monitoring.']]]]);
    $this->abstract = 'The abstract only introduces coastal monitoring and community participation. It omits the detailed research methods, results, and limitations found in the paper.';
    $this->source = ['title' => 'Authoritative Coastal Study', 'authors' => 'Source Author', 'year' => 2024,
        'description' => $this->abstract, 'doi' => '10.1234/coastal.2024', 'source' => 'fixture', 'url' => 'https://doi.org/10.1234/coastal.2024'];
    $this->fullText = 'METHODS: The source used a longitudinal matched-village design. RESULTS: A distinct field log documented seasonal reporting interruptions. LIMITATIONS: Coastal access restricted repeat visits. '.str_repeat('The paper describes recruitment, observation procedures, field logs, and interpretation in detail. ', 10);
    $this->paragraph = 'The paper describes a longitudinal comparison of village monitoring practices using repeated field observations and reporting logs. Its results discuss seasonal interruptions in reporting and the practical constraints affecting repeated coastal visits. These observations provide context for planning community monitoring, while the stated access limitations warrant caution when interpreting the findings.';
    config(['cache.default' => 'array', 'research_assistant.manual.enabled' => false, 'services.openrouter.key' => '',
        'services.gemini.key' => 'fixture-key', 'services.gemini.model' => 'fixture-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'synthesis' => $this->paragraph, 'relationship' => 'standalone', 'transition' => '',
    ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]])]);
    $this->attachSource = function (?ProposalDraft $draft = null, ?string $text = null): ProposalDraftLiteratureSource {
        return ($draft ?? $this->draft)->literatureSources()->create(['fingerprint' => ProposalDraftLiteratureSource::fingerprintFor($this->source['doi'], $this->source['title'], 2024),
            'title' => $this->source['title'], 'authors' => $this->source['authors'], 'publication_year' => 2024,
            'doi' => $this->source['doi'], 'abstract' => $this->abstract, 'provider' => 'fixture',
            'evidence_document' => ['pages' => [['number' => 1, 'text' => $text ?? $this->fullText],
                ['number' => 2, 'text' => $text ?? 'FINAL PAGE: Community validation meetings revealed a gap in the reporting protocol.']],
                'coverage' => ['total_pages' => 2, 'extracted_pages' => 2, 'limited' => false, 'partial_or_unknown' => false,
                    'complete' => true, 'truncated' => false, 'unreadable_pages' => []], 'notice' => 'All PDF pages were processed.']]);
    };
    $this->actingAs($this->faculty);
});

test('primary RRL generation reads the scoped attached paper and ignores forged client metadata', function () {
    $linked = ($this->attachSource)();
    $before = $this->paper->fresh()->source_data;
    $this->postJson(route('research-support.literature-synthesis'), ['proposal_draft_id' => $this->draft->id, 'source_link_id' => $linked->id,
        'title' => 'FORGED_TITLE', 'authors' => 'FORGED_AUTHOR', 'abstract' => 'FORGED_ABSTRACT'])->assertSuccessful()
        ->assertJsonPath('basis', 'full_text')->assertJsonPath('coverage.origin', 'uploaded_pdf')->assertJsonPath('coverage.complete', true)
        ->assertJsonPath('synthesis', $this->paragraph);
    Http::assertSent(fn ($request): bool => str_contains($request['messages'][1]['content'], 'longitudinal matched-village design')
        && str_contains($request['messages'][1]['content'], 'seasonal reporting interruptions')
        && str_contains($request['messages'][1]['content'], 'FINAL PAGE: Community validation meetings')
        && str_contains($request['messages'][1]['content'], $this->source['title'])
        && ! str_contains($request['messages'][1]['content'], 'FORGED_'));
    expect($this->paper->fresh()->source_data)->toBe($before)
        ->and($linked->fresh()->rrl_note)->toBeNull()->and(LiteratureSource::count())->toBe(0);
});

test('unreadable full-paper requests never silently fall back to an available abstract', function () {
    $linked = ($this->attachSource)(text: 'Unreadable scan.');
    $this->postJson(route('research-support.literature-synthesis'), ['proposal_draft_id' => $this->draft->id, 'source_link_id' => $linked->id])
        ->assertUnprocessable()->assertJsonValidationErrors('evidence_basis')
        ->assertJson(fn ($json) => $json->where('errors.evidence_basis.0', fn (string $message): bool => str_contains($message, 'The whole source paper could not be read.')
            && ! str_contains($message, 'Use abstracts instead'))->etc());
    Http::assertNothingSent();
});

test('abstract generation is rejected even when explicitly selected', function () {
    $linked = ($this->attachSource)(text: 'Unreadable scan.');
    $this->postJson(route('research-support.literature-synthesis'), ['proposal_draft_id' => $this->draft->id, 'source_link_id' => $linked->id, 'evidence_basis' => 'abstract'])
        ->assertUnprocessable()->assertJsonValidationErrors('evidence_basis');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Draft RRL.']],
        'context' => ['proposal_draft_id' => $this->draft->id],
        'action' => ['type' => 'draft_literature', 'source_tokens' => [str_repeat('a', 64)], 'evidence_basis' => 'abstract']])
        ->assertUnprocessable()->assertJsonValidationErrors('action.evidence_basis');
    Http::assertNothingSent();
});

test('abstract paragraphs cannot be saved or confirmed through legacy requests', function () {
    $linked = ($this->attachSource)();
    $this->putJson(route('faculty.proposal-drafts.literature-drafts.update', [$this->draft, $linked]), [
        'rrl_note' => $this->paragraph, 'rrl_evidence_basis' => 'abstract', 'rrl_draft_status' => 'confirmed',
    ])->assertUnprocessable()->assertJsonValidationErrors('rrl_evidence_basis');
    expect($linked->fresh()->rrl_note)->toBeNull();
    Http::assertNothingSent();
});

test('cached abstract drafts from older sessions cannot be confirmed', function () {
    $token = str_repeat('a', 64);
    Cache::put('assistant-literature:draft:'.hash('sha256', $token), [
        'user_id' => $this->faculty->id, 'proposal_draft_id' => $this->draft->id,
        'source' => $this->source, 'basis' => 'abstract',
    ], now()->addMinutes(30));
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Confirm this paragraph.']],
        'context' => ['proposal_draft_id' => $this->draft->id],
        'action' => ['type' => 'confirm_literature', 'drafts' => [['draft_token' => $token, 'paragraph' => $this->paragraph]]]])
        ->assertUnprocessable()->assertJsonValidationErrors('action.drafts');
    expect($this->draft->literatureSources()->count())->toBe(0);
    Http::assertNothingSent();
});

test('full-paper generation rejects browser text and incomplete scoped identifiers', function (array $input, string $error) {
    $this->postJson(route('research-support.literature-synthesis'), ['title' => 'A paper', ...$input])->assertUnprocessable()->assertJsonValidationErrors($error);
    Http::assertNothingSent();
})->with([
    [['evidence_text' => str_repeat('FORGED PAPER TEXT ', 40)], 'evidence_text'],
    [['proposal_draft_id' => 1], 'source_link_id'],
    [['source_link_id' => 1], 'proposal_draft_id'],
    [['full_text_token' => str_repeat('x', 8193)], 'full_text_token'],
]);

test('scoped generation cannot read another proposal source or an inaccessible draft', function (bool $outsider) {
    $other = $this->draft->replicate();
    $other->save();
    $linked = ($this->attachSource)($other);
    if ($outsider) {
        $user = User::factory()->create();
        $user->assignRole('faculty');
        $this->actingAs($user);
    }
    $this->postJson(route('research-support.literature-synthesis'), ['proposal_draft_id' => $this->draft->id, 'source_link_id' => $linked->id])->assertNotFound();
    Http::assertNothingSent();
})->with([true, false]);

test('standalone generation resolves provider issued full text and reports its coverage', function (bool $longLink) {
    $source = ['url' => 'https://example.org/study.pdf', 'provider' => 'fixture'];
    if ($longLink) {
        $source['url'] .= '?signature='.str_repeat('a', 2000);
        $source['identifier'] = str_repeat('b', 255);
    }
    $token = LiteratureFullTextToken::issue($source);
    if ($longLink) {
        expect(strlen($token))->toBeGreaterThan(4096)->toBeLessThanOrEqual(8192);
    }
    $coverage = ['characters' => mb_strlen($this->fullText), 'character_limit' => 120000, 'page_limit' => 200,
        'total_pages' => 2, 'extracted_pages' => 2, 'truncated' => false, 'limited' => false, 'complete' => true,
        'partial_or_unknown' => false, 'unreadable_pages' => []];
    mock(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->once()->with($token)->andReturn(['preview_text' => $this->fullText, 'coverage' => $coverage, 'notice' => 'All PDF pages were processed.']);
    $this->postJson(route('research-support.literature-synthesis'), ['title' => $this->source['title'], 'full_text_token' => $token])
        ->assertSuccessful()->assertJsonPath('basis', 'full_text')->assertJsonPath('coverage', $coverage);
    Http::assertSent(fn ($request): bool => str_contains($request['messages'][1]['content'], 'longitudinal matched-village design'));
})->with([false, true]);

test('partial or legacy PDF evidence cannot be presented as whole-paper generation', function (array $coverage) {
    $linked = ($this->attachSource)();
    $document = $linked->evidence_document;
    $document['coverage'] = $coverage;
    $linked->update(['evidence_document' => $document]);
    $this->postJson(route('research-support.literature-synthesis'), ['proposal_draft_id' => $this->draft->id, 'source_link_id' => $linked->id])
        ->assertUnprocessable()->assertJsonValidationErrors('evidence_basis');
    Http::assertNothingSent();
})->with([
    'old preview without coverage' => [[]],
    'first page only' => [['total_pages' => 2, 'extracted_pages' => 1, 'complete' => false, 'limited' => true]],
    'unknown total' => [['total_pages' => null, 'extracted_pages' => 2, 'complete' => true, 'limited' => false,
        'partial_or_unknown' => false, 'truncated' => false, 'unreadable_pages' => []]],
    'truncated text' => [['total_pages' => 2, 'extracted_pages' => 2, 'complete' => true, 'limited' => false,
        'partial_or_unknown' => false, 'truncated' => true, 'unreadable_pages' => []]],
]);

test('provider preview text with unknown coverage cannot silently qualify as a whole paper', function () {
    mock(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->once()->andReturn([
        'preview_text' => $this->fullText, 'coverage' => ['total_pages' => null, 'partial_or_unknown' => true],
    ]);
    $this->postJson(route('research-support.literature-synthesis'), ['title' => $this->source['title'], 'full_text_token' => 'provider-token'])
        ->assertUnprocessable()->assertJsonValidationErrors('evidence_basis');
    Http::assertNothingSent();
});

test('failed provider paper reads stop before generation instead of silently using abstracts', function () {
    mock(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->once()->with('provider-token')
        ->andThrow(new LiteratureFullTextPreviewException('Source is unavailable.', 502));
    $this->postJson(route('research-support.literature-synthesis'), ['title' => $this->source['title'], 'abstract' => $this->abstract, 'full_text_token' => 'provider-token'])
        ->assertUnprocessable()->assertJsonValidationErrors('evidence_basis');
    Http::assertNothingSent();
});

test('chat source selection reads its own attached paper and confirms the actual evidence basis', function () {
    $linked = ($this->attachSource)();
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(['results' => [$this->source], 'provider_notice' => null, 'search_notice' => null]);
    $context = ['proposal_draft_id' => $this->draft->id];
    $found = $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Find papers for my objectives']], 'context' => $context])
        ->assertSuccessful()->assertJsonPath('literature.results.0.can_read_paper', true)->json('literature.results.0.source_token');
    $drafted = $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Read the selected paper and draft RRL.']], 'context' => $context,
        'action' => ['type' => 'draft_literature', 'source_tokens' => [$found]]])->assertSuccessful()
        ->assertJsonPath('literature.drafts.0.evidence_basis', 'full_text')->assertJsonPath('literature.drafts.0.coverage.origin', 'uploaded_pdf')->json('literature.drafts.0');
    expect($linked->fresh()->rrl_note)->toBeNull()->and(LiteratureSource::count())->toBe(0);
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Accept this reviewed paragraph.']], 'context' => $context,
        'action' => ['type' => 'confirm_literature', 'drafts' => [['draft_token' => $drafted['draft_token'], 'paragraph' => $drafted['paragraph']]]]])
        ->assertSuccessful()->assertJsonPath('literature.sources.0.rrl_evidence_basis', 'full_text');
    expect($this->paper->fresh()->source_data['related_literature'])->toBe('<p>Keep the saved RRL.</p>');
    Http::assertSentCount(1);
});

test('chat validates all paper evidence before any generation and never borrows another proposal PDF', function () {
    ($this->attachSource)();
    $other = $this->draft->replicate();
    $other->save();
    $foreign = $other->literatureSources()->create(['fingerprint' => ProposalDraftLiteratureSource::fingerprintFor('10.1234/foreign.2024', 'Other Paper', 2024),
        'title' => 'Other Paper', 'doi' => '10.1234/foreign.2024', 'abstract' => $this->abstract, 'publication_year' => 2024,
        'evidence_document' => ['pages' => [['number' => 1, 'text' => str_repeat('FOREIGN PRIVATE PAPER. ', 50)]]], 'provider' => 'fixture']);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(['results' => [$this->source,
        [...$this->source, 'title' => $foreign->title, 'doi' => $foreign->doi]], 'provider_notice' => null, 'search_notice' => null]);
    $context = ['proposal_draft_id' => $this->draft->id];
    $results = $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Find papers']], 'context' => $context])
        ->assertSuccessful()->assertJsonPath('literature.results.1.can_read_paper', false)->json('literature.results');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Draft selected papers.']], 'context' => $context,
        'action' => ['type' => 'draft_literature', 'source_tokens' => array_column($results, 'source_token')]])->assertUnprocessable()->assertJsonValidationErrors('evidence_basis');
    Http::assertNothingSent();
});

test('chat can draft from several complete papers while keeping their evidence separate', function () {
    $first = ($this->attachSource)();
    $second = $first->replicate();
    $second->title = 'Participatory Coastal Reporting';
    $second->doi = '10.1234/participatory.2024';
    $second->fingerprint = ProposalDraftLiteratureSource::fingerprintFor($second->doi, $second->title, 2024);
    $document = $second->evidence_document;
    $document['pages'][0]['text'] = str_repeat('SECOND PAPER METHODS: Participatory mapping and collective reporting meetings. ', 12);
    $second->evidence_document = $document;
    $second->save();
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn([
        'results' => [$this->source, [...$this->source, 'title' => $second->title, 'doi' => $second->doi]],
        'provider_notice' => null, 'search_notice' => null,
    ]);
    $context = ['proposal_draft_id' => $this->draft->id];
    $results = $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Find papers']], 'context' => $context])
        ->assertSuccessful()->json('literature.results');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Draft the selected papers for my RRL.']],
        'context' => $context, 'action' => ['type' => 'draft_literature', 'source_tokens' => array_column($results, 'source_token')]])
        ->assertSuccessful()->assertJsonCount(2, 'literature.drafts')
        ->assertJsonPath('literature.drafts.0.evidence_basis', 'full_text')->assertJsonPath('literature.drafts.1.evidence_basis', 'full_text');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_contains($request['messages'][1]['content'], 'longitudinal matched-village design')
        && ! str_contains($request['messages'][1]['content'], 'SECOND PAPER METHODS'));
    Http::assertSent(fn ($request): bool => str_contains($request['messages'][1]['content'], 'SECOND PAPER METHODS')
        && ! str_contains($request['messages'][1]['content'], 'longitudinal matched-village design'));
    expect($this->paper->fresh()->source_data['related_literature'])->toBe('<p>Keep the saved RRL.</p>');
});
