<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreResearchCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace('research_head') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'max:30'],
            'term' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'reference_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'initial_evaluation_start_date' => ['nullable', 'date'],
            'initial_evaluation_end_date' => ['nullable', 'date'],
            'paper_revisions_start_date' => ['nullable', 'date'],
            'paper_revisions_end_date' => ['nullable', 'date'],
            'lrec_start_date' => ['nullable', 'date'],
            'lrec_end_date' => ['nullable', 'date'],
            'implementation_start_date' => ['nullable', 'date'],
            'implementation_end_date' => ['nullable', 'date'],
            'max_active_research_per_faculty' => ['required', 'integer', 'min:1', 'max:20'],
            'maximum_budget' => ['required', 'numeric', 'min:0', 'max:150000'],
            'categories' => ['required', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'open'])],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ([
                ['initial_evaluation_start_date', 'initial_evaluation_end_date'],
                ['paper_revisions_start_date', 'paper_revisions_end_date'],
                ['lrec_start_date', 'lrec_end_date'],
                ['implementation_start_date', 'implementation_end_date'],
            ] as [$startField, $endField]) {
                $start = $this->input($startField);
                $end = $this->input($endField);

                if ($start && $end && strtotime($end) < strtotime($start)) {
                    $validator->errors()->add($endField, 'The end date must be on or after the start date.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference_image.image' => 'The research-call reference must be a valid image.',
            'reference_image.mimes' => 'The research-call reference must be a JPG, PNG, or WebP image.',
            'reference_image.max' => 'The research-call reference may not be larger than 10 MB.',
        ];
    }
}
