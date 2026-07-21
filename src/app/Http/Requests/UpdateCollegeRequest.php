<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollegeRequest extends FormRequest
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
        return [
            'college' => ['required', 'string', Rule::in(User::COLLEGES)],
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
}
