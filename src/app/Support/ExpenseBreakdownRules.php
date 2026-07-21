<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ExpenseBreakdownRules
{
    /** @return array<string, ValidationRule|Closure|array<mixed>|string> */
    public static function rules(): array
    {
        return [
            'project_title' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:'.config('expense_breakdown.max_items')],
            'items.*' => [
                'array:category,account,sub_account,particulars,details,purpose,unit,quantity,unit_cost',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_array($value) && ! self::groupingExists($value)) {
                        $fail('The selected expense type, account, and sub-account do not match the official workbook.');
                    }
                },
            ],
            'items.*.category' => ['required', Rule::in(array_keys(config('expense_breakdown.categories')))],
            'items.*.account' => ['required', 'string', Rule::in(self::accountLabels())],
            'items.*.sub_account' => ['required', 'string', Rule::in(self::subAccountLabels())],
            'items.*.particulars' => ['required', 'string', 'max:255'],
            'items.*.details' => ['required', 'string', 'max:500'],
            'items.*.purpose' => ['required', 'string', 'max:500'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:'.config('expense_breakdown.maximum_quantity')],
            'items.*.unit_cost' => ['required', 'numeric', 'gt:0', 'max:'.config('expense_breakdown.maximum_unit_cost')],
        ];
    }

    /** @param array<string, mixed> $item */
    private static function groupingExists(array $item): bool
    {
        $category = $item['category'] ?? null;
        $account = $item['account'] ?? null;
        $subAccount = $item['sub_account'] ?? null;

        if (! is_string($category) || ! is_string($account) || ! is_string($subAccount)) {
            return false;
        }

        return collect(config('expense_breakdown.accounts.'.$category, []))
            ->contains(function (array $definition) use ($account, $subAccount): bool {
                return $definition['label'] === $account
                    && collect($definition['sub_accounts'])->contains('label', $subAccount);
            });
    }

    /** @return list<string> */
    private static function accountLabels(): array
    {
        return collect(config('expense_breakdown.accounts'))
            ->flatten(1)
            ->pluck('label')
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function subAccountLabels(): array
    {
        return collect(config('expense_breakdown.accounts'))
            ->flatten(1)
            ->flatMap(fn (array $account): array => $account['sub_accounts'])
            ->pluck('label')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'items' => 'expense items',
            'items.*.category' => 'expense type',
            'items.*.account' => 'account',
            'items.*.sub_account' => 'sub-account',
            'items.*.particulars' => 'particulars',
            'items.*.details' => 'description, specifications, or details',
            'items.*.purpose' => 'purpose in the project',
            'items.*.unit' => 'unit',
            'items.*.quantity' => 'quantity',
            'items.*.unit_cost' => 'unit cost',
        ];
    }
}
