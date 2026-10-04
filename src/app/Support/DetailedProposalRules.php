<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;

class DetailedProposalRules
{
    /** @param array<string, mixed> $data */
    public static function passesComplete(array $data): bool
    {
        return self::completionErrors($data) === [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, list<string>>
     */
    public static function completionErrors(array $data): array
    {
        $validator = ValidatorFacade::make(DetailedProposalData::normalizeObjectiveFields($data), self::rules(), self::messages(), self::attributes());

        foreach (self::afterCallbacks() as $callback) {
            $validator->after($callback);
        }

        return $validator->errors()->toArray();
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'specific_objectives.required' => 'Add at least one Specific Objective below the optional General Objective.',
            'specific_objectives.min' => 'Add at least one Specific Objective below the optional General Objective.',
            'specific_objectives.*.description.required' => 'Enter text for Specific Objective :position.',
        ];
    }

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
            'leader_title' => ['nullable', 'string', 'max:50'],
            'leader_email' => [$presenceRule, 'email:rfc', 'max:255'],
            'leader_contact' => [$presenceRule, 'digits:11'],
            'staff' => ['nullable', 'array', 'max:20'],
            'staff.*.title' => ['nullable', 'string', 'max:50'],
            'staff.*.name' => ['nullable', 'string', 'max:255'],
            'staff.*.email' => ['nullable', 'email:rfc', 'max:255'],
            'staff.*.contact' => ['nullable', 'digits:11'],
            'proponent_department' => ['nullable', 'string', 'max:255'],
            'proponent_college' => [$presenceRule, 'string', 'max:255'],
            'proponent_campus' => [$presenceRule, 'string', 'max:255'],
            'cooperating_agency' => ['nullable', 'string', 'max:500'],
            'executive_brief' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'rationale' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'general_objective' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
            'specific_objectives' => [$presenceRule, 'array', ...($allowDraft ? [] : ['min:1']), 'max:20'],
            'specific_objectives.*' => ['array:description'],
            'specific_objectives.*.description' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'expected_outputs' => [$presenceRule, 'array'],
            ...collect(config('detailed_proposal.expected_outputs'))
                ->mapWithKeys(fn (string $label, string $key): array => [
                    'expected_outputs.'.$key => ['nullable', 'array', 'max:20'],
                    'expected_outputs.'.$key.'.*' => ['array:quantity,unit,description'],
                    'expected_outputs.'.$key.'.*.quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
                    'expected_outputs.'.$key.'.*.unit' => ['nullable', 'string', 'max:100'],
                    'expected_outputs.'.$key.'.*.description' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
                ])
                ->all(),
            'introduction' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
            'related_literature' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'literature_research_history' => ['nullable', 'json', 'max:12000'],
            'literature_citations' => ['nullable', 'json', 'max:30000'],
            'methodology' => [$presenceRule, 'array'],
            'methodology.research_design' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'methodology.specific_methods' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'specific_method_objectives' => ['nullable', 'array', 'max:20'],
            'specific_method_objectives.*' => ['array:heading,methods'],
            'specific_method_objectives.*.heading' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
            'specific_method_objectives.*.methods' => ['nullable', 'array', 'max:20'],
            'specific_method_objectives.*.methods.*' => ['array:description'],
            'specific_method_objectives.*.methods.*.description' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
            'methodology.data_analysis' => ['nullable', 'string', 'max:'.$maximumNarrativeLength],
            'methodology_images' => ['nullable', 'array', 'max:20'],
            'methodology_images.*.id' => ['nullable', 'uuid'],
            'methodology_images.*.section' => ['required', 'string', Rule::in(array_keys(config('detailed_proposal.image_sections')))],
            'methodology_images.*.alignment' => ['required', 'string', Rule::in(['left', 'center', 'right'])],
            'methodology_images.*.size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'methodology_images.*.caption' => [$presenceRule, 'string', 'max:500'],
            'methodology_images.*.stored_path' => ['nullable', 'string', 'max:2048'],
            'methodology_images.*.mime_type' => ['nullable', 'string', 'max:255'],
            'methodology_images.*.original_filename' => ['nullable', 'string', 'max:255'],
            'methodology_images.*.image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'gif', 'bmp'])->max('10mb')],
            'responsibilities' => [$presenceRule, 'array', ...$minimumResponsibilities, 'max:30'],
            'responsibilities.*.name' => [$presenceRule, 'string', 'max:255'],
            'responsibilities.*.percentage' => [$presenceRule, 'integer', 'min:1', 'max:100'],
            'responsibilities.*.duties' => [$presenceRule, 'string', 'max:'.$maximumNarrativeLength],
            'checked_verified_by_name' => ['nullable', 'string', 'max:255'],
            'recommending_approval_name' => ['nullable', 'string', 'max:255'],
            'approved_by_name' => ['nullable', 'string', 'max:255'],
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
                foreach (['executive_brief', 'rationale', 'related_literature', 'methodology.research_design', 'methodology.specific_methods', 'references'] as $field) {
                    $value = data_get($validator->getData(), $field);

                    if (! $validator->errors()->has($field) && is_string($value) && preg_match('/^\s*$/u', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))) {
                        $validator->errors()->add($field, 'Provide text for '.(self::attributes()[$field] ?? str_replace('_', ' ', $field)).'.');
                    }
                }

                foreach (Arr::wrap($validator->getData()['specific_method_objectives'] ?? []) as $index => $group) {
                    if (! is_array($group)) {
                        continue;
                    }

                    if (blank($group['heading'] ?? null)) {
                        $validator->errors()->add('specific_method_objectives.'.$index.'.heading', 'Add a heading for method group '.($index + 1).'.');
                    }

                    if (empty($group['methods'])) {
                        $validator->errors()->add('specific_method_objectives.'.$index.'.methods', 'Add at least one method to group '.($index + 1).'.');
                    }

                    foreach (Arr::wrap($group['methods'] ?? []) as $methodIndex => $method) {
                        if (is_array($method) && blank($method['description'] ?? null)) {
                            $validator->errors()->add('specific_method_objectives.'.$index.'.methods.'.$methodIndex.'.description', 'Describe method '.($methodIndex + 1).' in group '.($index + 1).'.');
                        }
                    }
                }

                $expectedOutputs = Arr::wrap($validator->getData()['expected_outputs'] ?? []);

                $hasExpectedOutput = collect($expectedOutputs)
                    ->flatten(1)
                    ->contains(fn (mixed $entry): bool => is_array($entry) && filled($entry['description'] ?? null));

                if (! $hasExpectedOutput) {
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

                    if ($values->contains(fn (string $value): bool => $value !== '')) {
                        foreach (['name' => 'name', 'email' => 'email address', 'contact' => '11-digit contact number'] as $field => $label) {
                            if (blank($member[$field] ?? null)) {
                                $validator->errors()->add('staff.'.$index.'.'.$field, 'Provide the '.$label.' for project staff member '.($index + 1).'.');
                            }
                        }
                    }
                }

                foreach (Arr::wrap($validator->getData()['expected_outputs'] ?? []) as $key => $entries) {
                    foreach (Arr::wrap($entries) as $index => $entry) {
                        if (! is_array($entry)) {
                            continue;
                        }

                        if (collect($entry)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty()
                            && blank($entry['description'] ?? null)) {
                            $validator->errors()->add(
                                'expected_outputs.'.$key.'.'.$index.'.description',
                                'Each expected output entry needs a description.',
                            );
                        }
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
            'leader_title' => 'project leader professional title',
            'leader_email' => 'project leader email address',
            'leader_contact' => 'project leader contact number',
            'staff.*.title' => 'project staff professional title',
            'staff.*.name' => 'project staff name',
            'staff.*.email' => 'project staff email address',
            'staff.*.contact' => 'project staff contact number',
            'proponent_department' => 'proponent department',
            'proponent_college' => 'proponent college',
            'proponent_campus' => 'proponent campus',
            'executive_brief' => 'executive brief',
            'general_objective' => 'general objective',
            'specific_objectives' => 'specific objectives',
            'specific_objectives.*.description' => 'specific objective',
            'introduction' => 'Review of Related Literature opening paragraphs',
            'related_literature' => config('detailed_proposal.section_headings.literature'),
            'methodology.research_design' => 'research design',
            'methodology.specific_methods' => 'specific methods',
            'specific_method_objectives.*.methods.*.description' => 'specific method',
            'methodology.data_analysis' => 'data analysis',
            'methodology_images.*.image' => 'methodology image',
            'methodology_images.*.caption' => 'figure title',
            'responsibilities.*.name' => 'member name',
            'responsibilities.*.percentage' => 'member responsibility percentage',
            'responsibilities.*.duties' => 'member duties and responsibilities',
            'checked_verified_by_name' => 'checked and verified by name',
            'recommending_approval_name' => 'recommending approval name',
            'approved_by_name' => 'final approval name',
        ];
    }
}
