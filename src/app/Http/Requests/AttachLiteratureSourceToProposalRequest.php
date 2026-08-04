<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
