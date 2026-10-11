<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\CurriculumVitaeRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cvRules = [];

        foreach (CurriculumVitaeRules::rules(true) as $field => $rules) {
            if (Str::startsWith($field, ['people.*.agency', 'people.*.academic_background', 'people.*.specializations'])) {
                $cvRules[Str::replaceFirst('people.*.', 'cv_defaults.', $field)] = $rules;
            }
        }

        return [
            'college' => ['required', 'string', Rule::in(User::COLLEGES)],
            'contact_number' => ['nullable', 'string', 'digits:11'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'professional_title' => ['sometimes', 'nullable', 'string', 'max:50'],
            'cv_defaults' => ['sometimes', 'nullable', 'array:agency,academic_background,specializations'],
            ...$cvRules,
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user?->hasRole('research_coordinator') || $validator->errors()->has('college')) {
                    return;
                }

                if ($this->string('college')->toString() !== $user->college) {
                    $validator->errors()->add('college', 'Remove your Research Coordinator assignment before changing your college.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'contact_number' => 'contact number',
            'professional_title' => 'professional title',
            'cv_defaults.agency' => 'agency',
            'cv_defaults.academic_background' => 'academic background',
            'cv_defaults.specializations' => 'specializations',
        ];
    }
}
