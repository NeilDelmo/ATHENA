<?php

use App\Models\ProposalDraft;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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

test('audit clearing the last detailed proposal list value persists', function (string $field, array $value) {
    $document = $this->draft->documents()->create([
        'document_type' => 'detailed_proposal', 'position' => 0,
        'source_data' => [$field => $value],
    ]);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.detailed-proposal.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
        'project_leader' => $this->faculty->name,
        'research_agenda' => 'Edited after removing the last list entry',
        'specific_objectives_present' => 1,
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

test('audit removing all budget staff persists', function () {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => ['staff' => [['name' => 'Removed Person', 'campus' => 'Nasugbu', 'college' => 'CICS']]],
    ]);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'leader_campus' => 'Nasugbu',
    ])->assertOk();
    expect($document->fresh()->source_data['staff'] ?? [])->toBe([]);
});

test('audit disabling a manual budget override persists', function (string $field) {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => [$field => 6000],
    ]);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true, 'amounts' => ['travelling_expenses' => 100],
    ])->assertOk();
    expect($document->fresh()->source_data[$field] ?? null)->toBeNull();
})->with(['mooe_total_override', 'co_total_override', 'project_total_override']);

test('audit removing the last custom budget item persists', function () {
    $document = $this->draft->documents()->create([
        'document_type' => 'line_item_budget', 'position' => 0,
        'source_data' => ['custom_mooe_items' => [['particular' => 'Removed item', 'amount' => 500]]],
    ]);
    $this->actingAs($this->faculty)->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
        'document_version' => 0, 'save_as_draft' => true,
    ])->assertOk();
    expect($document->fresh()->source_data['custom_mooe_items'] ?? [])->toBe([]);
});

test('audit editing a CV preserves its previous PDF for history download', function () {
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
