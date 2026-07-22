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
        $isSupplemental = $this->input('purpose') === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL;

        return [
            'source_file_id' => [
                Rule::requiredIf(! $isSupplemental),
                'nullable',
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
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SUPPLEMENTAL,
                ]),
            ],
            'document_title' => [Rule::requiredIf($isSupplemental), 'nullable', 'string', 'max:255'],
            'issuing_office' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_file_id.exists' => 'Choose a faculty-submitted file from the latest proposal version.',
            'source_file_id.required' => 'Choose the faculty-submitted file this upload belongs to.',
            'review_file.required' => 'Select the reviewed or signed file to upload.',
            'review_file.mimes' => 'The upload must be a PDF, Word, or Excel document.',
            'review_file.max' => 'The upload may not be larger than 25 MB.',
            'purpose.in' => 'Choose whether this is a revision copy, signed copy, or supplemental paper.',
            'document_title.required' => 'Enter a title for the supplemental paper.',
        ];
    }
}
