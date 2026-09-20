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
                TopicProposal::STATUS_READY_FOR_SIGNATURE,
                TopicProposal::STATUS_GAD_REVIEW,
                TopicProposal::STATUS_LREC_QUEUED,
                TopicProposal::STATUS_LREC_REVIEW,
                'revision_requested',
                'rejected',
            ])],
            'research_head_clearance_confirmed' => ['exclude_unless:status,gad_review', 'accepted'],
            'initial_clearance_confirmed' => ['exclude_unless:status,lrec_queued', 'accepted'],
            'lrec_clearance_confirmed' => ['exclude_unless:status,ready_for_signature', 'accepted'],
            'committee_comments' => ['exclude_unless:status,revision_requested', 'nullable', 'array', 'max:100'],
            'committee_comments.*' => ['array:reviewer,comment,location'],
            'committee_comments.*.reviewer' => ['required', 'string', 'max:160'],
            'committee_comments.*.comment' => ['required', 'string', 'max:5000'],
            'committee_comments.*.location' => ['nullable', 'string', 'max:300'],
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
            'rejection_reason' => [
                'exclude_unless:status,rejected',
                'required',
                'string',
                'max:2000',
            ],
            'rejection_confirmed' => [
                'exclude_unless:status,rejected',
                'accepted',
            ],
            'signature_file_ids' => ['exclude'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Provide a clear rejection reason before finalizing this decision.',
            'rejection_confirmed.accepted' => 'Confirm that this rejection is final before continuing.',
        ];
    }
}
