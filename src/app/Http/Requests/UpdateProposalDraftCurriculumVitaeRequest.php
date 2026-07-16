<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\CurriculumVitaeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftCurriculumVitaeRequest extends FormRequest
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
            ->where('document_type', config('proposal_papers.curriculum-vitae.document_type'))
            ->where('position', 0)
            ->value('source_data');

        if (is_array($savedSource)) {
            $this->merge(array_replace($savedSource, $this->all()));
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return CurriculumVitaeRules::rules();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return CurriculumVitaeRules::attributes();
    }
}
