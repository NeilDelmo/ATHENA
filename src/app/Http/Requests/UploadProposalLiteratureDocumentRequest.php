<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadProposalLiteratureDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');
        $source = $this->route('proposalDraftLiteratureSource');

        return $draft instanceof ProposalDraft && $source instanceof ProposalDraftLiteratureSource
            && $source->proposal_draft_id === $draft->getKey()
            && $this->user()?->can('update', $draft) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimetypes:application/pdf,application/x-pdf', 'extensions:pdf', 'max:'.config('literature.evidence.maximum_pdf_kilobytes')],
        ];
    }
}
