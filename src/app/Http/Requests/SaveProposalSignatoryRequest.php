<?php

namespace App\Http\Requests;

use App\Models\ProposalSignatory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProposalSignatoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_key' => ['required', Rule::in(array_keys(ProposalSignatory::roles()))],
            'name' => ['required', 'string', 'max:120'],
            'position' => ['required', 'string', 'max:120'],
            'active' => ['required', 'boolean'],
        ];
    }
}
