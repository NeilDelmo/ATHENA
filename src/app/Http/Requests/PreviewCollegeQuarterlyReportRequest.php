<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class PreviewCollegeQuarterlyReportRequest extends SaveCollegeQuarterlyReportRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.*.') && ! str_ends_with($field, '.id')) {
                $rules[$field] = array_map(fn (mixed $rule): mixed => $rule === 'required' ? 'nullable' : $rule, $fieldRules);
            }
        }

        return $rules;
    }
}
