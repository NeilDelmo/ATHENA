<?php

use App\Actions\SaveProposalDraftDocument;
use App\Models\ProposalDraft;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'project_title' => 'Proposal audit fixture',
        'project_leader' => $this->faculty->name,
        'duration_months' => 12,
        'planned_start' => '2026-11-01',
        'planned_end' => '2027-11-01',
    ]);
    Storage::fake('local');
    $this->withoutVite();
});

test('clearing the last detailed proposal list value persists', function (string $field, array $value) {
    $document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => [$field => $value],
    ]);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.detailed-proposal.edit', $this->draft))
        ->assertOk()->assertSee('type="hidden" name="'.$field.'" value=""', false);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'project_leader' => $this->faculty->name,
        'research_agenda' => 'Edited after removing the last list entry',
        'specific_objectives_present' => 1,
        $field => '',
    ])->assertOk();
    $saved = $document->fresh()->source_data[$field] ?? [];
    if ($field === 'expected_outputs') {
        $saved = array_merge(...array_values($saved));
    }
    expect($saved)->toBe([]);
})->with([
    'all SDGs unchecked' => ['sdgs', [13]],
    'last expected output removed' => ['expected_outputs', ['publication' => [['description' => 'Old output']]]],
]);

test('removing all budget staff persists', function () {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => ['staff' => [['name' => 'Removed Person', 'campus' => 'Nasugbu', 'college' => 'CICS']]],
    ]);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()->assertSee('type="hidden" name="staff" value=""', false);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'leader_campus' => 'Nasugbu',
        'staff' => '',
    ])->assertOk();
    expect($document->fresh()->source_data['staff'] ?? [])->toBe([]);
});

test('disabling a manual budget override persists', function (string $field) {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => [$field => 6000],
    ]);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()->assertSee('type="hidden" name="'.$field.'" value=""', false);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'amounts' => ['travelling_expenses' => 100],
        $field => '',
    ])->assertOk();
    expect($document->fresh()->source_data[$field] ?? null)->toBeNull();
})->with(['mooe_total_override', 'co_total_override', 'project_total_override']);

test('removing the last custom budget item persists', function () {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => ['custom_mooe_items' => [['particular' => 'Removed item', 'amount' => 500]]],
    ]);
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()->assertSee('type="hidden" name="custom_mooe_items" value=""', false);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'custom_mooe_items' => '',
    ])->assertOk();
    expect($document->fresh()->source_data['custom_mooe_items'] ?? [])->toBe([]);
});

test('editing a CV preserves its previous PDF for history download', function () {
    $path = $this->draft->storageDirectory().'/prepared/manual/curriculum-vitae/previous.pdf';
    Storage::disk('local')->put($path, '%PDF-1.7 previous CV');
    $document = $this->draft->documents()->create([
        'document_type' => 'curriculum_vitae', 'position' => 0,
        'source_data' => ['people' => [['name' => 'Previous Name']]],
        'file_path' => $path, 'original_filename' => 'previous.pdf', 'mime_type' => 'application/pdf',
    ]);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.curriculum-vitae.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'people' => [['name' => 'Updated Name']],
    ])->assertOk();
    $version = $this->draft->documentVersions()->where('file_path', $path)->firstOrFail();
    $this->get(route('faculty.proposal-drafts.history.download', [$this->draft, $version]))->assertOk();
});

test('a collaborator does not replace a blank owner college with their own college', function () {
    $this->faculty->update(['college' => null]);
    $collaborator = User::factory()->create(['college' => 'College of Teacher Education']);
    $collaborator->assignRole('faculty');
    $this->draft->members()->create([
        'user_id' => $collaborator->id, 'name' => $collaborator->name,
        'email' => $collaborator->email, 'accepted_at' => now(),
    ]);
    $this->actingAs($collaborator)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'leader_college' => '',
    ])->assertOk();

    $document = $this->draft->documents()->where('document_type', 'line_item_budget')->sole();
    expect($document->source_data['leader_college'])->toBe('');
});

test('an expense save rolls back when its linked budget cannot be saved', function () {
    $saveDocument = app(SaveProposalDraftDocument::class);
    $this->mock(SaveProposalDraftDocument::class)->shouldReceive('handle')->andReturnUsing(
        function (...$arguments) use ($saveDocument) {
            if ($arguments[2] === 'line_item_budget') {
                throw ValidationException::withMessages(['document_version' => 'A teammate saved a newer budget.']);
            }

            return $saveDocument->handle(...$arguments);
        },
    );
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.expense-breakdown.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'items' => [[
            'category' => 'mooe', 'account' => 'Communication Expenses', 'sub_account' => 'Telephone Expenses',
            'particulars' => 'Prepaid Card', 'details' => 'Call Card', 'purpose' => 'For communication',
            'unit' => 'pc', 'quantity' => 1, 'unit_cost' => 100,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('document_version');

    expect($this->draft->documents()->exists())->toBeFalse()
        ->and($this->draft->documentVersions()->exists())->toBeFalse();
});
