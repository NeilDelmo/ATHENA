<?php

use App\Models\LiteratureSource;
use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use App\Models\User;
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
        'relevance_score' => 83, 'relevance_label' => 'Strong match', 'match_reason' => 'Matched community and mangrove in title and abstract.', 'matched_terms' => ['community', 'mangrove'],
    ];
    $this->paragraph = 'This abstract-based summary describes a survey of coastal communities examining participation in mangrove monitoring. The study discusses continuity of monitoring and involvement in coastal stewardship. These themes are relevant to investigating local monitoring practices, although its detailed methods and limitations require checking the full paper.';
    $this->synthesisMock = mock(LiteratureSynthesisService::class);
    $this->actingAs($this->faculty);
});

function literatureChatPayload(ProposalDraft $draft, string $question = 'Find studies for my objectives', array $action = []): array
{
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
    test()->synthesisMock->shouldReceive('synthesize')->once()->andReturn(['synthesis' => $paragraph, 'basis' => 'abstract', 'notice' => 'Drafted only from the indexed abstract.', 'word_count' => 49, 'relationship' => 'related', 'transition' => '']);

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
        ->andReturn(['synthesis' => $this->paragraph, 'basis' => 'abstract', 'notice' => 'Abstract only', 'relationship' => 'related']);
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
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Suggest three studies for my objectives'))->assertOk()->assertJsonCount(3, 'literature.results');
});

test('batch drafting uses the end of a long RRL and retains the preceding generated paragraph', function () {
    $this->paper->update(['source_data' => [...$this->paper->source_data, 'related_literature' => str_repeat('Earlier discussion. ', 200).' The final RRL paragraph discusses coastal biodiversity.']]);
    mock(LiteratureSearchService::class)->shouldReceive('search')->once()->andReturn(literatureSearchPayload([$this->source, [...$this->source, 'title' => 'Coastal Biodiversity Monitoring', 'doi' => '10.1234/biodiversity.2024']]));
    $results = $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertOk()->json('literature.results');
    $calls = [];
    $this->synthesisMock->shouldReceive('synthesize')->twice()->andReturnUsing(function (array $paper) use (&$calls): array {
        $calls[] = $paper;

        return ['synthesis' => $this->paragraph, 'basis' => 'abstract', 'notice' => 'Abstract only', 'relationship' => 'related'];
    });
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft, 'Draft selected studies', ['type' => 'draft_literature', 'source_tokens' => array_column($results, 'source_token')]))->assertOk()->assertJsonCount(2, 'literature.drafts');
    expect($calls[0]['preceding_rrl_context'])->toContain('The final RRL paragraph discusses coastal biodiversity.')
        ->and($calls[1]['preceding_rrl_context'])->toEndWith($this->paragraph)
        ->and(mb_strlen($calls[1]['preceding_rrl_context']))->toBeLessThanOrEqual(2500);
});

test('chat tells the user to choose a proposal when no proposal context is available', function () {
    mock(LiteratureSearchService::class)->shouldNotReceive('search');
    $this->postJson(route('research-support.chat'), ['messages' => [['role' => 'user', 'content' => 'Find RRL for my objectives']]])->assertOk()->assertJsonMissingPath('literature')->assertJsonPath('model', 'athena-literature');
});

test('chat returns readable empty results without inventing sources', function () {
    $search = mock(LiteratureSearchService::class);
    $search->shouldReceive('search')->once()->andReturn(literatureSearchPayload([]));
    $search->shouldReceive('allProvidersFailed')->once()->andReturn(false);
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertOk()->assertJsonPath('literature.results', []);
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
    $this->postJson(route('research-support.chat'), literatureChatPayload($this->draft))->assertForbidden();
})->with(['owner', 'locked']);

test('chat drafts from server-held evidence and performs no save before confirmation', function () {
    $results = findAssistantLiterature($this->draft, $this->source);
    $this->synthesisMock->shouldReceive('synthesize')->once()->withArgs(fn (array $paper): bool => $paper['abstract'] === $this->source['description'] && $paper['title'] === $this->source['title'] && $paper['preceding_rrl_context'] === 'Previous work discusses local monitoring programs.')
        ->andReturn(['synthesis' => $this->paragraph, 'basis' => 'abstract', 'notice' => 'Abstract only', 'word_count' => 49, 'relationship' => 'related', 'transition' => '']);
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
    $results = findAssistantLiterature($this->draft, [...$this->source, 'description' => 'No description available from source.']);
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
        ->assertJsonPath('literature.sources.0.rrl_evidence_basis', 'abstract')->assertJsonPath('literature.editor_url', route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft));
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
    $synthesis->shouldReceive('synthesize')->twice()->andReturn(['synthesis' => $this->paragraph, 'basis' => 'abstract', 'notice' => 'Abstract only', 'relationship' => 'related']);
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
