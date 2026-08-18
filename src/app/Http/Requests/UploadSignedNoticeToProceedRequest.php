<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadSignedNoticeToProceedRequest extends FormRequest
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
            'signed_notice_to_proceed' => ['required', File::types(['pdf'])->max('25mb')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'signed_notice_to_proceed.required' => 'Select the signed Notice to Proceed PDF to upload.',
            'signed_notice_to_proceed.mimes' => 'The signed Notice to Proceed must be a PDF.',
            'signed_notice_to_proceed.max' => 'The signed Notice to Proceed may not be larger than 25 MB.',
        ];
    }
}
