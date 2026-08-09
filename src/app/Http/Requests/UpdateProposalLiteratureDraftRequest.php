<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalLiteratureDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');
        $literatureLink = $this->route('proposalDraftLiteratureSource');

        return $proposalDraft instanceof ProposalDraft
            && $literatureLink instanceof ProposalDraftLiteratureSource
            && $literatureLink->proposal_draft_id === $proposalDraft->getKey()
            && $this->user()?->can('update', $proposalDraft) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'rrl_note' => ['required', 'string', 'min:40', 'max:5000'],
            'rrl_evidence_basis' => ['required', Rule::in(['abstract', 'full_text'])],
            'rrl_draft_status' => ['required', Rule::in([
                ProposalDraftLiteratureSource::DRAFT_SAVED,
                ProposalDraftLiteratureSource::DRAFT_CONFIRMED,
            ])],
        ];
    }
}
