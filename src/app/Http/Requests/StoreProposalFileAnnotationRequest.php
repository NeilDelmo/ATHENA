<?php

namespace App\Http\Requests;

use App\Models\ProposalFileAnnotation;
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
    public function rules(): array
    {
        return [
            'annotation_type' => ['required', Rule::in([
                ProposalFileAnnotation::TYPE_TEXT,
                ProposalFileAnnotation::TYPE_AREA,
            ])],
            'page_number' => ['required', 'integer', 'min:1', 'max:10000'],
            'selected_text' => ['nullable', 'required_if:annotation_type,text', 'string', 'max:5000'],
            'rectangles' => ['required', 'array', 'min:1', 'max:100'],
            'rectangles.*' => ['required', 'array:x,y,width,height'],
            'rectangles.*.x' => ['required', 'numeric', 'between:0,1'],
            'rectangles.*.y' => ['required', 'numeric', 'between:0,1'],
            'rectangles.*.width' => ['required', 'numeric', 'gt:0', 'max:1'],
            'rectangles.*.height' => ['required', 'numeric', 'gt:0', 'max:1'],
            'comment' => ['required', 'string', 'max:5000'],
        ];
    }
}
