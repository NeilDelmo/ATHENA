<?php

namespace App\Http\Requests;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResearchHeadTopicStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'approved',
                TopicProposal::STATUS_READY_FOR_SIGNATURE,
                'revision_requested',
                'rejected',
            ])],
            'redirect_to' => ['nullable', Rule::in(['topic'])],
            'revision_file_ids' => ['nullable', 'array'],
            'revision_file_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('proposal_version_files', 'id')
                    ->where(fn ($query) => $query->where(
                        'document_type',
                        '!=',
                        ProposalVersionFile::TYPE_HEAD_UPLOAD,
                    )),
            ],
            'revision_file_notes' => ['nullable', 'array'],
            'revision_file_notes.*' => ['nullable', 'string', 'max:2000'],
            'signature_file_ids' => [
                'nullable',
                'required_if:status,'.TopicProposal::STATUS_READY_FOR_SIGNATURE,
                'array',
                'min:1',
            ],
            'signature_file_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('proposal_version_files', 'id')
                    ->where(fn ($query) => $query->where(
                        'document_type',
                        '!=',
                        ProposalVersionFile::TYPE_HEAD_UPLOAD,
                    )),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'signature_file_ids.required_if' => 'Select at least one paper that actually needs a signed final PDF.',
            'signature_file_ids.min' => 'Select at least one paper that actually needs a signed final PDF.',
        ];
    }
}
