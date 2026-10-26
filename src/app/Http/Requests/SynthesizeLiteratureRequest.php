<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SynthesizeLiteratureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ]) === true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('evidence_basis')) {
            $this->merge(['evidence_basis' => 'full_text']);
        }

        if (! $this->filled('connection_mode')) {
            $this->merge(['connection_mode' => 'auto']);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proposal_draft_id' => ['nullable', 'required_with:source_link_id', 'integer', 'min:1'],
            'source_link_id' => ['nullable', 'required_with:proposal_draft_id', 'integer', 'min:1'],
            'title' => ['required_without:source_link_id', 'nullable', 'string', 'max:500'],
            'authors' => ['nullable', 'string', 'max:1200'],
            'year' => ['nullable', 'integer', 'min:1500', 'max:'.now()->year],
            'abstract' => ['nullable', 'string', 'max:6000'],
            'is_open_access' => ['nullable', 'boolean'],
            'evidence_basis' => ['required', Rule::in(['abstract', 'full_text'])],
            'evidence_text' => ['prohibited'],
            'full_text_token' => ['nullable', 'string', 'max:8192'],
            'proposal_title' => ['nullable', 'string', 'max:500'],
            'preceding_rrl_context' => ['nullable', 'string', 'max:2500'],
            'connection_mode' => ['required', Rule::in(['auto', 'standalone'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $basis = $this->string('evidence_basis')->toString();
                $abstract = trim((string) $this->input('abstract'));
                if ($basis === 'abstract' && ! $this->filled('source_link_id') && mb_strlen($abstract) < 80) {
                    $validator->errors()->add('abstract', 'The available abstract is too short for a responsible synthesis.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'evidence_basis.required' => 'Choose whether the draft should use the indexed abstract or loaded open-access full text.',
            'evidence_text.min' => 'The loaded full text is too short for a responsible synthesis.',
        ];
    }
}
