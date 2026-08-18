<?php

namespace App\Support;

use App\Models\ProposalDraft;
use App\Models\ResearchCall;

class ProposalBudgetConsistency
{
    private const DIFFERENCE_TOLERANCE = 0.005;

    /**
     * @return array{
     *     available: bool,
     *     consistent: bool,
     *     budget_ceiling: float,
     *     over_budget: bool,
     *     overage: float,
     *     totals: list<array{key: string, label: string, line_item_budget: float, expense_breakdown: float, difference: float}>,
     *     mismatches: list<array{key: string, label: string, line_item_budget: float, expense_breakdown: float, difference: float}>
     * }
     */
    public function compare(ProposalDraft $draft): array
    {
        $draft->loadMissing(['documents', 'researchCall']);
        $budgetCeiling = $draft->researchCall?->budgetCeiling() ?? ResearchCall::MAXIMUM_BUDGET;

        $lineItemBudgetSource = $draft->documents
            ->firstWhere('document_type', config('proposal_papers.line-item-budget.document_type'))
            ?->source_data;
        $expenseBreakdownSource = $draft->documents
            ->firstWhere('document_type', config('proposal_papers.expense-breakdown.document_type'))
            ?->source_data;

        if (! is_array($lineItemBudgetSource)
            || ! is_array($expenseBreakdownSource)
            || ! is_array($expenseBreakdownSource['items'] ?? null)) {
            return [
                'available' => false,
                'consistent' => true,
                'budget_ceiling' => $budgetCeiling,
                'over_budget' => false,
                'overage' => 0,
                'totals' => [],
                'mismatches' => [],
            ];
        }

        $lineItemBudgetSource = LineItemBudgetData::synchronizeSourceWithExpenseBreakdown(
            $lineItemBudgetSource,
            $expenseBreakdownSource['items'],
        );

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
        $largestProjectTotal = (float) $totals
            ->where('key', 'project_total')
            ->max('line_item_budget');
        $largestProjectTotal = max(
            $largestProjectTotal,
            (float) $totals->where('key', 'project_total')->max('expense_breakdown'),
        );
        $overage = max(0, $largestProjectTotal - $budgetCeiling);
        $overBudget = $budgetCeiling > 0 && $overage > self::DIFFERENCE_TOLERANCE;

        return [
            'available' => true,
            'consistent' => $mismatches->isEmpty() && ! $overBudget,
            'budget_ceiling' => $budgetCeiling,
            'over_budget' => $overBudget,
            'overage' => round($overage, 2),
            'totals' => $totals->all(),
            'mismatches' => $mismatches->all(),
        ];
    }
}
