<?php

use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\User;

test('sample cleanup removes all unchanged imported signatories', function () {
    $samples = [
        ['checked_verified_by_name', 'Dr. Maria Elena Santos', 'Director, Research Development and Innovation'],
        ['checked_verified_by_name', 'Dr. Victor A. Ramos', 'Research Center Director'],
        ['recommending_approval_name', 'Dr. Rafael D. Mendoza', 'Vice Chancellor for Research, Development and Extension'],
        ['recommending_approval_name', 'Dr. Celeste M. Flores', 'Vice President for Research and Innovation'],
        ['approved_by_name', 'Dr. Patricia Anne Villanueva', 'University President'],
        ['approved_by_name', 'Dr. Lorenzo B. Castillo', 'University Chancellor'],
        ['verified_by', 'Dr. Liza M. Navarro', 'Head, Research and Extension'],
        ['verified_by', 'Prof. Adrian C. Mercado', 'College Research Coordinator'],
        ['certified_by', 'Maria Teresa R. Cruz, CPA', 'University Accountant'],
        ['certified_by', 'Daniel P. Soriano, CPA', 'Chief Budget Officer'],
        ['verifier_name', 'Prof. Angela Mae de Leon', 'GAD Focal Person'],
        ['verifier_name', 'Dr. Irene C. Valdez', 'Chair, GAD Committee'],
        ['screening_head', 'Dr. Jose Miguel Aquino', 'Head, Research and Extension'],
        ['screening_head', 'Dr. Nina P. Robles', 'Research Office Head'],
        ['screening_center', 'Dr. Camille R. Bautista', 'Center Head'],
        ['screening_center', 'Engr. Marco Luis Herrera', 'Assistant Director, Research Center'],
        ['screening_verifier', 'Dr. Roberto S. Lim', 'Director of Research'],
        ['screening_verifier', 'Dr. Sofia T. Evangelista', 'Vice Chancellor for Research'],
    ];
    $people = collect($samples)->map(fn (array $sample) => ProposalSignatory::forceCreate([
        'role_key' => $sample[0], 'name' => $sample[1], 'position' => $sample[2],
        'active' => true, 'is_default' => false,
        'created_at' => '2026-09-09 14:53:16', 'updated_at' => '2026-09-09 14:53:16',
    ]));

    (require database_path('migrations/2026_10_12_224804_remove_unchanged_sample_proposal_signatories.php'))->up();

    foreach ($people as $person) {
        $this->assertModelMissing($person);
    }
});

test('sample cleanup preserves customized disabled default and unrelated entries', function () {
    $sample = [
        'role_key' => 'verified_by', 'name' => 'Dr. Liza M. Navarro', 'position' => 'Head, Research and Extension',
        'active' => true, 'is_default' => false,
        'created_at' => '2026-09-09 14:53:16', 'updated_at' => '2026-09-09 14:53:16',
    ];
    $people = collect([
        ['name' => 'Current Research Head'],
        ['position' => 'Head, Research Office'],
        ['role_key' => 'screening_head'],
        ['active' => false],
        ['is_default' => true],
        ['updated_at' => '2026-09-10 14:53:16'],
    ])->map(fn (array $changes) => ProposalSignatory::forceCreate([...$sample, ...$changes]));

    (require database_path('migrations/2026_10_12_224804_remove_unchanged_sample_proposal_signatories.php'))->up();

    foreach ($people as $person) {
        $this->assertModelExists($person);
        expect($person->fresh()->getAttributes())->toEqual($person->getAttributes());
    }
});

test('sample cleanup preserves proposal signatory snapshots and historical documents', function () {
    $person = ProposalSignatory::forceCreate([
        'role_key' => 'verified_by', 'name' => 'Dr. Liza M. Navarro', 'position' => 'Head, Research and Extension',
        'active' => true, 'is_default' => false,
        'created_at' => '2026-09-09 14:53:16', 'updated_at' => '2026-09-09 14:53:16',
    ]);
    $selections = ['verified_by' => ['id' => $person->id, 'name' => $person->name, 'position' => $person->position]];
    $draft = ProposalDraft::create([
        'user_id' => User::factory()->create()->id, 'project_title' => 'Existing proposal',
        'signatory_selections' => $selections,
    ]);
    $sourceData = ['verified_by' => 'Dr. Liza M. Navarro', 'verified_role' => 'Head, Research and Extension'];
    $paper = $draft->documents()->create([
        'document_type' => 'work_plan', 'position' => 0, 'file_path' => 'existing.pdf', 'source_data' => $sourceData,
    ]);
    $history = $draft->documentVersions()->create([
        'proposal_draft_document_id' => $paper->id, 'document_type' => 'work_plan', 'position' => 0,
        'version_number' => 1, 'action' => 'submitted', 'file_path' => 'existing.pdf', 'source_data' => $sourceData,
    ]);

    (require database_path('migrations/2026_10_12_224804_remove_unchanged_sample_proposal_signatories.php'))->up();

    $this->assertModelMissing($person);
    expect($draft->fresh()->signatory_selections)->toBe($selections)
        ->and($paper->fresh()->source_data)->toBe($sourceData)
        ->and($paper->fresh()->file_path)->toBe('existing.pdf')
        ->and($history->fresh()->source_data)->toBe($sourceData)
        ->and($history->fresh()->file_path)->toBe('existing.pdf');
});
