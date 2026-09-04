<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CurriculumVitaeRules
{
    private const INPUT_DATE_FORMATS = [
        'Y-m-d',
        'm/d/Y',
        'n/j/Y',
        'm-d-Y',
        'n-j-Y',
        'F j, Y',
        'M j, Y',
    ];

    /**
     * Normalize recognizable display values before strict validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalizeInput(array $input): array
    {
        if (! is_array($input['people'] ?? null)) {
            return $input;
        }

        $input['people'] = array_map(function (mixed $person): mixed {
            if (! is_array($person)) {
                return $person;
            }

            if (is_string($person['gender'] ?? null)) {
                $gender = Str::lower(trim($person['gender']));

                if (in_array($gender, ['male', 'female'], true)) {
                    $person['gender'] = $gender;
                }
            }

            if (array_key_exists('birthday', $person)) {
                $person['birthday'] = self::normalizeDate($person['birthday']);
            }

            return $person;
        }, $input['people']);

        return $input;
    }

    private static function normalizeDate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        foreach (self::INPUT_DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false
                && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
                && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public static function rules(bool $allowDraft = false): array
    {
        $presenceRule = $allowDraft ? 'nullable' : 'required';
        $minimumPeople = $allowDraft ? [] : ['min:1'];
        $sectionKeys = array_keys(config('curriculum_vitae.sections'));
        $personKeys = [
            'last_name', 'first_name', 'middle_name', 'agency', 'gender', 'birthday',
            'street', 'barangay', 'municipality', 'province', 'landline', 'cellphone', 'email',
            ...$sectionKeys,
        ];
        $rules = [
            'people' => [$presenceRule, 'array', ...$minimumPeople, 'max:'.config('curriculum_vitae.max_people')],
            'people.*' => [$allowDraft ? 'array' : 'array:'.implode(',', $personKeys)],
            'people.*.last_name' => [$presenceRule, 'string', 'max:120'],
            'people.*.first_name' => [$presenceRule, 'string', 'max:120'],
            'people.*.middle_name' => ['nullable', 'string', 'max:120'],
            'people.*.agency' => ['nullable', 'string', 'max:255'],
            'people.*.gender' => ['nullable', Rule::in(['male', 'female'])],
            'people.*.birthday' => ['nullable', 'date_format:Y-m-d'],
            'people.*.street' => ['nullable', 'string', 'max:120'],
            'people.*.barangay' => ['nullable', 'string', 'max:120'],
            'people.*.municipality' => ['nullable', 'string', 'max:120'],
            'people.*.province' => ['nullable', 'string', 'max:120'],
            'people.*.landline' => ['nullable', 'digits:11'],
            'people.*.cellphone' => ['nullable', 'digits:11'],
            'people.*.email' => ['nullable', 'email', 'max:255'],
        ];

        foreach (config('curriculum_vitae.sections') as $sectionKey => $section) {
            $fieldKeys = collect($section['fields'])->pluck('key')->all();
            $rules["people.*.{$sectionKey}"] = ['nullable', 'array', 'max:'.config('curriculum_vitae.max_rows_per_section')];
            $rules["people.*.{$sectionKey}.*"] = ['array:'.implode(',', $fieldKeys)];

            foreach ($section['fields'] as $field) {
                $rules["people.*.{$sectionKey}.*.{$field['key']}"] = self::fieldRules($field);
            }
        }

        return $rules;
    }

    /** @return array<mixed> */
    private static function fieldRules(array $field): array
    {
        return match ($field['type']) {
            'date' => ['nullable', 'date_format:Y-m-d'],
            'year' => ['nullable', 'digits:4'],
            'money' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'yes_no' => ['nullable', Rule::in(['yes', 'no'])],
            'select' => ['nullable', Rule::in($field['options'])],
            'suggestions' => ['nullable', 'string', 'max:500'],
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
