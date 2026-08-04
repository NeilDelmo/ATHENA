<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LiteratureSource extends Model
{
    protected $fillable = [
        'added_by',
        'fingerprint',
        'title',
        'authors',
        'abstract',
        'publication_year',
        'venue',
        'doi',
        'url',
        'provider',
        'citation_count',
        'is_open_access',
        'publication_type',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
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
        $authors = $this->sentencePart($this->authors ?: 'Author not listed');
        $year = $this->publication_year ?: 'n.d.';
        $title = $this->sentencePart($this->title);
        $venue = $this->sentencePart($this->venue);
        $location = $this->doi ? 'https://doi.org/'.$this->doi : $this->url;

        return collect([
            "{$authors}. ({$year}).",
            "{$title}.",
            $venue !== '' ? "{$venue}." : null,
            $location,
        ])->filter()->join(' ');
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
            'venue' => $this->venue,
            'doi' => $this->doi,
            'url' => $this->url,
            'source' => $this->provider,
            'citation_count' => $this->citation_count,
            'is_open_access' => $this->is_open_access,
            'type' => $this->publication_type,
            'added_by_name' => $this->relationLoaded('addedBy') ? $this->addedBy?->name : null,
            'collections' => $this->relationLoaded('collections')
                ? $this->collections->map->only(['id', 'name', 'slug'])->values()->all()
                : [],
            'rrl_note' => $this->rrlNoteDraft(),
            'reference' => $this->referenceDraft(),
        ];
    }

    private function sentencePart(?string $value): string
    {
        return rtrim(trim((string) $value), " .\t\n\r\0\x0B");
    }
}
