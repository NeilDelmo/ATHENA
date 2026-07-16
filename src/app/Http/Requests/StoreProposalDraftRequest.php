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
            'research_call_id' => ['required', 'integer', 'exists:research_calls,id'],
            'project_title' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('research_call_id')) {
                    return;
                }

                $researchCall = ResearchCall::find($this->integer('research_call_id'));

                if (! $researchCall?->isAcceptingSubmissions()) {
                    $validator->errors()->add(
                        'research_call_id',
                        'This research call is not accepting submissions.',
                    );
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'research_call_id' => 'research call',
            'project_title' => 'project title',
        ];
    }
}
