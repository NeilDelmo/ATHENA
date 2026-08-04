<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SynthesizeLiteratureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ]) === true;
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
            'abstract' => ['required', 'string', 'min:80', 'max:6000'],
            'is_open_access' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'abstract.required' => 'This record has no abstract to synthesize. Save it for later and review the paper manually.',
            'abstract.min' => 'The available abstract is too short for a responsible synthesis.',
        ];
    }
}
