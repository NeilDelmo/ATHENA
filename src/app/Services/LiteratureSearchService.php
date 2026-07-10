<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiteratureSearchService
{
    private const DESCRIPTION_FALLBACK = 'No description available from source.';

    /**
     * @var array<string, string>
     */
    private array $providers = [
        'semantic_scholar' => 'Semantic Scholar',
        'crossref' => 'Crossref',
        'openalex' => 'OpenAlex',
    ];

    /**
     * @var list<string>
     */
    private array $failedSources = [];

    /**
     * @param  array{year_from?: int|null, year_to?: int|null, min_citations?: int|null, open_access?: bool|null}  $filters
     * @return array{results: list<array<string, mixed>>, failed_sources: list<string>, sources: list<string>}
     */
    public function search(string $query, array $filters = []): array
    {
        $this->failedSources = [];
        $filters = $this->normalizeFilters($filters);

        $results = collect([
            ...$this->searchSemanticScholar($query, $filters),
            ...$this->searchCrossref($query, $filters),
            ...$this->searchOpenAlex($query, $filters),
        ])
            ->filter(fn (array $result) => filled($result['title'] ?? null))
            ->filter(fn (array $result) => $this->passesFilters($result, $filters))
            ->unique(function (array $result) {
                $doi = Str::lower((string) ($result['doi'] ?? ''));

                return $doi !== '' ? 'doi:'.$doi : 'title:'.Str::lower((string) $result['title']);
            })
            ->take(12)
            ->values()
            ->all();

        return [
            'results' => $results,
            'failed_sources' => $this->failedSources,
            'sources' => array_values($this->providers),
        ];
    }

    public function allProvidersFailed(): bool
    {
        return count($this->failedSources) >= count($this->providers);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSemanticScholar(string $query, array $filters): array
    {
        try {
            $response = Http::acceptJson()
                ->connectTimeout(6)
                ->timeout(12)
                ->get('https://api.semanticscholar.org/graph/v1/paper/search', [
                    'query' => $query,
                    'limit' => 8,
                    'fields' => 'title,abstract,authors,year,venue,url,externalIds,citationCount,openAccessPdf,publicationTypes',
                ]);
        } catch (ConnectionException $exception) {
            $this->recordFailure('semantic_scholar', $exception::class);

            return [];
        }

        if ($response->failed()) {
            $this->recordFailure('semantic_scholar', 'HTTP '.$response->status());

            return [];
        }

        return collect($response->json('data', []))
            ->map(fn (array $paper) => [
                'title' => $this->cleanText($paper['title'] ?? null),
                'description' => $this->description($paper['abstract'] ?? null),
                'authors' => $this->formatSemanticScholarAuthors($paper['authors'] ?? []),
                'year' => $paper['year'] ?? null,
                'venue' => $this->cleanText($paper['venue'] ?? null),
                'doi' => $this->normalizeDoi($paper['externalIds']['DOI'] ?? null),
                'url' => $paper['url'] ?? $this->doiUrl($paper['externalIds']['DOI'] ?? null),
                'source' => $this->providers['semantic_scholar'],
                'citation_count' => $paper['citationCount'] ?? null,
                'is_open_access' => filled($paper['openAccessPdf']['url'] ?? null),
                'type' => $this->cleanText($paper['publicationTypes'][0] ?? null),
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchCrossref(string $query, array $filters): array
    {
        $parameters = [
            'query.bibliographic' => $query,
            'rows' => 8,
            'sort' => 'relevance',
            'order' => 'desc',
        ];
        $crossrefFilters = $this->crossrefFilters($filters);

        if ($crossrefFilters !== []) {
            $parameters['filter'] = implode(',', $crossrefFilters);
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'User-Agent' => 'Athena Research Support (mailto:'.config('mail.from.address', 'hello@example.com').')',
                ])
                ->connectTimeout(6)
                ->timeout(12)
                ->get('https://api.crossref.org/works', $parameters);
        } catch (ConnectionException $exception) {
            $this->recordFailure('crossref', $exception::class);

            return [];
        }

        if ($response->failed()) {
            $this->recordFailure('crossref', 'HTTP '.$response->status());

            return [];
        }

        return collect($response->json('message.items', []))
            ->map(fn (array $work) => [
                'title' => $this->cleanText($work['title'][0] ?? null),
                'description' => $this->description($work['abstract'] ?? null),
                'authors' => $this->formatCrossrefAuthors($work['author'] ?? []),
                'year' => $this->crossrefYear($work),
                'venue' => $this->cleanText($work['container-title'][0] ?? null),
                'doi' => $this->normalizeDoi($work['DOI'] ?? null),
                'url' => $work['URL'] ?? $this->doiUrl($work['DOI'] ?? null),
                'source' => $this->providers['crossref'],
                'citation_count' => $work['is-referenced-by-count'] ?? null,
                'is_open_access' => false,
                'type' => $this->cleanText($work['type'] ?? null),
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchOpenAlex(string $query, array $filters): array
    {
        $parameters = [
            'search' => $query,
            'per_page' => 8,
            'select' => 'display_name,abstract_inverted_index,authorships,publication_year,primary_location,doi,id,cited_by_count,open_access,type',
        ];
        $openAlexFilters = $this->openAlexFilters($filters);
        $apiKey = (string) config('services.openalex.key');

        if ($openAlexFilters !== []) {
            $parameters['filter'] = implode(',', $openAlexFilters);
        }

        if ($apiKey !== '') {
            $parameters['api_key'] = $apiKey;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(6)
                ->timeout(12)
                ->get('https://api.openalex.org/works', $parameters);
        } catch (ConnectionException $exception) {
            $this->recordFailure('openalex', $exception::class);

            return [];
        }

        if ($response->failed()) {
            $this->recordFailure('openalex', 'HTTP '.$response->status());

            return [];
        }

        return collect($response->json('results', []))
            ->map(fn (array $work) => [
                'title' => $this->cleanText($work['display_name'] ?? null),
                'description' => $this->description($this->openAlexAbstract($work['abstract_inverted_index'] ?? null)),
                'authors' => $this->formatOpenAlexAuthors($work['authorships'] ?? []),
                'year' => $work['publication_year'] ?? null,
                'venue' => $this->cleanText(data_get($work, 'primary_location.source.display_name')),
                'doi' => $this->normalizeDoi($work['doi'] ?? null),
                'url' => data_get($work, 'primary_location.landing_page_url') ?? $this->doiUrl($work['doi'] ?? null) ?? ($work['id'] ?? null),
                'source' => $this->providers['openalex'],
                'citation_count' => $work['cited_by_count'] ?? null,
                'is_open_access' => (bool) data_get($work, 'open_access.is_oa', false),
                'type' => $this->cleanText($work['type'] ?? null),
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /**
     * @param  array{year_from?: int|null, year_to?: int|null, min_citations?: int|null, open_access?: bool|null}  $filters
     * @return array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'year_from' => isset($filters['year_from']) ? (int) $filters['year_from'] : null,
            'year_to' => isset($filters['year_to']) ? (int) $filters['year_to'] : null,
            'min_citations' => isset($filters['min_citations']) ? (int) $filters['min_citations'] : null,
            'open_access' => (bool) ($filters['open_access'] ?? false),
        ];
    }

    /**
     * @param  array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}  $filters
     */
    private function passesFilters(array $result, array $filters): bool
    {
        $year = $result['year'] ?? null;
        $citations = $result['citation_count'] ?? null;

        if ($filters['year_from'] && (! is_numeric($year) || (int) $year < $filters['year_from'])) {
            return false;
        }

        if ($filters['year_to'] && (! is_numeric($year) || (int) $year > $filters['year_to'])) {
            return false;
        }

        if ($filters['min_citations'] !== null && (! is_numeric($citations) || (int) $citations < $filters['min_citations'])) {
            return false;
        }

        if ($filters['open_access'] && ! (bool) ($result['is_open_access'] ?? false)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}  $filters
     * @return list<string>
     */
    private function crossrefFilters(array $filters): array
    {
        $filterParts = [];

        if ($filters['year_from']) {
            $filterParts[] = 'from-pub-date:'.$filters['year_from'].'-01-01';
        }

        if ($filters['year_to']) {
            $filterParts[] = 'until-pub-date:'.$filters['year_to'].'-12-31';
        }

        return $filterParts;
    }

    /**
     * @param  array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}  $filters
     * @return list<string>
     */
    private function openAlexFilters(array $filters): array
    {
        $filterParts = [];

        if ($filters['year_from'] && $filters['year_to']) {
            $filterParts[] = 'publication_year:'.$filters['year_from'].'-'.$filters['year_to'];
        } elseif ($filters['year_from']) {
            $filterParts[] = 'publication_year:>'.($filters['year_from'] - 1);
        } elseif ($filters['year_to']) {
            $filterParts[] = 'publication_year:<'.($filters['year_to'] + 1);
        }

        if ($filters['min_citations'] !== null && $filters['min_citations'] > 0) {
            $filterParts[] = 'cited_by_count:>'.($filters['min_citations'] - 1);
        }

        if ($filters['open_access']) {
            $filterParts[] = 'is_oa:true';
        }

        return $filterParts;
    }

    private function recordFailure(string $provider, string $reason): void
    {
        $source = $this->providers[$provider] ?? $provider;
        $this->failedSources[] = $source;

        Log::warning('Literature search provider failed.', [
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    private function description(mixed $value): string
    {
        $description = $this->cleanText($value);

        if ($description === '') {
            return self::DESCRIPTION_FALLBACK;
        }

        return Str::limit($description, 700);
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $withoutTags = preg_replace('/<[^>]+>/', ' ', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '';

        return trim((string) preg_replace('/\s+/', ' ', $withoutTags));
    }

    /**
     * @param  list<array{name?: string}>  $authors
     */
    private function formatSemanticScholarAuthors(array $authors): string
    {
        return $this->formatAuthorNames(collect($authors)
            ->pluck('name')
            ->filter()
            ->values()
            ->all());
    }

    /**
     * @param  list<array{given?: string, family?: string, name?: string}>  $authors
     */
    private function formatCrossrefAuthors(array $authors): string
    {
        $names = collect($authors)
            ->map(function (array $author) {
                $name = trim(collect([
                    $author['given'] ?? null,
                    $author['family'] ?? null,
                ])->filter()->join(' '));

                return $name !== '' ? $name : ($author['name'] ?? null);
            })
            ->filter()
            ->values()
            ->all();

        return $this->formatAuthorNames($names);
    }

    /**
     * @param  list<array{author?: array{display_name?: string}}>  $authorships
     */
    private function formatOpenAlexAuthors(array $authorships): string
    {
        return $this->formatAuthorNames(collect($authorships)
            ->pluck('author.display_name')
            ->filter()
            ->values()
            ->all());
    }

    /**
     * @param  list<string>  $names
     */
    private function formatAuthorNames(array $names): string
    {
        if ($names === []) {
            return 'Authors not listed';
        }

        $visibleNames = array_slice($names, 0, 4);
        $suffix = count($names) > 4 ? ' et al.' : '';

        return implode(', ', $visibleNames).$suffix;
    }

    private function crossrefYear(array $work): ?int
    {
        $dateParts = $work['published-print']['date-parts'][0]
            ?? $work['published-online']['date-parts'][0]
            ?? $work['published']['date-parts'][0]
            ?? $work['created']['date-parts'][0]
            ?? null;

        $year = is_array($dateParts) ? ($dateParts[0] ?? null) : null;

        return is_numeric($year) ? (int) $year : null;
    }

    private function normalizeDoi(mixed $doi): ?string
    {
        if (! is_string($doi) || trim($doi) === '') {
            return null;
        }

        return Str::of($doi)
            ->trim()
            ->replaceStart('https://doi.org/', '')
            ->replaceStart('http://doi.org/', '')
            ->replaceStart('doi:', '')
            ->toString();
    }

    private function doiUrl(mixed $doi): ?string
    {
        $normalizedDoi = $this->normalizeDoi($doi);

        return $normalizedDoi ? 'https://doi.org/'.$normalizedDoi : null;
    }

    private function openAlexAbstract(mixed $abstract): ?string
    {
        if (! is_array($abstract)) {
            return null;
        }

        $words = [];

        foreach ($abstract as $word => $positions) {
            if (! is_array($positions)) {
                continue;
            }

            foreach ($positions as $position) {
                if (is_numeric($position)) {
                    $words[(int) $position] = (string) $word;
                }
            }
        }

        if ($words === []) {
            return null;
        }

        ksort($words);

        return implode(' ', $words);
    }
}
