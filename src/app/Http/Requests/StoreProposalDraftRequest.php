<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProposalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProposalDraft::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_title' => ['required', 'string', 'max:255'],
            'research_call_id' => ['exclude'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_title' => 'project title',
            'research_call_id' => 'research call',
        ];
    }
}
