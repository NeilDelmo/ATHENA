<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssistProposalLiteratureEvidenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft && $this->user()?->can('update', $draft) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['explain', 'support', 'synthesize'])],
            'claim' => ['required_if:mode,support', 'nullable', 'string', 'max:2000'],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'],
            'evidence.*' => ['required', 'array:source_link_id,passage_ids'],
            'evidence.*.source_link_id' => ['required', 'integer', 'distinct'],
            'evidence.*.passage_ids' => ['required', 'array', 'min:1', 'max:12'],
            'evidence.*.passage_ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
