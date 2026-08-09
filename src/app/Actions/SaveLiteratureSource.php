<?php

namespace App\Actions;

use App\Models\LiteratureSource;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SaveLiteratureSource
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{source: LiteratureSource, already_saved: bool}
     */
    public function handle(array $validated, User $user): array
    {
        $doi = LiteratureSource::normalizeDoi($validated['doi'] ?? null);
        $title = Str::squish($validated['title']);
        $publicationYear = isset($validated['year']) ? (int) $validated['year'] : null;
        $fingerprint = LiteratureSource::fingerprintFor($doi ?: null, $title, $publicationYear);
        $collectionId = isset($validated['collection_id']) ? (int) $validated['collection_id'] : null;

        return Cache::lock('literature-source:'.$fingerprint, 10)->block(3, function () use (
            $validated,
            $user,
            $doi,
            $title,
            $publicationYear,
            $fingerprint,
            $collectionId,
        ): array {
            $source = LiteratureSource::firstOrNew(['fingerprint' => $fingerprint]);
            $alreadySaved = $source->exists;

            if (! $source->exists) {
                $source->added_by = $user->getKey();
            }

            $authors = Str::squish((string) ($validated['authors'] ?? '')) ?: $source->authors;
            $abstract = Str::squish((string) ($validated['description'] ?? '')) ?: $source->abstract;
            $venue = Str::squish((string) ($validated['venue'] ?? '')) ?: $source->venue;
            $url = $validated['url'] ?? $source->url;
            $publicationType = Str::squish((string) ($validated['type'] ?? '')) ?: $source->publication_type;
            $fullTextUrl = $validated['full_text_url'] ?? $source->full_text_url;
            $accessStatus = $fullTextUrl
                ? LiteratureSource::ACCESS_OPEN
                : ($validated['access_status'] ?? $source->access_status ?? LiteratureSource::ACCESS_UNKNOWN);
            $citationCount = max(
                (int) ($source->citation_count ?? 0),
                (int) ($validated['citation_count'] ?? 0),
            );

            $source->fill([
                'title' => $title,
                'authors' => $authors,
                'abstract' => $abstract,
                'publication_year' => $publicationYear ?? $source->publication_year,
                'publication_date' => $validated['publication_date'] ?? $source->publication_date,
                'venue' => $venue,
                'volume' => Str::squish((string) ($validated['volume'] ?? '')) ?: $source->volume,
                'issue' => Str::squish((string) ($validated['issue'] ?? '')) ?: $source->issue,
                'pages' => Str::squish((string) ($validated['pages'] ?? '')) ?: $source->pages,
                'publisher' => Str::squish((string) ($validated['publisher'] ?? '')) ?: $source->publisher,
                'doi' => $doi ?: $source->doi,
                'url' => $url,
                'full_text_url' => $fullTextUrl,
                'provider' => Str::squish($validated['source']),
                'provider_identifier' => Str::squish((string) ($validated['provider_identifier'] ?? '')) ?: $source->provider_identifier,
                'citation_count' => $citationCount ?: null,
                'is_open_access' => $source->is_open_access || (bool) ($validated['is_open_access'] ?? false),
                'access_status' => $source->effectiveAccessStatus() === LiteratureSource::ACCESS_OPEN
                    ? LiteratureSource::ACCESS_OPEN
                    : $accessStatus,
                'publication_type' => $publicationType,
            ]);
            $source->save();

            if ($collectionId !== null) {
                $source->collections()->syncWithoutDetaching([
                    $collectionId => ['added_by' => $user->getKey()],
                ]);
            }

            $source->load(['addedBy:id,name', 'collections:id,name,slug']);

            return ['source' => $source, 'already_saved' => $alreadySaved];
        });
    }
}
