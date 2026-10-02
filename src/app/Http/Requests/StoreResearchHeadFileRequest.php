<?php

namespace App\Http\Requests;

use App\Models\ProposalVersionFile;
use App\Models\TopicProposal;
use App\Support\InitialScreeningSubmissionOrder;
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

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('narrative_evaluation'))) {
            $this->merge([
                'narrative_evaluation' => trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $this->input('narrative_evaluation'))),
            ]);
        }
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
        $isSignedCopy = $this->input('purpose') === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED;
        $isEvaluation = $this->input('purpose') === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION;
        $isGadAssessment = $this->input('purpose') === ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT;
        $hasManualNarrative = $isEvaluation && filled($this->input('narrative_evaluation'));

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
                File::types($isSignedCopy
                    ? ['pdf']
                    : ['pdf', 'docx'])->max('25mb'),
            ],
            'purpose' => [
                'required',
                Rule::in([
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED,
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
                    ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
                ]),
            ],
            'co_evaluator_name' => [Rule::requiredIf($isEvaluation), 'nullable', 'string', 'max:160'],
            'recommended_action' => [
                Rule::requiredIf($isEvaluation),
                'nullable',
                Rule::in(InitialScreeningSubmissionOrder::recommendations()),
            ],
            'narrative_evaluation' => $isEvaluation
                ? ['nullable', 'string', 'min:3', 'max:5000']
                : ['prohibited'],
            'narrative_evaluation_confirmed' => $isEvaluation
                ? ($hasManualNarrative ? ['required', 'accepted'] : ['nullable', 'boolean'])
                : ['prohibited'],
            'gad_signature_confirmed' => $isGadAssessment
                ? ['required', 'accepted']
                : ['prohibited'],
            'gad_score' => $isGadAssessment
                ? ['nullable', 'numeric', 'between:0,20']
                : ['prohibited'],
            'document_title' => ['prohibited'],
            'issuing_office' => ['prohibited'],
            'note' => ['nullable', 'string', 'max:2000'],
            'return_to_review' => ['sometimes', 'boolean'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->boolean('return_to_review') && in_array($this->input('purpose'), [
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT,
            ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION,
        ], true)) {
            return route('topics.show', $this->route('topic')).'#initial-review-workflow';
        }

        return parent::getRedirectUrl();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'source_file_id.exists' => 'Choose a faculty-submitted file from the latest proposal version.',
            'source_file_id.required' => 'Choose the faculty-submitted file this upload belongs to.',
            'review_file.required' => 'Select the file to upload.',
            'review_file.mimes' => match ($this->input('purpose')) {
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED => 'The signed final copy must be a PDF.',
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION => 'The completed Initial Screening Form must be a PDF or DOCX document.',
                ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT => 'The completed GAD Checklist must be a PDF or DOCX document.',
                default => 'Upload the review document required for the current stage.',
            },
            'review_file.max' => 'The upload may not be larger than 25 MB.',
            'purpose.in' => 'Only the completed GAD Checklist, co-evaluator Initial Screening Form, or required signed copy can be uploaded through this review workflow.',
            'co_evaluator_name.required' => 'Enter the co-evaluator’s name for the completed Initial Screening Form.',
            'recommended_action.required' => 'Record the Recommended Action selected on the completed Initial Screening Form.',
            'recommended_action.in' => 'Choose a valid Recommended Action from the completed Initial Screening Form.',
            'narrative_evaluation.string' => 'Enter the Narrative Evaluation as text copied from the completed form.',
            'narrative_evaluation.min' => 'Enter at least 3 characters from the completed Narrative Evaluation.',
            'narrative_evaluation.max' => 'The Narrative Evaluation may not exceed 5,000 characters.',
            'narrative_evaluation_confirmed.required' => 'Check the transcription against all pages of the uploaded form and confirm that it matches.',
            'narrative_evaluation_confirmed.accepted' => 'Check the transcription against all pages of the uploaded form and confirm that it matches.',
            'gad_signature_confirmed.required' => 'Preview the completed GAD Checklist and confirm that the GAD verifier’s signature is present.',
            'gad_signature_confirmed.accepted' => 'Preview the completed GAD Checklist and confirm that the GAD verifier’s signature is present.',
            'gad_score.numeric' => 'Enter the GAD score shown on the scanned checklist.',
            'gad_score.between' => 'The GAD score must be between 0 and 20.',
        ];
    }
}
