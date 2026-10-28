<?php

namespace App\Http\Requests;

use App\Models\ProjectNarrativeReport;
use App\Models\ProjectProgressReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewImplementationReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $report = $this->route('report');

        return ($report instanceof ProjectProgressReport || $report instanceof ProjectNarrativeReport)
            && $report->isSubmitted()
            && $report->topic->canReviewImplementationReports($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['forward', 'revision_requested'])],
            'secretary_remarks' => ['nullable', 'required_if:decision,revision_requested', 'string', 'max:5000'],
        ];
    }
}
