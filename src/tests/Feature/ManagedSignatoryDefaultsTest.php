<?php

use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head', 'research_secretary', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Automatic signature names']);
});

test('research head and secretary persist defaults for every proposal without faculty selection', function (string $role) {
    $officer = User::factory()->create();
    $officer->assignRole($role);
    $input = ['role_key' => 'certified_by', 'name' => 'Maria Santos', 'position' => 'Budget Officer', 'active' => 1, 'is_default' => 1];
    $this->actingAs($officer)->post(route('signatories.store'), $input)->assertRedirect()->assertSessionHasNoErrors();
    $person = ProposalSignatory::query()->sole();
    $this->get(route('signatories.index'))->assertOk()->assertSee('Maria Santos')->assertSee('Use as default for this role');
    $newDraft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Another proposal']);
    foreach ([$this->draft, $newDraft] as $draft) {
        expect($draft->fresh()->signatoryFields('line_item_budget'))->toBe(['certified_by' => 'Maria Santos', 'certified_role' => 'Budget Officer']);
    }
    $this->patch(route('signatories.update', $person), [...$input, 'name' => 'Ana Reyes'])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->draft->fresh()->signatoryFields('line_item_budget')['certified_by'])->toBe('Ana Reyes')
        ->and(ProposalSignatory::query()->sole()->is_default)->toBeTrue();
    $this->actingAs($this->faculty)->get(route('faculty.proposal-drafts.show', $this->draft))->assertOk()->assertDontSee('Choose signatories');
    $this->get(route('signatories.edit', $this->draft))->assertForbidden();
    $this->put(route('signatories.select', $this->draft), ['lock_version' => 0, 'signatories' => ['certified_by' => $person->id]])->assertForbidden();
})->with(['research_head', 'research_secretary']);

test('faculty and coordinator cannot edit institutional signature defaults', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $person = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Office Head', 'position' => 'Research Head', 'active' => true, 'is_default' => true]);
    $input = ['role_key' => 'verified_by', 'name' => 'Unauthorized replacement', 'position' => 'Research Head', 'active' => 1, 'is_default' => 1];
    $this->actingAs($user)->get(route('signatories.index'))->assertForbidden();
    $this->post(route('signatories.store'), $input)->assertForbidden();
    $this->patch(route('signatories.update', $person), $input)->assertForbidden();
    $this->delete(route('signatories.destroy', $person))->assertForbidden();
    expect($person->fresh()->name)->toBe('Office Head');
})->with(['faculty', 'research_coordinator']);

test('switching a default invalidates affected prepared drafts and retains historical copies', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $first = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Original Head', 'position' => 'Research Head', 'active' => true, 'is_default' => true]);
    $paper = $this->draft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'original.pdf', 'source_data' => ['verified_by' => 'Original Head'], 'lock_version' => 1]);
    $historical = $this->draft->documentVersions()->create(['proposal_draft_document_id' => $paper->id, 'document_type' => 'work_plan', 'position' => 0, 'version_number' => 1, 'action' => 'submitted', 'file_path' => 'original.pdf', 'source_data' => ['verified_by' => 'Original Head']]);
    $other = $this->draft->documents()->create(['document_type' => 'curriculum_vitae', 'position' => 0, 'file_path' => 'cv.pdf', 'lock_version' => 1]);
    $this->draft->update(['signatory_selections' => ['verified_by' => ['name' => 'Legacy faculty choice', 'position' => 'Head']]]);
    $this->actingAs($head)->post(route('signatories.store'), ['role_key' => 'verified_by', 'name' => 'Current Head', 'position' => 'Research Office Head', 'active' => 1, 'is_default' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect($first->fresh()->is_default)->toBeFalse()
        ->and(ProposalSignatory::query()->where('role_key', 'verified_by')->where('is_default', true)->count())->toBe(1)
        ->and($this->draft->fresh()->signatoryFields('work_plan'))->toBe(['verified_by' => 'Current Head', 'verified_role' => 'Research Office Head'])
        ->and($paper->fresh()->file_path)->toBeNull()->and($paper->fresh()->lock_version)->toBe(2)
        ->and($other->fresh()->file_path)->toBe('cv.pdf')
        ->and($historical->fresh()->file_path)->toBe('original.pdf')
        ->and($historical->fresh()->source_data['verified_by'])->toBe('Original Head');
    $current = ProposalSignatory::query()->where('is_default', true)->sole();
    $this->delete(route('signatories.destroy', $current))->assertRedirect();
    expect($this->draft->fresh()->signatoryFields('work_plan')['verified_by'])->toBe(config('work_plan.verifier.name'));
});

test('non-default directory entries do not change automatic signature names', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->post(route('signatories.store'), ['role_key' => 'verified_by', 'name' => 'Another Person', 'position' => 'Research Head', 'active' => 1, 'is_default' => 0])->assertSessionHasNoErrors();
    expect($this->draft->fresh()->signatoryFields('work_plan')['verified_by'])->toBe(config('work_plan.verifier.name'))
        ->and($this->draft->fresh()->lock_version)->toBe(0);
});
