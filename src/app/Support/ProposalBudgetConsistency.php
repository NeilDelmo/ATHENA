<?php

namespace App\Support;

use App\Models\ProposalDraft;

class ProposalBudgetConsistency
{
    private const DIFFERENCE_TOLERANCE = 0.005;

    /**
     * @return array{
     *     available: bool,
     *     consistent: bool,
     *     totals: list<array{key: string, label: string, line_item_budget: float, expense_breakdown: float, difference: float}>,
     *     mismatches: list<array{key: string, label: string, line_item_budget: float, expense_breakdown: float, difference: float}>
     * }
     */
    public function compare(ProposalDraft $draft): array
    {
        $draft->loadMissing('documents');

        $lineItemBudgetSource = $draft->documents
            ->firstWhere('document_type', config('proposal_papers.line-item-budget.document_type'))
            ?->source_data;
        $expenseBreakdownSource = $draft->documents
            ->firstWhere('document_type', config('proposal_papers.expense-breakdown.document_type'))
            ?->source_data;

        if (! is_array($lineItemBudgetSource)
            || ! is_array($expenseBreakdownSource)
            || ! is_array($expenseBreakdownSource['items'] ?? null)
            || $expenseBreakdownSource['items'] === []) {
            return [
                'available' => false,
                'consistent' => true,
                'totals' => [],
                'mismatches' => [],
            ];
        }

        $lineItemBudget = LineItemBudgetData::fromValidated([
            ...$lineItemBudgetSource,
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'project_leader' => $draft->project_leader,
        ]);
        $expenseBreakdown = ExpenseBreakdownData::fromValidated([
            ...$expenseBreakdownSource,
            'project_title' => $draft->project_title,
        ]);
        $expenseSectionTotals = collect($expenseBreakdown['sections'])
            ->pluck('total', 'key');
        $totals = collect([
            [
                'key' => 'mooe_total',
                'label' => 'MOOE',
                'line_item_budget' => (float) $lineItemBudget['mooe_total'],
                'expense_breakdown' => (float) $expenseSectionTotals->get('mooe', 0),
            ],
            [
                'key' => 'co_total',
                'label' => 'Capital Outlay',
                'line_item_budget' => (float) $lineItemBudget['co_total'],
                'expense_breakdown' => (float) $expenseSectionTotals->get('capital_outlay', 0),
            ],
            [
                'key' => 'project_total',
                'label' => 'Total Project Cost',
                'line_item_budget' => (float) $lineItemBudget['project_total'],
                'expense_breakdown' => (float) $expenseBreakdown['grand_total'],
            ],
        ])->map(function (array $total): array {
            return [
                ...$total,
                'difference' => round(
                    $total['line_item_budget'] - $total['expense_breakdown'],
                    2,
                ),
            ];
        })->values();
        $mismatches = $totals
            ->filter(fn (array $total): bool => abs($total['difference']) > self::DIFFERENCE_TOLERANCE)
            ->values();

        return [
            'available' => true,
            'consistent' => $mismatches->isEmpty(),
            'totals' => $totals->all(),
            'mismatches' => $mismatches->all(),
        ];
    }
}
