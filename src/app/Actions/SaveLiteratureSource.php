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
            $citationCount = max(
                (int) ($source->citation_count ?? 0),
                (int) ($validated['citation_count'] ?? 0),
            );

            $source->fill([
                'title' => $title,
                'authors' => $authors,
                'abstract' => $abstract,
                'publication_year' => $publicationYear ?? $source->publication_year,
                'venue' => $venue,
                'doi' => $doi ?: $source->doi,
                'url' => $url,
                'provider' => Str::squish($validated['source']),
                'citation_count' => $citationCount ?: null,
                'is_open_access' => $source->is_open_access || (bool) ($validated['is_open_access'] ?? false),
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
