<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VcrdesMonitoringRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace(User::WORKSPACE_VCRDES) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'],
            'college' => ['nullable', 'string', Rule::in(array_keys(User::COLLEGES))],
            'status' => ['nullable', 'string', Rule::in(['ongoing', 'delayed', 'awaiting', 'completed'])],
        ];
    }
}
