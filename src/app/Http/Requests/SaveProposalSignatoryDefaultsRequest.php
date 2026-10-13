<?php

namespace App\Http\Requests;

use App\Models\ProposalSignatory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProposalSignatoryDefaultsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace(['research_head', 'research_secretary']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roles = implode(',', array_keys(ProposalSignatory::roles()));

        return [
            'defaults' => ['required', 'array:'.$roles, 'required_array_keys:'.$roles],
            'defaults.*' => ['nullable', 'string', Rule::in(array_keys(ProposalSignatory::assignmentOptions()))],
        ];
    }
}
