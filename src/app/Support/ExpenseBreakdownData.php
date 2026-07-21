<?php

namespace App\Support;

use Illuminate\Support\Str;

class ExpenseBreakdownData
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function fromValidated(array $validated): array
    {
        $items = collect($validated['items'])
            ->map(function (array $item): array {
                $isContingency = $item['category'] === 'mooe' && $item['account'] === 'Contingency';
                $quantity = $isContingency ? 1.0 : round((float) $item['quantity'], 2);
                $unitCost = round((float) $item['unit_cost'], 2);

                return [
                    'category' => $item['category'],
                    'account' => Str::squish($item['account']),
                    'sub_account' => Str::squish($item['sub_account']),
                    'particulars' => $isContingency ? 'N/A' : Str::squish($item['particulars']),
                    'details' => $isContingency ? 'N/A' : Str::squish($item['details']),
                    'purpose' => Str::squish($item['purpose']),
                    'unit' => $isContingency ? '' : Str::squish($item['unit']),
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => round($quantity * $unitCost, 2),
                    'is_contingency' => $isContingency,
                ];
            })
            ->values();

        $sections = collect(config('expense_breakdown.categories'))
            ->map(function (string $label, string $category) use ($items): array {
                $sectionItems = $items->where('category', $category)->values();
                $accounts = collect(config('expense_breakdown.accounts.'.$category))
                    ->map(function (array $accountDefinition) use ($sectionItems): array {
                        $accountItems = $sectionItems->where('account', $accountDefinition['label'])->values();
                        $subAccounts = collect($accountDefinition['sub_accounts'])
                            ->map(function (array $subAccountDefinition) use ($accountItems): array {
                                $subAccountItems = $accountItems
                                    ->where('sub_account', $subAccountDefinition['label'])
                                    ->values();

                                return [
                                    'label' => $subAccountDefinition['label'],
                                    'total_label' => $subAccountDefinition['total_label'],
                                    'items' => $subAccountItems->all(),
                                    'total' => round((float) $subAccountItems->sum('total_cost'), 2),
                                ];
                            })
                            ->filter(fn (array $subAccount): bool => $subAccount['items'] !== [])
                            ->values()
                            ->all();

                        return [
                            'label' => $accountDefinition['label'],
                            'is_contingency' => (bool) ($accountDefinition['is_contingency'] ?? false),
                            'sub_accounts' => $subAccounts,
                            'total' => round((float) $accountItems->sum('total_cost'), 2),
                        ];
                    })
                    ->filter(fn (array $account): bool => $account['sub_accounts'] !== [])
                    ->values()
                    ->all();

                return [
                    'key' => $category,
                    'label' => $label,
                    'accounts' => $accounts,
                    'total' => round((float) $sectionItems->sum('total_cost'), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'project_title' => Str::squish($validated['project_title']),
            'items' => $items->all(),
            'sections' => $sections,
            'grand_total' => round((float) $items->sum('total_cost'), 2),
        ];
    }
}
