<?php

namespace App\Models;

use App\Services\IeeeReferenceFormatter;
use App\Support\LiteratureFullTextToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalDraftLiteratureSource extends Model
{
    public const DRAFT_CONFIRMED = 'confirmed';

    public const DRAFT_NONE = 'none';

    public const DRAFT_SAVED = 'draft';

    protected $fillable = [
        'literature_source_id',
        'saved_by',
        'fingerprint',
        'title',
        'authors',
        'abstract',
        'publication_year',
        'publication_date',
        'venue',
        'volume',
        'issue',
        'pages',
        'publisher',
        'doi',
        'url',
        'full_text_url',
        'provider',
        'provider_identifier',
        'citation_count',
        'is_open_access',
        'access_status',
        'publication_type',
        'rrl_note',
        'rrl_draft_status',
        'rrl_evidence_basis',
        'rrl_word_count',
        'rrl_generated_at',
        'reference_text',
        'research_context',
    ];

    protected $attributes = [
        'access_status' => LiteratureSource::ACCESS_UNKNOWN,
        'rrl_draft_status' => self::DRAFT_NONE,
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'publication_date' => 'date',
            'citation_count' => 'integer',
            'is_open_access' => 'boolean',
            'rrl_word_count' => 'integer',
            'rrl_generated_at' => 'datetime',
            'research_context' => 'array',
        ];
    }

    public function proposalDraft(): BelongsTo
    {
        return $this->belongsTo(ProposalDraft::class);
    }

    public function literatureSource(): BelongsTo
    {
        return $this->belongsTo(LiteratureSource::class);
    }

    public function savedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saved_by');
    }

    public static function fingerprintFor(?string $doi, string $title, ?int $publicationYear = null): string
    {
        return LiteratureSource::fingerprintFor($doi, $title, $publicationYear);
    }

    public function referenceDraft(?int $number = null): string
    {
        return IeeeReferenceFormatter::format($this, $number);
    }

    public function rrlNoteDraft(): string
    {
        return trim((string) $this->rrl_note);
    }

    /** @return array<string, mixed> */
    public function toLibraryArray(?int $referenceNumber = null): array
    {
        $accessStatus = filled($this->full_text_url) || $this->access_status === LiteratureSource::ACCESS_OPEN
            ? LiteratureSource::ACCESS_OPEN
            : ($this->access_status ?: LiteratureSource::ACCESS_UNKNOWN);

        return [
            'id' => $this->getKey(),
            'literature_source_id' => $this->literature_source_id,
            'title' => $this->title,
            'authors' => $this->authors,
            'description' => $this->abstract,
            'year' => $this->publication_year,
            'publication_date' => $this->publication_date?->toDateString(),
            'venue' => $this->venue,
            'volume' => $this->volume,
            'issue' => $this->issue,
            'pages' => $this->pages,
            'publisher' => $this->publisher,
            'doi' => $this->doi,
            'url' => $this->url,
            'full_text_url' => $this->full_text_url,
            'full_text_token' => $this->fullTextToken(),
            'source' => $this->provider,
            'provider_identifier' => $this->provider_identifier,
            'citation_count' => $this->citation_count,
            'is_open_access' => $this->is_open_access,
            'access_status' => $accessStatus,
            'access_label' => LiteratureSource::accessLabel($accessStatus),
            'type' => $this->publication_type,
            'added_by_name' => $this->relationLoaded('literatureSource')
                && $this->literatureSource?->relationLoaded('addedBy')
                    ? $this->literatureSource->addedBy?->name
                    : null,
            'collections' => $this->relationLoaded('literatureSource')
                && $this->literatureSource?->relationLoaded('collections')
                    ? $this->literatureSource->collections->map->only(['id', 'name', 'slug'])->values()->all()
                    : [],
            'rrl_note' => $this->rrlNoteDraft(),
            'rrl_draft_status' => $this->rrl_draft_status,
            'rrl_evidence_basis' => $this->rrl_evidence_basis,
            'rrl_word_count' => $this->rrl_word_count,
            'rrl_generated_at' => $this->rrl_generated_at?->toIso8601String(),
            'reference_number' => $referenceNumber,
            'rrl_citation' => $referenceNumber !== null ? "[{$referenceNumber}]" : null,
            'reference' => $this->referenceDraft($referenceNumber),
            'reference_incomplete' => IeeeReferenceFormatter::isIncomplete($this),
            'research_context' => $this->research_context ?? [],
        ];
    }

    private function fullTextToken(): ?string
    {
        if (blank($this->full_text_url)) {
            return null;
        }

        return LiteratureFullTextToken::issue([
            'url' => $this->full_text_url,
            'provider' => $this->provider,
            'identifier' => $this->provider_identifier,
        ]);
    }
}
