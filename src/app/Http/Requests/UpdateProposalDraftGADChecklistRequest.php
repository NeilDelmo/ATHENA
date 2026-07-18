<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalDraftGADChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && ($this->user()?->can('update', $proposalDraft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $proposalDraft = $this->route('proposalDraft');

        if (! $proposalDraft instanceof ProposalDraft) {
            return;
        }

        $this->merge([
            'project_title' => $proposalDraft->project_title,
            'project_leader' => $proposalDraft->project_leader,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'project_title' => ['required', 'string', 'max:500'],
            'project_leader' => ['required', 'string', 'max:255'],
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_title' => 'project title',
            'project_leader' => 'project leader',
            'document_version' => 'document version',
        ];
    }
}
