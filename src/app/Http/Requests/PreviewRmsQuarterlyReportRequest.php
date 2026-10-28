<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class PreviewRmsQuarterlyReportRequest extends SaveRmsQuarterlyReportRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'campus' => ['nullable', 'string', 'max:255']];
    }
}
