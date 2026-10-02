<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\WorkPlanProposalObjectives;
use App\Support\WorkPlanRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProposalDraftWorkPlanRequest extends FormRequest
{
    /** @var list<string> */
    private array $linkedObjectives = [];

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

        $proposalObjectives = app(WorkPlanProposalObjectives::class);
        $this->linkedObjectives = $proposalObjectives->forDraft($proposalDraft);
        if ($this->linkedObjectives !== [] && is_array($this->input('entries', []))) {
            $this->merge(['entries' => $proposalObjectives->entries($this->input('entries', []), $this->linkedObjectives)]);
        }

        $this->merge([
            ...$proposalDraft->signatoryFields('work_plan'),
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
        $allowDraft = $this->allowsDraftValidation();
        $rules = WorkPlanRules::rules($allowDraft ? 'nullable' : 'required', $allowDraft);
        $rules['entries.*.objective'] = [$allowDraft ? 'nullable' : 'required', 'string', 'max:'.config('detailed_proposal.maximum_narrative_length')];

        return [
            ...$rules,
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'save_as_draft' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [...WorkPlanRules::afterCallbacks(
            $this->input('entries'),
            $this->input('total_duration_months'),
            $this->allowsDraftValidation(),
        ), function (Validator $validator): void {
            if ($this->linkedObjectives === [] && ! $this->allowsDraftValidation()) {
                $validator->errors()->add('entries', 'Save the specific objectives in the Detailed Proposal before preparing the Work Plan.');
            }
        }];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return WorkPlanRules::attributes();
    }

    private function allowsDraftValidation(): bool
    {
        return $this->routeIs('faculty.proposal-drafts.work-plan.update')
            && $this->boolean('save_as_draft');
    }
}
