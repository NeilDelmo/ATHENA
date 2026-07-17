<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class LineItemBudgetData
{
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
            'project_title' => $validated['project_title'],
            'planned_start' => Carbon::parse($validated['planned_start'])->format('F j, Y'),
            'planned_end' => Carbon::parse($validated['planned_end'])->format('F j, Y'),
            'duration' => Carbon::parse($validated['planned_start'])->format('F j, Y').' - '.Carbon::parse($validated['planned_end'])->format('F j, Y'),
            'project_leader' => self::personName((string) $validated['project_leader']),
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
