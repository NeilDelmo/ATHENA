<?php

namespace App\Models;

use App\Services\IeeeReferenceFormatter;
use App\Support\LiteratureFullTextToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LiteratureSource extends Model
{
    public const ACCESS_ABSTRACT_ONLY = 'abstract_only';

    public const ACCESS_OPEN = 'open_access';

    public const ACCESS_RESTRICTED = 'restricted';

    public const ACCESS_UNKNOWN = 'unknown';

    protected $fillable = [
        'added_by',
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
    ];

    protected $attributes = [
        'access_status' => self::ACCESS_UNKNOWN,
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'publication_date' => 'date',
            'citation_count' => 'integer',
            'is_open_access' => 'boolean',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(LiteratureCollection::class, 'literature_collection_source')
            ->withPivot('added_by')
            ->withTimestamps();
    }

    public function proposalLinks(): HasMany
    {
        return $this->hasMany(ProposalDraftLiteratureSource::class);
    }

    public static function fingerprintFor(?string $doi, string $title, ?int $publicationYear = null): string
    {
        $normalizedDoi = self::normalizeDoi($doi);
        $normalizedTitle = Str::of($title)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
        $identity = $normalizedDoi !== ''
            ? 'doi:'.$normalizedDoi
            : 'title:'.$normalizedTitle.'|year:'.($publicationYear ?: 'unknown');

        return hash('sha256', $identity);
    }

    public static function normalizeDoi(?string $doi): string
    {
        return Str::of((string) $doi)
            ->lower()
            ->trim()
            ->replaceStart('https://doi.org/', '')
            ->replaceStart('http://doi.org/', '')
            ->replaceStart('doi:', '')
            ->trim()
            ->toString();
    }

    public function referenceDraft(): string
    {
        return IeeeReferenceFormatter::format($this);
    }

    public function rrlNoteDraft(): string
    {
        return '';
    }

    /** @return array<string, mixed> */
    public function toLibraryArray(): array
    {
        return [
            'id' => $this->getKey(),
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
            'access_status' => $this->effectiveAccessStatus(),
            'access_label' => self::accessLabel($this->effectiveAccessStatus()),
            'type' => $this->publication_type,
            'added_by_name' => $this->relationLoaded('addedBy') ? $this->addedBy?->name : null,
            'collections' => $this->relationLoaded('collections')
                ? $this->collections->map->only(['id', 'name', 'slug'])->values()->all()
                : [],
            'rrl_note' => $this->rrlNoteDraft(),
            'reference' => $this->referenceDraft(),
            'reference_incomplete' => IeeeReferenceFormatter::isIncomplete($this),
        ];
    }

    public function effectiveAccessStatus(): string
    {
        if (filled($this->full_text_url) || $this->access_status === self::ACCESS_OPEN) {
            return self::ACCESS_OPEN;
        }

        return in_array($this->access_status, [
            self::ACCESS_ABSTRACT_ONLY,
            self::ACCESS_OPEN,
            self::ACCESS_RESTRICTED,
            self::ACCESS_UNKNOWN,
        ], true) ? $this->access_status : self::ACCESS_UNKNOWN;
    }

    public static function accessLabel(string $status): string
    {
        return match ($status) {
            self::ACCESS_OPEN => 'Open-access full text available',
            self::ACCESS_ABSTRACT_ONLY => 'Abstract available; full text not accessed',
            self::ACCESS_RESTRICTED => 'Paywalled or restricted',
            default => 'Full-text access unknown',
        };
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
