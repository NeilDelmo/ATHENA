<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CurriculumVitaeData
{
    /** @param array<string, mixed> $validated @return array{people: array<int, array<string, mixed>>} */
    public static function fromValidated(array $validated): array
    {
        return [
            'people' => collect($validated['people'])
                ->map(fn (array $person): array => self::normalizePerson($person))
                ->values()
                ->all(),
        ];
    }

    /** @param array<int, string> $names @return array<int, array<string, mixed>> */
    public static function seedPeople(array $names): array
    {
        return self::seedPeopleWithContacts(
            collect($names)->map(fn (string $name): array => ['name' => $name, 'email' => ''])->all(),
        );
    }

    /** @param array<int, array{name: string, email?: string}> $people @return array<int, array<string, mixed>> */
    public static function seedPeopleWithContacts(array $people): array
    {
        return collect($people)
            ->map(fn (array $person): array => [
                'name' => Str::squish($person['name']),
                'email' => Str::lower(trim((string) ($person['email'] ?? ''))),
            ])
            ->filter(fn (array $person): bool => filled($person['name']))
            ->unique(fn (array $person): string => $person['email'] ?: Str::lower($person['name']))
            ->map(function (array $person): array {
                return [
                    ...self::seedPerson($person['name']),
                    'email' => $person['email'],
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private static function seedPerson(string $name): array
    {
        $lastName = '';
        $firstName = '';
        $middleName = '';

        if (Str::contains($name, ',')) {
            [$lastName, $remainder] = array_pad(explode(',', $name, 2), 2, '');
            $tokens = preg_split('/\s+/u', trim($remainder)) ?: [];
            $firstName = array_shift($tokens) ?? '';
            $middleName = implode(' ', $tokens);
        } else {
            $tokens = preg_split('/\s+/u', $name) ?: [];
            $firstName = array_shift($tokens) ?? '';
            $lastName = count($tokens) > 0 ? (string) array_pop($tokens) : '';
            $middleName = implode(' ', $tokens);
        }

        return [
            'last_name' => $lastName ?: $firstName,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'agency' => '',
            'gender' => '',
            'birthday' => '',
            'street' => '',
            'barangay' => '',
            'municipality' => '',
            'province' => '',
            'landline' => '',
            'cellphone' => '',
            'email' => '',
            ...collect(array_keys(config('curriculum_vitae.sections')))
                ->mapWithKeys(fn (string $key): array => [$key => []])
                ->all(),
        ];
    }

    /** @param array<string, mixed> $person @return array<string, mixed> */
    private static function normalizePerson(array $person): array
    {
        $normalized = collect([
            'last_name', 'first_name', 'middle_name', 'agency', 'gender', 'street', 'barangay',
            'municipality', 'province', 'landline', 'cellphone', 'email',
        ])->mapWithKeys(fn (string $key): array => [$key => trim((string) ($person[$key] ?? ''))])->all();
        $normalized['birthday'] = filled($person['birthday'] ?? null)
            ? Carbon::parse($person['birthday'])->format('m/d/Y')
            : '';

        foreach (config('curriculum_vitae.sections') as $sectionKey => $section) {
            $fieldTypes = collect($section['fields'])->mapWithKeys(
                fn (array $field): array => [$field['key'] => $field['type']],
            );
            $sectionRows = collect($person[$sectionKey] ?? [])
                ->filter(fn (mixed $row): bool => is_array($row))
                ->map(function (array $row) use ($fieldTypes, $sectionKey): array {
                    $normalizedRow = $fieldTypes->mapWithKeys(function (string $type, string $key) use ($row): array {
                        $value = $row[$key] ?? null;

                        if ($type === 'money') {
                            return [$key => $value === null || $value === '' ? '' : number_format((float) $value, 2)];
                        }

                        return [$key => trim((string) ($value ?? ''))];
                    })->all();

                    if ($sectionKey === 'academic_background' && $normalizedRow['status'] === 'Ongoing') {
                        $normalizedRow['year_end'] = 'Present';
                    }

                    return $normalizedRow;
                })
                ->filter(fn (array $row): bool => collect($row)->contains(fn (string $value): bool => $value !== ''))
                ->values();
            $blankRow = $fieldTypes->mapWithKeys(fn (string $type, string $key): array => [$key => ''])->all();

            while ($sectionRows->count() < (int) $section['default_rows']) {
                $sectionRows->push($blankRow);
            }

            $normalized[$sectionKey] = $sectionRows->all();
        }

        return $normalized;
    }
}
