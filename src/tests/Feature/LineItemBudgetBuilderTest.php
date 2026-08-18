<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalDraftReadiness;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['faculty', 'research_head'] as $role) {
        Role::firstOrCreate(['name' => $role]);
    }

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Faculty Project Leader']);
    $this->faculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Open Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 100000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $call->id,
        'project_title' => 'Community Coastal Research',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => 'Faculty Project Leader',
    ]);
    $this->payload = fn (array $overrides = []): array => [
        'document_version' => 0,
        'leader_campus' => 'Pablo Borbon Campus',
        'leader_college' => 'College of Arts and Sciences',
        'staff' => [
            ['name' => 'Researcher One', 'campus' => 'Alangilan Campus', 'college' => 'College of Engineering'],
            ['name' => 'Researcher Two', 'campus' => '', 'college' => 'College of Informatics'],
        ],
        'amounts' => [
            'travelling_expenses' => '10000.00',
            'contingency' => '1000.00',
            'ict_equipment' => '3000.00',
        ],
        'custom_mooe_items' => [['particular' => 'Community consultation supplies', 'amount' => '5000.00']],
        'custom_co_items' => [['particular' => 'Field measurement device', 'amount' => '2000.00']],
        'level_of_call' => 'constituent_campus',
        'approval_body' => 'lrec',
        'resolution_number' => '1',
        'resolution_year' => '2026',
        'certified_by' => 'Maribel Santos',
        'certified_role' => 'Research Coordinator',
        ...$overrides,
    ];

    Storage::fake('local');
    $this->withoutVite();
});

test('the line item budget saves optional structured inputs and resumes them', function () {
    $payload = ($this->payload)([
        'mooe_total_override' => '17000.00',
        'co_total_override' => '6000.00',
        'project_total_override' => '23000.00',
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $payload)
        ->assertRedirect(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertSessionHas('success', 'Attachment B: Line-Item Budget saved.');

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect($document->completed_at)->not->toBeNull()
        ->and($document->source_data['staff'])->toHaveCount(2)
        ->and($document->source_data['amounts']['travelling_expenses'])->toBe('10000.00')
        ->and($document->source_data['project_total_override'])->toBe('23000.00')
        ->and($document->source_data['certified_by'])->toBe('Maribel Santos')
        ->and($document->source_data['certified_role'])->toBe('Research Coordinator')
        ->and($document->source_data)->not->toHaveKeys(['project_title', 'planned_start', 'planned_end', 'project_leader']);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('Researcher One')
        ->assertSee('Community consultation supplies')
        ->assertSee('Add another category or sub-category')
        ->assertSee('data-line-item-budget-custom-input', false)
        ->assertSee('Program Title stays empty')
        ->assertSee('ARASOF-Nasugbu')
        ->assertSee('value="CICS"', false)
        ->assertSee('value="CTE"', false)
        ->assertSee('value="CABEIHM"', false)
        ->assertSee('value="CCJE"', false)
        ->assertSee('value="CAS"', false)
        ->assertSee('value="CHS"', false)
        ->assertDontSee('College of Informatics and Computing Sciences')
        ->assertSee('>Project leader campus</label>', false)
        ->assertSee('>Project leader college</label>', false)
        ->assertDontSee('Project leader campus <span', false)
        ->assertDontSee('Project leader college <span', false)
        ->assertDontSee('Ctrl + S')
        ->assertSee('Exit editor')
        ->assertSee('#required-pdf-attachments', false)
        ->assertSee('Changes save automatically.')
        ->assertSee('data-line-item-budget-autosave="true"', false)
        ->assertSee('data-line-item-budget-autosave-form', false)
        ->assertDontSee('data-paper-save-exit', false)
        ->assertDontSee('Save and stay');

    $saveAndExitPayload = $payload;
    $saveAndExitPayload['document_version'] = 1;
    $saveAndExitPayload['exit_after_save'] = '1';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $saveAndExitPayload)
        ->assertRedirect(route('faculty.proposal-drafts.show', $this->draft))
        ->assertSessionHas('proposal_tab', 'attachments');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('activeProposalTab:', false)
        ->assertSee('attachments', false);
});

test('the line-item budget uses short profile defaults and omits an empty project staff row', function () {
    $this->faculty->update(['college' => User::COLLEGES['CICS']]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('ARASOF-Nasugbu')
        ->assertSee('CICS')
        ->assertSee('name="certified_by"', false)
        ->assertSee('name="certified_role"', false);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), ($this->payload)([
            'staff' => [],
        ]))
        ->assertOk()
        ->assertDontSee('Project Staff:');
});

test('matching over-budget papers show a shared warning and both need attention', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'source_data' => [
            'amounts' => ['travelling_expenses' => 100001],
        ],
        'completed_at' => now(),
        'lock_version' => 1,
    ]);
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'source_data' => [
            'items' => [[
                'category' => 'mooe',
                'account' => 'Travelling Expenses',
                'sub_account' => 'Local',
                'quantity' => 1,
                'unit_cost' => 100001,
            ]],
        ],
        'completed_at' => now(),
        'lock_version' => 1,
    ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee('Budget limit exceeded')
        ->assertSee('Submission blocked')
        ->assertSeeTextInOrder([
            'Attachment B: Line-Item Budget',
            'Needs attention',
            'Estimated Expense Breakdown',
            'Needs attention',
        ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('Budget limit exceeded')
        ->assertSee('Needs attention');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft))
        ->assertOk()
        ->assertSee('Budget limit exceeded')
        ->assertSee('Needs attention');

    $checklist = app(ProposalDraftReadiness::class)->checklist($this->draft->fresh());

    expect($checklist['line-item-budget']['status'])->toBe('Needs attention')
        ->and($checklist['expense-breakdown']['status'])->toBe('Needs attention');
});

test('the Line-Item Budget auto-save returns the current version without duplicating unchanged versions', function () {
    $this->draft->update([
        'duration_months' => null,
        'planned_start' => null,
        'planned_end' => null,
    ]);
    $payload = ($this->payload)(['save_as_draft' => true]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $payload, ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('document_version', 1)
        ->assertJsonPath('saved_as_draft', true);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
            ...$this->draft->documents()
                ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
                ->sole()
                ->source_data,
            'document_version' => 1,
            'save_as_draft' => true,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('document_version', 1);

    $document = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect($document->lock_version)->toBe(1)
        ->and($document->versions()->count())->toBe(1);
});

test('the line item budget pre-fills matching amounts from an expense breakdown draft', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'source_data' => [
            'items' => [
                [
                    'category' => 'mooe',
                    'account' => 'Communication Expenses',
                    'sub_account' => 'Telephone Expenses',
                    'quantity' => 12,
                    'unit_cost' => 300,
                ],
                [
                    'category' => 'mooe',
                    'account' => 'Professional Services',
                    'sub_account' => 'Other Professional Services',
                    'quantity' => 240,
                    'unit_cost' => 219.85,
                ],
                [
                    'category' => 'capital_outlay',
                    'account' => 'Machinery and Equipment Outlay',
                    'sub_account' => 'ICT Equipment',
                    'quantity' => 1,
                    'unit_cost' => 50000,
                ],
            ],
        ],
        'completed_at' => null,
        'lock_version' => 1,
    ]);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
            'document_version' => 0,
            'save_as_draft' => '1',
        ])
        ->assertRedirect(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft));

    $lineItemBudget = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole();

    expect($lineItemBudget->source_data['amounts'])
        ->toMatchArray([
            'telephone_expenses' => 3600.0,
            'other_professional_services' => 52764.0,
            'ict_equipment' => 50000.0,
        ]);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('Budget amounts synchronized');
});

test('the line item budget refreshes standard amounts after the expense breakdown changes', function () {
    $expenseBreakdown = $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'source_data' => [
            'items' => [[
                'category' => 'mooe',
                'account' => 'Communication Expenses',
                'sub_account' => 'Telephone Expenses',
                'quantity' => 12,
                'unit_cost' => 300,
            ]],
        ],
        'completed_at' => null,
        'lock_version' => 1,
    ]);
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'source_data' => [
            'amounts' => [
                'telephone_expenses' => 3600,
                'ict_equipment' => 50000,
            ],
        ],
        'completed_at' => null,
        'lock_version' => 1,
    ]);

    $expenseBreakdown->update([
        'source_data' => [
            'items' => [[
                'category' => 'mooe',
                'account' => 'Communication Expenses',
                'sub_account' => 'Telephone Expenses',
                'quantity' => 12,
                'unit_cost' => 500,
            ]],
        ],
    ]);

    $comparison = app(ProposalBudgetConsistency::class)->compare($this->draft->fresh());
    $capitalOutlay = collect($comparison['totals'])->firstWhere('key', 'co_total');
    $projectTotal = collect($comparison['totals'])->firstWhere('key', 'project_total');

    expect($comparison['consistent'])->toBeTrue()
        ->and($comparison['over_budget'])->toBeFalse()
        ->and($capitalOutlay['line_item_budget'])->toEqual(0.0)
        ->and($capitalOutlay['expense_breakdown'])->toEqual(0.0)
        ->and($projectTotal['line_item_budget'])->toEqual(6000.0)
        ->and($projectTotal['expense_breakdown'])->toEqual(6000.0);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertDontSee('Budget totals do not match')
        ->assertDontSee('Budget limit exceeded');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
            ...($this->payload)(),
            'document_version' => 1,
            'amounts' => [
                'telephone_expenses' => 3600,
                'ict_equipment' => 50000,
            ],
            'save_as_draft' => true,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('document_version', 2);

    $lineItemBudget = $this->draft->documents()
        ->where('document_type', ProposalVersionFile::TYPE_LINE_ITEM_BUDGET)
        ->sole()
        ->fresh();

    expect($lineItemBudget->source_data['amounts']['telephone_expenses'])->toEqual(6000.0)
        ->and($lineItemBudget->source_data['amounts']['ict_equipment'])->toBeNull();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.line-item-budget.edit', $this->draft))
        ->assertOk()
        ->assertSee('Budget amounts synchronized')
        ->assertSee('whenever this paper opens, previews, or saves.');
});

test('an empty saved expense breakdown clears stale line item amounts from the warning', function () {
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
        'position' => 0,
        'source_data' => [
            'amounts' => [
                'ict_equipment' => 61000,
            ],
        ],
        'completed_at' => null,
        'lock_version' => 1,
    ]);
    $this->draft->documents()->create([
        'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
        'position' => 0,
        'source_data' => [
            'items' => [],
        ],
        'completed_at' => null,
        'lock_version' => 1,
    ]);

    $comparison = app(ProposalBudgetConsistency::class)->compare($this->draft->fresh());
    $capitalOutlay = collect($comparison['totals'])->firstWhere('key', 'co_total');
    $projectTotal = collect($comparison['totals'])->firstWhere('key', 'project_total');

    expect($comparison['available'])->toBeTrue()
        ->and($comparison['consistent'])->toBeTrue()
        ->and($comparison['over_budget'])->toBeFalse()
        ->and($capitalOutlay['line_item_budget'])->toEqual(0.0)
        ->and($capitalOutlay['expense_breakdown'])->toEqual(0.0)
        ->and($projectTotal['line_item_budget'])->toEqual(0.0)
        ->and($projectTotal['expense_breakdown'])->toEqual(0.0);

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertDontSee('Budget totals do not match')
        ->assertDontSee('Budget limit exceeded')
        ->assertDontSee('Php 61,000.00');
});

test('empty optional fields are accepted while totals remain automatic', function () {
    $this->draft->update(['project_leader' => 'SHEENA LEI DELMO']);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), [])
        ->assertOk()
        ->assertSee('Community Coastal Research')
        ->assertSee('ARASOF-Nasugbu')
        ->assertSee('Sheena Lei Delmo')
        ->assertDontSee('SHEENA LEI DELMO')
        ->assertSee('0.00')
        ->assertDontSee('DJOANNA MARIE V. SALAC')
        ->assertDontSee('Head, Research');
});

test('the line-item preview remains available when shared project details are incomplete', function () {
    $this->draft->update([
        'duration_months' => null,
        'planned_start' => null,
        'planned_end' => null,
    ]);

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), [])
        ->assertOk();

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.download', $this->draft), [])
        ->assertSessionHasErrors();
});

test('the generated Line-Item Budget preserves the official structure and fills dynamic rows', function () {
    $this->draft->update(['project_leader' => 'SHEENA LEI DELMO']);
    $pdfConverter = new class implements DocumentPdfConverter
    {
        public string $sourceDocument = '';

        public function convertDocx(string $contents): string
        {
            $this->sourceDocument = $contents;

            return "%PDF-1.7\nGenerated Line-Item Budget";
        }

        public function convertXlsx(string $contents): string
        {
            return "%PDF-1.7\nGenerated spreadsheet PDF";
        }
    };
    app()->instance(DocumentPdfConverter::class, $pdfConverter);

    $response = $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.line-item-budget.download', $this->draft), ($this->payload)())
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('community-coastal-research-line-item-budget.pdf');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'line-budget-test-');
    file_put_contents($temporaryPath, $pdfConverter->sourceDocument);
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $documentXml = $archive->getFromName('word/document.xml');
        $footerXml = $archive->getFromName('word/footer1.xml');
        $settingsXml = $archive->getFromName('word/settings.xml');
        $document = new DOMDocument;
        $document->loadXML($documentXml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $rows = $xpath->query('//w:body/w:tbl[1]/w:tr');
        $tableGridWidths = [];

        foreach ($xpath->query('//w:body/w:tbl[1]/w:tblGrid/w:gridCol') as $gridColumn) {
            $tableGridWidths[] = (string) $xpath->evaluate('string(@w:w)', $gridColumn);
        }

        $rowText = fn (int $index): string => trim((string) $xpath->evaluate('string(.)', $rows->item($index)));
        $findRow = function (string $text) use ($rows, $xpath): DOMNode {
            foreach ($rows as $row) {
                if (str_contains((string) $xpath->evaluate('string(.)', $row), $text)) {
                    return $row;
                }
            }

            throw new RuntimeException("Missing row {$text}");
        };

        expect($rows->length)->toBe(48)
            ->and($tableGridWidths)->toBe(['461', '463', '625', '4168', '2176', '2313'])
            ->and($rowText(0))->toBe('Program Title:')
            ->and($rowText(1))->toContain('Community Coastal Research')
            ->and($xpath->evaluate('string((//w:body/w:tbl[1]/w:tr)[2]/w:tc[2]/w:p/w:pPr/w:jc/@w:val)'))->toBe('left')
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[2]/w:tc[2]//w:b')->length)->toBe(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[2]//w:b')->length)->toBeGreaterThan(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[3]//w:b')->length)->toBeGreaterThan(0)
            ->and($xpath->query('(//w:body/w:tbl[1]/w:tr)[3]/w:tc[4]//w:b')->length)->toBeGreaterThan(0)
            ->and($documentXml)->toContain('Sheena Lei Delmo')
            ->and($documentXml)->not->toContain('SHEENA LEI DELMO')
            ->and($documentXml)->toContain('Researcher One')
            ->and($documentXml)->toContain('Researcher Two')
            ->and($documentXml)->toContain('August 1, 2026 - July 31, 2027')
            ->and($documentXml)->toContain('Community consultation supplies')
            ->and($documentXml)->toContain('Field measurement device')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('Total for Maintenance'))))->toContain('16,000.00')
            ->and($xpath->evaluate('string(.//w:tc[1]//w:jc/@w:val)', $findRow('Total for Maintenance')))->toBe('right')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('Total for Capital'))))->toContain('5,000.00')
            ->and($xpath->evaluate('string(.//w:tc[1]//w:jc/@w:val)', $findRow('Total for Capital')))->toBe('right')
            ->and(trim((string) $xpath->evaluate('string(.)', $findRow('TOTAL PROJECT COST'))))->toContain('21,000.00')
            ->and($xpath->evaluate('string(.//w:tc[1]//w:jc/@w:val)', $findRow('TOTAL PROJECT COST')))->toBe('center')
            ->and($documentXml)->toContain('MARIBEL SANTOS')
            ->and($documentXml)->toContain('Research Coordinator')
            ->and($documentXml)->not->toContain('Vice President for Research, Development and Extension Services')
            ->and($documentXml)->not->toContain('Vice Chairperson, Research Council **')
            ->and($documentXml)->toContain('Approved by the Local Research Evaluation Committee as per LREC Resolution No. 1, S. 2026')
            ->and(substr_count($documentXml, 'w:default w:val="1"'))->toBe(1)
            ->and($footerXml)->toContain('Community Coastal Research')
            ->and($settingsXml)->toContain('updateFields');

    } finally {
        $archive->close();

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('contingency and the research call budget ceiling are enforced when finalizing, not previewing', function () {
    $contingencyPayload = ($this->payload)([
        'amounts' => ['travelling_expenses' => 1000, 'contingency' => 1000],
    ]);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), $contingencyPayload)
        ->assertOk()
        ->assertSee('LINE-ITEM BUDGET');

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $contingencyPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amounts.contingency');

    $overBudgetPayload = ($this->payload)(['project_total_override' => 100001]);

    $this->actingAs($this->faculty)
        ->postJson(route('faculty.proposal-drafts.line-item-budget.preview', $this->draft), $overBudgetPayload)
        ->assertOk()
        ->assertSee('TOTAL PROJECT COST');

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), $overBudgetPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('project_total_override');

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), [
            ...$overBudgetPayload,
            'save_as_draft' => true,
        ])
        ->assertOk()
        ->assertJsonPath('saved_as_draft', true);

    $this->actingAs($this->faculty)
        ->putJson(route('faculty.proposal-drafts.line-item-budget.update', $this->draft), ($this->payload)([
            'project_total_override' => 22000,
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'project_total_override' => 'The Total Project Cost must equal MOOE plus Capital Outlay (Php 21,000.00).',
        ]);
});
