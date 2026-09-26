<?php

use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\User;
use App\Services\DetailedProposalDocumentService;
use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Services\WorkPlanDocumentService;
use App\Support\DetailedProposalData;
use App\Support\GADChecklistData;
use App\Support\LineItemBudgetData;
use App\Support\WorkPlanData;
use Spatie\Permission\Models\Role;

test('head manages signatories and faculty selections are private role checked and frozen', function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Signatory test', 'status' => 'draft', 'lock_version' => 0]);
    $paper = $draft->documents()->create(['document_type' => 'work_plan', 'position' => 0, 'file_path' => 'old-prepared-paper.pdf', 'lock_version' => 0]);
    $input = ['role_key' => 'verified_by', 'name' => 'Original Name', 'position' => 'Research Head', 'active' => 1];
    $this->actingAs($faculty)->post(route('signatories.store'), $input)->assertForbidden();
    $this->actingAs($head)->post(route('signatories.store'), $input)->assertSessionHasNoErrors();
    $person = ProposalSignatory::firstOrFail();
    $this->get(route('signatories.index'))
        ->assertOk()
        ->assertSee('Original Name')
        ->assertSee('Back to dashboard')
        ->assertSee('Edit')
        ->assertSee('Delete')
        ->assertSee('aria-label="Signatory Directory"', false);
    $this->actingAs($other)->get(route('signatories.edit', $draft))->assertForbidden();
    $this->actingAs($faculty)->get(route('signatories.edit', $draft))->assertOk()->assertSee('Original Name');
    $this->put(route('signatories.select', $draft), ['lock_version' => 0, 'signatories' => ['certified_by' => $person->id]])->assertSessionHasErrors('signatories.certified_by');
    $this->put(route('signatories.select', $draft), ['lock_version' => 0, 'signatories' => ['verified_by' => $person->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('faculty.proposal-drafts.show', $draft))
        ->assertSessionHas('success', 'Signatories saved. Preview your papers and prepare the PDFs again before submitting.');
    expect($draft->fresh()->signatoryFields('work_plan'))->toBe(['verified_by' => 'Original Name', 'verified_role' => 'Research Head']);
    expect($paper->fresh()->file_path)->toBeNull()->and($paper->fresh()->lock_version)->toBe(1);
    $this->actingAs($head)
        ->from(route('signatories.index', ['edit' => $person]))
        ->patch(route('signatories.update', $person), [...$input, 'name' => 'New Name', 'active' => 0])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('signatories.index').'#signatory-'.$person->id)
        ->assertSessionHas('success', 'Directory updated. Previously selected names remain unchanged.');
    expect($draft->fresh()->signatoryFields('work_plan')['verified_by'])->toBe('Original Name');
    $this->actingAs($faculty)->put(route('signatories.select', $draft), ['lock_version' => 1, 'signatories' => ['verified_by' => $person->id]])->assertSessionHasErrors('signatories.verified_by');
    expect($draft->fresh()->signatoryFields('curriculum_vitae'))->toBe([])->and($draft->signatoryFields('expense_breakdown'))->toBe([]);
});

test('research head can search edit and remove directory entries while faculty cannot delete them', function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $signatory = ProposalSignatory::create([
        'role_key' => 'verified_by',
        'name' => 'Dr. Elena Santos',
        'position' => 'Research Head',
        'active' => true,
    ]);
    ProposalSignatory::create([
        'role_key' => 'certified_by',
        'name' => 'Marco Villanueva',
        'position' => 'Budget Officer',
        'active' => true,
    ]);

    $this->actingAs($head)
        ->get(route('signatories.index', ['search' => 'Elena', 'role' => 'verified_by', 'edit' => $signatory]))
        ->assertOk()
        ->assertSee('Dr. Elena Santos')
        ->assertSee('Save changes')
        ->assertDontSee('Marco Villanueva');

    $this->actingAs($faculty)
        ->delete(route('signatories.destroy', $signatory))
        ->assertForbidden();
    $this->assertModelExists($signatory);

    $this->actingAs($head)
        ->delete(route('signatories.destroy', $signatory))
        ->assertRedirect(route('signatories.index'))
        ->assertSessionHas('success', 'Dr. Elena Santos was removed from the directory. Existing proposal signature blocks remain unchanged.');
    $this->assertModelMissing($signatory);
});

test('saving signatories returns faculty to the proposal paper they came from', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'project_title' => 'Return to paper test',
        'status' => 'draft',
        'lock_version' => 0,
    ]);
    $signatory = ProposalSignatory::create([
        'role_key' => 'checked_verified_by_name',
        'name' => 'Dr. Maria Santos',
        'position' => 'Research Head',
        'active' => true,
    ]);

    $signatoryPage = route('signatories.edit', [
        'proposalDraft' => $draft,
        'paper' => 'detailed_proposal',
    ]);

    $this->actingAs($faculty)
        ->get($signatoryPage)
        ->assertOk()
        ->assertSee('name="return_paper" value="detailed_proposal"', false)
        ->assertSee(route('faculty.proposal-drafts.detailed-proposal.edit', $draft), false);

    $this->put(route('signatories.select', $draft), [
        'lock_version' => 0,
        'return_paper' => 'detailed_proposal',
        'signatories' => ['checked_verified_by_name' => $signatory->id],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('faculty.proposal-drafts.detailed-proposal.edit', $draft)
        ->assertSessionHas('success', 'Signatories saved. Preview your papers and prepare the PDFs again before submitting.');
});

test('faculty can refresh frozen signatory names after the research head renames the same directory entries', function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create([
        'user_id' => $faculty->id,
        'project_title' => 'Renamed signatory test',
        'status' => 'draft',
        'lock_version' => 0,
    ]);
    $finalApprover = ProposalSignatory::create([
        'role_key' => 'approved_by_name',
        'name' => 'Mary Jhezl Baldos',
        'position' => 'Final Approver',
        'active' => true,
    ]);
    $recommendingApprover = ProposalSignatory::create([
        'role_key' => 'recommending_approval_name',
        'name' => 'Mary Jhezl Baldos',
        'position' => 'Recommending Approver',
        'active' => true,
    ]);

    $this->actingAs($faculty)->put(route('signatories.select', $draft), [
        'lock_version' => 0,
        'signatories' => [
            'approved_by_name' => $finalApprover->id,
            'recommending_approval_name' => $recommendingApprover->id,
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($head)->patch(route('signatories.update', $finalApprover), [
        'role_key' => 'approved_by_name',
        'name' => 'Akira Soriano',
        'position' => 'Final Approver',
        'active' => 1,
    ])->assertSessionHasNoErrors();
    $this->patch(route('signatories.update', $recommendingApprover), [
        'role_key' => 'recommending_approval_name',
        'name' => 'Quey Baldos',
        'position' => 'Recommending Approver',
        'active' => 1,
    ])->assertSessionHasNoErrors();

    expect($draft->fresh()->signatoryFields('detailed_proposal'))
        ->toMatchArray([
            'approved_by_name' => 'Mary Jhezl Baldos',
            'recommending_approval_name' => 'Mary Jhezl Baldos',
        ]);

    $this->actingAs($faculty)->put(route('signatories.select', $draft), [
        'lock_version' => 1,
        'return_paper' => 'detailed_proposal',
        'signatories' => [
            'approved_by_name' => $finalApprover->id,
            'recommending_approval_name' => $recommendingApprover->id,
        ],
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirectToRoute('faculty.proposal-drafts.detailed-proposal.edit', $draft);

    expect($draft->fresh()->signatoryFields('detailed_proposal'))
        ->toMatchArray([
            'approved_by_name' => 'Akira Soriano',
            'recommending_approval_name' => 'Quey Baldos',
        ])
        ->and($draft->fresh()->lock_version)->toBe(2);

    $response = $this->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))->assertOk();

    expect($response->viewData('sourceData'))
        ->toMatchArray([
            'approved_by_name' => 'Akira Soriano',
            'recommending_approval_name' => 'Quey Baldos',
        ]);
});

test('all five generated papers contain the selected signatory names', function () {
    $selections = [];
    foreach (array_keys(ProposalSignatory::roles()) as $index => $key) {
        $selections[$key] = ['id' => $index + 1, 'name' => 'Selected Signer '.($index + 1), 'position' => 'Selected Position'];
    }
    $draft = new ProposalDraft(['signatory_selections' => $selections]);
    $base = ['project_title' => 'Document test', 'project_leader' => 'Project Leader', 'planned_start' => '2026-01-01', 'planned_end' => '2026-12-31'];
    $documents = [
        'detailed_proposal' => app(DetailedProposalDocumentService::class)->generate(DetailedProposalData::fromValidated([...$base, ...$draft->signatoryFields('detailed_proposal')])),
        'work_plan' => app(WorkPlanDocumentService::class)->generate(WorkPlanData::fromValidated([...$base, 'total_duration_months' => 12, 'prepared_by' => 'Project Leader', 'entries' => [['objective' => 'Objective', 'expected_output' => 'Output', 'activity' => 'Activity', 'months' => [1]]], ...$draft->signatoryFields('work_plan')])),
        'line_item_budget' => app(LineItemBudgetDocumentService::class)->generate(LineItemBudgetData::fromValidated([...$base, 'amounts' => [], ...$draft->signatoryFields('line_item_budget')])),
        'gad_checklist' => app(GADChecklistDocumentService::class)->generate(GADChecklistData::fromValidated([...$base, ...$draft->signatoryFields('gad_checklist')])),
        'initial_screening_form' => app(InitialScreeningFormDocumentService::class)->generate([...$base, ...$draft->signatoryFields('initial_screening_form')]),
    ];
    foreach ($documents as $paper => $contents) {
        $path = tempnam(sys_get_temp_dir(), 'signatory-test-');
        try {
            file_put_contents($path, $contents);
            $zip = new ZipArchive;
            $zip->open($path);
            $xml = new DOMDocument;
            $xml->loadXML($zip->getFromName('word/document.xml'));
            $zip->close();
            foreach (array_keys(ProposalSignatory::FIELDS[$paper]) as $key) {
                expect(mb_strtoupper($xml->textContent))->toContain(mb_strtoupper($selections[$key]['name']));
            }
        } finally {
            unlink($path);
        }
    }
});
