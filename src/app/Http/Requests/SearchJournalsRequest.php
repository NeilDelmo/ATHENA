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

        if (! $topic instanceof TopicProposal) {
            return $user->isUsingWorkspace('faculty_researcher');
        }

        return $topic->isDisseminationAvailable()
            && $user->isUsingWorkspace(['faculty_researcher', 'research_head'])
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
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'context' => ['nullable', 'string', 'max:3000'],
            'open_access' => ['nullable', 'boolean'],
            'recent_years' => ['nullable', 'integer', Rule::in([0, 5, 10])],
        ];
    }
}
