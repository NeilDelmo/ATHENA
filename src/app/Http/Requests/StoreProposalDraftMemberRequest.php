<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftMember;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProposalDraftMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft
            && ($this->user()?->can('manageMembers', $draft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);

        if ($linkedUser = $this->linkedUser()) {
            $this->merge(['name' => $linkedUser->name]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ProposalDraft $draft */
        $draft = $this->route('proposalDraft');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique(ProposalDraftMember::class, 'email')
                    ->where(fn (Builder $query): Builder => $query->where('proposal_draft_id', $draft->getKey())),
            ],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var ProposalDraft $draft */
            $draft = $this->route('proposalDraft');

            if (Str::lower($draft->owner()->value('email')) === $this->input('email')) {
                $validator->errors()->add('email', 'The proposal owner is already part of this workspace.');
            }

            if ($draft->members()->count() >= 50) {
                $validator->errors()->add('email', 'This proposal workspace already has the maximum of 50 collaborators.');
            }

            $linkedUser = $this->linkedUser();

            if ($linkedUser && ! $linkedUser->canUseWorkspace(User::WORKSPACE_FACULTY)) {
                $validator->errors()->add('email', 'This ATHENA account does not currently have access to the Faculty workspace.');
            }
        }];
    }

    public function linkedUser(): ?User
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where('email', Str::lower(trim((string) $this->input('email'))))
            ->first();
    }
}
