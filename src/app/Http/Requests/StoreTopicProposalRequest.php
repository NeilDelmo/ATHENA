<?php

namespace App\Http\Requests;

use App\Support\WorkPlanRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTopicProposalRequest extends FormRequest
{
    protected $errorBag = 'submission';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['faculty', 'faculty_researcher']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...WorkPlanRules::rules('required_without:work_plan'),
            'title' => ['required', 'string', 'max:255'],
            'research_call_id' => ['required', 'integer', 'exists:research_calls,id'],
            'detailed_proposal' => ['required_without:document', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'document' => ['nullable', 'required_without:detailed_proposal', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'work_plan' => ['nullable', 'required_without:entries', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'line_item_budget' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'expense_breakdown' => ['required', 'file', 'mimes:xls,xlsx', 'max:25600'],
            'curricula_vitae' => ['required', 'array', 'min:1', 'max:10'],
            'curricula_vitae.*' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'gad_checklist' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...WorkPlanRules::attributes(),
            'detailed_proposal' => 'detailed proposal',
            'document' => 'detailed proposal',
            'work_plan' => 'work plan',
            'line_item_budget' => 'line-item budget',
            'expense_breakdown' => 'expense breakdown',
            'curricula_vitae' => 'curriculum vitae files',
            'curricula_vitae.*' => 'curriculum vitae file',
            'gad_checklist' => 'GAD checklist',
        ];
    }
}
