<?php

use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head', 'research_secretary', 'research_coordinator'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $this->faculty = User::factory()->create();
    $this->faculty->assignRole('faculty');
    $this->draft = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Automatic signature names']);
    $this->head = User::factory()->create();
    $this->head->assignRole('research_head');
    $this->emptyDefaults = array_fill_keys(array_keys(ProposalSignatory::roles()), '');
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
    $this->put('/research-head/signatories/defaults', ['defaults' => $this->emptyDefaults])->assertForbidden();
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

test('signatory board offers institutional names and active directory names without writing defaults on GET', function () {
    $this->actingAs($this->head)->get(route('signatories.index'))
        ->assertOk()
        ->assertViewHas('assignmentOptions', fn (array $options): bool => count($options) === 6)
        ->assertViewHas('defaultAssignments', $this->emptyDefaults);
    expect(ProposalSignatory::query()->count())->toBe(0);

    $alternate = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Alternate Research Head', 'position' => 'Directory designation', 'active' => true]);
    ProposalSignatory::create(['role_key' => 'certified_by', 'name' => 'Alternate Research Head', 'position' => 'Duplicate directory name', 'active' => true]);
    ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Unavailable Signatory', 'position' => 'Inactive designation', 'active' => false]);
    ProposalSignatory::create(['role_key' => 'approved_by_name', 'name' => 'Assoc. Prof. ALBERTSON D. AMANTE', 'position' => 'Existing designation', 'active' => true, 'is_default' => true]);
    $before = ProposalSignatory::query()->get()->toArray();

    $response = $this->get(route('signatories.index'))
        ->assertOk()
        ->assertSee('data-signatory-default-board', false)
        ->assertDontSee('Template default')
        ->assertSee('<option value="" hidden>Asst. Prof. DJOANNA MARIE V. SALAC</option>', false)
        ->assertViewHas('defaultAssignments', [...$this->emptyDefaults, 'approved_by_name' => 'template:approved_by_name'])
        ->assertViewHas('assignmentOptions', fn (array $options): bool => count($options) === 7
            && ($options['directory:'.$alternate->id]['name'] ?? null) === 'Alternate Research Head'
            && ! in_array('Unavailable Signatory', array_column($options, 'name'), true))
        ->assertSee('Checked and verified by')
        ->assertSee('Final approval');

    expect(ProposalSignatory::query()->get()->toArray())->toBe($before);

    if (getenv('ATHENA_EXPORT_SIGNATORY_BOARD') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/signatory-default-board.html'), $response->getContent());
    }
});

test('head and secretary can reuse a directory name in any signature field without moving its source', function (string $workspace) {
    $officer = User::factory()->create();
    $officer->assignRole($workspace);
    $source = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Shared Office Signer', 'position' => 'Source designation', 'active' => true]);
    $existing = ProposalSignatory::create(['role_key' => 'certified_by', 'name' => 'Shared Office Signer', 'position' => 'Older designation', 'active' => true]);
    $former = ProposalSignatory::create(['role_key' => 'certified_by', 'name' => 'Former Budget Signer', 'position' => 'Budget designation', 'active' => true, 'is_default' => true]);

    $this->actingAs($officer)->put('/research-head/signatories/defaults', ['defaults' => [
        ...$this->emptyDefaults,
        'certified_by' => 'directory:'.$source->id,
        'approved_by_name' => 'directory:'.$source->id,
    ]])->assertRedirect(route('signatories.index'))->assertSessionHasNoErrors();

    expect($source->fresh()->toArray())->toMatchArray(['role_key' => 'verified_by', 'name' => 'Shared Office Signer', 'position' => 'Source designation', 'is_default' => false])
        ->and($existing->fresh()->is_default)->toBeTrue()
        ->and($existing->fresh()->position)->toBe('Older designation')
        ->and($former->fresh()->is_default)->toBeFalse()
        ->and(ProposalSignatory::query()->where('role_key', 'approved_by_name')->where('is_default', true)->sole()->name)->toBe('Shared Office Signer')
        ->and($this->draft->fresh()->signatoryFields('line_item_budget'))->toBe(['certified_by' => 'Shared Office Signer', 'certified_role' => 'Older designation']);
})->with(['research_head', 'research_secretary']);

test('a configured institutional name can be assigned to a different signature field', function () {
    $this->actingAs($this->head)->put('/research-head/signatories/defaults', ['defaults' => [
        ...$this->emptyDefaults,
        'certified_by' => 'template:checked_verified_by_name',
    ]])->assertRedirect()->assertSessionHasNoErrors();

    expect(ProposalSignatory::query()->sole()->toArray())->toMatchArray([
        'role_key' => 'certified_by',
        'name' => 'Asst. Prof. DJOANNA MARIE V. SALAC',
        'position' => "Chancellor\nVice Chairperson, LREC",
        'active' => true,
        'is_default' => true,
    ]);
});

test('saving unchanged assignments preserves directory IDs designations and prepared files', function () {
    $source = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Shared Current Signer', 'position' => 'Source designation', 'active' => true]);
    $current = ProposalSignatory::create(['role_key' => 'certified_by', 'name' => 'Shared Current Signer', 'position' => 'Custom budget designation', 'active' => true, 'is_default' => true]);
    $paper = $this->draft->documents()->create(['document_type' => 'line_item_budget', 'position' => 0, 'file_path' => 'prepared.pdf', 'lock_version' => 4]);
    $before = ProposalSignatory::query()->get()->toArray();

    $this->actingAs($this->head)->get(route('signatories.index'))
        ->assertViewHas('defaultAssignments', [...$this->emptyDefaults, 'certified_by' => 'directory:'.$source->id]);
    $this->put('/research-head/signatories/defaults', ['defaults' => [
        ...$this->emptyDefaults,
        'certified_by' => 'directory:'.$source->id,
    ]])->assertRedirect()->assertSessionHasNoErrors();

    expect(ProposalSignatory::query()->get()->toArray())->toBe($before)
        ->and($current->fresh()->position)->toBe('Custom budget designation')
        ->and($paper->fresh()->file_path)->toBe('prepared.pdf')
        ->and($paper->fresh()->lock_version)->toBe(4)
        ->and($this->draft->fresh()->lock_version)->toBe(0);
});

test('a managed name matching its template stays a named assignment until explicitly reset', function () {
    $current = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Asst. Prof. DJOANNA MARIE V. SALAC', 'position' => 'Custom current designation', 'active' => true, 'is_default' => true]);
    $defaults = [...$this->emptyDefaults, 'verified_by' => 'template:checked_verified_by_name'];

    $this->actingAs($this->head)->get(route('signatories.index'))->assertViewHas('defaultAssignments', $defaults);
    $this->put('/research-head/signatories/defaults', ['defaults' => $defaults])->assertRedirect()->assertSessionHasNoErrors();
    expect($current->fresh()->is_default)->toBeTrue()
        ->and($current->fresh()->position)->toBe('Custom current designation')
        ->and($this->draft->fresh()->lock_version)->toBe(0);

    $this->put('/research-head/signatories/defaults', ['defaults' => $this->emptyDefaults])->assertRedirect()->assertSessionHasNoErrors();
    expect($current->fresh()->is_default)->toBeFalse()
        ->and($this->draft->fresh()->signatoryFields('work_plan')['verified_role'])->toBe('Head, Research')
        ->and($this->draft->fresh()->lock_version)->toBe(1);
});

test('invalid board input rejects every assignment before making changes', function (string $case) {
    $source = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Valid Directory Signer', 'position' => 'Source designation', 'active' => true]);
    $inactive = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Inactive Directory Signer', 'position' => 'Inactive designation', 'active' => false]);
    $defaults = [...$this->emptyDefaults, 'approved_by_name' => 'directory:'.$source->id];
    $error = 'defaults';

    if ($case === 'unknown field') {
        $defaults['unknown_field'] = 'directory:'.$source->id;
    } elseif ($case === 'missing field') {
        unset($defaults['certified_by']);
    } else {
        $defaults['certified_by'] = $case === 'inactive name' ? 'directory:'.$inactive->id : 'directory:999999';
        $error = 'defaults.certified_by';
    }

    $this->actingAs($this->head)->put('/research-head/signatories/defaults', ['defaults' => $defaults])->assertSessionHasErrors($error);
    $this->get(route('signatories.index'))
        ->assertOk()
        ->assertSee('data-signatory-default-board', false)
        ->assertSee('x-data="{ addSignatoryOpen: false }"', false);

    expect(ProposalSignatory::query()->count())->toBe(2)
        ->and(ProposalSignatory::query()->where('is_default', true)->count())->toBe(0)
        ->and($this->draft->fresh()->lock_version)->toBe(0);
})->with(['unknown field', 'missing field', 'inactive name', 'unknown name']);

test('board revalidates a directory name removed after form validation', function (bool $deleted) {
    $source = ProposalSignatory::create(['role_key' => 'verified_by', 'name' => 'Stale Directory Signer', 'position' => 'Source designation', 'active' => true]);
    $changed = false;
    DB::listen(function (QueryExecuted $query) use ($source, $deleted, &$changed): void {
        if (! $changed && str_contains($query->sql, 'proposal_signatories') && str_contains($query->sql, 'for update')) {
            $changed = true;
            $deleted ? $source->delete() : $source->update(['active' => false]);
        }
    });

    $this->actingAs($this->head)->put('/research-head/signatories/defaults', ['defaults' => [
        ...$this->emptyDefaults,
        'certified_by' => 'directory:'.$source->id,
    ]])->assertSessionHasErrors('defaults.certified_by');

    expect($changed)->toBeTrue()
        ->and(ProposalSignatory::query()->where('is_default', true)->count())->toBe(0)
        ->and($this->draft->fresh()->lock_version)->toBe(0);
})->with([false, true]);

test('saving the board invalidates changed editable papers once and retains submitted and historical files', function () {
    $source = ProposalSignatory::create(['role_key' => 'screening_center', 'name' => 'New Shared Signer', 'position' => 'Source designation', 'active' => true]);
    $workPlan = $this->draft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'work-plan.pdf', 'lock_version' => 1]);
    $budget = $this->draft->documents()->create(['document_type' => 'line_item_budget', 'position' => 0, 'file_path' => 'budget.pdf', 'lock_version' => 2]);
    $other = $this->draft->documents()->create(['document_type' => 'curriculum_vitae', 'position' => 0, 'file_path' => 'cv.pdf', 'lock_version' => 3]);
    $historical = $this->draft->documentVersions()->create(['proposal_draft_document_id' => $workPlan->id, 'document_type' => 'work_plan', 'position' => 0, 'version_number' => 1, 'action' => 'submitted', 'file_path' => 'historical.pdf', 'source_data' => ['verified_by' => 'Historic Signer']]);
    $submitted = ProposalDraft::create(['user_id' => $this->faculty->id, 'project_title' => 'Already submitted proposal', 'status' => 'submitted']);
    $submittedPaper = $submitted->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'submitted.pdf', 'lock_version' => 4]);

    $this->actingAs($this->head)->put('/research-head/signatories/defaults', ['defaults' => [
        ...$this->emptyDefaults,
        'verified_by' => 'directory:'.$source->id,
        'certified_by' => 'directory:'.$source->id,
    ]])->assertRedirect()->assertSessionHasNoErrors();

    expect($this->draft->fresh()->lock_version)->toBe(1)
        ->and($workPlan->fresh()->file_path)->toBeNull()->and($workPlan->fresh()->lock_version)->toBe(2)
        ->and($budget->fresh()->file_path)->toBeNull()->and($budget->fresh()->lock_version)->toBe(3)
        ->and($other->fresh()->file_path)->toBe('cv.pdf')
        ->and($other->fresh()->lock_version)->toBe(3)
        ->and($historical->fresh()->file_path)->toBe('historical.pdf')
        ->and($historical->fresh()->source_data['verified_by'])->toBe('Historic Signer')
        ->and($submittedPaper->fresh()->file_path)->toBe('submitted.pdf')
        ->and($submittedPaper->fresh()->lock_version)->toBe(4)
        ->and($submitted->fresh()->lock_version)->toBe(0);

    $this->put('/research-head/signatories/defaults', ['defaults' => $this->emptyDefaults])->assertRedirect()->assertSessionHasNoErrors();
    expect(ProposalSignatory::query()->where('is_default', true)->count())->toBe(0)
        ->and($this->draft->fresh()->signatoryFields('work_plan')['verified_by'])->toBe('Asst. Prof. DJOANNA MARIE V. SALAC')
        ->and($this->draft->fresh()->signatoryFields('line_item_budget')['certified_by'])->toBe('Dr. ENRICO M. DALANGIN')
        ->and($this->draft->fresh()->lock_version)->toBe(2)
        ->and($source->fresh()->active)->toBeTrue();
});

test('non-default directory entries do not change automatic signature names', function () {
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->actingAs($head)->post(route('signatories.store'), ['role_key' => 'verified_by', 'name' => 'Another Person', 'position' => 'Research Head', 'active' => 1, 'is_default' => 0])->assertSessionHasNoErrors();
    expect($this->draft->fresh()->signatoryFields('work_plan')['verified_by'])->toBe(config('work_plan.verifier.name'))
        ->and($this->draft->fresh()->lock_version)->toBe(0);
});
