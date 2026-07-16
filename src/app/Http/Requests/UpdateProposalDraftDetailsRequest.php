<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft !== null
            && ($this->user()?->can('update', $proposalDraft) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_title' => ['required', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:12'],
            'planned_start' => ['required', 'date'],
            'planned_end' => ['required', 'date', 'after_or_equal:planned_start'],
            'project_leader' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_title' => 'project title',
            'duration_months' => 'project duration',
            'planned_start' => 'planned start date',
            'planned_end' => 'planned end date',
            'project_leader' => 'project leader',
        ];
    }
}
