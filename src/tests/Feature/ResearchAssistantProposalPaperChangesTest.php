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
    $call = ResearchCall::create(['title' => 'Reviewed attachment edits', 'academic_year' => '2026-2027', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(), 'status' => 'open']);
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Community monitoring', 'project_leader' => $this->faculty->name, 'duration_months' => 12]);
    $this->detailed = $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0, 'lock_version' => 3,
        'source_data' => ['rationale' => 'Assess community monitoring needs.', 'specific_objectives' => [['description' => 'Assess community needs.']],
            'methodology' => ['research_design' => 'Compare monitoring practices.', 'specific_methods' => 'Describe monitoring practices.', 'data_analysis' => 'Compare observations.'],
            'specific_method_objectives' => [['heading' => 'Community monitoring methods', 'methods' => [['description' => 'Describe the monitoring approach.']]]],
            'expected_outputs' => ['publication' => [['quantity' => 2, 'unit' => 'articles', 'description' => 'Describe monitoring findings.']]],
            'responsibilities' => [['name' => 'Saved Member', 'percentage' => 50, 'duties' => 'Document monitoring practices.']],
            'related_literature' => '']]);
    $this->workPlan = $this->draft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'lock_version' => 5,
        'source_data' => ['entries' => [['objective' => 'Assess community needs.', 'expected_output' => 'Document monitoring needs.', 'activity' => 'Review monitoring practices.', 'months' => [1, 2]],
            ['objective' => 'Compare monitoring approaches.', 'expected_output' => 'Keep another output.', 'activity' => 'Keep another activity.', 'months' => [3]]]]]);
    $this->expenses = $this->draft->documents()->create(['document_type' => 'expense_breakdown', 'position' => 0, 'lock_version' => 7,
        'source_data' => ['items' => [['category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Office Supplies Expenses',
            'particulars' => 'Recording sheets', 'details' => 'Printed field recording sheets.', 'purpose' => 'Record monitoring observations.', 'unit' => 'pack', 'quantity' => 12, 'unit_cost' => 25]]]]);
    config(['cache.default' => 'array', 'research_assistant.manual.enabled' => false, 'services.openrouter.key' => '',
        'services.gemini.key' => 'fixture-key', 'services.gemini.model' => 'fixture-model', 'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    $this->fakePaperChanges = function (array $suggestions): void {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'summary' => 'Review the proposed wording.', 'suggestions' => $suggestions,
        ], JSON_THROW_ON_ERROR)], 'finish_reason' => 'stop']]])]);
    };
    $this->paperPayload = function (string $question, array $context = []): array {
        return ['messages' => [['role' => 'user', 'content' => $question]], 'context' => ['proposal_draft_id' => $this->draft->id, ...$context]];
    };
    $this->actingAs($this->faculty);
});

test('reviewed attachment wording targets its own saved document and never writes before acceptance', function (string $slug, string $field, string $before, string $value, int $version) {
    ($this->fakePaperChanges)([['field' => $field, 'before' => $before, 'value' => $value, 'reason' => 'Clarifies the saved wording.']]);
    $baseline = $this->draft->documents()->get()->mapWithKeys(fn ($document): array => [$document->document_type => $document->source_data])->all();
    $response = $this->postJson(route('research-support.chat'), ($this->paperPayload)('Revise my proposal.', ['paper_slug' => $slug, 'field' => $field]))->assertSuccessful()
        ->assertJsonPath('proposal_changes.editor_url', route('faculty.proposal-drafts.'.$slug.'.edit', $this->draft))
        ->assertJsonPath('proposal_changes.suggestions.0.paper_slug', $slug)->assertJsonPath('proposal_changes.suggestions.0.field', $field);
    $suggestion = $response->json('proposal_changes.suggestions.0');
    $this->postJson(route('research-support.proposal-changes.resolve'), ['proposal_draft_id' => $this->draft->id,
        'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']])->assertSuccessful()
        ->assertJsonPath('proposal_change.before', $before)->assertJsonPath('proposal_change.value', $value)
        ->assertJsonPath('proposal_change.document_version', $version)->assertJsonPath('proposal_change.paper_slug', $slug)
        ->assertJsonPath('proposal_change.editor_url', route('faculty.proposal-drafts.'.$slug.'.edit', $this->draft));
    expect($this->draft->documents()->get()->mapWithKeys(fn ($document): array => [$document->document_type => $document->source_data])->all())->toEqual($baseline);
    Http::assertSent(function ($request) use ($field): bool {
        $payload = json_decode($request['messages'][1]['content'], true, flags: JSON_THROW_ON_ERROR);

        return array_keys($payload['fields']) === [$field] && ! str_contains(json_encode($payload['fields']), '25');
    });
})->with([
    ['work-plan', 'entries.0.activity', 'Review monitoring practices.', 'Review current monitoring practices.', 5],
    ['work-plan', 'entries.0.expected_output', 'Document monitoring needs.', 'Document the monitoring needs.', 5],
    ['expense-breakdown', 'items.0.details', 'Printed field recording sheets.', 'Printed sheets for field recording.', 7],
    ['expense-breakdown', 'items.0.purpose', 'Record monitoring observations.', 'Record observations from monitoring.', 7],
]);

test('explicit attachment names override another current paper without touching the detailed proposal', function (string $question, string $slug, string $field, string $before) {
    ($this->fakePaperChanges)([['field' => $field, 'before' => $before, 'value' => 'Clarified monitoring wording.', 'reason' => 'Clarifies the text.']]);
    $this->postJson(route('research-support.chat'), ($this->paperPayload)($question, ['paper_slug' => 'detailed-proposal', 'field' => 'rationale']))->assertSuccessful()
        ->assertJsonPath('proposal_changes.suggestions.0.paper_slug', $slug)->assertJsonPath('proposal_changes.suggestions.0.field', $field);
})->with([
    ['Improve activity 1 in my work plan.', 'work-plan', 'entries.0.activity', 'Review monitoring practices.'],
    ['Revise the expense breakdown purpose for item 1.', 'expense-breakdown', 'items.0.purpose', 'Record monitoring observations.'],
]);

test('vague focused attachment edits and explicit unsupported paper requests are routed deliberately', function (string $question, string $paperSlug, string $field, bool $expected) {
    expect(app(ResearchAssistantProposalAssistanceService::class)->wantsSuggestions([], ['proposal_draft_id' => $this->draft->id,
        'paper_slug' => $paperSlug, 'field' => $field], $question))->toBe($expected);
    Http::assertNothingSent();
})->with([
    ['Improve this field.', 'work-plan', 'entries[0][activity]', true],
    ['Polish my expense purpose.', 'expense-breakdown', 'items[0][purpose]', true],
    ['Edit my CV.', 'curriculum-vitae', '', true],
    ['Revise my budget.', 'line-item-budget', '', true],
    ['Find studies for my work plan.', 'work-plan', '', false],
]);

test('noneditable proposal fields and assessment papers never fall back to unrelated detailed text', function (string $question, array $context) {
    $this->postJson(route('research-support.chat'), ($this->paperPayload)($question, $context))->assertUnprocessable();
    Http::assertNothingSent();
})->with([
    ['Revise my budget.', ['paper_slug' => 'detailed-proposal']],
    ['Edit my CV.', ['paper_slug' => 'detailed-proposal']],
    ['Revise the GAD checklist.', ['paper_slug' => 'detailed-proposal']],
    ['Edit the screening form.', ['paper_slug' => 'detailed-proposal']],
    ['Improve this field.', ['paper_slug' => 'work-plan', 'field' => 'entries[0][months]']],
    ['Revise my work plan schedule.', ['paper_slug' => 'work-plan']],
    ['Improve this field.', ['paper_slug' => 'expense-breakdown', 'field' => 'items[0][unit_cost]']],
    ['Revise my work plan and expense breakdown.', []],
]);

test('attachment suggestions reject added numbers unavailable rows protected fields and oversized wording', function (string $field, string $before, string $value) {
    ($this->fakePaperChanges)([['field' => $field, 'before' => $before, 'value' => $value, 'reason' => 'Attempted replacement.']]);
    $this->postJson(route('research-support.chat'), ($this->paperPayload)('Improve the expense breakdown wording.'))
        ->assertSuccessful()->assertJsonCount(0, 'proposal_changes.suggestions');
})->with([
    ['items.0.purpose', 'Record monitoring observations.', 'Record monitoring observations across 12 sites.'],
    ['items.1.purpose', '', 'Create an invented row.'],
    ['items.0.quantity', '12', '20'],
    ['items.0.details', 'Printed field recording sheets.', str_repeat('x', 501)],
]);

test('reviewed attachment changes reject newer versions missing rows and unauthorized accounts', function (string $change) {
    ($this->fakePaperChanges)([['field' => 'entries.0.activity', 'before' => 'Review monitoring practices.', 'value' => 'Review current monitoring practices.', 'reason' => 'Clarifies wording.']]);
    $suggestion = $this->postJson(route('research-support.chat'), ($this->paperPayload)('Improve my work plan activity 1.'))->assertSuccessful()->json('proposal_changes.suggestions.0');
    if ($change === 'version') {
        $this->workPlan->increment('lock_version');
    } elseif ($change === 'row') {
        $this->workPlan->update(['source_data' => ['entries' => []]]);
    } elseif ($change === 'draft') {
        $this->draft->increment('lock_version');
    } else {
        $outsider = User::factory()->create();
        $outsider->assignRole('faculty');
        $this->actingAs($outsider);
    }
    $response = $this->postJson(route('research-support.proposal-changes.resolve'), ['proposal_draft_id' => $this->draft->id,
        'token' => $suggestion['token'], 'suggestion_id' => $suggestion['id']]);
    $change === 'outsider' ? $response->assertNotFound() : $response->assertConflict();
})->with(['version', 'row', 'draft', 'outsider']);

test('additional detailed proposal narratives support exact focused edits without changing row facts', function (string $field, string $before) {
    ($this->fakePaperChanges)([['field' => $field, 'before' => $before, 'value' => 'Clarified monitoring wording.', 'reason' => 'Clarifies the saved text.']]);
    $baseline = $this->detailed->fresh()->source_data;
    $this->postJson(route('research-support.chat'), ($this->paperPayload)('Improve this field.', ['paper_slug' => 'detailed-proposal', 'field' => $field]))->assertSuccessful()
        ->assertJsonPath('proposal_changes.suggestions.0.field', $field)->assertJsonPath('proposal_changes.suggestions.0.paper_slug', 'detailed-proposal');
    expect($this->detailed->fresh()->source_data)->toEqual($baseline);
    Http::assertSent(fn ($request): bool => array_keys(json_decode($request['messages'][1]['content'], true)['fields']) === [$field]);
})->with([
    ['specific_method_objectives.0.heading', 'Community monitoring methods'],
    ['specific_method_objectives.0.methods.0.description', 'Describe the monitoring approach.'],
    ['expected_outputs.publication.0.description', 'Describe monitoring findings.'],
    ['responsibilities.0.duties', 'Document monitoring practices.'],
]);

test('specific methods requests target visible saved method rows instead of their computed summary', function () {
    ($this->fakePaperChanges)([['field' => 'specific_method_objectives.0.methods.0.description', 'before' => 'Describe the monitoring approach.',
        'value' => 'Describe the approach to monitoring.', 'reason' => 'Clarifies the visible method.']]);
    $this->postJson(route('research-support.chat'), ($this->paperPayload)('Improve my specific methods.', ['paper_slug' => 'detailed-proposal']))->assertSuccessful()
        ->assertJsonPath('proposal_changes.suggestions.0.field', 'specific_method_objectives.0.methods.0.description');
    Http::assertSent(function ($request): bool {
        $fields = json_decode($request['messages'][1]['content'], true)['fields'];

        return isset($fields['specific_method_objectives.0.methods.0.description']) && ! isset($fields['methodology.specific_methods']);
    });
});

test('attachment change history preserves the safe paper and field but resolves the immutable server replacement', function () {
    ($this->fakePaperChanges)([['field' => 'items.0.purpose', 'before' => 'Record monitoring observations.', 'value' => 'Record observations from monitoring.', 'reason' => 'Clarifies wording.']]);
    $packet = $this->postJson(route('research-support.chat'), ($this->paperPayload)('Revise the expense purpose for item 1.'))->assertSuccessful()->json('proposal_changes');
    $packet['suggestions'][0]['value'] = 'Tampered history preview.';
    $packet['editor_url'] = 'https://other.example/';
    $this->postJson(route('research-support.history.save'), ['context' => ['proposal_draft_id' => $this->draft->id],
        'messages' => [['role' => 'user', 'content' => 'Revise expense wording.'], ['role' => 'assistant', 'content' => 'Review it.', 'proposal_changes' => $packet]]])->assertSuccessful();
    $saved = ResearchAssistantConversation::query()->where('user_id', $this->faculty->id)->latest('id')->firstOrFail()->messages[1]['proposal_changes'];
    expect($saved['suggestions'][0]['paper_slug'])->toBe('expense-breakdown')
        ->and($saved['editor_url'])->toBe(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft));
    $this->postJson(route('research-support.proposal-changes.resolve'), ['proposal_draft_id' => $this->draft->id,
        'token' => $saved['suggestions'][0]['token'], 'suggestion_id' => $saved['suggestions'][0]['id']])->assertSuccessful()
        ->assertJsonPath('proposal_change.value', 'Record observations from monitoring.')->assertJsonPath('proposal_change.paper_slug', 'expense-breakdown');
});
