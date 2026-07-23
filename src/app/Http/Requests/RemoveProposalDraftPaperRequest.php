<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RemoveProposalDraftPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && ($this->user()?->can('update', $proposalDraft) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
