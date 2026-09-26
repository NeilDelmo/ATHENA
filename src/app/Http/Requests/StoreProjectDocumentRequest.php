<?php

namespace App\Http\Requests;

use App\Models\ProjectDocument;
use App\Models\TopicProposal;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreProjectDocumentRequest extends FormRequest
{
    protected $errorBag = 'projectDocuments';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');
        $user = $this->user();

        return $topic instanceof TopicProposal
            && $user instanceof User
            && Gate::forUser($user)->allows('view', $topic)
            && $topic->user_id === $user->id
            && $user->isUsingWorkspace([
                User::WORKSPACE_FACULTY,
                User::WORKSPACE_FACULTY_RESEARCHER,
            ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', File::types(['pdf'])->max('25mb')],
            'category' => ['required', Rule::in(array_keys(ProjectDocument::uploadCategoryOptions()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'documents.required' => 'Choose at least one PDF to add to the project files.',
            'documents.max' => 'Upload no more than 10 PDFs at a time.',
            'documents.*.required' => 'Each selected project file is required.',
            'documents.*.mimes' => 'Every project file must be a PDF.',
            'documents.*.max' => 'Each PDF may not be larger than 25 MB.',
            'category.in' => 'Choose a valid project file category.',
        ];
    }
}
