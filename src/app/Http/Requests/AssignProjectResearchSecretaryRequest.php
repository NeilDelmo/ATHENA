<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignProjectResearchSecretaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic instanceof TopicProposal
            && $topic->hasIssuedNoticeToProceed()
            && $topic->user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'research_secretary_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('research_secretary_id') || blank($this->input('research_secretary_id'))) {
                    return;
                }

                $topic = $this->route('topic');
                $isAcceptedProjectMember = $topic instanceof TopicProposal
                    && $topic->collaborators()
                        ->whereNotNull('accepted_at')
                        ->where('user_id', $this->integer('research_secretary_id'))
                        ->exists();

                if (! $isAcceptedProjectMember) {
                    $validator->errors()->add('research_secretary_id', 'Choose an accepted member of this project group.');
                }
            },
        ];
    }
}
