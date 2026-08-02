<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectNarrativeReportRequest extends FormRequest
{
    protected $errorBag = 'narrativeProgress';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->isMonitoringAvailable()
            && $topic->user_id === $this->user()?->id;
    }

    protected function prepareForValidation(): void
    {
        $accomplishments = collect($this->input('accomplishments', []))
            ->filter(fn (mixed $row): bool => is_array($row) && collect($row)->contains(fn (mixed $value): bool => filled($value)))
            ->values()
            ->all();

        $this->merge(['accomplishments' => $accomplishments]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'submission_date' => ['required', 'date', 'before_or_equal:today'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'researchers' => ['required', 'string', 'max:1000'],
            'implementation_start' => ['required', 'date'],
            'implementation_end' => ['required', 'date', 'after_or_equal:implementation_start'],
            'funding_agency' => ['required', 'string', 'max:255'],
            'accomplishments' => ['required', 'array', 'min:1', 'max:'.config('progress_report.max_accomplishments')],
            'accomplishments.*.objective' => ['required', 'string', 'max:1000'],
            'accomplishments.*.target' => ['required', 'string', 'max:2000'],
            'accomplishments.*.actual' => ['required', 'string', 'max:2000'],
            'introduction' => ['required', 'string', 'max:5000'],
            'rationale' => ['required', 'string', 'max:5000'],
            'objectives' => ['required', 'string', 'max:5000'],
            'methodology' => ['required', 'string', 'max:5000'],
            'results_discussion' => ['required', 'string', 'max:5000'],
            'prepared_by_date_signed' => ['nullable', 'date', 'before_or_equal:today'],
        ];

        foreach (range(1, (int) config('progress_report.max_figures')) as $index) {
            $required = $index === 1 ? 'required' : 'nullable';
            $captionRules = [$required, 'string', 'max:200'];
            $sectionRules = [$required, Rule::in(['methodology', 'results_discussion'])];

            if ($index > 1) {
                $captionRules[] = 'required_with:photo_'.$index;
                $sectionRules[] = 'required_with:photo_'.$index;
            }

            $rules['photo_'.$index] = [$required, 'image', 'mimes:jpg,jpeg,png', 'max:10240'];
            $rules['photo_caption_'.$index] = $captionRules;
            $rules['photo_section_'.$index] = $sectionRules;
        }

        return $rules;
    }
}
