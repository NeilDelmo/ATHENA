<?php

namespace App\Http\Requests;

use App\Services\AccessibleRecordSearch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->activeWorkspace() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', Rule::in(array_keys(AccessibleRecordSearch::CATEGORIES))],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
