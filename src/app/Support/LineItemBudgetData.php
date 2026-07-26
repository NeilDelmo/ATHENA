<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LineItemBudgetData
{
    /**
     * Convert saved Estimated Expense Breakdown rows into the matching
     * standard Line-Item Budget amounts.
     *
     * @param  array<int, mixed>  $items
     * @return array<string, float>
     */
    public static function amountsFromExpenseBreakdown(array $items): array
    {
        $amounts = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $category = (string) ($item['category'] ?? '');
            $section = $category === 'capital_outlay' ? 'co' : 'mooe';
            $subAccount = trim((string) ($item['sub_account'] ?? ''));
            $account = trim((string) ($item['account'] ?? ''));
            $targetLabel = $subAccount !== '' && $subAccount !== 'none'
                ? $subAccount
                : $account;

            $lineItem = collect(config("line_item_budget.sections.{$section}.items", []))
                ->filter(fn (array $lineItem): bool => $lineItem['label'] === $targetLabel)
                ->sortByDesc('level')
                ->first();

            if (! is_array($lineItem)) {
                continue;
            }

            $quantity = (float) ($item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);

            if ($quantity <= 0 || $unitCost <= 0) {
                continue;
            }

            $key = (string) $lineItem['key'];
            $amounts[$key] = round(
                ($amounts[$key] ?? 0) + round($quantity * $unitCost, 2),
                2,
            );
        }

        return $amounts;
    }

    /** @param array<string, mixed> $validated @return array<string, mixed> */
    public static function fromValidated(array $validated): array
    {
        $amounts = collect($validated['amounts'] ?? [])
            ->map(fn (mixed $amount): ?float => self::amount($amount))
            ->all();
        $staff = self::meaningfulRows($validated['staff'] ?? [], ['name', 'campus', 'college']);
        $customMooe = self::budgetRows($validated['custom_mooe_items'] ?? []);
        $customCo = self::budgetRows($validated['custom_co_items'] ?? []);
        $mooeKeys = collect(config('line_item_budget.sections.mooe.items'))->pluck('key');
        $coKeys = collect(config('line_item_budget.sections.co.items'))->pluck('key');
        $computedMooe = $mooeKeys->sum(fn (string $key): float => $amounts[$key] ?? 0.0)
            + collect($customMooe)->sum('amount');
        $computedCo = $coKeys->sum(fn (string $key): float => $amounts[$key] ?? 0.0)
            + collect($customCo)->sum('amount');
        $mooeOverride = self::amount($validated['mooe_total_override'] ?? null);
        $coOverride = self::amount($validated['co_total_override'] ?? null);
        $mooeTotal = $mooeOverride ?? $computedMooe;
        $coTotal = $coOverride ?? $computedCo;
        $computedProjectTotal = $mooeTotal + $coTotal;
        $projectOverride = self::amount($validated['project_total_override'] ?? null);

        return [
            'program_title' => '',
            'project_title' => (string) ($validated['project_title'] ?? ''),
            'planned_start' => self::date($validated['planned_start'] ?? null),
            'planned_end' => self::date($validated['planned_end'] ?? null),
            'duration' => self::date($validated['planned_start'] ?? null).' - '.self::date($validated['planned_end'] ?? null),
            'project_leader' => self::personName((string) ($validated['project_leader'] ?? '')),
            'leader_campus' => trim((string) ($validated['leader_campus'] ?? config('line_item_budget.default_campus'))),
            'leader_college' => trim((string) ($validated['leader_college'] ?? '')),
            'staff' => $staff,
            'amounts' => $amounts,
            'custom_mooe_items' => $customMooe,
            'custom_co_items' => $customCo,
            'computed_mooe_total' => $computedMooe,
            'mooe_total' => $mooeTotal,
            'mooe_total_overridden' => $mooeOverride !== null,
            'computed_co_total' => $computedCo,
            'co_total' => $coTotal,
            'co_total_overridden' => $coOverride !== null,
            'computed_project_total' => $computedProjectTotal,
            'project_total' => $projectOverride ?? $computedProjectTotal,
            'project_total_overridden' => $projectOverride !== null,
            'level_of_call' => $validated['level_of_call'] ?? null,
            'approval_body' => $validated['approval_body'] ?? null,
            'resolution_number' => trim((string) ($validated['resolution_number'] ?? '')),
            'resolution_year' => trim((string) ($validated['resolution_year'] ?? '')),
            'certified_by' => (string) config('work_plan.verifier.name'),
            'certified_role' => (string) config('work_plan.verifier.role'),
        ];
    }

    public static function amount(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 2);
    }

    private static function date(mixed $value): string
    {
        return blank($value) ? '' : Carbon::parse($value)->format('F j, Y');
    }

    private static function personName(string $name): string
    {
        $name = Str::squish($name);

        return $name === Str::upper($name)
            ? Str::of($name)->lower()->title()->toString()
            : $name;
    }

    /** @param array<int, mixed> $rows @param array<int, string> $keys @return array<int, array<string, string>> */
    private static function meaningfulRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row) use ($keys): array {
                return collect($keys)->mapWithKeys(
                    fn (string $key): array => [$key => trim((string) ($row[$key] ?? ''))],
                )->all();
            })
            ->filter(fn (array $row): bool => collect($row)->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }

    /** @param array<int, mixed> $rows @return array<int, array{particular: string, amount: ?float}> */
    private static function budgetRows(array $rows): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(fn (array $row): array => [
                'particular' => trim((string) ($row['particular'] ?? '')),
                'amount' => self::amount($row['amount'] ?? null),
            ])
            ->filter(fn (array $row): bool => $row['particular'] !== '' || $row['amount'] !== null)
            ->values()
            ->all();
    }
}
