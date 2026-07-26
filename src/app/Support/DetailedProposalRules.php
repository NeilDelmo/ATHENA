<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DetailedProposalRules
{
    /** @return array<string, mixed> */
    public static function rules(bool $allowDraft = false): array
    {
        $maximumNarrativeLength = (int) config('detailed_proposal.maximum_narrative_length');
        $presenceRule = $allowDraft ? 'nullable' : 'required';
        $minimumSdgs = $allowDraft ? [] : ['min:1'];
        $minimumResponsibilities = $allowDraft ? [] : ['min:1'];

        return [
            'project_title' => [$presenceRule, 'string', 'max:500'],
            'project_leader' => [$presenceRule, 'string', 'max:255'],
            'research_agenda' => [$presenceRule, 'string', 'max:500'],
            'sdgs' => [$presenceRule, 'array', ...$minimumSdgs],
            'sdgs.*' => [$presenceRule, 'integer', 'distinct', Rule::in(array_keys(config('detailed_proposal.sdgs')))],
            'leader_email' => [$presenceRule, 'email:rfc', 'max:255'],
            'leader_contact' => [$presenceRule, 'string', 'max:80'],
            'staff' => ['nullable', 'array', 'max:20'],
            'staff.*.name' => ['nullable', 'string', 'max:255'],
            'staff.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'staff.*.contact' => ['nullable', 'string', 'max:80'],
            'proponent_department' => ['nullable', 'string', 'max:255'],
            'proponent_college' => [$presenceRule, 'string', 'max:255'],
            'proponent_campus' => [$presenceRule, 'string', 'max:255'],
            'cooperating_agency' => ['nullable', 'string', 'max:500'],
            'executive_brief' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'rationale' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'objectives' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'expected_outputs' => [$presenceRule, 'array'],
            ...collect(config('detailed_proposal.expected_outputs'))
                ->mapWithKeys(fn (string $label, string $key): array => [
                    'expected_outputs.'.$key => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
                ])
                ->all(),
            'related_literature' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'methodology' => [$presenceRule, 'array'],
            'methodology.research_design' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'methodology.specific_methods' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'methodology.data_analysis' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'responsibilities' => [$presenceRule, 'array', ...$minimumResponsibilities, 'max:30'],
            'responsibilities.*.name' => [$presenceRule, 'string', 'max:255'],
            'responsibilities.*.duties' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'references' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
        ];
    }

    /** @return list<callable> */
    public static function afterCallbacks(bool $allowDraft = false): array
    {
        if ($allowDraft) {
            return [];
        }

        return [
            function (Validator $validator): void {
                $expectedOutputs = Arr::wrap($validator->getData()['expected_outputs'] ?? []);

                if (collect($expectedOutputs)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                    $validator->errors()->add(
                        'expected_outputs',
                        'Provide at least one expected output under the expanded 6Ps and 2Is.',
                    );
                }

                foreach (Arr::wrap($validator->getData()['staff'] ?? []) as $index => $member) {
                    if (! is_array($member)) {
                        continue;
                    }

                    $values = collect(['name', 'email', 'contact'])
                        ->map(fn (string $key): string => trim((string) ($member[$key] ?? '')));

                    if ($values->contains(fn (string $value): bool => $value !== '')
                        && $values->contains(fn (string $value): bool => $value === '')) {
                        $validator->errors()->add(
                            'staff.'.$index.'.name',
                            'Each project staff row must include a name, email address, and contact number.',
                        );
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'research_agenda' => 'BatStateU research agenda',
            'sdgs' => 'Sustainable Development Goals',
            'leader_email' => 'project leader email address',
            'leader_contact' => 'project leader contact number',
            'proponent_department' => 'proponent department',
            'proponent_college' => 'proponent college',
            'proponent_campus' => 'proponent campus',
            'executive_brief' => 'executive brief',
            'related_literature' => 'review of related literature',
            'methodology.research_design' => 'research design',
            'methodology.specific_methods' => 'specific methods',
            'methodology.data_analysis' => 'data analysis',
            'responsibilities.*.name' => 'member name',
            'responsibilities.*.duties' => 'member duties and responsibilities',
        ];
    }
}
