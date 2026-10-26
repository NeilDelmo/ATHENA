<?php

namespace App\Http\Requests;

use App\Models\ProposalDraft;
use App\Models\ProposalDraftLiteratureSource;
use App\Support\DetailedProposalData;
use App\Support\DetailedProposalRules;
use App\Support\ProposalWorkspacePeople;
use DOMDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

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

        if ($this->boolean('specific_objectives_present')) {
            $merged['specific_objectives'] = $this->input('specific_objectives', []);
        } elseif ($this->has('objectives') && ! $this->has('specific_objectives')) {
            unset($merged['specific_objectives']);
        }

        $methodologyImages = $this->has('methodology_images_present')
            ? ($this->all()['methodology_images'] ?? [])
            : (is_array($savedSource) ? ($savedSource['methodology_images'] ?? []) : []);
        $merged['methodology_images'] = collect(is_array($methodologyImages) ? $methodologyImages : [])
            ->filter(fn (mixed $image): bool => is_array($image))
            ->map(function (array $image) use ($savedMethodologyImages): array {
                $savedImage = $savedMethodologyImages->get($image['id'] ?? null);

                return [
                    ...$image,
                    'stored_path' => $savedImage['path'] ?? null,
                    'mime_type' => $savedImage['mime_type'] ?? null,
                    'original_filename' => $savedImage['original_filename'] ?? null,
                ];
            })
            ->values()
            ->all();

        if (! is_array($savedSource)) {
            $leader = $this->matchingLeader($draft);

            foreach ([
                'leader_title' => 'title',
                'leader_email' => 'email',
                'leader_contact' => 'contact',
                'proponent_department' => 'department',
                'proponent_college' => 'college',
            ] as $field => $profileField) {
                if (! array_key_exists($field, $merged)
                    || (! in_array($field, ['leader_title', 'proponent_department'], true) && blank($merged[$field]))) {
                    $merged[$field] = $leader[$profileField] ?? '';
                }
            }
        }

        $merged['proponent_department'] ??= '';

        $merged['project_leader'] = Str::of((string) ($merged['project_leader'] ?? $draft->project_leader))
            ->squish()
            ->toString();
        $merged = $this->normalizeStructuredProposalFields($merged);
        $merged['literature_citations'] = $this->normalizeLiteratureCitations(
            $draft,
            $merged['literature_citations'] ?? '[]',
            $merged,
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
            'specific_objectives_present' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [
            ...DetailedProposalRules::afterCallbacks($this->allowsDraftValidation()),
            function (Validator $validator): void {
                foreach ($this->input('methodology_images', []) as $index => $image) {
                    if (is_array($image) && blank($image['stored_path'] ?? null)
                        && ! $this->hasFile("methodology_images.{$index}.image")) {
                        $validator->errors()->add(
                            "methodology_images.{$index}.image",
                            'Choose this figure image again before saving or preparing the paper.',
                        );
                    }
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return DetailedProposalRules::attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return DetailedProposalRules::messages();
    }

    /** @return array<string, mixed> */
    private function matchingLeader(ProposalDraft $draft): array
    {
        $leaderName = Str::of((string) $this->input('project_leader', $draft->project_leader))->squish()->lower()->toString();
        $people = collect(app(ProposalWorkspacePeople::class)->forDraft($draft));

        return $leaderName === ''
            ? $people->firstWhere('owner', true)
            : ($people->first(
                fn (array $person): bool => Str::of((string) $person['name'])->squish()->lower()->toString() === $leaderName,
            ) ?? []);
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
        $data = DetailedProposalData::normalizeObjectiveFields($data);

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

    /** @param array<string, mixed> $proposalData */
    private function normalizeLiteratureCitations(ProposalDraft $draft, mixed $value, array $proposalData): string
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
        $markerSources = [];
        foreach (self::LITERATURE_CITATION_FIELDS as $field) {
            $markerSources[$field] = $this->citationMarkerSourceIds((string) data_get($proposalData, $field, ''));
        }

        $normalized = collect($citations)
            ->filter(fn (mixed $citation): bool => is_array($citation))
            ->map(function (array $citation) use ($sourceIds, $markerSources): ?array {
                $sourceLinkId = (int) ($citation['source_link_id'] ?? 0);
                $literatureSourceId = $sourceIds->get($sourceLinkId);
                $field = $citation['field'] ?? null;
                $selectedText = Str::squish((string) ($citation['selected_text'] ?? ''));

                if ($literatureSourceId === null
                    || ! in_array($field, self::LITERATURE_CITATION_FIELDS, true)
                    || ($field !== 'references' && $selectedText === '' && ! in_array($sourceLinkId, $markerSources[$field] ?? [], true))) {
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

    /** @return list<int> */
    private function citationMarkerSourceIds(string $html): array
    {
        if (! str_contains($html, 'data-proposal-citation')) {
            return [];
        }

        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NONET);
            $ids = [];
            foreach ($document->getElementsByTagName('span') as $marker) {
                $value = $marker->getAttribute('data-proposal-citation');
                if (ctype_digit($value) && (int) $value > 0) {
                    $ids[] = (int) $value;
                }
            }

            return array_values(array_unique($ids));
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }
}
