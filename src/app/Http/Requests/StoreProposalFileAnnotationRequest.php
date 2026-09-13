<?php

namespace App\Http\Requests;

use App\Models\ProposalFileAnnotation;
use App\Models\ProposalVersionFile;
use App\Support\ProposalRevisionTargetCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalFileAnnotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(ProposalRevisionTargetCatalog $revisionTargets): array
    {
        $file = $this->route('file');
        $editorTargets = $file instanceof ProposalVersionFile
            ? collect($revisionTargets->forFile($file))->pluck('value')->all()
            : [];

        return [
            'annotation_type' => ['required', Rule::in([
                ProposalFileAnnotation::TYPE_TEXT,
                ProposalFileAnnotation::TYPE_AREA,
                ProposalFileAnnotation::TYPE_PIN,
            ])],
            'page_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'selected_text' => ['nullable', 'required_if:annotation_type,text', 'string', 'max:5000'],
            'rectangles' => ['required', 'array', 'min:1', 'max:100', Rule::when($this->input('annotation_type') === ProposalFileAnnotation::TYPE_PIN, 'size:1')],
            'rectangles.*' => ['required', 'array:x,y,width,height'],
            'rectangles.*.x' => ['required', 'numeric', 'between:0,1'],
            'rectangles.*.y' => ['required', 'numeric', 'between:0,1'],
            'rectangles.*.width' => ['required', 'numeric', 'gt:0', 'max:1'],
            'rectangles.*.height' => ['required', 'numeric', 'gt:0', 'max:1'],
            'comment' => ['required', 'string', 'max:5000'],
            'editor_target' => ['nullable', 'string', Rule::in($editorTargets)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'editor_target.in' => 'Choose a field from this paper, or select paper-level feedback.',
        ];
    }
}
