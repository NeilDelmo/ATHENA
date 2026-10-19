<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportDraftEditStateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal && $this->user()?->isUsingWorkspace(User::WORKSPACE_FACULTY_RESEARCHER)
            && $topic->isAccessibleTo($this->user()) && $this->user()->can('view', $topic);
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->route()->parameters());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['monitoring', 'progress', 'terminal'])],
            'reportingDate' => ['required', $this->input('kind') === 'terminal' ? Rule::in(['terminal']) : 'date_format:Y-m-d'],
            'source' => ['required', 'integer', 'min:0'],
        ];
    }
}
