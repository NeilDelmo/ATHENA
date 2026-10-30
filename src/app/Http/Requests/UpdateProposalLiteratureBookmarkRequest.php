<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalLiteratureBookmarkRequest extends FormRequest
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

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_saved' => ['required', 'boolean'],
        ];
    }
}
