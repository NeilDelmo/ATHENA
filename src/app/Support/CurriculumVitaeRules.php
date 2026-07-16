<?php

namespace App\Support;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class CurriculumVitaeRules
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rules(): array
    {
        $sectionKeys = array_keys(config('curriculum_vitae.sections'));
        $personKeys = [
            'last_name', 'first_name', 'middle_name', 'agency', 'gender', 'birthday',
            'street', 'barangay', 'municipality', 'province', 'landline', 'cellphone', 'email',
            ...$sectionKeys,
        ];
        $rules = [
            'people' => ['required', 'array', 'min:1', 'max:'.config('curriculum_vitae.max_people')],
            'people.*' => ['array:'.implode(',', $personKeys)],
            'people.*.last_name' => ['required', 'string', 'max:120'],
            'people.*.first_name' => ['required', 'string', 'max:120'],
            'people.*.middle_name' => ['nullable', 'string', 'max:120'],
            'people.*.agency' => ['nullable', 'string', 'max:255'],
            'people.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'people.*.birthday' => ['nullable', 'date_format:Y-m-d'],
            'people.*.street' => ['nullable', 'string', 'max:120'],
            'people.*.barangay' => ['nullable', 'string', 'max:120'],
            'people.*.municipality' => ['nullable', 'string', 'max:120'],
            'people.*.province' => ['nullable', 'string', 'max:120'],
            'people.*.landline' => ['nullable', 'string', 'max:50'],
            'people.*.cellphone' => ['nullable', 'string', 'max:50'],
            'people.*.email' => ['nullable', 'email', 'max:255'],
        ];

        foreach (config('curriculum_vitae.sections') as $sectionKey => $section) {
            $fieldKeys = collect($section['fields'])->pluck('key')->all();
            $rules["people.*.{$sectionKey}"] = ['nullable', 'array', 'max:'.config('curriculum_vitae.max_rows_per_section')];
            $rules["people.*.{$sectionKey}.*"] = ['array:'.implode(',', $fieldKeys)];

            foreach ($section['fields'] as $field) {
                $rules["people.*.{$sectionKey}.*.{$field['key']}"] = self::fieldRules($field['type']);
            }
        }

        return $rules;
    }

    /** @return array<mixed> */
    private static function fieldRules(string $type): array
    {
        return match ($type) {
            'date' => ['nullable', 'date_format:Y-m-d'],
            'year' => ['nullable', 'digits:4'],
            'money' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'yes_no' => ['nullable', Rule::in(['yes', 'no'])],
            default => ['nullable', 'string', 'max:500'],
        };
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'people' => 'curriculum vitae members',
            'people.*.last_name' => 'last name',
            'people.*.first_name' => 'first name',
            'people.*.middle_name' => 'middle name',
            'people.*.birthday' => 'birthday',
            'people.*.email' => 'email address',
        ];
    }
}
