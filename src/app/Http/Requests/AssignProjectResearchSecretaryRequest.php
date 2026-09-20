<?php

namespace App\Http\Requests;

use App\Models\TopicProposal;
use App\Models\User;
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
            && ($this->user()?->isUsingWorkspace(User::WORKSPACE_RESEARCH_HEAD) ?? false);
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

                $secretary = User::query()->find($this->integer('research_secretary_id'));

                if (! $secretary?->hasRole(User::WORKSPACE_RESEARCH_SECRETARY)) {
                    $validator->errors()->add('research_secretary_id', 'Choose an account with the Research Secretary role.');
                }
            },
        ];
    }
}
