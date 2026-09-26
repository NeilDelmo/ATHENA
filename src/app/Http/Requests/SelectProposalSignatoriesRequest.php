<?php

namespace App\Http\Requests;

use App\Models\ProposalSignatory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectProposalSignatoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('proposalDraft')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'lock_version' => ['required', 'integer'],
            'return_paper' => ['nullable', Rule::in(array_keys(ProposalSignatory::FIELDS))],
            'signatories' => ['required', 'array:'.implode(',', array_keys(ProposalSignatory::roles()))],
        ];
        foreach (ProposalSignatory::roles() as $key => $label) {
            $rules['signatories.'.$key] = ['nullable', 'integer', Rule::exists('proposal_signatories', 'id')->where('role_key', $key)->where('active', true)];
        }

        return $rules;
    }
}
