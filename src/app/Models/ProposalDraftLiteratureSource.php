<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProposalDraftLiteratureSource extends Model
{
    protected $fillable = [
        'literature_source_id',
        'saved_by',
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
        'rrl_note',
        'reference_text',
    ];

    protected function casts(): array
    {
        return [
            'publication_year' => 'integer',
            'citation_count' => 'integer',
            'is_open_access' => 'boolean',
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

    public function referenceDraft(): string
    {
        if (filled($this->reference_text)) {
            return trim($this->reference_text);
        }

        $authors = $this->sentencePart($this->authors ?: 'Author not listed');
        $year = $this->publication_year ?: 'n.d.';
        $title = $this->sentencePart($this->title);
        $venue = $this->sentencePart($this->venue);
        $location = $this->doi
            ? 'https://doi.org/'.Str::of($this->doi)->replaceStart('https://doi.org/', '')
            : $this->url;

        return collect([
            "{$authors}. ({$year}).",
            "{$title}.",
            $venue !== '' ? "{$venue}." : null,
            $location,
        ])->filter()->join(' ');
    }

    public function rrlNoteDraft(): string
    {
        return trim((string) $this->rrl_note);
    }

    /** @return array<string, mixed> */
    public function toLibraryArray(): array
    {
        return [
            'id' => $this->getKey(),
            'literature_source_id' => $this->literature_source_id,
            'title' => $this->title,
            'authors' => $this->authors,
            'year' => $this->publication_year,
            'venue' => $this->venue,
            'doi' => $this->doi,
            'url' => $this->url,
            'source' => $this->provider,
            'citation_count' => $this->citation_count,
            'is_open_access' => $this->is_open_access,
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
            'reference' => $this->referenceDraft(),
        ];
    }

    private function sentencePart(?string $value): string
    {
        return rtrim(trim((string) $value), " .\t\n\r\0\x0B");
    }
}
