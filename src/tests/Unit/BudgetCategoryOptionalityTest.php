<?php

use App\Models\ProposalDraft;
use App\Models\ProposalDraftDocument;
use App\Models\ProposalVersionFile;
use App\Services\ExpenseBreakdownDocumentService;
use App\Services\LineItemBudgetDocumentService;
use App\Support\ExpenseBreakdownData;
use App\Support\ExpenseBreakdownRules;
use App\Support\LineItemBudgetData;
use App\Support\LineItemBudgetRules;
use App\Support\ProposalBudgetConsistency;
use App\Support\ProposalDraftReadiness;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->project = [
        'project_title' => 'Optional Budget Categories',
        'project_leader' => 'Faculty Researcher',
        'duration_months' => 12,
        'planned_start' => '2026-08-01',
        'planned_end' => '2027-07-31',
    ];
    $this->items = [
        'mooe' => [
            'category' => 'mooe',
            'account' => 'Communication Expenses',
            'sub_account' => 'Telephone Expenses',
            'particulars' => 'Mobile data',
            'details' => 'Monthly data plan',
            'purpose' => 'Coordinate fieldwork',
            'unit' => 'month',
            'quantity' => 12,
            'unit_cost' => 300,
        ],
        'capital_outlay' => [
            'category' => 'capital_outlay',
            'account' => 'Machinery and Equipment Outlay',
            'sub_account' => 'ICT Equipment',
            'particulars' => 'Workstation',
            'details' => 'Research workstation',
            'purpose' => 'Analyze research data',
            'unit' => 'unit',
            'quantity' => 1,
            'unit_cost' => 50000,
        ],
    ];
});

test('either budget category or both pass completion validation with matching totals', function (array $categories, float $mooe, float $capitalOutlay) {
    $items = array_values(array_intersect_key($this->items, array_flip($categories)));
    $expenseValidator = Validator::make([
        ...$this->project,
        'items' => $items,
    ], ExpenseBreakdownRules::rules());
    $expenseValidator->after(ExpenseBreakdownRules::afterCallbacks(150000));

    expect($expenseValidator->passes())->toBeTrue();

    $expense = ExpenseBreakdownData::fromValidated($expenseValidator->validated());
    $lineItemValidator = Validator::make([
        ...$this->project,
        'amounts' => LineItemBudgetData::synchronizedAmountsFromExpenseBreakdown($items),
    ], LineItemBudgetRules::rules());
    $lineItemValidator->after(LineItemBudgetRules::afterCallbacks(150000));

    expect($lineItemValidator->passes())->toBeTrue();

    $budget = LineItemBudgetData::fromValidated($lineItemValidator->validated());

    expect($budget['mooe_total'])->toEqual($mooe)
        ->and($budget['co_total'])->toEqual($capitalOutlay)
        ->and($budget['project_total'])->toEqual($mooe + $capitalOutlay)
        ->and(collect($expense['sections'])->pluck('total', 'key')->all())->toBe([
            'mooe' => $mooe,
            'capital_outlay' => $capitalOutlay,
        ])
        ->and($expense['grand_total'])->toEqual($budget['project_total']);
})->with([
    'MOOE only' => [['mooe'], 3600.0, 0.0],
    'Capital Outlay only' => [['capital_outlay'], 0.0, 50000.0],
    'both categories' => [['mooe', 'capital_outlay'], 3600.0, 50000.0],
]);

test('removing either category clears its synchronized amounts without blocking budget readiness', function (string $category, string $unusedKey) {
    $items = [$this->items[$category]];
    $source = LineItemBudgetData::synchronizeSourceWithExpenseBreakdown([
        'amounts' => ['telephone_expenses' => 3600, 'ict_equipment' => 50000],
    ], $items);
    $draft = new ProposalDraft($this->project);
    $draft->setRelation('researchCall', null);
    $draft->setRelation('documents', new Collection([
        new ProposalDraftDocument([
            'document_type' => ProposalVersionFile::TYPE_LINE_ITEM_BUDGET,
            'source_data' => $source,
            'completed_at' => now(),
        ]),
        new ProposalDraftDocument([
            'document_type' => ProposalVersionFile::TYPE_EXPENSE_BREAKDOWN,
            'source_data' => ['items' => $items],
            'completed_at' => now(),
        ]),
    ]));

    $comparison = app(ProposalBudgetConsistency::class)->compare($draft);
    $checklist = app(ProposalDraftReadiness::class)->checklist($draft);

    expect($source['amounts'][$unusedKey])->toBeNull()
        ->and($comparison['consistent'])->toBeTrue()
        ->and($comparison['mismatches'])->toBeEmpty()
        ->and($checklist['line-item-budget']['status'])->toBe('Complete')
        ->and($checklist['expense-breakdown']['status'])->toBe('Complete');
})->with([
    'remove Capital Outlay' => ['mooe', 'ict_equipment'],
    'remove MOOE' => ['capital_outlay', 'telephone_expenses'],
]);

test('optional categories still require complete valid expense items within the budget ceiling', function (string $category) {
    $item = $this->items[$category];

    foreach ([
        'items' => [],
        'items.0.purpose' => [array_replace($item, ['purpose' => null])],
        'items.0.quantity' => [array_replace($item, ['quantity' => 0])],
        'items.0.unit_cost' => [array_replace($item, ['unit_cost' => -1])],
        'items.0' => [array_replace($item, ['category' => $category === 'mooe' ? 'capital_outlay' : 'mooe'])],
    ] as $error => $items) {
        $validator = Validator::make([...$this->project, 'items' => $items], ExpenseBreakdownRules::rules());

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has($error))->toBeTrue();
    }

    $validator = Validator::make([...$this->project, 'items' => [$item]], ExpenseBreakdownRules::rules());
    $validator->after(ExpenseBreakdownRules::afterCallbacks(1000));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('items'))->toBeTrue();

    $draftValidator = Validator::make(['items' => []], ExpenseBreakdownRules::rules(allowDraft: true));

    expect($draftValidator->passes())->toBeTrue();
})->with(['mooe', 'capital_outlay']);

test('single category previews and generated documents retain zero totals for the unused category', function (string $category, string $unusedLabel, string $unusedBudgetLabel) {
    $items = [$this->items[$category]];
    $expense = ExpenseBreakdownData::fromValidated([...$this->project, 'items' => $items]);
    $budget = LineItemBudgetData::fromValidated([
        ...$this->project,
        'amounts' => LineItemBudgetData::synchronizedAmountsFromExpenseBreakdown($items),
    ]);

    $this->view('faculty.expense-breakdowns.preview', ['expenseBreakdown' => $expense])
        ->assertSee($unusedLabel)
        ->assertSee('0.00')
        ->assertSee(number_format($expense['grand_total'], 2));
    $this->view('faculty.line-item-budgets.preview', ['lineItemBudget' => $budget])
        ->assertSee($unusedBudgetLabel)
        ->assertSee('0.00')
        ->assertSee(number_format($budget['project_total'], 2));

    foreach ([
        'xlsx' => app(ExpenseBreakdownDocumentService::class)->generate($expense),
        'docx' => app(LineItemBudgetDocumentService::class)->generate($budget),
    ] as $format => $contents) {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'optional-budget-test-');
        file_put_contents($temporaryPath, $contents);
        $archive = new ZipArchive;

        try {
            expect($archive->open($temporaryPath))->toBeTrue();
            $document = new DOMDocument;
            $entry = $format === 'xlsx' ? 'xl/worksheets/sheet1.xml' : 'word/document.xml';
            expect($document->loadXML($archive->getFromName($entry), LIBXML_NONET))->toBeTrue();
            $xpath = new DOMXPath($document);

            if ($format === 'xlsx') {
                $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $row = '//s:row[s:c/s:is/s:t="'.$unusedLabel.'"]';

                expect($xpath->evaluate('string('.$row.'/s:c[last()]/s:v)'))->toBe('0')
                    ->and($xpath->evaluate('string('.$row.'/s:c[last()]/s:f)'))->toBe('SUM(0)');
            } else {
                $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                $totalPrefix = $category === 'mooe' ? 'Total for Capital' : 'Total for Maintenance';
                $row = '//w:tr[contains(normalize-space(w:tc[1]), "'.$totalPrefix.'")]';

                expect($xpath->evaluate('normalize-space('.$row.'/w:tc[last()])'))->toBe('0.00');
            }
        } finally {
            $archive->close();
            unlink($temporaryPath);
        }
    }
})->with([
    'MOOE only' => ['mooe', 'TOTAL CAPITAL OUTLAY:', 'Total for Capital Outlays (CO)'],
    'Capital Outlay only' => ['capital_outlay', 'TOTAL MOOE:', 'Total for Maintenance and Other Operating Expenses (MOOE)'],
]);
