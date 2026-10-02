<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Support\ProposalDraftReadiness;
use App\Support\WorkPlanProposalObjectives;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Linked Objectives Call', 'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(),
        'status' => 'open', 'created_by' => $this->faculty->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id, 'research_call_id' => $call->id,
        'project_title' => 'Linked Objectives', 'duration_months' => 12,
        'planned_start' => '2026-10-01', 'planned_end' => '2027-09-30',
        'project_leader' => $this->faculty->name,
    ]);
    $this->proposal = $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_DETAILED_PROPOSAL,
        'position' => 0, 'source_data' => [
            'general_objective' => 'Improve community services.',
            'specific_objectives' => [
                ['description' => 'Establish the baseline'],
                ['description' => 'Evaluate the intervention'],
            ],
        ],
    ]);
    $this->entries = [
        ['objective' => 'Establish the baseline', 'activity' => 'Conduct interviews', 'expected_output' => 'Baseline dataset', 'months' => [1, 2]],
        ['objective' => 'Evaluate the intervention', 'activity' => 'Analyze outcomes', 'expected_output' => 'Evaluation report', 'months' => [3, 4]],
    ];
    $this->withoutVite();
});

test('the Work Plan editor imports proposal objectives and preserves existing activities', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'source_data' => ['entries' => $this->entries],
    ]);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.work-plan.edit', $this->draft))
        ->assertOk()->assertSee('Objective from Detailed Proposal')
        ->assertSee('readonly required', false)->assertDontSee('Add another objective')
        ->assertViewHas('sourceData', fn (array $source): bool => $source['entries'] == $this->entries);
});

test('Work Plan saving and preview use authoritative objectives instead of submitted wording', function () {
    $entries = $this->entries;
    $entries[0]['objective'] = 'Unrelated client objective';
    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.work-plan.update', $this->draft), [
            'document_version' => 0, 'save_as_draft' => true, 'entries' => $entries,
        ])->assertOk();
    $document = $this->draft->documents()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    expect($document->source_data['entries'])->toEqual($this->entries);
    $this->postJson(route('faculty.proposal-drafts.work-plan.preview', $this->draft), ['entries' => $entries])
        ->assertOk()->assertSee('Establish the baseline')->assertDontSee('Unrelated client objective');
});

test('reordered and added proposal objectives keep their matching work plan details', function () {
    $objectives = ['New specific objective', 'Evaluate the intervention', 'Establish the baseline'];
    $this->proposal->update(['source_data' => ['specific_objectives' => array_map(fn (string $objective): array => ['description' => $objective], $objectives)]]);
    $mapper = app(WorkPlanProposalObjectives::class);
    $entries = $mapper->entries($this->entries, $mapper->forDraft($this->draft));
    expect(array_column($entries, 'objective'))->toBe($objectives)
        ->and($entries[0]['activity'])->toBe('')
        ->and($entries[1]['activity'])->toBe('Analyze outcomes')
        ->and($entries[2]['months'])->toBe([1, 2]);
});

test('renamed proposal objectives retain the work plan row and long descriptions are not truncated', function () {
    $objective = str_repeat('Updated objective description ', 25);
    $this->proposal->update(['source_data' => ['specific_objectives' => [['description' => $objective], ['description' => 'Evaluate the intervention']]]]);
    $entries = $this->entries;
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.work-plan.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'entries' => $entries,
    ])->assertOk();
    $document = $this->draft->documents()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole();
    expect($document->source_data['entries'][0]['objective'])->toBe(trim($objective))
        ->and($document->source_data['entries'][0]['activity'])->toBe('Conduct interviews');
});

test('missing proposal objectives prevent preparing an independent Work Plan', function () {
    $this->proposal->update(['source_data' => []]);
    $this->actingAs($this->faculty)->postJson(route('faculty.proposal-drafts.work-plan.download', $this->draft), ['entries' => $this->entries])
        ->assertUnprocessable()->assertJsonValidationErrors('entries');
});

test('legacy proposal objectives import as specific objectives and removed objectives leave the Work Plan', function () {
    $this->proposal->update(['source_data' => ['objectives' => "Specific Objectives\n1. Evaluate the intervention"]]);
    $mapper = app(WorkPlanProposalObjectives::class);
    $entries = $mapper->entries($this->entries, $mapper->forDraft($this->draft->fresh()));
    expect($entries)->toHaveCount(1)
        ->and($entries[0]['objective'])->toBe('Evaluate the intervention')
        ->and($entries[0]['activity'])->toBe('Analyze outcomes');
});

test('new proposal objectives require Work Plan activities before the paper is complete', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'source_data' => ['entries' => $this->entries], 'completed_at' => now(),
    ]);
    $readiness = app(ProposalDraftReadiness::class);
    expect($readiness->checklist($this->draft->fresh())['work-plan']['complete'])->toBeTrue();
    $this->proposal->update(['source_data' => ['specific_objectives' => [
        ['description' => 'Establish the baseline'],
        ['description' => 'Evaluate the intervention'],
        ['description' => 'Share the findings'],
    ]]]);
    expect($readiness->checklist($this->draft->fresh())['work-plan']['complete'])->toBeFalse();
});
