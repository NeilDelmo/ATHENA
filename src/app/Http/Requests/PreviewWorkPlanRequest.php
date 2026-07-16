<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\WorkPlanRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewWorkPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ]) ?? false;
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
     * @return list<callable>
     */
    public function after(): array
    {
        return WorkPlanRules::afterCallbacks(
            $this->input('entries'),
            $this->input('total_duration_months'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return WorkPlanRules::attributes();
    }
}
