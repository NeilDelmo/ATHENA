<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Support\DetailedProposalRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateProposalDraftDetailedProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('proposalDraft');

        return $draft instanceof ProposalDraft
            && ($this->user()?->can('update', $draft) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $draft = $this->route('proposalDraft');

        if (! $draft instanceof ProposalDraft) {
            return;
        }

        $savedSource = $draft->documents()
            ->where('document_type', config('proposal_papers.detailed-proposal.document_type'))
            ->where('position', 0)
            ->value('source_data');
        $merged = is_array($savedSource)
            ? array_replace($savedSource, $this->all())
            : $this->all();

        if (blank($merged['leader_email'] ?? null)) {
            $merged['leader_email'] = $this->matchingLeaderEmail($draft);
        }

        $merged['proponent_department'] ??= '';

        if (blank($merged['proponent_college'] ?? null)) {
            $merged['proponent_college'] = (string) ($this->user()?->college ?? '');
        }

        $this->replace([
            ...$merged,
            'project_title' => $draft->project_title,
            'project_leader' => $draft->project_leader,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...DetailedProposalRules::rules(),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return DetailedProposalRules::afterCallbacks();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return DetailedProposalRules::attributes();
    }

    private function matchingLeaderEmail(ProposalDraft $draft): string
    {
        $draft->loadMissing(['owner:id,name,email', 'members.user:id,name,email']);
        $leaderName = Str::of((string) $draft->project_leader)->squish()->lower()->toString();
        $people = collect([[
            'name' => $draft->owner->name,
            'email' => $draft->owner->email,
        ]])->concat($draft->members->map(fn ($member): array => [
            'name' => $member->user?->name ?? $member->name,
            'email' => $member->user?->email ?? $member->email,
        ]));
        $match = $people->first(
            fn (array $person): bool => Str::of((string) $person['name'])->squish()->lower()->toString() === $leaderName,
        );

        return (string) ($match['email'] ?? $draft->owner->email);
    }
}
