<?php

use App\Models\ProposalDraft;
use App\Models\ProposalSignatory;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CommentResponseFormDocumentService;
use App\Services\DetailedProposalDocumentService;
use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;
use App\Services\WorkPlanDocumentService;
use App\Support\DetailedProposalData;
use App\Support\GADChecklistData;
use App\Support\LineItemBudgetData;
use App\Support\ProposalDraftReadiness;
use App\Support\TerminalReportData;
use App\Support\WorkPlanData;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Role;

test('every Research Head and VCRDES signature uses the default without a directory selection', function () {
    $draft = new ProposalDraft(['signatory_selections' => [
        'checked_verified_by_name' => ['name' => 'Previously selected head', 'position' => 'Head'],
        'recommending_approval_name' => ['name' => 'Previously selected VCRDES', 'position' => 'VCRDES'],
    ]]);
    foreach (ProposalSignatory::FIELDS as $paper => $fields) {
        foreach (array_intersect_key(ProposalSignatory::defaultSelections(), $fields) as $key => $default) {
            expect($draft->signatoryFields($paper)[$key])->toBe($default['name']);
        }
    }
    expect(TerminalReportData::defaultSignatoryNames())->toBe([
        'reviewed_head' => 'Asst. Prof. DJOANNA MARIE V. SALAC',
        'reviewed_center' => 'Dr. CRISTINA AMOR ROSALES',
        'verified_chancellor' => 'Dr. FROILAN G. DESTREZA',
        'verified_director' => 'Dr. ROSENDA A. BRONCE',
        'approved_by' => 'Assoc. Prof. ALBERTSON D. AMANTE',
    ])
        ->and($draft->signatoryFields('detailed_proposal')['approved_by_name'])->toBe('Assoc. Prof. ALBERTSON D. AMANTE')
        ->and($draft->resolvedSignatorySelections()['approved_by_name']['position'])->toBe('Vice President for Research Development, and Extension Services')
        ->and($draft->resolvedSignatorySelections()['checked_verified_by_name']['position'])->toBe('Head, Research Office')
        ->and($draft->signatoryFields('line_item_budget'))->toBe([
            'certified_by' => 'Dr. ENRICO M. DALANGIN',
            'certified_role' => "Chancellor\nVice Chairperson, LREC",
        ])
        ->and($draft->signatoryFields('gad_checklist'))->toBe([
            'verifier_name' => 'Ms. ELLAINE G. LID-AYAN',
            'verifier_role' => 'GAD, Head Secretariat',
        ]);
});

test('proposal previews and generated documents use the institutional default signatories and roles', function () {
    $this->withoutVite();
    $draft = new ProposalDraft;
    $base = ['project_title' => 'Default signatories', 'project_leader' => 'Project Leader'];
    $papers = [
        [
            DetailedProposalData::fromValidated([...$base, ...$draft->signatoryFields('detailed_proposal')]),
            DetailedProposalDocumentService::class,
            'faculty.detailed-proposals.preview', 'detailedProposal',
            ['Asst. Prof. DJOANNA MARIE V. SALAC', 'Head, Research Office', 'Dr. FROILAN G. DESTREZA', 'Vice Chancellor for Research Development and Extension Services', 'Assoc. Prof. ALBERTSON D. AMANTE', 'Vice President for Research Development, and Extension Services'],
        ],
        [
            WorkPlanData::fromValidated([...$base, 'total_duration_months' => 12, 'prepared_by' => 'Project Leader', 'entries' => [['objective' => 'Objective', 'expected_output' => 'Output', 'activity' => 'Activity', 'months' => [1]]], ...$draft->signatoryFields('work_plan')]),
            WorkPlanDocumentService::class,
            'faculty.work-plans.preview', 'workPlan',
            ['Asst. Prof. DJOANNA MARIE V. SALAC', 'Head, Research'],
        ],
        [
            LineItemBudgetData::fromValidated([...$base, 'certified_by' => '', 'certified_role' => '']),
            LineItemBudgetDocumentService::class,
            'faculty.line-item-budgets.preview', 'lineItemBudget',
            ['Dr. ENRICO M. DALANGIN', 'Chancellor', 'Vice Chairperson, LREC'],
        ],
        [
            GADChecklistData::fromValidated([...$base, ...$draft->signatoryFields('gad_checklist')]),
            GADChecklistDocumentService::class,
            'faculty.gad-checklist.preview', 'gadChecklist',
            ['Ms. ELLAINE G. LID-AYAN', 'GAD, Head Secretariat'],
        ],
    ];

    foreach ($papers as [$data, $service, $view, $variable, $expected]) {
        $preview = $this->view($view, [$variable => $data]);
        $contents = app($service)->generate($data);
        $path = tempnam(sys_get_temp_dir(), 'default-signatories-');
        try {
            file_put_contents($path, $contents);
            $zip = new ZipArchive;
            expect($zip->open($path))->toBeTrue();
            $document = new DOMDocument;
            $document->loadXML($zip->getFromName('word/document.xml'));
            $zip->close();
            foreach ($expected as $text) {
                $preview->assertSee($text);
                expect($document->textContent)->toContain($text);
            }
            if ($variable === 'lineItemBudget') {
                $preview->assertSee('<p>Chancellor</p><p>Vice Chairperson, LREC</p>', false);
            }
        } finally {
            unlink($path);
        }
    }
});

test('comment and screening documents fill missing names and keep the roles printed on their forms', function () {
    $draft = new ProposalDraft;
    expect($draft->resolvedSignatorySelections()['comment_response_head']['position'])->toBe("Research Head/ RDES Head\nMember, LREC")
        ->and($draft->resolvedSignatorySelections()['comment_response_vice_chancellor']['position'])->toBe("Vice Chancellor for Research, Development and Extension Services\nMember, LREC")
        ->and($draft->resolvedSignatorySelections()['screening_head']['position'])->toBe('Head, Research/ Head, Research and Extension')
        ->and($draft->resolvedSignatorySelections()['screening_center'])->toMatchArray([
            'name' => 'Dr. CRISTINA AMOR ROSALES', 'position' => 'Center Head/ Assistant Director for Research',
        ])
        ->and($draft->resolvedSignatorySelections()['screening_verifier']['position'])->toBe('Director, Research/ Vice Chancellor for RDES');
    $base = ['project_title' => 'Default review signatories', 'project_leader' => 'Project Leader', 'staff' => [], 'leader_campus' => '', 'leader_college' => '', 'leader_department' => ''];
    foreach ([
        [CommentResponseFormDocumentService::class, ['Asst. Prof. DJOANNA MARIE V. SALAC', 'Dr. FROILAN G. DESTREZA', 'Research Head/ RDES Head', 'Member, LREC']],
        [InitialScreeningFormDocumentService::class, ['ASST. PROF. DJOANNA MARIE V. SALAC', 'DR. CRISTINA AMOR ROSALES', 'DR. FROILAN G. DESTREZA', 'Head, Research/ Head, Research and Extension', 'Center Head/ Assistant Director for Research', 'Director, Research/ Vice Chancellor for RDES']],
    ] as [$service, $expected]) {
        $path = tempnam(sys_get_temp_dir(), 'review-signatories-');
        try {
            file_put_contents($path, app($service)->generate($base));
            $zip = new ZipArchive;
            expect($zip->open($path))->toBeTrue();
            $document = new DOMDocument;
            $document->loadXML($zip->getFromName('word/document.xml'));
            $zip->close();
            foreach ($expected as $text) {
                expect($document->textContent)->toContain($text);
            }
        } finally {
            unlink($path);
        }
    }
});

test('notice to proceed defaults include both institutional officers and their committee roles', function () {
    $this->withoutVite();
    $topic = TopicProposal::create([
        'user_id' => User::factory()->create()->id,
        'title' => 'Default notice signatories',
        'estimated_duration_months' => 12,
        'estimated_budget' => 50000,
    ]);
    $dataService = app(NoticeToProceedDataService::class);
    $data = $dataService->defaults($topic);
    $signatories = [
        'issuing_officer_name' => 'Dr. FROILAN G. DESTREZA',
        'issuing_officer_title' => 'Vice Chancellor for Research Development and Extension Services',
        'issuing_officer_committee_role' => 'Member, Local Research Evaluation Committee',
        'verifying_officer_name' => 'Assoc. Prof. ALBERTSON D. AMANTE',
        'verifying_officer_title' => 'Vice President for Research Development, and Extension Services',
        'verifying_officer_committee_role' => 'Chairperson, Local Research Evaluation Committee',
    ];
    expect($data)->toMatchArray($signatories);
    $contents = app(NoticeToProceedDocumentService::class)->generate($dataService->documentValues([
        ...$data, 'approved_start_date' => '2026-01-01', 'approved_end_date' => '2026-12-31',
    ]));
    $path = tempnam(sys_get_temp_dir(), 'notice-default-signatories-');
    try {
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($zip->getFromName('word/document.xml'));
        $zip->close();
        foreach ($signatories as $text) {
            expect($document->textContent)->toContain($text);
        }
    } finally {
        unlink($path);
    }
});

test('detailed proposal titles keep their capitalization in previews and Word even for legacy uppercase names', function () {
    $this->withoutVite();
    $names = [
        'checked_verified_by_name' => 'Asst. Prof. DJOANNA MARIE V. SALAC',
        'recommending_approval_name' => 'Dr. FROILAN G. DESTREZA',
        'approved_by_name' => 'Assoc. Prof. ALBERTSON D. AMANTE',
    ];
    $legacyNames = array_map('mb_strtoupper', $names);
    $proposal = DetailedProposalData::fromValidated(['project_title' => 'Signature capitalization', ...$legacyNames]);

    expect($proposal)->toMatchArray($names);
    $preview = $this->view('faculty.detailed-proposals.preview', ['detailedProposal' => [...$proposal, ...$legacyNames]]);
    foreach ($names as $key => $name) {
        $preview->assertSee($name)->assertDontSee($legacyNames[$key]);
    }

    $contents = app(DetailedProposalDocumentService::class)->generate([...$proposal, ...$legacyNames]);
    $path = tempnam(sys_get_temp_dir(), 'signatory-case-test-');
    try {
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($zip->getFromName('word/document.xml'));
        $zip->close();
        foreach ($names as $key => $name) {
            expect($document->textContent)->toContain($name)->not->toContain($legacyNames[$key]);
        }
    } finally {
        unlink($path);
    }
});

test('head manages defaults and faculty cannot select names per proposal', function () {
    $this->withoutVite();
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Signatory test']);
    $input = ['role_key' => 'certified_by', 'name' => 'Budget Officer', 'position' => 'Accountant', 'active' => 1, 'is_default' => 1];
    $this->actingAs($faculty)->post(route('signatories.store'), $input)->assertForbidden();
    $this->actingAs($head)->post(route('signatories.store'), $input)->assertSessionHasNoErrors();
    $person = ProposalSignatory::query()->sole();
    $this->get(route('signatories.index'))->assertOk()->assertSee('Budget Officer')->assertSee('Use as default for this role');
    $this->actingAs($faculty)->get(route('signatories.edit', $draft))->assertForbidden();
    $this->put(route('signatories.select', $draft), ['lock_version' => 0, 'signatories' => ['certified_by' => $person->id]])->assertForbidden();
    expect($draft->fresh()->signatoryFields('line_item_budget'))->toBe(['certified_by' => 'Budget Officer', 'certified_role' => 'Accountant']);
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
        ->assertSee('name="role" value="verified_by"', false)
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

test('proposal papers show automatic names without a selection link', function () {
    $draft = new ProposalDraft;
    foreach (array_keys(ProposalSignatory::FIELDS) as $paper) {
        $html = Blade::render('<x-proposal-signatory-summary :proposal-draft="$draft" :paper="$paper" />', compact('draft', 'paper'));
        expect($html)->toContain('filled automatically')->not->toContain('Other signatories', 'signatories.edit');
    }
});
test('renamed office defaults reach existing editable proposals automatically', function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $head = User::factory()->create();
    $head->assignRole('research_head');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Renamed default', 'signatory_selections' => ['approved_by_name' => ['name' => 'Old faculty choice', 'position' => 'Approver']]]);
    $person = ProposalSignatory::create(['role_key' => 'approved_by_name', 'name' => 'Office Default', 'position' => 'Final Approver', 'active' => true, 'is_default' => true]);
    $this->actingAs($head)->patch(route('signatories.update', $person), ['role_key' => 'approved_by_name', 'name' => 'New Office Default', 'position' => 'Final Approver', 'active' => 1, 'is_default' => 1])->assertSessionHasNoErrors();
    expect($draft->fresh()->signatoryFields('detailed_proposal')['approved_by_name'])->toBe('New Office Default');
    $this->actingAs($faculty)->get(route('faculty.proposal-drafts.detailed-proposal.edit', $draft))->assertOk()->assertSee('New Office Default')->assertDontSee('Other signatories');
});
test('all six generated papers contain the office-managed default signatory names', function () {
    $selections = [];
    foreach (array_keys(ProposalSignatory::roles()) as $index => $key) {
        $signatory = ProposalSignatory::create(['role_key' => $key, 'name' => 'Office Signer '.($index + 1), 'position' => 'Office Position', 'active' => true, 'is_default' => true]);
        $selections[$key] = ['id' => $signatory->id, 'name' => $signatory->name, 'position' => $signatory->position];
    }
    $draft = ProposalDraft::create(['user_id' => User::factory()->create()->id, 'project_title' => 'Office signature test']);
    $base = ['project_title' => 'Document test', 'project_leader' => 'Project Leader', 'planned_start' => '2026-01-01', 'planned_end' => '2026-12-31'];
    $documents = [
        'detailed_proposal' => app(DetailedProposalDocumentService::class)->generate(DetailedProposalData::fromValidated([...$base, ...$draft->signatoryFields('detailed_proposal')])),
        'work_plan' => app(WorkPlanDocumentService::class)->generate(WorkPlanData::fromValidated([...$base, 'total_duration_months' => 12, 'prepared_by' => 'Project Leader', 'entries' => [['objective' => 'Objective', 'expected_output' => 'Output', 'activity' => 'Activity', 'months' => [1]]], ...$draft->signatoryFields('work_plan')])),
        'line_item_budget' => app(LineItemBudgetDocumentService::class)->generate(LineItemBudgetData::fromValidated([...$base, 'amounts' => [], ...$draft->signatoryFields('line_item_budget')])),
        'gad_checklist' => app(GADChecklistDocumentService::class)->generate(GADChecklistData::fromValidated([...$base, ...$draft->signatoryFields('gad_checklist')])),
        'initial_screening_form' => app(InitialScreeningFormDocumentService::class)->generate([...$base, ...$draft->signatoryFields('initial_screening_form')]),
        'comment_response_form' => app(CommentResponseFormDocumentService::class)->generate([...$base, 'staff' => [], ...$draft->signatoryFields('comment_response_form')]),
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
                expect(mb_strtoupper($xml->textContent))->toContain(mb_strtoupper($draft->resolvedSignatorySelections()[$key]['name']));
            }
        } finally {
            unlink($path);
        }
    }
});

test('comment form signatories are automatic and cannot be replaced by faculty', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $draft = ProposalDraft::create(['user_id' => $faculty->id, 'project_title' => 'Default comments signatories']);
    $this->actingAs($faculty)->put(route('signatories.select', $draft), ['lock_version' => 0, 'return_paper' => 'comment_response_form', 'signatories' => ['comment_response_head' => 999999]])->assertForbidden();
    expect($draft->fresh()->signatoryFields('comment_response_form'))->toBe(['comment_response_head' => 'Asst. Prof. DJOANNA MARIE V. SALAC', 'comment_response_vice_chancellor' => 'Dr. FROILAN G. DESTREZA']);
    expect(app(ProposalDraftReadiness::class)->commentResponseSignatoriesAreComplete($draft))->toBeTrue();
});
test('legacy comments form signatory links return faculty to revision feedback', function () {
    $this->withoutVite();
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');
    $topic = TopicProposal::create(['user_id' => $faculty->id, 'title' => 'Comments Form Revision', 'status' => 'revision_requested']);
    $this->actingAs($faculty)->get(route('faculty.proposal-drafts.revision', [$topic, 'signatories' => 'comment_response_form']))->assertRedirect(route('faculty.topics.revision', $topic).'#revision-feedback');
    $other = User::factory()->create();
    $other->assignRole('faculty');
    $this->actingAs($other)->get(route('faculty.proposal-drafts.revision', [$topic, 'signatories' => 'comment_response_form']))->assertForbidden();
});
