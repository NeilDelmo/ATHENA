<?php

use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\ResearchCategory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\ResearchAssistantProposalAssistanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create(['title' => 'Assistance fixture', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Saved fixture proposal', 'project_leader' => $this->faculty->name,
        'duration_months' => 12, 'planned_start' => now()->addMonth(), 'planned_end' => now()->addMonths(13)]);
    $this->document = $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['rationale' => '<p>Assess community monitoring needs.</p>', 'related_literature' => ''], 'lock_version' => 1]);
    $this->indexPath = storage_path('framework/testing/proposal-assistance-'.Str::uuid().'.json');
    File::ensureDirectoryExists(dirname($this->indexPath));
    $this->writeManual = function (string $number = 'ten'): void {
        File::put($this->indexPath, json_encode(['schema_version' => 1, 'version' => hash('sha256', $number),
            'title' => 'Fixture Research Manual', 'chunks' => [[
                'id' => 'rrl', 'section' => 'DETAILED RESEARCH PROPOSAL', 'source_kind' => 'appendix', 'page_start' => 92, 'page_end' => 92,
                'content' => 'Review of Related Literature: minimum of '.$number.' literature/studies reviewed for proposal approval.',
            ]]], JSON_THROW_ON_ERROR));
    };
    ($this->writeManual)();
    config(['research_assistant.manual.enabled' => true, 'research_assistant.manual.index_path' => $this->indexPath,
        'cache.default' => 'array', 'services.openrouter.key' => null, 'services.gemini.key' => 'fixture-key',
        'services.gemini.model' => 'fixture-model', 'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    $this->fakeSuggestions = function (array $suggestions): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['summary' => 'Review the proposed edits.', 'suggestions' => $suggestions], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']],
            'model' => 'fixture-model', 'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 60],
        ])]);
    };
    $this->suggestion = ['field' => 'rationale', 'before' => 'Assess community monitoring needs.',
        'value' => 'Assess the needs for community monitoring.', 'reason' => 'Clarifies the wording.'];
    $this->assistance = app(ResearchAssistantProposalAssistanceService::class);
});

afterEach(function () {
    File::delete($this->indexPath);
});

test('proposal assistance compares empty and distinct actively cited literature against retrieved requirements', function () {
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => 0, 'minimum' => 10, 'shortfall' => 10, 'quality_verified' => false]);
    $ids = [];
    foreach (range(1, 10) as $number) {
        $ids[] = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'study'.$number), 'title' => 'Study '.$number, 'provider' => 'fixture'])->id;
    }
    $html = collect(array_slice($ids, 0, 7))->map(fn (int $id): string => '<p>Reviewed findings.<span data-proposal-citation="'.$id.'">[1]</span></p>')->join('');
    $html .= '<p>Repeated findings.<span data-proposal-citation="'.$ids[0].'">[1]</span></p>';
    $this->document->update(['source_data' => ['related_literature' => $html, 'references' => '<p>Ten library references.</p>']]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => 7, 'known_count' => 7, 'minimum' => 10, 'shortfall' => 3, 'minimum_satisfied' => false, 'quality_verified' => false]);
    ($this->writeManual)('twelve');
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['minimum' => 12, 'shortfall' => 5]);
    Http::assertNothingSent();
});

test('proposal assistance never guesses study counts from untracked writing or stale citation records', function () {
    $this->document->update(['source_data' => ['related_literature' => '<p>Seven studies are discussed here [1]–[7].</p>',
        'literature_citations' => '[{"source_link_id":99,"field":"related_literature"}]']]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => null, 'count_status' => 'unknown', 'shortfall' => null, 'minimum_satisfied' => null]);
    Http::assertNothingSent();
});

test('mixed valid and stale citations cannot establish a complete RRL count', function () {
    $linked = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'mixed-study'), 'title' => 'Mixed study', 'provider' => 'fixture']);
    $this->document->update(['source_data' => ['related_literature' => '<p>Review <span data-proposal-citation="'.$linked->id.'">[1]</span> and removed <span data-proposal-citation="99999999">[2]</span>.</p>']]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => null, 'known_count' => 1, 'count_status' => 'partial', 'minimum_satisfied' => null]);
    $this->document->update(['source_data' => ['related_literature' => '<span data-proposal-citation="'.$linked->id.'"></span>']]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => 0, 'known_count' => 0, 'count_status' => 'empty']);
    Http::assertNothingSent();
});

test('saved RRL coverage includes its exported opening paragraphs and deduplicates across both fields', function () {
    $ids = [];
    foreach (range(1, 2) as $number) {
        $ids[] = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'opening-study'.$number), 'title' => 'Opening study '.$number, 'provider' => 'fixture'])->id;
    }
    $this->document->update(['source_data' => [
        'introduction' => '<p>Opening study review <span data-proposal-citation="'.$ids[0].'">[1]</span>.</p>',
        'related_literature' => '<p>Further review <span data-proposal-citation="'.$ids[0].'">[1]</span> and <span data-proposal-citation="'.$ids[1].'">[2]</span>.</p>',
    ]]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => 2, 'known_count' => 2, 'count_status' => 'tracked']);
    $data = $this->document->source_data;
    $data['introduction'] = '<p>Untracked opening study discussion.</p>';
    $this->document->update(['source_data' => $data]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => null, 'known_count' => 2, 'count_status' => 'partial']);
    Http::assertNothingSent();
});

test('proposal literature counts remain partial when untracked blocks or invalid markers accompany known sources', function (string $untracked) {
    $link = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'tracked'), 'title' => 'Tracked study', 'provider' => 'fixture']);
    $html = '<p>Tracked findings.<span data-proposal-citation="'.$link->id.'">[1]</span></p>'.$untracked;
    $this->document->update(['source_data' => ['related_literature' => $html]]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => null, 'known_count' => 1, 'count_status' => 'partial', 'minimum_satisfied' => null]);
})->with(['<div>Additional untracked studies.</div>', 'Direct untracked text.', '<p>Removed source.<span data-proposal-citation="999999">[2]</span></p>']);

test('ten tracked sources satisfy only the numerical minimum and an unavailable rule stays unknown', function () {
    $html = '';
    foreach (range(1, 10) as $number) {
        $link = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'review'.$number), 'title' => 'Reviewed study '.$number, 'provider' => 'fixture']);
        $html .= '<p>Reviewed findings.<span data-proposal-citation="'.$link->id.'">[1]</span></p>';
    }
    $this->document->update(['source_data' => ['related_literature' => $html]]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['count' => 10, 'minimum_satisfied' => true, 'quality_verified' => false]);
    config(['research_assistant.manual.enabled' => false]);
    expect($this->assistance->assessment($this->draft)['rrl'])->toMatchArray(['minimum' => null, 'minimum_satisfied' => null, 'shortfall' => null]);
});

test('proposal suggestions are read only and resolve to exact saved HTML after validation', function () {
    ($this->fakeSuggestions)([$this->suggestion]);
    $before = $this->document->source_data;
    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale');
    $suggestion = $response['proposal_changes']['suggestions'][0];
    $resolved = $this->assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id']);
    expect($suggestion['token'])->toHaveLength(64)
        ->and($resolved)->toMatchArray(['token' => $suggestion['token'], 'before' => $before['rationale'], 'value' => $this->suggestion['value'], 'field' => 'rationale', 'document_version' => 1])
        ->and($this->document->fresh()->source_data)->toBe($before);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request['response_format']['json_schema']['strict'] === true
        && ! str_contains(json_encode($request['messages']), $this->faculty->email));
});

test('proposal suggestions reject arbitrary fields invented numeric commitments and mismatched baselines', function () {
    ($this->fakeSuggestions)([
        [...$this->suggestion, 'field' => 'related_literature'],
        [...$this->suggestion, 'value' => 'Survey 300 participants in 2027.'],
        [...$this->suggestion, 'before' => 'Unrelated old text'],
    ]);
    expect($this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes']['suggestions'])->toBe([]);
});

test('generic proposal improvements cannot borrow numerical commitments from unrelated RRL source findings', function () {
    $data = $this->document->source_data;
    $data['related_literature'] = '<p>An unrelated published study examined monitoring across 12 sites.</p>';
    $this->document->update(['source_data' => $data]);
    ($this->fakeSuggestions)([[...$this->suggestion, 'value' => 'Assess community monitoring needs across 12 sites.']]);

    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale');

    expect($response['proposal_changes']['suggestions'])->toBe([])
        ->and($this->document->fresh()->source_data['rationale'])->toBe($data['rationale']);
    Http::assertSent(function ($request): bool {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        $writingContext = json_encode([$payload['fields'], $payload['connected_saved_context']], JSON_THROW_ON_ERROR);

        return ! array_key_exists('related_literature', $payload['fields'])
            && ! array_key_exists('related_literature', $payload['connected_saved_context'])
            && ! str_contains($writingContext, 'unrelated published study')
            && ! str_contains($writingContext, '12 sites');
    });
});

test('proposal suggestions strip malicious markup from generated plaintext', function () {
    ($this->fakeSuggestions)([[...$this->suggestion, 'value' => '<script>alert(1)</script><b>Assess the needs for community monitoring.</b>']]);
    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale');
    expect($response['proposal_changes']['suggestions'][0]['value'])->toBe('Assess the needs for community monitoring.');
});

test('proposal suggestions retrieve manual guidance for the requested section', function () {
    $index = json_decode(File::get($this->indexPath), true, flags: JSON_THROW_ON_ERROR);
    $index['chunks'][] = ['id' => 'design', 'section' => 'Detailed research proposal design', 'source_kind' => 'appendix', 'page_start' => 93, 'page_end' => 93,
        'content' => 'The detailed research proposal design must explain the comparison method used in community monitoring.'];
    File::put($this->indexPath, json_encode($index, JSON_THROW_ON_ERROR));
    $this->document->update(['source_data' => ['methodology' => ['research_design' => 'Compare community monitoring methods.']]]);
    ($this->fakeSuggestions)([['field' => 'methodology.research_design', 'before' => 'Compare community monitoring methods.',
        'value' => 'Compare methods for community monitoring.', 'reason' => 'Clarifies the existing comparison.']]);
    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my research design');
    expect(collect($response['sources'])->where('type', 'Official Research Manual')->pluck('section')->all())
        ->toContain('Detailed research proposal design');
    Http::assertSent(fn ($request): bool => str_contains(json_encode($request['messages']), 'must explain the comparison method'));
});

test('plain proposal edit requests are recognized while literature evidence requests keep their existing flow', function (string $question, bool $expectsEdits) {
    expect($this->assistance->wantsSuggestions([], ['proposal_draft_id' => $this->draft->id], $question))->toBe($expectsEdits);
    Http::assertNothingSent();
})->with([
    ['Suggest improvements to my proposal', true],
    ['Add a rationale based on my saved objectives', true],
    ['Draft my data analysis from these saved methods', true],
    ['Add studies to my proposal RRL', false],
    ['Suggest literature sources for my methodology', false],
]);

test('wording revisions cannot transfer numerical facts from another saved section', function () {
    $this->document->update(['source_data' => ['methodology' => ['data_analysis' => ''], 'specific_objectives' => [['description' => 'Compare monitoring measurements across 12 sites.']]]]);
    ($this->fakeSuggestions)([['field' => 'methodology.data_analysis', 'before' => '',
        'value' => 'Compare monitoring measurements across the 12 sites.', 'reason' => 'Uses the comparison already described in the saved objective.']]);
    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Draft my data analysis');
    expect($response['proposal_changes']['suggestions'])->toBe([])
        ->and($this->document->fresh()->source_data['methodology']['data_analysis'])->toBe('');
});

test('proposal wording suggestions preserve facts inside narrative fields', function (string $before, string $value, int $count) {
    $this->document->update(['source_data' => ['rationale' => $before]]);
    ($this->fakeSuggestions)([['field' => 'rationale', 'before' => $before, 'value' => $value, 'reason' => 'Revises wording.']]);

    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Revise my rationale.');

    expect($response['proposal_changes']['suggestions'])->toHaveCount($count)
        ->and($this->document->fresh()->source_data['rationale'])->toBe($before);
})->with([
    'removed number' => ['Survey 12 sites.', 'Survey the sites.', 0],
    'negative percentage' => ['The change was -12.5%.', 'The change was 12.5%.', 0],
    'date parts' => ['Visit on 10/11/2026.', 'Visit on 11/10/2026.', 0],
    'month' => ['Visit in May.', 'Visit in June.', 0],
    'currency' => ['Materials cost PHP 500.', 'Materials cost USD 500.', 0],
    'name' => ['Dr. Maria Santos coordinates monitoring.', 'Dr. Juan Cruz coordinates monitoring.', 0],
    'approval' => ['The protocol is not approved.', 'The protocol is approved.', 0],
    'valid wording' => ['Survey 12 sites in May.', 'In May, survey 12 sites.', 1],
]);

test('proposal replacements preserve paragraph breaks in the reviewed text and history', function () {
    ($this->fakeSuggestions)([[...$this->suggestion, 'value' => "Assess community monitoring needs.\n\nDescribe the needs for community monitoring."]]);
    $packet = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes'];
    expect($packet['suggestions'][0]['value'])->toBe("Assess community monitoring needs.\n\nDescribe the needs for community monitoring.")
        ->and($this->assistance->historyPacket($packet)['suggestions'][0]['value'])->toBe($packet['suggestions'][0]['value']);
});

test('safe wording around protected approval paragraphs can be suggested and resolved', function () {
    $before = '<p>The protocol is not approved.</p><p>The design needs further explanation.</p>';
    $value = "The protocol is not approved.\n\nThe design requires further explanation.";
    $this->document->update(['source_data' => ['rationale' => $before]]);
    ($this->fakeSuggestions)([['field' => 'rationale', 'before' => "The protocol is not approved.\nThe design needs further explanation.",
        'value' => $value, 'reason' => 'Clarifies the design wording.']]);

    $suggestion = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Revise my rationale.')['proposal_changes']['suggestions'][0];
    expect($this->assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id'])['value'])->toBe($value)
        ->and($this->document->fresh()->source_data['rationale'])->toBe($before);
});

test('older cached proposal suggestions cannot bypass current factual protections', function () {
    ($this->fakeSuggestions)([$this->suggestion]);
    $suggestion = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale.')['proposal_changes']['suggestions'][0];
    $key = 'assistant-proposal-changes:'.hash('sha256', $suggestion['token']);
    Cache::put($key, [...Cache::get($key), 'value' => 'Assess needs at 99 sites.'], now()->addMinutes(30));

    expect(fn () => $this->assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id']))->toThrow(HttpException::class);
    expect($this->document->fresh()->source_data['rationale'])->toBe('<p>Assess community monitoring needs.</p>');
});

test('objective suggestions target existing rows and do not invent a new row', function () {
    $this->document->update(['source_data' => ['specific_objectives' => [['description' => 'Describe community needs.'], ['description' => 'Assess monitoring practices.']]]]);
    ($this->fakeSuggestions)([['field' => 'specific_objectives.1.description', 'before' => 'Assess monitoring practices.', 'value' => 'Assess current monitoring practices.', 'reason' => 'Clarifies the objective.']]);
    expect($this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve objective 2')['proposal_changes']['suggestions'][0]['field'])->toBe('specific_objectives.1.description');
    expect(fn () => $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve objective 3'))->toThrow(HttpException::class);
});

test('an empty narrative can be suggested using saved objectives without inventing a study or numerical commitment', function () {
    $this->document->update(['source_data' => ['rationale' => '', 'specific_objectives' => [['description' => 'Describe community monitoring needs.']]]]);
    ($this->fakeSuggestions)([['field' => 'rationale', 'before' => '', 'value' => 'The proposal seeks to describe community monitoring needs.', 'reason' => 'Introduces the saved objective.']]);
    $response = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale');
    expect($response['proposal_changes']['suggestions'][0]['before'])->toBe('')
        ->and($this->document->fresh()->source_data['rationale'])->toBe('');
    Http::assertSent(fn ($request): bool => str_contains(json_encode($request['messages']), 'Describe community monitoring needs.')
        && str_contains(json_encode($request['messages']), 'connected_saved_context'));
});

test('proposal suggestion tokens reject changed papers changed manuals and mismatched selection', function (string $change) {
    ($this->fakeSuggestions)([$this->suggestion]);
    $suggestion = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes']['suggestions'][0];
    if ($change === 'document') {
        $this->document->update(['lock_version' => 2]);
    } elseif ($change === 'draft') {
        $this->draft->increment('lock_version');
    } elseif ($change === 'manual') {
        ($this->writeManual)('twelve');
    } else {
        $suggestion['id'] = Str::uuid()->toString();
    }
    expect(fn () => $this->assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id']))
        ->toThrow($change === 'selection' ? ValidationException::class : HttpException::class);
})->with(['document', 'draft', 'manual', 'selection']);

test('proposal changes cannot be generated or resolved by outsiders or after submission', function () {
    ($this->fakeSuggestions)([$this->suggestion]);
    $suggestion = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes']['suggestions'][0];
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    expect(fn () => $this->assistance->resolve($outsider, $this->draft, $suggestion['token'], $suggestion['id']))->toThrow(AuthorizationException::class);
    $this->draft->update(['status' => 'submitted']);
    expect(fn () => $this->assistance->resolve($this->faculty, $this->draft, $suggestion['token'], $suggestion['id']))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale'))->toThrow(AuthorizationException::class);
});

test('chat routes explicit and plain proposal change requests through the existing assistant', function (bool $explicit) {
    ($this->fakeSuggestions)([$this->suggestion]);
    $payload = ['messages' => [['role' => 'user', 'content' => 'Improve my rationale']], 'context' => ['proposal_draft_id' => $this->draft->id]];
    if ($explicit) {
        $payload['action'] = ['type' => 'suggest_proposal_changes'];
    }
    $response = $this->actingAs($this->faculty)->postJson(route('research-support.chat'), $payload)->assertSuccessful()
        ->assertJsonPath('proposal_changes.proposal_draft_id', $this->draft->id)
        ->assertJsonPath('proposal_changes.suggestions.0.field', 'rationale')
        ->assertJsonPath('proposal_changes.assessment.rrl.requirement_reference', 'Manual requirement 1');
    $suggestion = $response->json('proposal_changes.suggestions.0');
    $this->postJson(route('research-support.proposal-changes.resolve'), ['proposal_draft_id' => $this->draft->id,
        'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertSuccessful()
        ->assertJsonPath('proposal_change.before', '<p>Assess community monitoring needs.</p>')
        ->assertJsonPath('proposal_change.document_version', 1);
    expect($this->document->fresh()->source_data['rationale'])->toBe('<p>Assess community monitoring needs.</p>');
})->with([true, false]);

test('RRL count questions use the saved study check for both the reply and card', function (string $question) {
    $response = $this->actingAs($this->faculty)->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => $question]], 'context' => ['proposal_draft_id' => $this->draft->id],
    ])->assertSuccessful()->assertJsonPath('proposal_changes.assessment.rrl.count', 0)
        ->assertJsonPath('proposal_changes.assessment.rrl.minimum', 10)
        ->assertJsonCount(0, 'proposal_changes.suggestions');
    expect(collect($response->json('sources'))->pluck('reference')->all())->toContain('Manual requirement 1');
    expect($response->json('reply'))->toBe($response->json('proposal_changes.assessment.rrl.study_check.summary'));
    Http::assertNothingSent();
})->with(['How many RRL does my proposal have?', 'Does my RRL meet the requirements?', 'How many studies did I cite in Section XI?', 'Do I need more studies in my literature review?']);

test('RRL length questions still reach general guidance', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => 'The form specifies a study minimum.']]], 'model' => 'fixture-model'])]);
    $this->actingAs($this->faculty)->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'How many pages should my RRL be?']],
        'context' => ['proposal_draft_id' => $this->draft->id],
    ])->assertSuccessful()->assertJsonPath('model', 'fixture-model')
        ->assertJsonMissingPath('proposal_changes.assessment.rrl.study_check');
});

test('the saved RRL check resolves manual citations without double counting or treating the bibliography as reviewed', function () {
    $linked = $this->draft->literatureSources()->create([
        'fingerprint' => hash('sha256', 'santos'), 'title' => 'Feedback in games', 'doi' => '10.1234/feedback', 'provider' => 'fixture',
    ]);
    $references = '<p data-proposal-managed-reference="'.$linked->id.'">[1] A. Santos, “Feedback in games,” 2023. https://doi.org/10.1234/feedback</p>'
        .'<p>Santos, A. (2023). Feedback in games. https://doi.org/10.1234/feedback</p>';
    $rrl = '<p>Santos (2023) reported that faster feedback improved student engagement.</p>'
        .'<p>Game feedback reduced task abandonment among students.<span data-proposal-citation="'.$linked->id.'">[1]</span></p>';
    foreach (range(2, 8) as $number) {
        $references .= '<p>['.$number.'] Study '.$number.'.</p>';
        $rrl .= '<p>Formative feedback increased completion rates among first-year students ['.$number.'].</p>';
    }
    $references .= '<p>Cruz, J. (2024). Classroom trials.</p><p>Cruz, J. (2024). Pilot trials.</p>'
        .'<p>[9] Review-only citation.</p><p>[10] Bibliography-only citation.</p>';
    $rrl .= '<p>Cruz (2024) found that feedback improved students’ confidence.</p><p>[9]</p><p>Reyes (2024) measured changes in student performance.</p>';
    $before = ['introduction' => '', 'related_literature' => $rrl, 'references' => $references];
    $this->document->update(['source_data' => $before]);

    $message = ['role' => 'user', 'content' => 'In this project, is my RRL enough or do I need a certain amount before I submit it?'];
    $context = ['proposal_draft_id' => $this->draft->id];
    $response = $this->actingAs($this->faculty)->postJson(route('research-support.chat'), [
        'messages' => [$message], 'context' => $context,
    ])->assertSuccessful()->assertJsonPath('proposal_changes.assessment.rrl.known_count', 8)
        ->assertJsonPath('proposal_changes.assessment.rrl.minimum_satisfied', null)
        ->assertJsonPath('proposal_changes.assessment.rrl.shortfall', null);

    $check = $response->json('proposal_changes.assessment.rrl.study_check');
    expect($response->json('reply'))->toBe($check['summary'])->toContain('8 distinct studies', '3 citations to review');
    $entries = collect($check['entries'])->groupBy('status');
    expect($entries['supported'])->toHaveCount(8)
        ->and($entries['needs_review'])->toHaveCount(3)
        ->and($entries['references_only']->pluck('label')->all())->toContain('[10] Bibliography-only citation.')
        ->and($entries['supported']->pluck('excerpt')->all())->toContain('Santos (2023) reported that faster feedback improved student engagement.');

    $this->postJson(route('research-support.history.save'), [
        'context' => $context, 'messages' => [$message, ['role' => 'assistant', 'content' => $response->json('reply'),
            'proposal_changes' => $response->json('proposal_changes')]],
    ])->assertSuccessful();
    $saved = ResearchAssistantConversation::query()->where('user_id', $this->faculty->id)->latest('id')->firstOrFail();
    expect($saved->messages[1]['proposal_changes']['assessment']['rrl']['study_check'])->toEqual($check)
        ->and($this->document->fresh()->source_data)->toEqual($before);

    $this->document->update(['source_data' => [...$before,
        'related_literature' => $rrl.'<p>A classroom trial found that immediate feedback improved exam scores [9].</p>'
            .'<p>An independent evaluation reported longer retention after feedback [10].</p>']]);
    $this->postJson(route('research-support.chat'), [
        'messages' => [$message], 'context' => $context, 'action' => ['type' => 'check_rrl'],
    ])->assertSuccessful()->assertJsonPath('proposal_changes.assessment.rrl.known_count', 10)
        ->assertJsonPath('proposal_changes.assessment.rrl.minimum_satisfied', true);

    Http::assertNothingSent();
});

test('the RRL check flags conflicting citation numbers and protects saved draft evidence', function () {
    $this->document->update(['source_data' => [
        'related_literature' => '<p>Earlier trials reported an improvement in completion [1].</p>',
        'references' => '<p>[1] Classroom trial.</p><p>[1] Follow-up trial.</p>',
    ]]);
    $payload = [
        'messages' => [['role' => 'user', 'content' => 'Check my saved RRL.']],
        'context' => ['proposal_draft_id' => $this->draft->id], 'action' => ['type' => 'check_rrl'],
    ];
    $this->actingAs($this->faculty)->postJson(route('research-support.chat'), $payload)->assertSuccessful()
        ->assertJsonPath('proposal_changes.assessment.rrl.known_count', 0)
        ->assertJsonPath('proposal_changes.assessment.rrl.study_check.entries.0.status', 'needs_review')
        ->assertJsonPath('proposal_changes.assessment.rrl.study_check.entries.0.reason', 'More than one reference matches.');
    $unrelated = TopicProposal::create([
        'user_id' => $this->faculty->id, 'research_category_id' => ResearchCategory::create(['name' => 'Unrelated research'])->id,
        'title' => 'Other project', 'description' => 'Unrelated to this draft.',
        'estimated_budget' => 12000, 'estimated_duration_months' => 6, 'status' => 'pending',
    ]);
    $this->postJson(route('research-support.chat'), [...$payload, 'context' => [...$payload['context'], 'topic_id' => $unrelated->id]])
        ->assertUnprocessable();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    $this->actingAs($outsider)->postJson(route('research-support.chat'), $payload)->assertForbidden();
    Http::assertNothingSent();
});

test('the RRL check merges bibliography variants and distinguishes planned work from prior findings', function () {
    $this->document->update(['source_data' => [
        'related_literature' => '<p>The trial reported that timely feedback improved retention [1].</p>'
            .'<p>Immediate feedback reduced student absences in the same trial [2].</p>'
            .'<p>Our proposed method will test whether feedback improved retention [3].</p>'
            .'<p>A separate study found no significant change in &lt;ATP&gt; levels [4, p. 3].</p>'
            .'<p>Prior trials reported improved completion (Daniels, 2020, 2021).</p>',
        'references' => '<p>[1] Santos, A. (2023). Feedback in games. Journal A.</p>'
            .'<p>[2] Santos, A. (2023). Feedback in games. Journal A, 2(1), 1–9.</p>'
            .'<p>[3] Cruz, A. (2024). Classroom feedback. Journal B.</p>'
            .'<ol start="4"><li>Reyes, A. (2024). Classroom attendance. Journal B.</li></ol>'
            .'<p>Daniels, A. (2020). Early trial. Journal C.</p><p>Daniels, A. (2021). Later trial. Journal C.</p>',
    ]]);
    $this->actingAs($this->faculty)->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Check my saved RRL.']],
        'context' => ['proposal_draft_id' => $this->draft->id], 'action' => ['type' => 'check_rrl'],
    ])->assertSuccessful()->assertJsonPath('proposal_changes.assessment.rrl.known_count', 2)
        ->assertJsonCount(5, 'proposal_changes.assessment.rrl.study_check.entries')
        ->assertJsonPath('proposal_changes.assessment.rrl.study_check.entries.0.status', 'needs_review')
        ->assertJsonPath('proposal_changes.assessment.rrl.study_check.entries.0.excerpt', 'Our proposed method will test whether feedback improved retention [3].')
        ->assertJsonPath('proposal_changes.assessment.rrl.study_check.entries.4.excerpt', 'A separate study found no significant change in <ATP> levels [4, p. 3].');
});

test('resolve endpoint rejects a stale document expired token foreign draft and unauthorized account', function () {
    ($this->fakeSuggestions)([$this->suggestion]);
    $this->actingAs($this->faculty);
    $suggestion = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes']['suggestions'][0];
    $payload = ['proposal_draft_id' => $this->draft->id, 'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']];
    $expiredResponse = $this->postJson(route('research-support.proposal-changes.resolve'), [...$payload, 'token' => str_repeat('x', 64)]);
    expect([$expiredResponse->getStatusCode(), $expiredResponse->headers->get('Location')])->toBe([422, null]);
    $other = $this->draft->replicate();
    $other->save();
    $foreignResponse = $this->postJson(route('research-support.proposal-changes.resolve'), [...$payload, 'proposal_draft_id' => $other->id]);
    expect($foreignResponse->getStatusCode())->toBe(422);
    $this->document->update(['lock_version' => 2]);
    $this->postJson(route('research-support.proposal-changes.resolve'), $payload)->assertConflict();
    $outsider = User::factory()->create();
    $outsider->assignRole('faculty');
    $this->actingAs($outsider)->postJson(route('research-support.proposal-changes.resolve'), $payload)->assertNotFound();
});

test('saved proposal change history retains bounded previews without storing assessment or unsafe markup', function () {
    ($this->fakeSuggestions)([$this->suggestion]);
    $packet = $this->assistance->respond($this->faculty, ['proposal_draft_id' => $this->draft->id], 'Improve my rationale')['proposal_changes'];
    $packet['proposal_title'] = '<b>Saved title</b>';
    $packet['suggestions'][0]['value'] = '<script>alert(1)</script><b>Safe rewritten text.</b>';
    $packet['assessment']['untrusted'] = 'ASSESSMENT_PAYLOAD_MUST_NOT_PERSIST';
    $this->actingAs($this->faculty)->postJson(route('research-support.history.save'), [
        'context' => ['proposal_draft_id' => $this->draft->id],
        'messages' => [['role' => 'user', 'content' => 'Improve my rationale'], ['role' => 'assistant', 'content' => 'Review the edits.', 'proposal_changes' => $packet]],
    ])->assertSuccessful();
    $saved = ResearchAssistantConversation::query()->where('user_id', $this->faculty->id)->latest('id')->firstOrFail()->messages[1]['proposal_changes'];
    expect($saved['proposal_title'])->toBe('Saved title')
        ->and($saved['suggestions'][0]['value'])->toBe('Safe rewritten text.')
        ->and($saved['assessment']['rrl'])->toMatchArray(['count' => 0, 'minimum' => 10, 'quality_verified' => false])
        ->and($saved['editor_url'])->toBe(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->and(json_encode($saved))->not->toContain('ASSESSMENT_PAYLOAD_MUST_NOT_PERSIST', '<script>');
});
