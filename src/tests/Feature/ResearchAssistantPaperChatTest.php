<?php

use App\Models\ProposalDraft;
use App\Models\ResearchAssistantConversation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Coastal monitoring fixture',
        'duration_months' => 12, 'planned_start' => '2026-11-01', 'planned_end' => '2027-10-31']);
    config(['services.openrouter.key' => null, 'services.gemini.key' => 'test-key',
        'services.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai']);
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'The saved paper supports this answer.'], 'finish_reason' => 'stop']]])]);
    $this->actingAs($this->faculty);
});

test('ordinary questions read saved work plan activities without a PDF or a current paper slug', function () {
    $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['specific_objectives' => [['description' => 'Measure coastal turbidity']]]]);
    $paper = $this->draft->documents()->create(['document_type' => 'work_plan', 'position' => 0,
        'source_data' => ['entries' => [['objective' => 'Measure coastal turbidity', 'activity' => 'Calibrate turbidity sensors at coastal stations', 'expected_output' => 'Calibrated sensors', 'months' => [true, false, true]]]]]);
    $response = $this->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'Which activities are saved in my work plan?']],
        'context' => ['proposal_draft_id' => $this->draft->id],
    ])->assertSuccessful();
    expect(collect($response->json('sources'))->pluck('title')->join(' '))->toContain('Work Plan')
        ->and($paper->fresh()->file_path)->toBeNull();
    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, 'Calibrate turbidity sensors at coastal stations')
            && str_contains($prompt, 'private_saved_draft');
    });
    Process::assertNothingRan();
});

test('saved expense answers include generated arithmetic without repeating unrelated narrative content', function () {
    $this->draft->documents()->create(['document_type' => 'expense_breakdown', 'position' => 0,
        'source_data' => ['items' => [['category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Office Supplies Expenses',
            'particulars' => 'Turbidity calibration kits', 'purpose' => 'Calibration before field observation', 'quantity' => 2, 'unit_cost' => 1750]]]]);
    $this->draft->documents()->create(['document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => ['rationale' => str_repeat('UNRELATED_LONG_NARRATIVE ', 1500), 'leader_email' => 'PRIVATE-CONTACT@example.test']]);
    $this->postJson(route('research-support.chat'), [
        'messages' => [['role' => 'user', 'content' => 'What is the total cost of the calibration kits in my expense breakdown?']],
        'context' => ['proposal_draft_id' => $this->draft->id, 'paper_slug' => 'detailed-proposal'],
    ])->assertSuccessful();
    Http::assertSent(function ($request): bool {
        $prompt = collect($request['messages'])->pluck('content')->join("\n");

        return str_contains($prompt, '3500') && str_contains($prompt, 'Turbidity calibration kits')
            && ! str_contains($prompt, 'UNRELATED_LONG_NARRATIVE') && ! str_contains($prompt, 'PRIVATE-CONTACT');
    });
    Process::assertNothingRan();
});

test('explicit saved paper analysis is available before generation and shows its reading basis', function () {
    $paper = $this->draft->documents()->create(['document_type' => 'work_plan', 'position' => 0,
        'source_data' => ['entries' => [['activity' => 'Validate sensor readings', 'expected_output' => 'Validation table', 'months' => [true]]]]]);
    $options = $this->getJson(route('research-support.documents', ['proposal_draft_id' => $this->draft->id]))->assertSuccessful()->json('documents');
    $selection = collect($options)->first(fn (array $option): bool => $option['format'] === 'SAVED' && str_contains($option['label'], 'Work Plan'));
    expect($selection)->not->toBeNull();
    $this->postJson(route('research-support.chat'), [
        'action' => ['type' => 'analyze_document', 'document_token' => $selection['token']],
        'messages' => [['role' => 'user', 'content' => 'Explain the activities in this work plan.']],
        'context' => ['proposal_draft_id' => $this->draft->id],
    ])->assertSuccessful()->assertJsonPath('document_reading.basis', 'saved_draft_projection')
        ->assertJsonPath('document_reading.format', 'SAVED');
    expect($paper->fresh()->file_path)->toBeNull();
    Process::assertNothingRan();
});

test('document reading history keeps bounded coverage and never stores extracted passages or selection tokens', function () {
    $reading = ['label' => '<b>Work plan</b>', 'format' => 'SAVED', 'basis' => 'saved_draft_projection', 'version' => 2,
        'coverage' => ['selected_passages' => 3, 'total_passages' => 12, 'omitted_passages' => 9, 'truncated' => true,
            'limitations' => ['Only selected saved sections were read.']]];
    $this->postJson(route('research-support.history.save'), ['context' => ['proposal_draft_id' => $this->draft->id],
        'messages' => [['role' => 'user', 'content' => 'Read my work plan.'], ['role' => 'assistant', 'content' => 'Here is the reading.', 'document_reading' => $reading]],
    ])->assertSuccessful();
    $saved = ResearchAssistantConversation::query()->where('user_id', $this->faculty->id)->latest('id')->firstOrFail()->messages[1]['document_reading'];
    expect($saved['label'])->toBe('Work plan')->and($saved['coverage']['selected_passages'])->toBe(3)
        ->and(array_keys($saved))->not->toContain('content', 'passages', 'token');
    Http::assertNothingSent();
});
