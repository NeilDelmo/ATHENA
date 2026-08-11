<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachLiteratureSourceToProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && $this->user()?->can('update', $proposalDraft) === true;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'rrl_note' => ['nullable', 'string', 'min:40', 'max:5000'],
            'rrl_evidence_basis' => ['nullable', Rule::in(['abstract', 'full_text'])],
            'research_context' => ['nullable', 'array', 'max:5'],
            'research_context.*' => ['string', 'max:80'],
        ];
    }
}
