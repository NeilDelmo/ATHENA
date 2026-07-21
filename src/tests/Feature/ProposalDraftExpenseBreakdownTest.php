<?php

use App\Models\ProposalDraft;
use App\Models\ProposalVersionFile;
use App\Models\ResearchCall;
use App\Models\User;
use App\Services\ExpenseBreakdownDocumentService;
use App\Support\ExpenseBreakdownData;
use App\Support\ProposalDraftReadiness;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'faculty']);
    Role::firstOrCreate(['name' => 'research_head']);

    $head = User::factory()->create();
    $head->assignRole('research_head');
    $this->faculty = User::factory()->create(['name' => 'Faculty Owner']);
    $this->faculty->assignRole('faculty');
    $this->otherFaculty = User::factory()->create();
    $this->otherFaculty->assignRole('faculty');
    $call = ResearchCall::create([
        'title' => 'Open Institutional Research Call',
        'academic_year' => '2026-2027',
        'opens_at' => now()->subDay(),
        'closes_at' => now()->addMonth(),
        'max_active_research_per_faculty' => 2,
        'maximum_budget' => 500000,
        'status' => 'open',
        'created_by' => $head->id,
    ]);
    $this->draft = ProposalDraft::create([
        'user_id' => $this->faculty->id,
        'research_call_id' => $call->id,
        'project_title' => 'Online Research Journal',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
        'project_leader' => 'Faculty Owner',
    ]);
    $this->payload = [
        'document_version' => 0,
        'items' => [
            [
                'category' => 'mooe',
                'account' => 'Communication Expenses',
                'sub_account' => 'Telephone Expenses',
                'particulars' => 'Prepaid Card',
                'details' => 'Prepaid Call Card',
                'purpose' => 'For communication purposes',
                'unit' => 'pc',
                'quantity' => 12,
                'unit_cost' => 300,
            ],
            [
                'category' => 'mooe',
                'account' => 'Professional Services',
                'sub_account' => 'Other Professional Services',
                'particulars' => 'Back End Developer',
                'details' => 'Back End Developer',
                'purpose' => 'Responsible for system development',
                'unit' => 'hours',
                'quantity' => 240,
                'unit_cost' => 219.85,
            ],
            [
                'category' => 'capital_outlay',
                'account' => 'Machinery and Equipment Outlay',
                'sub_account' => 'ICT Equipment',
                'particulars' => 'Workstation',
                'details' => 'Development workstation',
                'purpose' => 'For application development and testing',
                'unit' => 'unit',
                'quantity' => 1,
                'unit_cost' => 50000,
            ],
        ],
    ];

    $this->withoutVite();
});

test('the estimated expense paper opens as a structured editor instead of a PDF uploader', function () {
    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft))
        ->assertOk()
        ->assertSee('Expense items')
        ->assertSee('Descriptions / Specifications / Details')
        ->assertSee('Purpose in the project')
        ->assertSee('Unit Cost (Php)')
        ->assertSee('Select an official account')
        ->assertSee('Preview paper')
        ->assertSee('Download Excel file')
        ->assertDontSee('Choose completed PDF');

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.show', $this->draft))
        ->assertOk()
        ->assertSee(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft), false)
        ->assertDontSee('Upload PDF');
});

test('expense items are validated saved resumed and marked ready', function () {
    $invalid = $this->payload;
    unset($invalid['items'][0]['purpose']);

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.expense-breakdown.update', $this->draft), $invalid)
        ->assertSessionHasErrors('items.0.purpose');

    $invalidGrouping = $this->payload;
    $invalidGrouping['items'][0]['sub_account'] = 'ICT Equipment';

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.expense-breakdown.update', $this->draft), $invalidGrouping)
        ->assertSessionHasErrors('items.0');

    $this->actingAs($this->faculty)
        ->put(route('faculty.proposal-drafts.expense-breakdown.update', $this->draft), $this->payload)
        ->assertRedirect(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft))
        ->assertSessionHas('success', 'Estimated Expense Breakdown saved.');

    $document = $this->draft->documents()->sole();

    expect($document->document_type)->toBe(ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN)
        ->and($document->source_data['items'])->toHaveCount(3)
        ->and($document->source_data['items'][1]['unit_cost'])->toBe(219.85)
        ->and($document->file_path)->toBeNull()
        ->and($document->completed_at)->not->toBeNull()
        ->and(app(ProposalDraftReadiness::class)->checklist($this->draft->fresh())['expense-breakdown']['complete'])->toBeTrue();

    $this->actingAs($this->faculty)
        ->get(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft))
        ->assertOk()
        ->assertSee('Back End Developer')
        ->assertSee('Professional Services');
});

test('the preview follows the supplied official table and calculates grouped totals', function () {
    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.expense-breakdown.preview', $this->draft), $this->payload)
        ->assertOk()
        ->assertSee('Estimated Breakdown and Details of Expenses')
        ->assertSee('Project Title:')
        ->assertSee('Online Research Journal')
        ->assertSee('Descriptions/Specifications/Details')
        ->assertSee('Total for Telephone Expenses')
        ->assertSee('TOTAL MOOE:')
        ->assertSee('TOTAL CAPITAL OUTLAY:')
        ->assertSee('TOTAL MOOE and CAPITAL OUTLAY:')
        ->assertSee('106,364.00');
});

test('the generated Excel file preserves the supplied workbook layout styles and formulas', function () {
    $validated = [
        'project_title' => $this->draft->project_title,
        'items' => $this->payload['items'],
    ];
    $contents = app(ExpenseBreakdownDocumentService::class)
        ->generate(ExpenseBreakdownData::fromValidated($validated));
    $temporaryPath = tempnam(sys_get_temp_dir(), 'expense-breakdown-test-');

    expect($contents)->toStartWith('PK');
    file_put_contents($temporaryPath, $contents);
    $archive = new ZipArchive;

    try {
        expect($archive->open($temporaryPath))->toBeTrue();
        $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $stylesXml = $archive->getFromName('xl/styles.xml');
        $worksheetDocument = new DOMDocument;
        $workbookDocument = new DOMDocument;

        expect($worksheetDocument->loadXML($worksheetXml))->toBeTrue()
            ->and($workbookDocument->loadXML($workbookXml))->toBeTrue();

        expect($worksheetXml)
            ->toContain('paperSize="14"')
            ->toContain('orientation="portrait"')
            ->toContain('Descriptions/ Specifications/ Details')
            ->toContain('Total for Telephone Expenses')
            ->toContain('TOTAL MOOE and CAPITAL OUTLAY:')
            ->toContain('<f>G')
            ->toContain('*H')
            ->toContain('<v>106364</v>');
        expect($workbookXml)
            ->toContain('Breakdown!$A$1:$I$')
            ->toContain('fullCalcOnLoad="1"');
        expect($stylesXml)
            ->toContain('FFC9DAF8')
            ->toContain('FFFFF2CC')
            ->toContain('FFFFD966')
            ->toContain('FFB6D7A8');
        expect($archive->locateName('word/document.xml'))->toBeFalse();
        expect($archive->locateName('xl/calcChain.xml'))->toBeFalse();
    } finally {
        $archive->close();
        unlink($temporaryPath);
    }

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.expense-breakdown.download', $this->draft), $this->payload)
        ->assertDownload('online-research-journal-estimated-expense-breakdown.xlsx');
});

test('contingency uses the official single-amount workbook row', function () {
    $payload = [
        'items' => [[
            'category' => 'mooe',
            'account' => 'Contingency',
            'sub_account' => 'none',
            'particulars' => 'N/A',
            'details' => 'N/A',
            'purpose' => 'For unexpected/unforeseen expenses',
            'unit' => 'N/A',
            'quantity' => 1,
            'unit_cost' => 2500,
        ]],
    ];

    $this->actingAs($this->faculty)
        ->post(route('faculty.proposal-drafts.expense-breakdown.preview', $this->draft), $payload)
        ->assertOk()
        ->assertSee('For unexpected/unforeseen expenses')
        ->assertSee('2,500.00');

    $expenseBreakdown = ExpenseBreakdownData::fromValidated([
        'project_title' => $this->draft->project_title,
        ...$payload,
    ]);

    expect($expenseBreakdown['items'][0])
        ->quantity->toBe(1.0)
        ->total_cost->toBe(2500.0)
        ->is_contingency->toBeTrue();
});

test('another faculty member cannot access expense breakdown endpoints', function () {
    foreach ([
        fn () => $this->get(route('faculty.proposal-drafts.expense-breakdown.edit', $this->draft)),
        fn () => $this->put(route('faculty.proposal-drafts.expense-breakdown.update', $this->draft), $this->payload),
        fn () => $this->post(route('faculty.proposal-drafts.expense-breakdown.preview', $this->draft), $this->payload),
        fn () => $this->post(route('faculty.proposal-drafts.expense-breakdown.download', $this->draft), $this->payload),
    ] as $request) {
        $this->actingAs($this->otherFaculty);
        $request()->assertForbidden();
    }
});
