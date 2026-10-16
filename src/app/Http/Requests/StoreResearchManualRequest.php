<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreResearchManualRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('research_head')
            && $this->user()->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'edition' => ['required', 'string', 'max:100'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'manual' => ['required', 'file', 'mimes:pdf', 'max:25600'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'manual.mimes' => 'Upload a PDF research manual.',
            'manual.max' => 'The research manual may not be larger than 25 MB.',
        ];
    }
}
