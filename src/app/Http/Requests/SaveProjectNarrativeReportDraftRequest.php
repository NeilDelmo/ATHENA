<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
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
            'draft_version' => ['required', 'integer', 'min:0'],
            'submission_date' => ['nullable', 'date', 'before_or_equal:today'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'researchers' => ['nullable', 'string', 'max:1000'],
            'implementation_start' => ['nullable', 'date'],
            'implementation_end' => ['nullable', 'date', 'after_or_equal:implementation_start'],
            'funding_agency' => ['nullable', 'string', 'max:255'],
            'accomplishments' => ['nullable', 'array', 'max:'.config('progress_report.max_accomplishments')],
            'accomplishments.*.objective' => ['nullable', 'string', 'max:1000'],
            'accomplishments.*.target' => ['nullable', 'string', 'max:2000'],
            'accomplishments.*.actual' => ['nullable', 'string', 'max:2000'],
            'introduction' => ['nullable', 'string', 'max:5000'],
            'rationale' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'methodology' => ['nullable', 'string', 'max:5000'],
            'results_discussion' => ['nullable', 'string', 'max:5000'],
            'prepared_by_date_signed' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        foreach (range(1, (int) config('progress_report.max_figures')) as $index) {
            $rules['photo_caption_'.$index] = ['nullable', 'string', 'max:200'];
            $rules['photo_section_'.$index] = ['nullable', Rule::in(['methodology', 'results_discussion'])];
        }

        return $rules;
    }
}
