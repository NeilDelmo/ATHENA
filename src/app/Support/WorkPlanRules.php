<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkPlanRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rules(string $presenceRule = 'required'): array
    {
        return [
            'title' => [$presenceRule, 'string', 'max:255'],
            'project_title' => [$presenceRule, 'string', 'max:255'],
            'total_duration_months' => [$presenceRule, 'integer', 'min:1', 'max:12'],
            'planned_start' => [$presenceRule, 'date'],
            'planned_end' => [$presenceRule, 'date', 'after_or_equal:planned_start'],
            'entries' => [$presenceRule, 'array', 'min:1', 'max:'.config('work_plan.max_objectives')],
            'entries.*' => ['array:objective,expected_output,activity,months'],
            'entries.*.objective' => [$presenceRule, 'string', 'max:500'],
            'entries.*.expected_output' => [$presenceRule, 'string', 'max:500'],
            'entries.*.activity' => [$presenceRule, 'string', 'max:1500'],
            'entries.*.months' => [$presenceRule, 'array', 'min:1', 'max:12'],
            'entries.*.months.*' => ['integer', Rule::in(range(1, 12)), 'lte:total_duration_months'],
            'prepared_by' => [$presenceRule, 'string', 'max:120'],
            'prepared_date' => ['nullable', 'date'],
            'verified_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public static function afterCallbacks(mixed $entries, mixed $durationMonths): array
    {
        return [function (Validator $validator) use ($durationMonths, $entries): void {
            if (! is_array($entries)) {
                return;
            }

            if (is_numeric($durationMonths) && count($entries) > (int) $durationMonths) {
                $validator->errors()->add(
                    'entries',
                    'A '.$durationMonths.'-month plan can contain at most '.$durationMonths.' objectives because each objective must use a different month.',
                );
            }

            $monthOwners = [];

            foreach ($entries as $entryIndex => $entry) {
                if (! is_array($entry) || ! is_array($entry['months'] ?? null)) {
                    continue;
                }

                foreach ($entry['months'] as $month) {
                    if (! is_numeric($month)) {
                        continue;
                    }

                    $month = (int) $month;
                    $ownerIndex = $monthOwners[$month] ?? null;

                    if ($ownerIndex === $entryIndex) {
                        $validator->errors()->add(
                            "entries.{$entryIndex}.months",
                            'M'.$month.' was selected more than once for Objective '.($entryIndex + 1).'.',
                        );

                        continue;
                    }

                    if ($ownerIndex !== null) {
                        $validator->errors()->add(
                            "entries.{$entryIndex}.months",
                            'M'.$month.' is already assigned to Objective '.($ownerIndex + 1).'. Each month can be assigned to only one objective.',
                        );

                        continue;
                    }

                    $monthOwners[$month] = $entryIndex;
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'project_title' => 'project title',
            'total_duration_months' => 'total duration',
            'planned_start' => 'planned start date',
            'planned_end' => 'planned end date',
            'entries' => 'work plan objectives',
            'entries.*.objective' => 'objective',
            'entries.*.expected_output' => 'expected output',
            'entries.*.activity' => 'activities or work plan',
            'entries.*.months' => 'scheduled months',
            'entries.*.months.*' => 'scheduled month',
            'prepared_by' => 'project leader',
            'prepared_date' => 'project leader date signed',
            'verified_date' => 'verification date',
        ];
    }
}
