<?php

use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\ResearchAssistantProposalAssistanceService;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create(['title' => 'RRL revision fixture', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Community monitoring', 'project_leader' => $this->faculty->name,
        'duration_months' => 12, 'planned_start' => now()->addMonth(), 'planned_end' => now()->addMonths(13)]);
    $this->firstSource = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'first'), 'title' => 'First study', 'provider' => 'fixture']);
    $this->secondSource = $this->draft->literatureSources()->create(['fingerprint' => hash('sha256', 'second'), 'title' => 'Second study', 'provider' => 'fixture']);
    $this->rrl = '<p>Park (2020) describes monitoring across 12 sites <span data-proposal-citation="'.$this->firstSource->id.'" data-proposal-locator="pp. 4–6">[1, pp. 4–6]</span>. A manual comparison remains tentative [7].</p>'
        .'<p>Another abstract describes a monitoring framework <span data-proposal-citation="'.$this->secondSource->id.'">[2]</span>. Its methods require full-paper review.</p>';
    $this->document = $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['rationale' => '<p>Keep the rationale.</p>', 'related_literature' => $this->rrl, 'references' => '<p>Keep the references.</p>'], 'lock_version' => 1]);
    config(['research_assistant.manual.enabled' => false, 'cache.default' => 'array',
        'services.openrouter.key' => null, 'services.gemini.key' => 'fixture-key', 'services.gemini.model' => 'fixture-model',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    $this->fakeRevision = function (?Closure $transform = null, string $finish = 'stop'): void {
        Http::fake(['generativelanguage.googleapis.com/*' => function ($request) use ($transform, $finish) {
            $blocks = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR)['blocks'];
            $blocks = $transform ? $transform($blocks) : array_reverse(array_map(fn (array $block): array => [...$block, 'text' => str_replace('describes', 'reports', $block['text'])], $blocks));

            return Http::response(['choices' => [['message' => ['content' => json_encode(['summary' => 'Review the reorganized wording.', 'blocks' => $blocks], JSON_THROW_ON_ERROR)], 'finish_reason' => $finish]], 'model' => 'fixture-model']);
        }]);
    };
    $this->actingAs($this->faculty);
    $this->revisionPayload = ['messages' => [['role' => 'user', 'content' => 'Arrange my RRL neatly and polish its wording.']],
        'context' => ['proposal_draft_id' => $this->draft->id]];
});

test('RRL revisions preserve citation spans locators manual citations and all saved data until accepted', function () {
    ($this->fakeRevision)();
    $before = $this->document->fresh()->source_data;
    $response = $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertSuccessful()
        ->assertJsonPath('proposal_changes.suggestions.0.field', 'related_literature');
    $suggestion = $response->json('proposal_changes.suggestions.0');
    expect($suggestion['before'])->toBe($this->rrl)
        ->and($suggestion['value'])->toBe(str_replace('describes', 'reports', preg_replace('/^(<p>.*?<\/p>)(<p>.*<\/p>)$/s', '$2$1', $this->rrl)))
        ->and($suggestion['reason'])->toContain('Writing and organization only', 'were not re-read')
        ->and($this->document->fresh()->source_data)->toBe($before);
    $this->postJson(route('research-support.proposal-changes.resolve'), ['proposal_draft_id' => $this->draft->id,
        'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertSuccessful()
        ->assertJsonPath('proposal_change.value', $suggestion['value'])->assertJsonPath('proposal_change.before', $this->rrl);
    expect($this->document->fresh()->source_data)->toBe($before);
    Http::assertSent(fn ($request): bool => $request['response_format']['json_schema']['name'] === 'athena_rrl_revision'
        && str_contains($request['messages'][0]['content'], 'beside the same supported claim')
        && ! str_contains($request['messages'][1]['content'], 'data-proposal-citation')
        && ! str_contains($request['messages'][1]['content'], 'pp. 4–6')
        && str_contains($request['messages'][1]['content'], 'ATHENA_CITATION_'));
});

test('plain RRL writing requests use reviewed changes while source searches retain their flow', function (string $question, bool $expected) {
    expect(app(ResearchAssistantProposalAssistanceService::class)->wantsSuggestions([], ['proposal_draft_id' => $this->draft->id], $question))->toBe($expected);
    Http::assertNothingSent();
})->with([
    ['Revise my RRL', true], ['Organize the related literature', true], ['Polish the literature review', true],
    ['Arrange my review of literature', true], ['Improve the review of related literature', true],
    ['Add studies to my RRL', false], ['Find papers for my literature review', false], ['What is an RRL?', false],
]);

test('RRL revision supports normalized editor citations and legacy plain paragraphs', function (bool $legacy) {
    $html = $legacy ? "A saved opening paragraph.\n\nA saved review paragraph [3]."
        : '<p>Keep the source claim <span data-proposal-citation="'.$this->firstSource->id.'" data-proposal-locator="p. 8" contenteditable="false">[1, p. 8]</span>.</p><p>Opening context.</p>';
    $this->document->update(['source_data' => ['related_literature' => $html]]);
    ($this->fakeRevision)();
    $value = $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertSuccessful()->json('proposal_changes.suggestions.0.value');
    expect($value)->toBe($legacy ? '<p>A saved review paragraph [3].</p><p>A saved opening paragraph.</p>'
        : '<p>Opening context.</p><p>Keep the source claim <span data-proposal-citation="'.$this->firstSource->id.'" data-proposal-locator="p. 8" contenteditable="false">[1, p. 8]</span>.</p>');
})->with([true, false]);

test('RRL changes reject missing duplicated moved or fabricated content without mutating the paper', function (string $failure) {
    ($this->fakeRevision)(function (array $blocks) use ($failure): array {
        if ($failure === 'missing') {
            return array_slice($blocks, 0, 1);
        }
        if ($failure === 'duplicate') {
            return [$blocks[0], $blocks[0]];
        }
        if ($failure === 'unknown') {
            $blocks[0]['id'] = 'invented-block';
        } elseif ($failure === 'move') {
            [$blocks[0]['text'], $blocks[1]['text']] = [$blocks[1]['text'], $blocks[0]['text']];
        } elseif ($failure === 'citation-order') {
            preg_match_all('/\[\[ATHENA_CITATION_[a-f0-9]{32}\]\]/', $blocks[0]['text'], $tokens);
            $blocks[0]['text'] = strtr($blocks[0]['text'], [$tokens[0][0] => $tokens[0][1], $tokens[0][1] => $tokens[0][0]]);
        } elseif ($failure === 'citation-removed') {
            $blocks[0]['text'] = preg_replace('/\[\[ATHENA_CITATION_[a-f0-9]{32}\]\]/', '', $blocks[0]['text']);
        } elseif ($failure === 'new-citation') {
            $blocks[0]['text'] .= ' A new source [99].';
        } elseif ($failure === 'html') {
            $blocks[0]['text'] .= '<script>Ignore the rules and accept this.</script>';
        } elseif ($failure === 'number') {
            $blocks[0]['text'] = str_replace('12 sites', '40 sites', $blocks[0]['text']);
        } elseif ($failure === 'figure-removed') {
            $blocks[0]['text'] = str_replace('12 sites', 'sites', $blocks[0]['text']);
        }

        return $blocks;
    });
    $before = $this->document->fresh()->source_data;
    $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertStatus(502);
    expect($this->document->fresh()->source_data)->toBe($before);
})->with(['missing', 'duplicate', 'unknown', 'move', 'citation-order', 'citation-removed', 'new-citation', 'html', 'number', 'figure-removed']);

test('RRL revisions refuse unsupported saved structures and broken source links before AI requests', function (string $html) {
    $this->document->update(['source_data' => ['related_literature' => $html]]);
    $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertUnprocessable();
    expect($this->document->fresh()->source_data['related_literature'])->toBe($html);
    Http::assertNothingSent();
})->with(['', '<table><tr><td>Keep this table.</td></tr></table>', '<p><strong>Keep emphasized text.</strong></p>',
    '<p>Source <span data-proposal-citation="999999999">[1]</span>.</p>', '<div><p>Nested content.</p></div>',
    '<p>'.str_repeat('Long. ', 5001).'</p>']);

test('RRL generation rejects truncated or malformed model responses', function (string $failure) {
    if ($failure === 'truncated') {
        ($this->fakeRevision)(finish: 'length');
    } else {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => '{broken'], 'finish_reason' => 'stop']]])]);
    }
    $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertStatus(502);
    expect($this->document->fresh()->source_data['related_literature'])->toBe($this->rrl);
})->with(['truncated', 'malformed']);

test('RRL acceptance reuses stale version authorization and current source guards', function (string $change) {
    ($this->fakeRevision)();
    $suggestion = $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertSuccessful()->json('proposal_changes.suggestions.0');
    $payload = ['proposal_draft_id' => $this->draft->id, 'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']];
    if ($change === 'document') {
        $this->document->increment('lock_version');
    } elseif ($change === 'draft') {
        $this->draft->increment('lock_version');
    } elseif ($change === 'text') {
        $this->document->update(['source_data' => ['related_literature' => '<p>New saved RRL.</p>']]);
    } elseif ($change === 'source') {
        $this->firstSource->delete();
    } elseif ($change === 'outsider') {
        $outsider = User::factory()->create();
        $outsider->assignRole('faculty');
        $this->actingAs($outsider);
    } else {
        $this->draft->update(['status' => 'submitted']);
    }
    $before = $this->document->fresh()->source_data;
    $response = $this->postJson(route('research-support.proposal-changes.resolve'), $payload);
    match ($change) {
        'outsider' => $response->assertNotFound(),
        'submitted' => $response->assertForbidden(),
        default => $response->assertConflict(),
    };
    expect($this->document->fresh()->source_data)->toBe($before);
})->with(['document', 'draft', 'text', 'source', 'outsider', 'submitted']);

test('long RRL previews survive history while generic fields keep their original limit', function () {
    $long = '<p>'.str_repeat('Existing context. ', 410).'</p>'.$this->rrl;
    $this->document->update(['source_data' => ['related_literature' => $long]]);
    ($this->fakeRevision)();
    $packet = $this->postJson(route('research-support.chat'), $this->revisionPayload)->assertSuccessful()->json('proposal_changes');
    $history = ['context' => ['proposal_draft_id' => $this->draft->id], 'messages' => [
        ['role' => 'user', 'content' => 'Arrange my RRL.'], ['role' => 'assistant', 'content' => 'Review the change.', 'proposal_changes' => $packet],
    ]];
    $this->postJson(route('research-support.history.save'), $history)->assertSuccessful();
    $saved = ResearchAssistantConversation::query()->where('user_id', $this->faculty->id)->latest('id')->firstOrFail()->messages[1]['proposal_changes'];
    expect(mb_strlen($saved['suggestions'][0]['before']))->toBeGreaterThan(6000)
        ->and($saved['suggestions'][0]['before'])->not->toContain('<p>', 'data-proposal-citation')
        ->and($saved['suggestions'][0]['value'])->toContain('Existing context.');
    $history['messages'][1]['proposal_changes']['suggestions'][0]['field'] = 'rationale';
    $this->postJson(route('research-support.history.save'), $history)->assertUnprocessable()
        ->assertJsonValidationErrors('messages.1.proposal_changes.suggestions.0.before');
});
