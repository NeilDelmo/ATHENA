<?php

namespace App\Http\Requests;

use App\Support\WorkPlanRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewWorkPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['faculty', 'faculty_researcher']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return WorkPlanRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return WorkPlanRules::attributes();
    }
}
