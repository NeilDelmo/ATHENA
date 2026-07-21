<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\ExpenseBreakdownRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftExpenseBreakdownRequest extends FormRequest
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
            ->where('document_type', config('proposal_papers.expense-breakdown.document_type'))
            ->where('position', 0)
            ->value('source_data');

        $this->merge([
            ...(is_array($savedSource) ? array_replace($savedSource, $this->all()) : []),
            'project_title' => $draft->project_title,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...ExpenseBreakdownRules::rules(),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ExpenseBreakdownRules::attributes();
    }
}
