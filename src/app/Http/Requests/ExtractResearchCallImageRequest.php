<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtractResearchCallImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'reference_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_image.required' => 'Choose a research-call image to analyze.',
            'reference_image.image' => 'The research-call reference must be a valid image.',
            'reference_image.mimes' => 'The research-call reference must be a JPG, PNG, or WebP image.',
            'reference_image.max' => 'The research-call reference may not be larger than 10 MB.',
        ];
    }
}
