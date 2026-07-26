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

        $draft->loadMissing('owner:id,college');

        $savedSource = $draft->documents()
            ->where('document_type', config('proposal_papers.line-item-budget.document_type'))
            ->where('position', 0)
            ->value('source_data');

        $merged = [
            ...(is_array($savedSource) ? array_replace($savedSource, $this->all()) : []),
            'project_title' => $draft->project_title,
            'planned_start' => $draft->planned_start?->toDateString(),
            'planned_end' => $draft->planned_end?->toDateString(),
            'project_leader' => $draft->project_leader,
        ];

        if (blank($merged['leader_college'] ?? null)) {
            $merged['leader_college'] = (string) ($draft->owner?->college ?? $this->user()?->college ?? '');
        }

        $this->merge($merged);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...LineItemBudgetRules::rules($this->allowsDraftValidation()),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'save_as_draft' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        $draft = $this->route('proposalDraft');
        $maximumBudget = $draft instanceof ProposalDraft
            ? (float) ($draft->researchCall()->value('maximum_budget') ?? 0)
            : 0;

        return LineItemBudgetRules::afterCallbacks(
            $maximumBudget,
            $this->routeIs('faculty.proposal-drafts.line-item-budget.update') && $this->boolean('save_as_draft'),
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return LineItemBudgetRules::attributes();
    }

    private function allowsDraftValidation(): bool
    {
        return $this->routeIs('faculty.proposal-drafts.line-item-budget.preview')
            || ($this->routeIs('faculty.proposal-drafts.line-item-budget.update') && $this->boolean('save_as_draft'));
    }
}
