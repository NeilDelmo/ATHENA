<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResearchOfficeLrecFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordLrecFeedback', $this->route('topic')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'committee_comments' => ['required', 'array', 'min:1', 'max:100'],
            'committee_comments.*' => ['required', 'array:reviewer,comment,location'],
            'committee_comments.*.reviewer' => ['exclude'],
            'committee_comments.*.comment' => ['required', 'string', 'max:5000'],
            'committee_comments.*.location' => ['nullable', 'string', 'max:300'],
            'status' => ['prohibited'],
            'revision_file_ids' => ['prohibited'],
        ];
    }
}
