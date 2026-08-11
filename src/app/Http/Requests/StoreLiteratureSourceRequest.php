<?php

namespace App\Http\Requests;

use App\Models\LiteratureSource;
use App\Models\User;
use App\Support\LiteratureFullTextToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLiteratureSourceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $token = $this->input('full_text_token');

        if (! is_string($token) || trim($token) === '') {
            $this->merge([
                'full_text_url' => null,
                'is_open_access' => false,
                'access_status' => $this->input('access_status') === LiteratureSource::ACCESS_OPEN
                    ? (filled($this->input('description')) ? LiteratureSource::ACCESS_ABSTRACT_ONLY : LiteratureSource::ACCESS_UNKNOWN)
                    : $this->input('access_status'),
            ]);

            return;
        }

        $source = LiteratureFullTextToken::decode($token);
        $this->merge([
            'full_text_url' => $source['url'],
            'provider_identifier' => $this->input('provider_identifier') ?: $source['identifier'],
            'access_status' => LiteratureSource::ACCESS_OPEN,
            'is_open_access' => true,
        ]);
    }

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
            'description' => ['nullable', 'string', 'max:12000'],
            'authors' => ['nullable', 'string', 'max:2000'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'publication_date' => ['nullable', 'date_format:Y-m-d'],
            'venue' => ['nullable', 'string', 'max:500'],
            'volume' => ['nullable', 'string', 'max:100'],
            'issue' => ['nullable', 'string', 'max:100'],
            'pages' => ['nullable', 'string', 'max:100'],
            'publisher' => ['nullable', 'string', 'max:500'],
            'doi' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url:http,https', 'max:2048'],
            'full_text_url' => ['nullable', 'url:http,https', 'max:2048'],
            'full_text_token' => ['nullable', 'string', 'max:8192'],
            'source' => ['required', 'string', 'max:100'],
            'provider_identifier' => ['nullable', 'string', 'max:255'],
            'citation_count' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'is_open_access' => ['sometimes', 'boolean'],
            'access_status' => ['nullable', Rule::in([
                LiteratureSource::ACCESS_OPEN,
                LiteratureSource::ACCESS_ABSTRACT_ONLY,
                LiteratureSource::ACCESS_RESTRICTED,
                LiteratureSource::ACCESS_UNKNOWN,
            ])],
            'type' => ['nullable', 'string', 'max:100'],
            'collection_id' => ['nullable', 'integer', 'exists:literature_collections,id'],
        ];
    }
}
