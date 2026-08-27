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
            $this->merge(['evidence_basis' => 'abstract']);
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
            'title' => ['required', 'string', 'max:500'],
            'authors' => ['nullable', 'string', 'max:1200'],
            'year' => ['nullable', 'integer', 'min:1500', 'max:'.now()->year],
            'abstract' => ['nullable', 'string', 'max:6000'],
            'is_open_access' => ['nullable', 'boolean'],
            'evidence_basis' => ['required', Rule::in(['abstract', 'full_text'])],
            'evidence_text' => ['nullable', 'string', 'min:500', 'max:30000'],
            'proposal_title' => ['nullable', 'string', 'max:500'],
            'preceding_rrl_context' => ['nullable', 'string', 'max:2500'],
            'connection_mode' => ['required', Rule::in(['auto', 'standalone'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $basis = $this->string('evidence_basis')->toString();
                $abstract = trim((string) $this->input('abstract'));
                $evidenceText = trim((string) $this->input('evidence_text'));

                if ($basis === 'abstract' && mb_strlen($abstract) < 80) {
                    $validator->errors()->add('abstract', 'The available abstract is too short for a responsible synthesis.');
                }

                if ($basis === 'full_text' && mb_strlen($evidenceText) < 500) {
                    $validator->errors()->add('evidence_text', 'Load a usable open-access full-text preview before generating from full text.');
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
