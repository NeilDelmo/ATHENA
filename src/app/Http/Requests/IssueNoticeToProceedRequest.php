<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueNoticeToProceedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notice_date' => ['required', 'date'],
            'researcher_names' => ['required', 'array', 'min:1', 'max:20'],
            'researcher_names.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'campus_line' => ['required', 'string', 'max:255'],
            'project_title' => ['required', 'string', 'max:500'],
            'resolution_number' => ['required', 'string', 'max:50'],
            'resolution_year' => ['required', 'integer', 'between:2000,2100'],
            'approved_start_date' => ['required', 'date'],
            'approved_end_date' => ['required', 'date', 'after_or_equal:approved_start_date'],
            'approved_duration_months' => ['required', 'integer', 'between:1,120'],
            'approved_budget' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'issuing_officer_name' => ['required', 'string', 'max:255'],
            'issuing_officer_title' => ['required', 'string', 'max:255'],
            'issuing_officer_committee_role' => ['required', 'string', 'max:255'],
            'verifying_officer_name' => ['required', 'string', 'max:255'],
            'verifying_officer_title' => ['required', 'string', 'max:255'],
            'verifying_officer_committee_role' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'researcher_names.required' => 'Add at least one researcher to the notice.',
            'researcher_names.*.required' => 'Each researcher row must contain a name.',
            'researcher_names.*.distinct' => 'Each researcher may only appear once.',
            'approved_end_date.after_or_equal' => 'The approved end date must be on or after the approved start date.',
        ];
    }
}
