<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLiteratureSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isUsingWorkspace([
            User::WORKSPACE_FACULTY,
            User::WORKSPACE_FACULTY_RESEARCHER,
        ]) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'authors' => ['nullable', 'string', 'max:2000'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'venue' => ['nullable', 'string', 'max:500'],
            'doi' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'source' => ['required', 'string', 'max:100'],
            'citation_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'is_open_access' => ['sometimes', 'boolean'],
            'type' => ['nullable', 'string', 'max:100'],
            'collection_id' => ['nullable', 'integer', 'exists:literature_collections,id'],
        ];
    }
}
