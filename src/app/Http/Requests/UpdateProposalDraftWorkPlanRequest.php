<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\WorkPlanRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftWorkPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && ($this->user()?->can('update', $proposalDraft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $proposalDraft = $this->route('proposalDraft');

        if (! $proposalDraft instanceof ProposalDraft) {
            return;
        }

        $this->merge([
            'project_title' => $proposalDraft->project_title,
            'total_duration_months' => $proposalDraft->duration_months,
            'planned_start' => $proposalDraft->planned_start?->toDateString(),
            'planned_end' => $proposalDraft->planned_end?->toDateString(),
            'prepared_by' => $proposalDraft->project_leader,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...WorkPlanRules::rules(),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return WorkPlanRules::afterCallbacks(
            $this->input('entries'),
            $this->input('total_duration_months'),
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return WorkPlanRules::attributes();
    }
}
