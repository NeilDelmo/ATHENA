<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Support\DetailedProposalRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateProposalDraftDetailedProposalRequest extends FormRequest
{
    private const LITERATURE_CITATION_FIELDS = [
        'executive_brief',
        'rationale',
        'general_objective',
        'introduction',
        'related_literature',
        'methodology.research_design',
        'methodology.specific_methods',
        'methodology.data_analysis',
        'references',
    ];

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

        $merged['project_leader'] = Str::of((string) ($merged['project_leader'] ?? $draft->project_leader))
            ->squish()
            ->toString();
        $merged = $this->normalizeStructuredProposalFields($merged);
        $merged['literature_citations'] = $this->normalizeLiteratureCitations(
            $draft,
            $merged['literature_citations'] ?? '[]',
        );

        $this->replace([
            ...$merged,
            ...$draft->signatoryFields('detailed_proposal'),
            'project_title' => $draft->project_title,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...DetailedProposalRules::rules($this->allowsDraftValidation()),
            'document_version' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'min:0'],
            'draft_version' => ['nullable', 'integer', 'min:0'],
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeStructuredProposalFields(array $data): array
    {
        if (! is_array($data['specific_objectives'] ?? null)) {
            $data['specific_objectives'] = collect(preg_split('/\R+/u', (string) ($data['objectives'] ?? '')) ?: [])
                ->map(fn (string $objective): string => preg_replace('/^\s*(?:\d+[.)]|[-•])\s*/u', '', $objective) ?: '')
                ->filter()
                ->map(fn (string $description): array => ['description' => $description])
                ->values()
                ->all();
        }

        $data['expected_outputs'] = collect(config('detailed_proposal.expected_outputs'))
            ->mapWithKeys(function (string $label, string $key) use ($data): array {
                $value = $data['expected_outputs'][$key] ?? [];

                if (is_string($value)) {
                    $value = $value === '' ? [] : [[
                        'quantity' => null,
                        'unit' => '',
                        'description' => $value,
                    ]];
                }

                return [$key => is_array($value) ? $value : []];
            })
            ->all();

        return $data;
    }

    private function normalizeLiteratureCitations(ProposalDraft $draft, mixed $value): string
    {
        try {
            $citations = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value;
        } catch (\JsonException) {
            $citations = [];
        }

        if (! is_array($citations)) {
            return '[]';
        }

        $sourceIds = $draft->literatureSources()
            ->get(['id', 'literature_source_id'])
            ->mapWithKeys(fn (ProposalDraftLiteratureSource $source): array => [$source->getKey() => $source->literature_source_id]);

        $normalized = collect($citations)
            ->filter(fn (mixed $citation): bool => is_array($citation))
            ->map(function (array $citation) use ($sourceIds): ?array {
                $sourceLinkId = (int) ($citation['source_link_id'] ?? 0);
                $literatureSourceId = $sourceIds->get($sourceLinkId);
                $field = $citation['field'] ?? null;
                $selectedText = Str::squish((string) ($citation['selected_text'] ?? ''));

                if ($literatureSourceId === null
                    || ! in_array($field, self::LITERATURE_CITATION_FIELDS, true)
                    || ($field !== 'references' && $selectedText === '')) {
                    return null;
                }

                $identifier = Str::limit(trim((string) ($citation['id'] ?? '')), 80, '');

                return [
                    'id' => $identifier !== '' ? $identifier : Str::uuid()->toString(),
                    'source_link_id' => $sourceLinkId,
                    'literature_source_id' => $literatureSourceId,
                    'field' => $field,
                    'selected_text' => Str::limit($selectedText, 2000, ''),
                    'locator' => Str::limit(Str::squish((string) ($citation['locator'] ?? '')), 100, ''),
                    'created_at' => Str::limit(trim((string) ($citation['created_at'] ?? '')), 40, ''),
                ];
            })
            ->filter()
            ->unique(fn (array $citation): string => implode('|', [
                $citation['source_link_id'],
                $citation['field'],
                Str::lower($citation['selected_text']),
                Str::lower($citation['locator']),
            ]))
            ->take(100)
            ->values()
            ->all();

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }
}
