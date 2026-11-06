<?php

use App\Exceptions\LiteratureFullTextPreviewException;
use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\LiteratureFullTextPreviewService;
use App\Services\LiteratureSearchService;
use App\Services\LiteratureSynthesisService;
use App\Services\ResearchAssistantLiteratureService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\mock;

beforeEach(function () {
    config(['services.openrouter.key' => '', 'services.gemini.key' => '']);
    Http::preventStrayRequests();
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create(['title' => 'Assistant Literature Research Call', 'academic_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id, 'project_title' => 'Community Mangrove Monitoring', 'duration_months' => 12, 'project_leader' => $this->faculty->name]);
    $this->paper = $this->draft->documents()->create([
        'document_type' => config('proposal_papers.detailed-proposal.document_type'),
        'position' => 0,
        'source_data' => [
            'general_objective' => 'Evaluate mangrove stewardship practices.',
            'specific_objectives' => [['description' => 'Assess community participation in mangrove monitoring.'], ['description' => 'Measure coastal biodiversity using remote sensing.']],
            'related_literature' => 'Previous work discusses local monitoring programs.',
        ],
    ]);
    $this->source = [
        'title' => 'Community Participation in Mangrove Monitoring',
        'description' => 'This study surveyed coastal communities and assessed participation in mangrove monitoring. It describes monitoring continuity, community involvement, and the importance of local coastal stewardship.',
        'authors' => 'Maria Santos, Luis Cruz', 'year' => 2024, 'doi' => '10.1234/mangrove.2024', 'url' => 'https://doi.org/10.1234/mangrove.2024', 'source' => 'OpenAlex',
        'full_text_token' => 'fixture-paper',
        'relevance_score' => 83, 'relevance_label' => 'Strong match', 'match_reason' => 'Matched community and mangrove in title and abstract.', 'matched_terms' => ['community', 'mangrove'],
    ];
    $this->paragraph = 'The paper describes a survey of coastal communities examining participation in mangrove monitoring. Its methods compare field observations and regular reporting logs, and its findings discuss continuity of monitoring and involvement in coastal stewardship. These themes are relevant to investigating local monitoring practices, with self-reported participation identified as a limitation.';
    mock(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->with('fixture-paper')->andReturn([
        'preview_text' => str_repeat('METHODS: Community surveys and field observation logs. RESULTS: Monitoring continuity varied with participation. LIMITATIONS: Participation was self-reported. ', 10),
        'coverage' => ['total_pages' => 1, 'extracted_pages' => 1, 'complete' => true, 'limited' => false,
            'partial_or_unknown' => false, 'truncated' => false, 'unreadable_pages' => []],
    ]);
    $this->synthesisMock = mock(LiteratureSynthesisService::class);
    $this->actingAs($this->faculty);
});

function literatureChatPayload(ProposalDraft $draft, string $question = 'Find studies for my objectives', array $action = []): array
{
    if (($action['type'] ?? null) === 'draft_literature' && ! array_key_exists('evidence_basis', $action)) {
        $action['evidence_basis'] = 'full_text';
    }

    return ['messages' => [['role' => 'user', 'content' => $question]], 'context' => ['proposal_draft_id' => $draft->id, 'paper_slug' => 'detailed-proposal'], ...($action !== [] ? ['action' => $action] : [])];
}

function literatureSearchPayload(array $results): array
{
    return ['results' => $results, 'provider_notice' => null, 'search_notice' => null];
}

function findAssistantLiterature(ProposalDraft $draft, array $source): array
{
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$source]));

    return test()->postJson(route('research-support.chat'), literatureChatPayload($draft))->assertOk()->json('literature');
}

function draftAssistantLiterature(ProposalDraft $draft, string $sourceToken, string $paragraph): array
{
    test()->synthesisMock->shouldReceive('synthesize')->once()->andReturn(['synthesis' => $paragraph, 'basis' => 'full_text', 'notice' => 'Drafted from the source paper.', 'word_count' => 49, 'relationship' => 'related', 'transition' => '']);

    return test()->postJson(route('research-support.chat'), literatureChatPayload($draft, 'Draft the selected RRL', ['type' => 'draft_literature', 'source_tokens' => [$sourceToken]]))->assertOk()->json('literature');
}

test('chat searches a numbered saved objective with recent filters even without an AI provider', function () {
    $year = now()->year;
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(function (string $query, array $filters, string $context) use ($year): bool {
        return $query === 'Measure coastal biodiversity using remote sensing.' && $filters === ['year_from' => $year - 4, 'year_to' => $year] && str_contains($context, 'coastal biodiversity');
    })->andReturn(literatureSearchPayload([$this->source]));

    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Suggest recent studies for objective 2'))->assertOk()
        ->assertJsonPath('model', 'athena-literature')->assertJsonPath('literature.kind', 'results')
        ->assertJsonPath('literature.context_basis', 'Saved specific objective 2')
        ->assertJsonPath('literature.results.0.relevance.reason', $this->source['match_reason'])
        ->assertJsonPath('literature.results.0.evidence_basis', 'abstract')->assertJsonPath('literature.results.0.can_synthesize', true);
    Http::assertNothingSent();
});

test('chat and the literature workspace use the same abbreviation aware academic search', function () {
    config(['literature.web_harvest.enabled' => false, 'services.openalex.semantic_search' => false]);
    Http::fake([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Internet of Things for Flood Monitoring',
            'abstract' => 'The researchers evaluated connected water level sensors for flood monitoring. Measurements were compared with reference readings to assess their reliability in changing water conditions.',
            'authors' => [['name' => 'Public Researcher']], 'year' => 2024,
            'externalIds' => ['DOI' => '10.1234/flood-monitoring'],
            'url' => 'https://example.org/flood-monitoring',
        ]]]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/*' => Http::response(['results' => []]),
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
        'api.ies.ed.gov/*' => Http::response(['response' => ['docs' => []]]),
        'doaj.org/*' => Http::response(['results' => []]),
        'export.arxiv.org/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"/>'),
    ]);

    $workspace = $this->postJson(route('research-support.literature-search'), [
        'query' => 'IoT flood monitoring',
    ])->assertOk()->json('results');
    $chat = $this->postJson(route('research-support.chat'), literatureChatPayload(
        $this->draft, 'Find studies about IoT flood monitoring',
    ))->assertOk()->json('literature.results');

    expect($workspace)->toHaveCount(1)
        ->and($chat)->toHaveCount(1)
        ->and($chat[0]['title'])->toBe($workspace[0]['title'])
        ->and($chat[0]['doi'])->toBe($workspace[0]['doi'])
        ->and($chat[0]['relevance']['matched_terms'])->toContain('iot', 'flood', 'monitoring')
        ->and($chat[0]['evidence_basis'])->toBe('abstract');
});

test('chat uses current browser objectives and explicitly labels unsaved context', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query, array $filters, string $context): bool => $query === 'Evaluate mobile tools for coastal reporting.' && str_contains($context, 'mobile tools'))->andReturn(literatureSearchPayload([$this->source]));
    $payload = literatureChatPayload($this->draft);
    $payload['context']['form'] = ['values' => [['field' => 'specific_objectives.0.description', 'label' => 'Objective 1', 'value' => 'Evaluate mobile tools for coastal reporting.']]];

    $this->postJson(route('research-support.chat'), $payload)->assertOk()->assertJsonPath('literature.context_basis', 'Current browser objective values (may be unsaved)');
});

test('chat uses an explicitly requested topic and year range', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query, array $filters): bool => str_contains($query, 'solar irrigation') && $filters === ['year_from' => 2020, 'year_to' => 2024])->andReturn(literatureSearchPayload([$this->source]));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Find papers on solar irrigation from 2020 to 2024'))->assertOk()->assertJsonPath('literature.year_from', 2020);
});

test('chat preserves common articles in an explicit search topic', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query): bool => $query === 'the effects of social media')
        ->andReturn(literatureSearchPayload([$this->source]));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Find studies on the effects of social media'))->assertOk();
});

test('chat distinguishes literature search commands from guidance and examples', function (string $question) {
    expect(app(ResearchAssistantLiteratureService::class)->isLiteratureRequest($question))->toBeFalse();
})->with(['How do I find papers?', 'Give an example RRL paragraph', 'Explain how to search for sources', 'Do not suggest literature yet']);

test('choosing studies after RRL clarification searches the selected proposal objectives', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$this->source]));
    $payload = literatureChatPayload($this->draft, 'Finding studies');
    $payload['messages'] = [
        ['role' => 'user', 'content' => 'RRL part'],
        ['role' => 'assistant', 'content' => 'Do you mean requirements, reviewing your saved RRL, or finding studies?'],
        ...$payload['messages'],
    ];

    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()->assertJsonPath('literature.kind', 'results');
    Http::assertNothingSent();
});

test('chat searches when a direct literature request also asks for an explanation', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$this->source]));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Find recent studies and explain why they fit'))->assertOk()->assertJsonPath('literature.kind', 'results');
});

test('chat honors an ordinal objective across a browser snapshot and the synthesis request', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query): bool => $query === 'Measure coastal biodiversity using satellites.')
        ->andReturn(literatureSearchPayload([$this->source]));
    $payload = literatureChatPayload($this->draft, 'Find three studies for my second objective');
    $payload['context']['form'] = ['values' => [
        ['field' => 'specific_objectives.0.description', 'label' => 'Objective 1', 'value' => 'Evaluate unrelated fishing practices.'],
        ['field' => 'specific_objectives.1.description', 'label' => 'Objective 2', 'value' => 'Measure coastal biodiversity using satellites.'],
    ]];
    $results = $this->postJson(route('research-support.chat'), $payload)->assertOk()->assertJsonPath('literature.context_basis', 'Current browser specific objective 2 (may be unsaved)')->json('literature');
    $this->synthesisMock->shouldReceive('synthesize')->once()->withArgs(fn (array $paper): bool => $paper['proposal_objectives'] === 'Measure coastal biodiversity using satellites.')
        ->andReturn(['synthesis' => $this->paragraph, 'basis' => 'full_text', 'notice' => 'Paper text', 'relationship' => 'related']);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft selected study', ['type' => 'draft_literature', 'source_tokens' => [$results['results'][0]['source_token']]]))->assertOk();
});

test('chat falls back to the targeted saved objective when a browser snapshot contains a different objective', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query): bool => $query === 'Measure coastal biodiversity using remote sensing.')
        ->andReturn(literatureSearchPayload([$this->source]));
    $payload = literatureChatPayload($this->draft, 'Find papers for objective 2');
    $payload['context']['form'] = ['values' => [['field' => 'specific_objectives.0.description', 'label' => 'Objective 1', 'value' => 'Investigate unrelated fishing practices.']]];
    $this->postJson(route('research-support.chat'), $payload)->assertOk()->assertJsonPath('literature.context_basis', 'Saved specific objective 2');
});

test('chat respects a requested study count within its search limit', function () {
    $sources = array_map(fn (int $number): array => [...$this->source, 'title' => 'Mangrove Monitoring Study '.$number], range(1, 5));
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload($sources));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Suggest three studies for my objectives'))->assertOk()->assertJsonCount(3, 'literature.results')
        ->assertJsonPath('reply', fn (string $reply): bool => ! str_contains($reply, 'Chat can show up to'));
});

test('chat explains the five-paper cap when asked for more studies', function (string $question, int $available, int $shown) {
    $sources = array_map(fn (int $number): array => [...$this->source, 'title' => 'Mangrove Monitoring Study '.$number], range(1, $available));
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload($sources));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, $question))->assertOk()
        ->assertJsonCount($shown, 'literature.results')
        ->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'You asked for 10 studies.')
            && str_contains($reply, 'Chat can show up to 5 studies per search.')
            && str_contains($reply, 'Sources tab'));
})->with([
    'number' => ['Find 10 RRL studies for my objectives', 8, 5],
    'spelled out with fewer results' => ['Suggest ten papers for my objectives', 3, 3],
]);

test('chat does not present a publication year as the requested study count', function () {
    $sources = array_map(fn (int $number): array => [...$this->source, 'title' => 'Mangrove Monitoring Study '.$number], range(1, 6));
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload($sources));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Find the 2024 studies about mangrove monitoring'))->assertOk()
        ->assertJsonCount(5, 'literature.results')
        ->assertJsonPath('reply', fn (string $reply): bool => ! str_contains($reply, 'Chat can show up to'));
});

test('batch drafting uses the end of a long RRL and retains the preceding generated paragraph', function () {
    $this->paper->update(['source_data' => [...$this->paper->source_data, 'related_literature' => str_repeat('Earlier discussion. ', 200).' The final RRL paragraph discusses coastal biodiversity.']]);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$this->source, [...$this->source, 'title' => 'Coastal Biodiversity Monitoring', 'doi' => '10.1234/biodiversity.2024']]));
    $results = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertOk()->json('literature.results');
    $calls = [];
    $this->synthesisMock->shouldReceive('synthesize')->twice()->andReturnUsing(function (array $paper) use (&$calls): array {
        $calls[] = $paper;

        return ['synthesis' => $this->paragraph, 'basis' => 'full_text', 'notice' => 'Paper text', 'relationship' => 'related'];
    });
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft selected studies', ['type' => 'draft_literature', 'source_tokens' => array_column($results, 'source_token')]))->assertOk()->assertJsonCount(2, 'literature.drafts');
    expect($calls[0]['preceding_rrl_context'])->toContain('The final RRL paragraph discusses coastal biodiversity.')
        ->and($calls[1]['preceding_rrl_context'])->toEndWith($this->paragraph)
        ->and(mb_strlen($calls[1]['preceding_rrl_context']))->toBeLessThanOrEqual(2500);
});

test('chat tells the user to choose a proposal when no proposal context is available', function () {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Find RRL for my objectives']]])->assertOk()->assertJsonPath('literature.kind', 'choose_proposal')->assertJsonPath('literature.proposals.0.id', $this->draft->id)->assertJsonPath('model', 'athena-literature');
});

test('chat returns readable empty results without inventing sources', function () {
    $search = mock(LiteratureSearchService::class);
    $search->shouldReceive('search')->once()->andReturn(literatureSearchPayload([]));
    $search->shouldReceive('allProvidersFailed')->once()->andReturn(false);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertOk()
        ->assertJsonMissingPath('literature')->assertJsonPath('reply', fn (string $reply): bool => str_contains($reply, 'broaden the search') && str_ends_with($reply, '?'));
});

test('chat reports academic provider failure instead of fabricating literature', function () {
    $search = mock(LiteratureSearchService::class);
    $search->shouldReceive('search')->once()->andReturn(literatureSearchPayload([]));
    $search->shouldReceive('allProvidersFailed')->once()->andReturn(true);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertServiceUnavailable();
});

test('chat restricts paper literature actions to editable accessible proposals', function (string $restriction) {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    if ($restriction === 'owner') {
        $other = User::factory()->create();
        $other->assignRole('faculty');
        $this->actingAs($other);
    } else {
        $this->draft->update(['status' => ProposalDraft::STATUS_SUBMITTING]);
    }
    $response = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft));
    if ($restriction === 'owner') {
        $response->assertForbidden();
    } else {
        $response->assertSuccessful()->assertJsonPath('literature.kind', 'choose_proposal')->assertJsonPath('literature.proposals', []);
        $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Add reviewed text', [
            'type' => 'confirm_literature', 'drafts' => [['draft_token' => str_repeat('a', 64), 'paragraph' => $this->paragraph]],
        ]))->assertForbidden();
    }
})->with(['owner', 'locked']);

test('chat drafts from server-held evidence and performs no save before confirmation', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $this->synthesisMock->shouldReceive('synthesize')->once()->withArgs(fn (array $paper): bool => $paper['abstract'] === $this->source['description'] && $paper['title'] === $this->source['title'] && $paper['preceding_rrl_context'] === 'Previous work discusses local monitoring programs.')
        ->andReturn(['synthesis' => $this->paragraph, 'basis' => 'full_text', 'notice' => 'Paper text', 'word_count' => 49, 'relationship' => 'related', 'transition' => '']);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [$results['results'][0]['source_token']]]))->assertOk()->assertJsonPath('literature.drafts.0.paragraph', $this->paragraph);
    expect(LiteratureSource::count())->toBe(0)->and($this->draft->literatureSources()->count())->toBe(0)->and($this->paper->fresh()->source_data['related_literature'])->toBe('Previous work discusses local monitoring programs.');
});

test('chat refuses expired and fabricated source tokens', function (string $kind) {
    $token = str_repeat('a', 64);
    if ($kind === 'expired') {
        $results = findAssistantLiterature($this->draft, $this->source);
        $token = $results['results'][0]['source_token'];
        $this->travel(31)->minutes();
    }
    $this->synthesisMock->shouldNotReceive('synthesize');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [$token]]))->assertUnprocessable()->assertJsonValidationErrors('action');
})->with(['fabricated', 'expired']);

test('chat refuses a source token moved to a different proposal', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $otherDraft = $this->draft->replicate();
    $otherDraft->save();
    $this->synthesisMock->shouldNotReceive('synthesize');
    $this->postJson(route('research-support.chat'), literatureChatPayload($otherDraft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [$results['results'][0]['source_token']]]))->assertUnprocessable();
});

test('chat marks metadata-only studies and refuses to synthesize them', function () {
    $results = findAssistantLiterature($this->draft, [...$this->source, 'description' => 'No description available from source.', 'full_text_token' => null]);
    expect($results['results'][0]['can_synthesize'])->toBeFalse()->and($results['results'][0]['evidence_basis'])->toBe('metadata_only');
    $this->synthesisMock->shouldNotReceive('synthesize');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [$results['results'][0]['source_token']]]))->assertUnprocessable();
});

test('chat confirms reviewed paragraphs with references and leaves the paper editor in control', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $drafted = draftAssistantLiterature($this->draft, $results['results'][0]['source_token'], $this->paragraph);
    $request = literatureChatPayload($this->draft, 'Add the reviewed RRL to my paper', ['type' => 'confirm_literature', 'drafts' => [['draft_token' => $drafted['drafts'][0]['draft_token'], 'paragraph' => $this->paragraph]]]);
    $response = $this->postJson(route('research-support.chat'), $request)->assertOk()->assertJsonPath('literature.kind', 'insertion')
        ->assertJsonPath('literature.sources.0.rrl_note', $this->paragraph)->assertJsonPath('literature.sources.0.rrl_draft_status', 'confirmed')
        ->assertJsonPath('literature.sources.0.rrl_evidence_basis', 'full_text')->assertJsonPath('literature.editor_url', route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft));
    expect($response->json('literature.sources.0.reference'))->toContain('2024', '10.1234/mangrove.2024');
    $this->postJson(route('research-support.chat'), $request)->assertOk();
    expect(LiteratureSource::count())->toBe(1)->and($this->draft->literatureSources()->count())->toBe(1)->and($this->paper->fresh()->source_data['related_literature'])->toBe('Previous work discusses local monitoring programs.');
    $request['action']['drafts'][0]['paragraph'] .= ' A changed paragraph.';
    $this->postJson(route('research-support.chat'), $request)->assertUnprocessable();
    expect($this->draft->literatureSources()->first()->rrl_note)->toBe($this->paragraph);
});

test('chat preserves literature cards and editable drafts in saved conversation history', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $drafted = draftAssistantLiterature($this->draft, $results['results'][0]['source_token'], $this->paragraph);
    $drafted['confirmed'] = true;
    $drafted['applied'] = true;
    $saved = $this->postJson(route('research-support.history.save'), ['messages' => [
        ['role' => 'user', 'content' => 'Find related studies'], ['role' => 'assistant', 'content' => 'Here are studies', 'literature' => $results], ['role' => 'assistant', 'content' => 'Review this paragraph', 'literature' => $drafted],
    ], 'context' => ['proposal_draft_id' => $this->draft->id]])->assertOk()->json('conversation');
    $this->getJson(route('research-support.history.show', $saved['id']))->assertOk()
        ->assertJsonPath('conversation.messages.1.literature.results.0.source_token', $results['results'][0]['source_token'])
        ->assertJsonPath('conversation.messages.2.literature.drafts.0.paragraph', $this->paragraph)
        ->assertJsonPath('conversation.messages.2.literature.confirmed', true)
        ->assertJsonPath('conversation.messages.2.literature.applied', true);
});

test('confirmation retries retain their reviewed paragraph after another draft replaces the linked note', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $synthesis = $this->synthesisMock;
    $synthesis->shouldReceive('synthesize')->twice()->andReturn(['synthesis' => $this->paragraph, 'basis' => 'full_text', 'notice' => 'Paper text', 'relationship' => 'related']);
    $action = ['type' => 'draft_literature', 'source_tokens' => [$results['results'][0]['source_token']]];
    $first = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', $action))->assertOk()->json('literature.drafts.0.draft_token');
    $second = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', $action))->assertOk()->json('literature.drafts.0.draft_token');
    $firstRequest = literatureChatPayload($this->draft, 'Add reviewed RRL', ['type' => 'confirm_literature', 'drafts' => [['draft_token' => $first, 'paragraph' => $this->paragraph]]]);
    $this->postJson(route('research-support.chat'), $firstRequest)->assertOk();
    $updated = $this->paragraph.' The researcher revised these notes after further reading.';
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Add revised RRL', ['type' => 'confirm_literature', 'drafts' => [['draft_token' => $second, 'paragraph' => $updated]]]))->assertOk();
    $this->postJson(route('research-support.chat'), $firstRequest)->assertOk()->assertJsonPath('literature.sources.0.rrl_note', $this->paragraph);
    expect($this->draft->literatureSources()->first()->rrl_note)->toBe($updated);
});

test('chat rejects too many selected studies and unexpected evidence fields', function () {
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64), str_repeat('d', 64)]]))->assertUnprocessable()->assertJsonValidationErrors('action.source_tokens');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft RRL', ['type' => 'draft_literature', 'source_tokens' => [str_repeat('a', 64)], 'abstract' => 'Invented evidence']))->assertUnprocessable()->assertJsonValidationErrors('action');
});

test('RRL drafting uses attached papers and remains reviewable before adding citations', function () {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $linked = $this->draft->literatureSources()->create([
        'fingerprint' => ProposalDraftLiteratureSource::fingerprintFor($this->source['doi'], $this->source['title'], 2024),
        'title' => $this->source['title'], 'doi' => $this->source['doi'], 'authors' => $this->source['authors'], 'publication_year' => 2024, 'provider' => 'fixture',
        'evidence_document' => ['pages' => [['number' => 1, 'text' => str_repeat('Complete source paper text. ', 30)]],
            'coverage' => ['total_pages' => 1, 'extracted_pages' => 1, 'complete' => true, 'limited' => false,
                'partial_or_unknown' => false, 'truncated' => false, 'unreadable_pages' => []]],
    ]);
    $before = $this->paper->fresh()->source_data;
    $this->synthesisMock->shouldReceive('synthesize')->once()->withArgs(fn (array $paper): bool => $paper['title'] === $this->source['title']
        && $paper['evidence_basis'] === 'full_text' && str_contains($paper['evidence_text'], 'Complete source paper text.'))
        ->andReturn(['synthesis' => $this->paragraph, 'relationship' => 'related']);
    $packet = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL for my proposal.', ['type' => 'draft_rrl']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'draft')->assertJsonPath('literature.drafts.0.paragraph', $this->paragraph)
        ->json('literature');
    expect($this->paper->fresh()->source_data)->toBe($before)
        ->and($this->draft->literatureSources()->count())->toBe(1);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Add the reviewed RRL.', [
        'type' => 'confirm_literature', 'drafts' => [['draft_token' => $packet['drafts'][0]['draft_token'], 'paragraph' => $this->paragraph]],
    ]))->assertSuccessful()->assertJsonPath('literature.kind', 'insertion');
    expect($linked->fresh()->rrl_note)->toBe($this->paragraph);
});

test('RRL drafting finds readable papers for new sections and skips inaccessible search results', function (bool $typedRequest) {
    $this->paper->update(['source_data' => [...$this->paper->source_data, 'related_literature' => '']]);
    $unreadable = [...$this->source, 'title' => 'Inaccessible paper', 'doi' => '10.1234/inaccessible', 'full_text_token' => 'unreadable-paper'];
    app(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->with('unreadable-paper')
        ->andThrow(new LiteratureFullTextPreviewException('The PDF is inaccessible.'));
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$unreadable, $this->source]));
    $this->synthesisMock->shouldReceive('synthesize')->once()->andReturn(['synthesis' => $this->paragraph, 'relationship' => 'related']);
    $packet = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Write my RRL using source papers.', $typedRequest ? [] : ['type' => 'draft_rrl']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'draft')->assertJsonCount(1, 'literature.drafts')
        ->assertJsonPath('literature.drafts.0.title', $this->source['title'])->json('literature');
    expect($this->paper->fresh()->source_data['related_literature'])->toBe('');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Add the reviewed RRL.', [
        'type' => 'confirm_literature', 'drafts' => [['draft_token' => $packet['drafts'][0]['draft_token'], 'paragraph' => $this->paragraph]],
    ]))->assertSuccessful()->assertJsonPath('literature.sources.0.title', $this->source['title']);
})->with([false, true]);

test('RRL drafting keeps the conversation usable when full papers are unavailable', function (string $availability) {
    $source = [...$this->source, 'full_text_token' => $availability === 'unreadable' ? 'unreadable-paper' : null];
    if ($availability === 'unreadable') {
        app(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->with('unreadable-paper')
            ->andThrow(new LiteratureFullTextPreviewException('The PDF is inaccessible.'));
    }
    $results = $availability === 'none' ? [] : [$source];
    $search = mock(LiteratureSearchService::class);
    $search->shouldReceive('search')->once()->andReturn(literatureSearchPayload($results));
    if ($results === []) {
        $search->shouldReceive('allProvidersFailed')->andReturn(false);
    }
    $this->synthesisMock->shouldNotReceive('synthesize');
    $response = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL.', ['type' => 'draft_rrl']))
        ->assertSuccessful()->assertJsonMissingPath('literature.drafts.0');
    $response->assertJsonPath('literature.kind', 'results')->assertJsonPath('literature.intent', 'draft')
        ->assertJsonCount(count($results), 'literature.results');
})->with(['metadata', 'unreadable', 'none']);

test('RRL drafting cannot read another faculty members project', function () {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->synthesisMock->shouldNotReceive('synthesize');
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    $this->actingAs($outsider)->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL.', ['type' => 'draft_rrl']))
        ->assertForbidden();
});

function fakeAssistantLiteraturePlan(array $plan): void
{
    config(['services.openrouter.key' => 'topic-test-key', 'services.openrouter.model' => 'topic-test-model', 'services.openrouter.base_url' => 'https://topic-ai.test/v1']);
    Http::fake(['topic-ai.test/*' => Http::response([
        'choices' => [['message' => ['content' => json_encode($plan)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 45, 'completion_tokens' => 20],
    ])]);
}

test('unclear RRL topics ask a conversational question before searching or drafting', function (?string $action) {
    $this->draft->update(['project_title' => 'bibe girl']);
    $this->paper->update(['source_data' => ['related_literature' => '', 'rationale' => 'Topic-check-excluded-private-note']]);
    $before = $this->paper->fresh()->source_data;
    $reply = 'What does "bibe girl" refer to in your project, and what problem would you like to study?';
    fakeAssistantLiteraturePlan(['intent' => 'clarify', 'query' => '', 'reply' => $reply]);
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->synthesisMock->shouldNotReceive('synthesize');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL for my proposal using complete source papers.', $action ? ['type' => $action] : []))
        ->assertSuccessful()->assertJsonPath('reply', $reply)->assertJsonMissingPath('literature')
        ->assertJsonMissingPath('proposal_changes')->assertJsonPath('usage.completion_tokens', 20);
    expect($this->paper->fresh()->source_data)->toBe($before)
        ->and($this->draft->fresh()->project_title)->toBe('bibe girl');
    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        $data = json_decode($request['messages'][1]['content'], true);

        return $data['project_title'] === 'bibe girl' && $data['objectives'] === ''
            && $request['response_format']['json_schema']['name'] === 'athena_literature_topic'
            && ! array_key_exists('project_details', $data) && ! array_key_exists('linked_source_titles', $data)
            && ! str_contains($request->body(), 'Topic-check-excluded-private-note');
    });
})->with(['draft button' => ['draft_rrl'], 'typed request' => [null], 'search action' => ['search_literature']]);

test('clear objectives let RRL drafting proceed despite an informal title', function () {
    $this->draft->update(['project_title' => 'bibe girl']);
    fakeAssistantLiteraturePlan(['intent' => 'draft', 'query' => 'community mangrove monitoring participation', 'reply' => '']);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()
        ->withArgs(fn (string $query): bool => $query === 'community mangrove monitoring participation')
        ->andReturn(literatureSearchPayload([$this->source]));
    $this->synthesisMock->shouldReceive('synthesize')->once()->andReturn(['synthesis' => $this->paragraph, 'relationship' => 'related']);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL for my proposal.', ['type' => 'draft_rrl']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'draft');
    Http::assertSent(function ($request): bool {
        $data = json_decode($request['messages'][1]['content'], true);

        return str_contains($data['objectives'], 'mangrove stewardship');
    });
});

test('a short clear research title is not rejected by its word count', function () {
    $this->draft->update(['project_title' => 'Dengue']);
    $this->paper->update(['source_data' => ['related_literature' => '']]);
    fakeAssistantLiteraturePlan(['intent' => 'search', 'query' => 'dengue prevention', 'reply' => '']);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->withArgs(fn (string $query): bool => $query === 'dengue prevention')
        ->andReturn(literatureSearchPayload([$this->source]));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertSuccessful()->assertJsonPath('literature.kind', 'results');
});

test('ordinary topic clarification replies continue the original RRL drafting request', function (bool $secondQuestion, bool $legacyResult) {
    $this->draft->update(['project_title' => 'bibe girl']);
    $this->paper->update(['source_data' => ['related_literature' => '']]);
    $reply = $legacyResult
        ? 'I can draft your RRL once readable source papers are available. I could not read complete papers from this search. Try another topic or attach source PDFs, then choose Draft RRL again.'
        : 'What research topic does "bibe girl" refer to?';
    $payload = literatureChatPayload($this->draft);
    $payload['messages'] = [
        ['role' => 'user', 'content' => 'Draft an RRL for my proposal using complete source papers.'],
        ['role' => 'assistant', 'content' => $reply],
        ...($secondQuestion ? [
            ['role' => 'user', 'content' => 'It involves nearby communities.'],
            ['role' => 'assistant', 'content' => 'Which community activity are you studying?'],
        ] : []),
        ['role' => 'user', 'content' => 'I mean community participation in mangrove monitoring.'],
    ];
    fakeAssistantLiteraturePlan(['intent' => 'draft', 'query' => 'community participation mangrove monitoring', 'reply' => '']);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()
        ->withArgs(fn (string $query, array $filters, string $context): bool => $query === 'community participation mangrove monitoring' && $context === $query)
        ->andReturn(literatureSearchPayload([$this->source]));
    $this->synthesisMock->shouldReceive('synthesize')->once()
        ->withArgs(fn (array $paper): bool => $paper['proposal_topic'] === 'community participation mangrove monitoring' && $paper['proposal_objectives'] === '')
        ->andReturn(['synthesis' => $this->paragraph, 'relationship' => 'related']);
    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()->assertJsonPath('literature.kind', 'draft');
    expect($this->paper->fresh()->source_data['related_literature'])->toBe('')
        ->and($this->draft->fresh()->project_title)->toBe('bibe girl');
    Http::assertSent(function ($request) use ($reply): bool {
        $data = json_decode($request['messages'][1]['content'], true);

        return collect($data['recent_messages'])->contains(fn (array $message): bool => $message['content'] === $reply)
            && str_contains($data['question'], 'community participation');
    });
})->with(['first clarification' => [false, false], 'second clarification' => [true, false], 'older empty result' => [false, true]]);

test('topic followups can change the subject without starting a literature search', function () {
    config(['services.openrouter.key' => 'topic-test-key', 'services.openrouter.model' => 'topic-test-model', 'services.openrouter.base_url' => 'https://topic-ai.test/v1']);
    Http::fake(['topic-ai.test/*' => Http::sequence()
        ->push(['choices' => [['message' => ['content' => json_encode(['intent' => 'other', 'query' => '', 'reply' => ''])], 'finish_reason' => 'stop']]])
        ->push(['choices' => [['message' => ['content' => 'A literature review connects prior research to your research question.'], 'finish_reason' => 'stop']], 'model' => 'topic-test-model'])]);
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $payload = literatureChatPayload($this->draft);
    $payload['messages'] = [
        ['role' => 'user', 'content' => 'Draft my RRL.'],
        ['role' => 'assistant', 'content' => 'Which research topic should I use?'],
        ['role' => 'user', 'content' => 'Actually, stop. Explain what a literature review does.'],
    ];
    $this->postJson(route('research-support.chat'), $payload)->assertSuccessful()
        ->assertJsonPath('reply', 'A literature review connects prior research to your research question.')->assertJsonMissingPath('literature');
    Http::assertSentCount(2);
});

test('invalid topic planning output cannot trigger a search or generate sources', function (array|string $plan) {
    config(['services.openrouter.key' => 'topic-test-key', 'services.openrouter.model' => 'topic-test-model', 'services.openrouter.base_url' => 'https://topic-ai.test/v1']);
    Http::fake(['topic-ai.test/*' => Http::response(['choices' => [['message' => ['content' => is_array($plan) ? json_encode($plan) : $plan], 'finish_reason' => 'stop']]])]);
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->synthesisMock->shouldNotReceive('synthesize');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft my RRL.', ['type' => 'draft_rrl']))
        ->assertStatus(502)->assertJsonMissingPath('literature');
})->with([
    'malformed' => ['{broken'],
    'unknown intent' => [['intent' => 'save', 'query' => 'mangroves', 'reply' => '']],
    'empty ready query' => [['intent' => 'draft', 'query' => '', 'reply' => '']],
]);

test('a topic planner outage remains distinguishable from an unclear research topic', function (int $status) {
    config(['services.openrouter.key' => 'topic-test-key', 'services.openrouter.model' => 'topic-test-model', 'services.openrouter.base_url' => 'https://topic-ai.test/v1']);
    Http::fake(['topic-ai.test/*' => Http::response([], $status)]);
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft my RRL.', ['type' => 'draft_rrl']))
        ->assertStatus($status === 429 ? 429 : 502)->assertJsonMissingPath('literature');
})->with([429, 503]);

test('private proposal topic planning cannot send another facultys data to AI', function () {
    fakeAssistantLiteraturePlan(['intent' => 'draft', 'query' => 'mangroves', 'reply' => '']);
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->actingAs($outsider)->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft my RRL.', ['type' => 'draft_rrl']))
        ->assertForbidden();
    Http::assertNothingSent();
});

test('explicit chat search actions keep their requested topic and year filters without AI', function () {
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()
        ->withArgs(fn (string $query, array $filters): bool => $query === 'mangrove monitoring' && $filters === ['year_from' => 2020, 'year_to' => 2024])
        ->andReturn(literatureSearchPayload([$this->source]));
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Find studies for my proposal.', [
        'type' => 'search_literature', 'query' => 'Find studies about mangrove monitoring from 2020 to 2024',
    ]))->assertSuccessful()->assertJsonPath('literature.query', 'mangrove monitoring')
        ->assertJsonPath('literature.year_from', 2020)->assertJsonPath('literature.year_to', 2024);
    Http::assertNothingSent();
});

test('RRL topic planning keeps earlier decisions from the same saved chat', function () {
    $conversation = ResearchAssistantConversation::factory()->create([
        'user_id' => $this->faculty->id, 'context' => ['proposal_draft_id' => $this->draft->id, 'workspace' => 'faculty'],
        'messages' => array_fill(0, 10, ['role' => 'user', 'content' => 'Continue our RRL discussion.']),
        'summary' => 'Decision: Focus on community participation in coastal flood monitoring.', 'summarized_message_count' => 2,
    ]);
    fakeAssistantLiteraturePlan(['intent' => 'clarify', 'query' => '', 'reply' => 'Should I keep the coastal flood monitoring topic?']);
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->postJson(route('research-support.chat'), [
        ...literatureChatPayload($this->draft, 'Draft the RRL using our earlier decision.', ['type' => 'draft_rrl']),
        'conversation_id' => $conversation->id,
    ])->assertSuccessful();
    Http::assertSent(function ($request): bool {
        $basis = json_decode($request['messages'][1]['content'], true);

        return str_contains($basis['earlier_conversation'], 'community participation in coastal flood monitoring');
    });
});

test('automatic RRL drafting keeps looking beyond unreadable top results and uses the clarified topic', function () {
    $this->draft->update(['project_title' => 'Unclear working title']);
    app(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->with('blocked-paper')
        ->andThrow(new LiteratureFullTextPreviewException('The publisher blocked the download.', 502));
    $papers = array_map(fn (int $index): array => [...$this->source, 'title' => "Blocked study {$index}", 'doi' => "10.1234/blocked-{$index}", 'full_text_token' => 'blocked-paper'], range(1, 5));
    $papers[] = [...$this->source, 'title' => 'Gamified Studying and Learning Engagement'];
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload($papers));
    $this->synthesisMock->shouldReceive('synthesize')->once()->andReturn(['synthesis' => $this->paragraph, 'relationship' => 'related']);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL.', ['type' => 'draft_rrl', 'query' => 'gamified studying']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'draft')->assertJsonPath('literature.topic', 'gamified studying')
        ->assertJsonPath('literature.drafts.0.title', 'Gamified Studying and Learning Engagement')
        ->assertJsonPath('literature.read_failures.0.title', 'Blocked study 1');
});

test('unreadable paper links return a clear draft outcome and never remain marked readable', function () {
    app(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->with('blocked-paper')
        ->andThrow(new LiteratureFullTextPreviewException('The publisher blocked the download.', 502));
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([
        [...$this->source, 'full_text_token' => 'blocked-paper'],
    ]));
    $this->synthesisMock->shouldNotReceive('synthesize');
    $before = $this->paper->fresh()->source_data;
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL.', ['type' => 'draft_rrl', 'query' => 'gamified studying']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'results')->assertJsonPath('literature.intent', 'draft')
        ->assertJsonPath('literature.topic', 'gamified studying')->assertJsonPath('literature.results.0.can_read_paper', false)
        ->assertJsonPath('literature.results.0.can_synthesize', false)
        ->assertJsonPath('literature.read_failures.0.reason', fn ($reason): bool => str_contains($reason, 'publisher blocked'));
    expect($this->paper->fresh()->source_data)->toBe($before);
});

test('RRL drafting from another page asks for an accessible editable proposal instead of guessing', function () {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Draft an RRL for my proposal using complete source papers. Use the project title and objectives. Show the draft for my review.']]])
        ->assertSuccessful()->assertJsonPath('literature.kind', 'choose_proposal')
        ->assertJsonPath('literature.proposals.0.id', $this->draft->id)->assertJsonPath('literature.intent', 'draft');
    Http::assertNothingSent();
});

test('an empty fallback search retains the draft recovery actions and any linked paper failures', function (bool $linkedPaper) {
    if ($linkedPaper) {
        $this->draft->literatureSources()->create([
            'fingerprint' => ProposalDraftLiteratureSource::fingerprintFor(null, 'Unreadable linked paper', 2024),
            'title' => 'Unreadable linked paper', 'publication_year' => 2024, 'provider' => 'OpenAlex',
            'full_text_url' => 'https://example.org/blocked.pdf',
        ]);
        app(LiteratureFullTextPreviewService::class)->shouldReceive('preview')->withArgs(fn (string $token): bool => $token !== 'fixture-paper')
            ->andThrow(new LiteratureFullTextPreviewException('The publisher rejected the request.'));
    }
    $search = mock(LiteratureSearchService::class);
    $search->shouldReceive('search')->once()->andReturn(literatureSearchPayload([]));
    $search->shouldReceive('allProvidersFailed')->once()->andReturn(false);
    $this->synthesisMock->shouldNotReceive('synthesize');
    $response = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft an RRL.', ['type' => 'draft_rrl', 'query' => 'gamified studying']))
        ->assertSuccessful()->assertJsonPath('literature.kind', 'results')->assertJsonPath('literature.intent', 'draft')
        ->assertJsonPath('literature.topic', 'gamified studying')->assertJsonPath('literature.results', [])
        ->assertJsonPath('literature.editor_url', route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft));
    $response->assertJsonCount($linkedPaper ? 1 : 0, 'literature.read_failures');
})->with([false, true]);
