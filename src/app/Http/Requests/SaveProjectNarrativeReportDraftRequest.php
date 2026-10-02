<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use App\Support\ProgressReportData;
use App\Support\TerminalReportData;
use App\Support\TerminalReportRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectNarrativeReportDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->isMonitoringAvailable()
            && $this->user() !== null
            && $topic->isAccessibleTo($this->user());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'reporting_date' => ['nullable', 'date_format:Y-m-d'],
            'draft_version' => ['required', 'integer', 'min:0'],
            'report_type' => ['sometimes', Rule::in(['progress', 'terminal'])],
            'submission_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'researchers' => ['nullable', 'string', 'max:1000'],
            'implementation_start' => ['nullable', 'date'],
            'implementation_end' => ['nullable', 'date', 'after_or_equal:implementation_start'],
            'funding_agency' => ['nullable', 'string', 'max:255'],
            'accomplishments' => ['nullable', 'array', 'max:1000'],
            'accomplishments.*.objective' => ['nullable', 'string', 'max:1000'],
            'accomplishments.*.target' => ['nullable', 'string', 'max:2000'],
            'accomplishments.*.actual' => ['nullable', 'string', 'max:2000'],
            'introduction' => ['nullable', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'rationale' => ['nullable', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'objectives' => ['nullable', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'methodology' => ['nullable', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'results_discussion' => ['nullable', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')],
            'prepared_by_date_signed' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        if ($this->input('report_type') !== 'terminal') {
            $rules['figures'] = ['nullable', 'array'];
            $rules['figures.*'] = ['array:caption,section,after_paragraph'];
            $rules['figures.*.caption'] = ['nullable', 'string', 'max:1000'];
            $rules['figures.*.section'] = ['nullable', Rule::in(['methodology', 'results_discussion'])];
            $rules['figures.*.after_paragraph'] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }
        foreach (app(ProgressReportData::class)->legacyFigureIndexes($this->all()) as $index) {
            $rules['photo_caption_'.$index] = ['nullable', 'string', 'max:1000'];
            $rules['photo_section_'.$index] = ['nullable', Rule::in(['methodology', 'results_discussion'])];
            $rules['photo_after_paragraph_'.$index] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        if ($this->input('report_type') === 'terminal') {
            $topic = $this->route('topic');
            $approvedBudget = $topic instanceof TopicProposal
                ? (float) ($topic->latestVersion?->estimated_budget ?? $topic->estimated_budget ?? 0)
                : null;
            $rules = array_merge($rules, TerminalReportRules::rules(true, $approvedBudget));
            foreach (['introduction', 'rationale', 'methodology', 'results_discussion'] as $field) {
                $rules[$field] = ['nullable', 'string', 'max:100000'];
            }
            $rules['researchers'] = ['nullable', 'string', 'max:10000'];
            $rules['funding_agency'] = ['nullable', 'string', 'max:255'];
            $rules['objectives'] = ['nullable', 'string', 'max:10000'];
            $rules['accomplishments'] = ['nullable', 'array', 'max:30'];
            $evidenceKeys = array_keys(app(TerminalReportData::class)->evidence($this->route('topic')));
            $rules['reuse_cover_image'] = ['nullable', Rule::in($evidenceKeys)];
            $rules['cover_image_caption'] = ['nullable', 'string', 'max:200'];
            foreach (range(1, 30) as $index) {
                $rules['reuse_photo_'.$index] = ['nullable', Rule::in($evidenceKeys)];
                $rules['photo_after_paragraph_'.$index] = ['nullable', 'integer', 'min:0', 'max:1000'];
                $rules['photo_section_'.$index] = ['nullable', Rule::in(['methodology', 'results_discussion'])];
                $rules['photo_caption_'.$index] = ['nullable', 'string', 'max:200'];
                if (! true) {
                    $rules['photo_'.$index] = ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
                    $rules['photo_caption_'.$index][] = 'required_with:photo_'.$index.',reuse_photo_'.$index;
                    $rules['photo_section_'.$index][] = 'required_with:photo_'.$index.',reuse_photo_'.$index;
                }
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'terminal_data.total_expenditure.max' => 'The final total expenditure may not exceed the approved project budget.',
        ];
    }
}
