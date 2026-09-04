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
            'research_call_id' => ['required', 'integer', 'exists:research_calls,id'],
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

                $researchCall = ResearchCall::find($this->integer('research_call_id'));

                if (! $researchCall?->isAcceptingSubmissions()) {
                    $validator->errors()->add(
                        'research_call_id',
                        'This research call is not within its submission window. Choose a call that is open now.',
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
