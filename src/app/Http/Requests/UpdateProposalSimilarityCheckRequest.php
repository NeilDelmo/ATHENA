<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalSimilarityCheckRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'similarity_score' => ['nullable', 'numeric', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'report' => ['required_if:status,completed', 'nullable', 'file', 'mimes:pdf', 'max:25600', 'prohibited_unless:status,completed'],
        ];
    }
}
