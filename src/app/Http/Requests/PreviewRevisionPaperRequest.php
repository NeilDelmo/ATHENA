<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use Illuminate\Foundation\Http\FormRequest;

class PreviewRevisionPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->user_id === $this->user()?->id
            && $topic->status === 'revision_requested';
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['paper' => ['required', 'file', 'mimes:doc,docx', 'extensions:doc,docx', 'max:25600']];
    }
}
