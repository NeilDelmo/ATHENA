<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchJournalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $topic = $this->route('topic');

        if (! $user) {
            return false;
        }

        if ($topic === null) {
            return $user->isUsingWorkspace('faculty_researcher');
        }

        return $topic instanceof TopicProposal
            && $topic->isDisseminationAvailable()
            && $user->isUsingWorkspace('faculty_researcher')
            && $topic->isAccessibleTo($user)
            && $user->can('view', $topic);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'query' => ['nullable', 'required_without:context', 'string', 'min:3', 'max:500'],
            'context' => ['nullable', 'required_without:query', 'string', 'min:3', 'max:6000'],
            'indexing' => ['nullable', Rule::in(['prefer_scopus', 'scopus_only', 'any'])],
            'open_access' => ['nullable', 'boolean'],
            'recent_years' => ['nullable', 'integer', Rule::in([0, 5, 10])],
        ];
    }
}
