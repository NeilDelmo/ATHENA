<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LineItemBudgetRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:'.config('line_item_budget.maximum_amount')];
        $amountKeys = collect(config('line_item_budget.sections'))
            ->flatMap(fn (array $section): array => $section['items'])
            ->pluck('key')
            ->implode(',');

        return [
            'project_title' => ['required', 'string', 'max:255'],
            'planned_start' => ['required', 'date'],
            'planned_end' => ['required', 'date', 'after_or_equal:planned_start'],
            'project_leader' => ['required', 'string', 'max:120'],
            'leader_campus' => ['nullable', 'string', 'max:120'],
            'leader_college' => ['nullable', 'string', 'max:120'],
            'staff' => ['nullable', 'array'],
            'staff.*' => ['array:name,campus,college'],
            'staff.*.name' => ['nullable', 'string', 'max:120'],
            'staff.*.campus' => ['nullable', 'string', 'max:120'],
            'staff.*.college' => ['nullable', 'string', 'max:120'],
            'amounts' => ['nullable', 'array:'.$amountKeys],
            'amounts.*' => $amount,
            'custom_mooe_items' => ['nullable', 'array', 'max:'.config('line_item_budget.max_custom_items')],
            'custom_mooe_items.*' => ['array:particular,amount'],
            'custom_mooe_items.*.particular' => ['nullable', 'string', 'max:255'],
            'custom_mooe_items.*.amount' => $amount,
            'custom_co_items' => ['nullable', 'array', 'max:'.config('line_item_budget.max_custom_items')],
            'custom_co_items.*' => ['array:particular,amount'],
            'custom_co_items.*.particular' => ['nullable', 'string', 'max:255'],
            'custom_co_items.*.amount' => $amount,
            'mooe_total_override' => $amount,
            'co_total_override' => $amount,
            'project_total_override' => $amount,
            'level_of_call' => ['nullable', Rule::in(['central_agency', 'constituent_campus'])],
            'approval_body' => ['nullable', Rule::in(['research_council', 'lrec'])],
            'resolution_number' => ['nullable', 'string', 'max:50'],
            'resolution_year' => ['nullable', 'string', 'max:10'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public static function afterCallbacks(float $maximumBudget = 0): array
    {
        return [function (Validator $validator) use ($maximumBudget): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = LineItemBudgetData::fromValidated($validator->validated());
            $contingency = $data['amounts']['contingency'] ?? null;

            if ($contingency !== null && $contingency > ($data['computed_mooe_total'] * 0.10) + 0.005) {
                $validator->errors()->add(
                    'amounts.contingency',
                    'Contingency cannot exceed 10% of the automatically computed MOOE total.',
                );
            }

            if ($maximumBudget > 0 && $data['project_total'] > $maximumBudget) {
                $validator->errors()->add(
                    'project_total_override',
                    'The total project cost cannot exceed the research call budget of Php '.number_format($maximumBudget, 2).'.',
                );
            }
        }];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'leader_campus' => 'project leader campus',
            'leader_college' => 'project leader college',
            'staff.*.name' => 'project staff name',
            'staff.*.campus' => 'project staff campus',
            'staff.*.college' => 'project staff college',
            'amounts.*' => 'budget amount',
            'custom_mooe_items.*.particular' => 'custom MOOE particular',
            'custom_mooe_items.*.amount' => 'custom MOOE amount',
            'custom_co_items.*.particular' => 'custom capital outlay particular',
            'custom_co_items.*.amount' => 'custom capital outlay amount',
            'mooe_total_override' => 'MOOE total override',
            'co_total_override' => 'capital outlays total override',
            'project_total_override' => 'project total override',
            'level_of_call' => 'level of call',
            'approval_body' => 'approving body',
            'resolution_number' => 'resolution number',
            'resolution_year' => 'resolution year',
        ];
    }
}
