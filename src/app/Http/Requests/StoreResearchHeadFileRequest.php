<?php

namespace App\Http\Requests;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreResearchHeadFileRequest extends FormRequest
{
    protected $errorBag = 'headUpload';

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
        $topic = $this->route('topic');
        $latestVersionId = $topic instanceof TopicProposal
            ? $topic->latestVersion()->value('id')
            : null;

        return [
            'source_file_id' => [
                'required',
                'integer',
                Rule::exists('proposal_version_files', 'id')->where(
                    fn ($query) => $query
                        ->where('proposal_version_id', $latestVersionId ?? 0)
                        ->where('document_type', '!=', ProposalVersionFile::TYPE_HEAD_UPLOAD),
                ),
            ],
            'review_file' => [
                'required',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx'])->max('25mb'),
            ],
            'purpose' => [
                'required',
                Rule::in([
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_REVISION,
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                ]),
            ],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_file_id.exists' => 'Choose a faculty-submitted file from the latest proposal version.',
            'review_file.required' => 'Select the reviewed or signed file to upload.',
            'review_file.mimes' => 'The upload must be a PDF, Word, or Excel document.',
            'review_file.max' => 'The upload may not be larger than 25 MB.',
            'purpose.in' => 'Choose whether this file is for revision or is a signed copy.',
        ];
    }
}
