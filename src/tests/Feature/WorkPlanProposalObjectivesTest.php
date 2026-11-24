<?php

use App\Actions\SaveProposalDraftDocument;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Support\ProposalDraftReadiness;
use App\Support\WorkPlanProposalObjectives;
use Illuminate\Support\Facades\Storage;
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
        ->assertOk()->assertSee('From Detailed Proposal')
        ->assertDontSee('Add another objective')
        ->assertViewHas('workPlanComplete', true)
        ->assertViewHas('workPlanDocument', fn ($document): bool => $document->completed_at === null)
        ->assertViewHas('sourceData', fn (array $source): bool => $source['entries'] == $this->entries);
});

test('the Work Plan starts with matching proposal methods and shows overall outputs as reference', function () {
    $this->proposal->update(['source_data' => [
        ...$this->proposal->source_data,
        'specific_method_objectives' => [
            ['heading' => ' evaluate   THE intervention ', 'methods' => [['description' => 'Compare outcomes']]],
            ['heading' => 'Establish the baseline', 'methods' => [
                ['description' => 'Conduct interviews'], ['description' => 'Validate the dataset'],
            ]],
            ['heading' => 'Unrelated method group', 'methods' => [['description' => 'Do not import this']]],
        ],
        'expected_outputs' => ['publication' => [['description' => 'One journal article']]],
    ]]);

    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.work-plan.edit', $this->draft))
        ->assertOk()
        ->assertSee('Proposal outputs')
        ->assertSee('One journal article')
        ->assertViewHas('sourceData', fn (array $source): bool => $source['entries'][0]['activity'] === "Conduct interviews\nValidate the dataset"
            && $source['entries'][1]['activity'] === 'Compare outcomes'
            && $source['entries'][0]['expected_output'] === '');
});

test('unmatched proposal methods are not assigned by their position', function () {
    $this->proposal->update(['source_data' => [
        ...$this->proposal->source_data,
        'specific_method_objectives' => [
            ['heading' => 'Another objective', 'methods' => [['description' => 'Unrelated activity']]],
        ],
    ]]);

    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.work-plan.edit', $this->draft))
        ->assertOk()->assertViewHas('sourceData', fn (array $source): bool => array_column($source['entries'], 'activity') === ['', '']);
});

test('proposal method suggestions preserve customized work plan activities and months', function () {
    $this->proposal->update(['source_data' => [
        ...$this->proposal->source_data,
        'specific_method_objectives' => [
            ['heading' => 'Establish the baseline', 'methods' => [['description' => 'New proposal method']]],
        ],
    ]]);
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN, 'position' => 0,
        'source_data' => ['entries' => $this->entries],
    ]);

    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.work-plan.edit', $this->draft))
        ->assertOk()
        ->assertViewHas('sourceData', fn (array $source): bool => $source['entries'] == $this->entries)
        ->assertViewHas('proposalActivities', fn (array $activities): bool => $activities['Establish the baseline'] === 'New proposal method');
});

test('work plans save overlapping months without replacing edited activities from the proposal', function () {
    $entries = $this->entries;
    $entries[1]['months'] = [1, 2];
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.work-plan.update', $this->draft), [
        'document_version' => 0, 'entries' => $entries,
    ])->assertOk();

    expect($this->draft->documents()->where('document_type', ProposalVersionFile::TYPE_WORK_PLAN)->sole()->source_data['entries'])
        ->toEqual($entries);
});

test('a new Work Plan stays complete after reopening following the final autosave', function (string $saveAsDraft) {
    $editor = route('faculty.proposal-drafts.work-plan.edit', $this->draft);
    $autosave = route('faculty.proposal-drafts.work-plan.update', $this->draft);
    $this->actingAs($this->faculty)->get($editor)
        ->assertOk()
        ->assertViewHas('workPlanDocument', fn ($document): bool => $document === null)
        ->assertViewHas('workPlanComplete', false);

    $completedEntries = array_map(fn (array $entry): array => [
        ...$entry,
        'months' => array_map('strval', $entry['months']),
    ], $this->entries);
    $partialEntries = $completedEntries;
    $partialEntries[0]['months'] = [];
    $partial = $this->put($autosave, [
        'document_version' => '0',
        'save_as_draft' => '1',
        'entries' => $partialEntries,
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('saved_as_draft', true);
    $this->get($editor)->assertOk()->assertViewHas('workPlanComplete', false);

    $this->put($autosave, [
        'document_version' => (string) $partial->json('document_version'),
        'save_as_draft' => $saveAsDraft,
        'entries' => $completedEntries,
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('saved_as_draft', false);

    $reopened = $this->get($editor)
        ->assertOk()
        ->assertViewHas('workPlanComplete', true);
    expect($reopened->viewData('workPlanDocument')->completed_at)->not->toBeNull()
        ->and($reopened->viewData('sourceData')['entries'])->toEqual($completedEntries)
        ->and(app(ProposalDraftReadiness::class)->checklist($this->draft->fresh())['work-plan']['status'])->toBe('Complete');
})->with(['complete save' => '0', 'draft save with complete content' => '1']);

test('proposal narrative edits preserve the prepared Work Plan unless its objectives change', function (bool $objectivesChanged) {
    Storage::fake('local');
    $path = $this->draft->storageDirectory().'/prepared/work-plan/current.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7 prepared Work Plan');
    $workPlan = $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_WORK_PLAN,
        'position' => 0,
        'source_data' => ['entries' => $this->entries],
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'completed_at' => now(),
    ]);
    $source = [...$this->proposal->source_data, 'related_literature' => 'Updated research evidence.'];
    if ($objectivesChanged) {
        $source['specific_objectives'][0]['description'] = 'Measure the revised baseline';
    }

    app(SaveProposalDraftDocument::class)->handle(
        $this->draft, $this->faculty, ProposalVersionFile::TYPE_DETAILED_PROPOSAL, 0, 0,
        ['source_data' => $source, 'completed_at' => now()],
    );

    expect($workPlan->fresh()->file_path)->toBe($objectivesChanged ? null : $path);
    Storage::disk('local')->assertExists($path);
})->with(['narrative only' => false, 'shared objectives' => true]);

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

test('the Work Plan paper preview shows unfinished and unscheduled objectives', function () {
    $this->draft->update(['duration_months' => 24, 'planned_end' => '2028-09-30']);
    $entries = [
        ['objective' => 'Establish the baseline', 'activity' => 'Collect initial responses'],
        [...$this->entries[1], 'months' => [13]],
    ];

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.work-plan.preview', $this->draft), ['entries' => $entries])
        ->assertOk()
        ->assertSee('Establish the baseline')
        ->assertSee('Collect initial responses')
        ->assertSee('data-scheduled-month="13"', false)
        ->assertViewHas('workPlan', fn (array $workPlan): bool => array_column($workPlan['entries_by_year'][1], 'objective') === ['Establish the baseline']
            && $workPlan['entries_by_year'][1][0]['months'] === []
            && array_column($workPlan['entries_by_year'][2], 'objective') === ['Evaluate the intervention']);

    $this->postJson(route('faculty.proposal-drafts.work-plan.download', $this->draft), [
        'save_as_draft' => true, 'entries' => $entries,
    ])->assertUnprocessable()->assertJsonValidationErrors(['entries.0.expected_output', 'entries.0.months']);
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
