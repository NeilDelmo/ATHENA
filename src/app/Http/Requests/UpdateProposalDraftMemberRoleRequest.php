<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProposalDraftMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft
            && ($this->user()?->can('manageMembers', $draft) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ProposalDraft $draft */
        $draft = $this->route('proposalDraft');

        return [
            'project_role' => ['required', Rule::in(ProposalDraftMember::PROJECT_ROLES)],
            'member_id' => [
                'nullable',
                'integer',
                Rule::exists(ProposalDraftMember::class, 'id')
                    ->where(fn (Builder $query): Builder => $query->where('proposal_draft_id', $draft->getKey())),
            ],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('member_id') || ! $this->filled('member_id')) {
                return;
            }

            /** @var ProposalDraft $draft */
            $draft = $this->route('proposalDraft');
            $member = $draft->members()->find($this->integer('member_id'));

            if (! $member?->isAccepted() || $member->user_id === null) {
                $validator->errors()->add('member_id', 'Choose a team member who has accepted the project invitation.');
            }
        }];
    }
}
