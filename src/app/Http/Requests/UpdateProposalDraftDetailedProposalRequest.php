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
        $savedMethodologyImages = collect(is_array($savedSource) ? ($savedSource['methodology_images'] ?? []) : [])
            ->filter(fn (mixed $image): bool => is_array($image) && filled($image['id'] ?? null))
            ->keyBy('id');
        $merged = is_array($savedSource)
            ? array_replace($savedSource, $this->all())
            : $this->all();

        $methodologyImages = $this->has('methodology_images_present')
            ? ($this->all()['methodology_images'] ?? [])
            : (is_array($savedSource) ? ($savedSource['methodology_images'] ?? []) : []);
        $merged['methodology_images'] = collect(is_array($methodologyImages) ? $methodologyImages : [])
            ->filter(fn (mixed $image): bool => is_array($image))
            ->map(function (array $image) use ($savedMethodologyImages): array {
                $savedImage = $savedMethodologyImages->get($image['id'] ?? null);

                if (! is_array($savedImage)) {
                    return $image;
                }

                return [
                    ...$image,
                    'stored_path' => $savedImage['path'] ?? null,
                    'mime_type' => $savedImage['mime_type'] ?? null,
                    'original_filename' => $savedImage['original_filename'] ?? null,
                ];
            })
            ->values()
            ->all();

        if (blank($merged['leader_email'] ?? null)) {
            $merged['leader_email'] = $this->matchingLeaderEmail($draft);
        }

        $merged['proponent_department'] ??= '';

        if (blank($merged['proponent_college'] ?? null)) {
            $merged['proponent_college'] = (string) ($this->user()?->college ?? '');
        }

        if (blank($merged['leader_contact'] ?? null)) {
            $merged['leader_contact'] = (string) ($this->user()?->contact_number ?? '');
        }

        // `leader_contact` is the same single source of truth that lives on
        // the User row — the auto-detected contact number from the profile.
        // A user with multiple roles (faculty + research head, faculty +
        // faculty researcher, faculty + research coordinator) has exactly one
        // `contact_number`, so this single assignment keeps the leader contact
        // in sync across every workspace that user can act in.

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
            ...DetailedProposalRules::rules($this->allowsDraftValidation()),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'save_as_draft' => ['sometimes', 'boolean'],
            'methodology_images_present' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return DetailedProposalRules::afterCallbacks($this->allowsDraftValidation());
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

    private function allowsDraftValidation(): bool
    {
        return $this->routeIs('faculty.proposal-drafts.detailed-proposal.preview')
            || ($this->routeIs('faculty.proposal-drafts.detailed-proposal.update') && $this->boolean('save_as_draft'));
    }
}
