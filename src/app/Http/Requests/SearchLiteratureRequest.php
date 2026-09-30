<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SearchLiteratureRequest extends FormRequest
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
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'context' => ['nullable', 'string', 'max:6000'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'min_citations' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'open_access' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $from = $this->integer('year_from');
            $to = $this->integer('year_to');

            if ($from && $to && $from > $to) {
                $validator->errors()->add('year_from', 'The starting year must be before or equal to the ending year.');
            }
        }];
    }
}
