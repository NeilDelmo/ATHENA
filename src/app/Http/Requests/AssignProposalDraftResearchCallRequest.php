<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ResearchCall;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignProposalDraftResearchCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposalDraft = $this->route('proposalDraft');

        return $proposalDraft instanceof ProposalDraft
            && ($this->user()?->can('submit', $proposalDraft) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'research_call_id' => ['required', 'integer', 'exists:research_calls,id'],
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
                        'Choose a research call that is currently accepting submissions.',
                    );

                }
            },
        ];
    }
}
