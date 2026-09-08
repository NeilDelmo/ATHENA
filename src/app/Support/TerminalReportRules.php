<?php

namespace App\Support;

use Closure;
use Illuminate\Validation\Rule;

class TerminalReportRules
{
    public const NARRATIVES = ['abstract', 'literature_review', 'conclusions', 'recommendations', 'bibliography'];

    public const SIGNATORY_ROLES = [
        'reviewed_head' => ['Reviewed by', 'Head, Research'],
        'reviewed_center' => ['Reviewed by', 'Assistant Director, Research/Center Head'],
        'verified_chancellor' => ['Checked and verified by', 'Vice Chancellor for Research, Development and Extension Services'],
        'verified_director' => ['Checked and verified by', 'Director, Research'],
        'approved_by' => ['Approved by', 'Vice President for Research, Development and Extension Services'],
    ];

    public static function rules(bool $draft = false): array
    {
        $required = $draft ? 'nullable' : 'required';
        $rules = [
            'terminal_data' => [$required, 'array:abstract,literature_review,conclusions,recommendations,bibliography,collaborating_agency,total_expenditure,authors,signatories,tables'],
            'terminal_data.collaborating_agency' => ['nullable', 'string', 'max:1000'],
            'terminal_data.total_expenditure' => [$required, 'numeric', 'min:0', 'max:9999999999.99'],
            'terminal_data.authors' => [$required, 'array', 'max:30'],
            'terminal_data.authors.*' => ['array:name,rank,campus,college,role,date_signed'],
            'terminal_data.authors.*.name' => [$required, 'string', 'max:255'],
            'terminal_data.authors.*.rank' => ['nullable', 'string', 'max:255'],
            'terminal_data.authors.*.campus' => ['nullable', 'string', 'max:255'],
            'terminal_data.authors.*.college' => ['nullable', 'string', 'max:255'],
            'terminal_data.authors.*.role' => [$required, Rule::in(['Project Leader', 'Project Staff'])],
            'terminal_data.authors.*.date_signed' => ['nullable', 'date', 'before_or_equal:today'],
            'terminal_data.signatories' => [$required, 'array:'.implode(',', array_keys(self::SIGNATORY_ROLES))],
            'terminal_data.tables' => ['nullable', 'array', 'max:30'],
            'terminal_data.tables.*' => ['array:caption,section,after_paragraph,headers,rows'],
            'terminal_data.tables.*.caption' => [$required, 'string', 'max:300'],
            'terminal_data.tables.*.section' => [$required, Rule::in(['methodology', 'results_discussion'])],
            'terminal_data.tables.*.after_paragraph' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'terminal_data.tables.*.headers' => [$required, 'array', 'min:1', 'max:8'],
            'terminal_data.tables.*.headers.*' => [$required, 'string', 'max:300'],
            'terminal_data.tables.*.rows' => [$required, 'array', 'min:1', 'max:200'],
            'terminal_data.tables.*.rows.*' => ['array', 'min:1', 'max:8'],
            'terminal_data.tables.*.rows.*.*' => ['nullable', 'string', 'max:5000'],
        ];
        foreach (self::NARRATIVES as $field) {
            $rules['terminal_data.'.$field] = [$required, 'string', 'max:100000'];
        }
        if (! $draft) {
            $rules['terminal_data.abstract'][] = function (string $attribute, mixed $value, Closure $fail): void {
                $count = self::wordCount((string) $value);
                if ($count < 200 || $count > 250) {
                    $fail('The abstract must contain 200–250 words (currently '.$count.').');
                }
            };
        }
        foreach (self::SIGNATORY_ROLES as $key => $role) {
            $rules['terminal_data.signatories.'.$key] = [$required, 'array:name,date_signed'];
            $rules['terminal_data.signatories.'.$key.'.name'] = [$required, 'string', 'max:255'];
            $rules['terminal_data.signatories.'.$key.'.date_signed'] = ['nullable', 'date', 'before_or_equal:today'];
        }

        return $rules;
    }

    public static function wordCount(string $value): int
    {
        return count(preg_split('/\s+/u', trim(html_entity_decode(strip_tags($value))), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
