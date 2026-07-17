<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\LineItemBudgetRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftLineItemBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft
            && ($this->user()?->can('update', $draft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $draft = $this->route('proposalDraft');

        if (! $draft instanceof ProposalDraft) {
            return;
        }

        $savedSource = $draft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->value('source_data');

        $this->merge([
            ...(is_array($savedSource) ? array_replace($savedSource, $this->all()) : []),
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'project_leader' => $draft->project_leader,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...LineItemBudgetRules::rules(),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        $draft = $this->route('proposalDraft');
        $maximumBudget = $draft instanceof ProposalDraft
            ? (float) ($draft->researchCall()->value('maximum_budget') ?? 0)
            : 0;

        return LineItemBudgetRules::afterCallbacks($maximumBudget);
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return LineItemBudgetRules::attributes();
    }
}
