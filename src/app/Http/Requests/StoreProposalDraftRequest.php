<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProposalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProposalDraft::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_title' => ['required', 'string', 'max:255'],
            'research_call_id' => ['nullable', 'integer', 'exists:research_calls,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('research_call_id')) {
                    return;
                }

                $researchCall = $this->filled('research_call_id')
                    ? ResearchCall::find($this->integer('research_call_id'))
                    : null;

                if (ResearchCall::query()->acceptingSubmissions()->exists() && $researchCall === null) {
                    $validator->errors()->add(
                        'research_call_id',
                        'Choose an open research call before creating this proposal.',
                    );

                    return;
                }

                if ($researchCall !== null && ! $researchCall->isAcceptingSubmissions()) {
                    $validator->errors()->add(
                        'research_call_id',
                        'Choose a research call that is currently accepting submissions.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'project_title' => 'project title',
            'research_call_id' => 'research call',
        ];
    }
}
