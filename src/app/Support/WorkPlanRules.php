<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

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
