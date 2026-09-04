<?php

namespace App\Services;

use App\Models\HarvestedLiteratureSource;
use App\Models\LiteratureSource;

class LiteratureWebSearchService
{
    /** @return list<array<string, mixed>> */
    public function search(string $query): array
    {
        if (! config('literature.web_harvest.enabled')) {
            return [];
        }

        $repositories = (array) config('literature.web_harvest.repositories');

        return HarvestedLiteratureSource::query()
            ->where('status', 'ready')
            ->whereIn('repository_key', array_keys($repositories))
            ->whereFullText(['title', 'authors', 'abstract'], $query)
            ->orderByRaw('MATCH(title, authors, abstract) AGAINST (? IN NATURAL LANGUAGE MODE) DESC', [$query])
            ->limit(60)
            ->get()
            ->map(fn (HarvestedLiteratureSource $record): array => [
                'title' => $record->title,
                'authors' => $record->authors,
                'description' => $record->abstract,
                'year' => $record->publication_year,
                'doi' => $record->doi,
                'url' => $record->url,
                'venue' => $record->metadata['venue'] ?? null,
                'publisher' => $record->metadata['publisher'] ?? null,
                'type' => $record->metadata['type'] ?? null,
                'source' => $repositories[$record->repository_key]['name'].' (web)',
                'provider_identifier' => $record->url,
                'access_status' => filled($record->abstract) ? LiteratureSource::ACCESS_ABSTRACT_ONLY : LiteratureSource::ACCESS_UNKNOWN,
                '_provider_rank' => 999,
                'provenance' => [[
                    'repository' => $record->repository_key,
                    'url' => $record->url,
                    'retrieved_at' => $record->harvested_at?->toIso8601String(),
                    'method' => 'html_metadata',
                    'license' => $record->metadata['license'] ?? null,
                ]],
            ])->all();
    }
}
