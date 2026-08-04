<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LiteratureSearchService
{
    private const DESCRIPTION_FALLBACK = 'No description available from source.';

    private const PROVIDER_RESULT_LIMIT = 60;

    private const SEARCH_RESULT_LIMIT = 50;

    /** @var list<string> */
    private const QUERY_STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'how', 'in',
        'is', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'using', 'what', 'with',
    ];

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

    /** @var array<string, string> */
    private array $failureReasons = [];

    /**
     * @param  array{year_from?: int|null, year_to?: int|null, min_citations?: int|null, open_access?: bool|null}  $filters
     * @return array{results: list<array<string, mixed>>, failed_sources: list<string>, sources: list<string>, query_guidance: array{is_broad: bool, term_count: int, message: ?string, suggestion: ?string}, provider_notice: ?string}
     */
    public function search(string $query, array $filters = []): array
    {
        $this->failedSources = [];
        $this->failureReasons = [];
        $filters = $this->normalizeFilters($filters);
        $queryTerms = $this->queryTerms($query);
        $queryGuidance = $this->queryGuidance($query, $queryTerms);

        if (count($queryTerms) < 2) {
            return [
                'results' => [],
                'failed_sources' => [],
                'sources' => array_values($this->providers),
                'query_guidance' => $queryGuidance,
                'provider_notice' => null,
            ];
        }

        $responses = $this->providerResponses($query, $filters);

        $results = $this->deduplicateResults(collect([
            ...$this->semanticScholarResults($this->successfulResponse($responses['semantic_scholar'] ?? null, 'semantic_scholar')),
            ...$this->crossrefResults($this->successfulResponse($responses['crossref'] ?? null, 'crossref')),
            ...$this->openAlexResults($this->successfulResponse($responses['openalex'] ?? null, 'openalex')),
        ])
            ->filter(fn (array $result) => filled($result['title'] ?? null))
            ->filter(fn (array $result) => $this->passesFilters($result, $filters)))
            ->map(function (array $result) use ($query, $queryTerms): array {
                return [
                    ...$result,
                    ...$this->relevanceSignals($query, $queryTerms, $result),
                ];
            })
            ->filter(fn (array $result): bool => $this->passesRelevanceGate($result, count($queryTerms)))
            ->sort(function (array $left, array $right): int {
                foreach (['relevance_score', '_metadata_score', 'citation_count', 'year'] as $field) {
                    $comparison = ((int) ($right[$field] ?? -1)) <=> ((int) ($left[$field] ?? -1));

                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return strcasecmp((string) $left['title'], (string) $right['title']);
            })
            ->take(self::SEARCH_RESULT_LIMIT)
            ->map(function (array $result): array {
                unset(
                    $result['_provider_rank'],
                    $result['_metadata_score'],
                    $result['_content_match_count'],
                    $result['_term_coverage'],
                    $result['_has_evidence_metadata'],
                    $result['_trusted_provider_match'],
                );

                return $result;
            })
            ->values()
            ->all();

        return [
            'results' => $results,
            'failed_sources' => $this->failedSources,
            'sources' => array_values($this->providers),
            'query_guidance' => $queryGuidance,
            'provider_notice' => $this->providerNotice(count($results)),
        ];
    }

    public function allProvidersFailed(): bool
    {
        return count($this->failedSources) >= count($this->providers);
    }

    /**
     * @param  array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}  $filters
     * @return array<string, Response|\Throwable>
     */
    private function providerResponses(string $query, array $filters): array
    {
        $crossrefParameters = [
            'query.bibliographic' => $query,
            'rows' => self::PROVIDER_RESULT_LIMIT,
            'sort' => 'relevance',
            'order' => 'desc',
        ];
        $crossrefFilters = $this->crossrefFilters($filters);

        if ($crossrefFilters !== []) {
            $crossrefParameters['filter'] = implode(',', $crossrefFilters);
        }

        $openAlexParameters = [
            'search' => $query,
            'per_page' => self::PROVIDER_RESULT_LIMIT,
            'select' => 'display_name,abstract_inverted_index,authorships,publication_year,primary_location,doi,id,cited_by_count,open_access,type',
        ];
        $openAlexFilters = $this->openAlexFilters($filters);
        $apiKey = (string) config('services.openalex.key');

        if ($openAlexFilters !== []) {
            $openAlexParameters['filter'] = implode(',', $openAlexFilters);
        }

        if ($apiKey !== '') {
            $openAlexParameters['api_key'] = $apiKey;
        }

        $semanticScholarParameters = [
            'query' => str_replace('-', ' ', $query),
            'limit' => self::PROVIDER_RESULT_LIMIT,
            'fields' => 'title,abstract,authors,year,venue,url,externalIds,citationCount,openAccessPdf,publicationTypes',
        ];

        $semanticScholarYear = $this->semanticScholarYear($filters);

        if ($semanticScholarYear !== null) {
            $semanticScholarParameters['year'] = $semanticScholarYear;
        }

        if ($filters['open_access']) {
            $semanticScholarParameters['openAccessPdf'] = 'true';
        }

        $semanticScholarApiKey = (string) config('services.semantic_scholar.key');

        return Http::pool(function (Pool $pool) use ($crossrefParameters, $openAlexParameters, $semanticScholarApiKey, $semanticScholarParameters): array {
            $semanticScholarRequest = $pool->as('semantic_scholar')
                ->acceptJson()
                ->connectTimeout(6)
                ->timeout(12);

            if ($semanticScholarApiKey !== '') {
                $semanticScholarRequest->withHeaders(['x-api-key' => $semanticScholarApiKey]);
            }

            return [
                $semanticScholarRequest->get('https://api.semanticscholar.org/graph/v1/paper/search', $semanticScholarParameters),
                $pool->as('crossref')
                    ->acceptJson()
                    ->withHeaders([
                        'User-Agent' => 'Athena Research Support (mailto:'.config('mail.from.address', 'hello@example.com').')',
                    ])
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://api.crossref.org/works', $crossrefParameters),
                $pool->as('openalex')
                    ->acceptJson()
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://api.openalex.org/works', $openAlexParameters),
            ];
        });
    }

    private function successfulResponse(mixed $response, string $provider): ?Response
    {
        if (! $response instanceof Response) {
            $reason = $response instanceof ConnectionException || $response instanceof \Throwable
                ? $response::class
                : 'Missing response';
            $this->recordFailure($provider, $reason);

            return null;
        }

        if ($response->failed()) {
            $this->recordFailure($provider, 'HTTP '.$response->status());

            return null;
        }

        return $response;
    }

    /** @return list<array<string, mixed>> */
    private function semanticScholarResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('data', []))
            ->map(fn (array $paper, int $index) => [
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
                '_provider_rank' => $index + 1,
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function crossrefResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('message.items', []))
            ->map(fn (array $work, int $index) => [
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
                '_provider_rank' => $index + 1,
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openAlexResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('results', []))
            ->map(fn (array $work, int $index) => [
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
                '_provider_rank' => $index + 1,
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
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    private function deduplicateResults(Collection $results): Collection
    {
        return $results
            ->groupBy(fn (array $result): string => $this->resultFingerprint($result))
            ->map(fn (Collection $duplicates): array => $this->mergeDuplicateResults($duplicates))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $duplicates
     * @return array<string, mixed>
     */
    private function mergeDuplicateResults(Collection $duplicates): array
    {
        $ordered = $duplicates->sort(function (array $left, array $right): int {
            $metadataComparison = $this->metadataScore($right) <=> $this->metadataScore($left);

            if ($metadataComparison !== 0) {
                return $metadataComparison;
            }

            return ((int) ($left['_provider_rank'] ?? 999)) <=> ((int) ($right['_provider_rank'] ?? 999));
        })->values();

        $merged = $ordered->first();

        foreach ($ordered->slice(1) as $duplicate) {
            foreach (['description', 'authors', 'year', 'venue', 'doi', 'url', 'type'] as $field) {
                if ($this->missingResultValue($field, $merged[$field] ?? null) && ! $this->missingResultValue($field, $duplicate[$field] ?? null)) {
                    $merged[$field] = $duplicate[$field];
                }
            }

            $merged['citation_count'] = max(
                (int) ($merged['citation_count'] ?? 0),
                (int) ($duplicate['citation_count'] ?? 0),
            ) ?: null;
            $merged['is_open_access'] = (bool) ($merged['is_open_access'] ?? false)
                || (bool) ($duplicate['is_open_access'] ?? false);
            $merged['_provider_rank'] = min(
                (int) ($merged['_provider_rank'] ?? 999),
                (int) ($duplicate['_provider_rank'] ?? 999),
            );
        }

        return $merged;
    }

    /** @param array<string, mixed> $result */
    private function resultFingerprint(array $result): string
    {
        $doi = Str::lower((string) ($result['doi'] ?? ''));

        if ($doi !== '') {
            return 'doi:'.$doi;
        }

        return 'title:'.$this->normalizeSearchText((string) ($result['title'] ?? ''));
    }

    private function missingResultValue(string $field, mixed $value): bool
    {
        if ($field === 'description') {
            return blank($value) || $value === self::DESCRIPTION_FALLBACK;
        }

        if ($field === 'authors') {
            return blank($value) || $value === 'Authors not listed';
        }

        return blank($value);
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

    /**
     * @param  array{year_from: int|null, year_to: int|null, min_citations: int|null, open_access: bool}  $filters
     */
    private function semanticScholarYear(array $filters): ?string
    {
        if ($filters['year_from'] && $filters['year_to']) {
            return $filters['year_from'].'-'.$filters['year_to'];
        }

        if ($filters['year_from']) {
            return $filters['year_from'].'-';
        }

        if ($filters['year_to']) {
            return '-'.$filters['year_to'];
        }

        return null;
    }

    /**
     * @param  list<string>  $terms
     * @param  array<string, mixed>  $result
     * @return array{relevance_score: int, relevance_label: string, match_reason: string, matched_terms: list<string>, _metadata_score: int, _content_match_count: int, _term_coverage: float, _has_evidence_metadata: bool, _trusted_provider_match: bool}
     */
    private function relevanceSignals(string $query, array $terms, array $result): array
    {
        $normalizedQuery = $this->normalizeSearchText($query);
        $title = $this->normalizeSearchText((string) ($result['title'] ?? ''));
        $description = $this->normalizeSearchText((string) ($result['description'] ?? ''));
        $venue = $this->normalizeSearchText((string) ($result['venue'] ?? ''));
        $titleTerms = $this->matchedTerms($terms, $title);
        $descriptionTerms = $this->matchedTerms($terms, $description);
        $venueTerms = $this->matchedTerms($terms, $venue);
        $contentTerms = collect([...$titleTerms, ...$descriptionTerms])->unique()->values()->all();
        $allMatchedTerms = collect([...$contentTerms, ...$venueTerms])->unique()->values()->all();
        $termCount = count($terms);
        $titleCoverage = count($titleTerms) / $termCount;
        $descriptionCoverage = count($descriptionTerms) / $termCount;
        $venueCoverage = count($venueTerms) / $termCount;
        $termCoverage = count($allMatchedTerms) / $termCount;
        $exactTitlePhrase = $termCount > 1
            && $normalizedQuery !== ''
            && str_contains($title, $normalizedQuery);
        $metadataScore = $this->metadataScore($result);
        $providerRank = (int) ($result['_provider_rank'] ?? 999);
        $providerRankBonus = max(0, 8 - $providerRank);
        $trustedProviderMatch = $providerRank <= 5 && $metadataScore >= 3;
        $score = ($titleCoverage * 46)
            + ($descriptionCoverage * 30)
            + ($venueCoverage * 4)
            + ($exactTitlePhrase ? 10 : 0)
            + (($metadataScore / 6) * 5)
            + $providerRankBonus;

        if ($termCount === 1) {
            $score = min(64, $score);
        } elseif ($termCoverage < 0.5) {
            $score *= 0.72;
        }

        if ($trustedProviderMatch && $contentTerms === []) {
            $score = max(28, $score);
        }

        $score = (int) round(max(0, min(100, $score)));
        $matchedLocations = collect([
            'title' => $titleTerms !== [],
            'abstract' => $descriptionTerms !== [],
            'publication' => $venueTerms !== [],
        ])->filter()->keys()->all();

        return [
            'relevance_score' => $score,
            'relevance_label' => $this->relevanceLabel($score, $termCount === 1),
            'match_reason' => $matchedLocations === []
                ? 'Highly ranked by the academic index for the full query; verify the abstract before using it.'
                : 'Matched in '.collect($matchedLocations)->join(', ', ' and ').'.',
            'matched_terms' => $allMatchedTerms,
            '_metadata_score' => $metadataScore,
            '_content_match_count' => count($contentTerms),
            '_term_coverage' => $termCoverage,
            '_has_evidence_metadata' => ($result['description'] ?? null) !== self::DESCRIPTION_FALLBACK
                || (filled($result['authors'] ?? null) && $result['authors'] !== 'Authors not listed'),
            '_trusted_provider_match' => $trustedProviderMatch,
        ];
    }

    /** @param array<string, mixed> $result */
    private function metadataScore(array $result): int
    {
        return collect([
            ($result['description'] ?? null) !== self::DESCRIPTION_FALLBACK,
            filled($result['authors'] ?? null) && $result['authors'] !== 'Authors not listed',
            filled($result['doi'] ?? null),
            filled($result['year'] ?? null),
            filled($result['venue'] ?? null),
            filled($result['url'] ?? null),
        ])->filter()->count();
    }

    /** @param array<string, mixed> $result */
    private function passesRelevanceGate(array $result, int $termCount): bool
    {
        if (! ($result['_has_evidence_metadata'] ?? false)) {
            return false;
        }

        if ((bool) ($result['_trusted_provider_match'] ?? false)) {
            return true;
        }

        if ((int) ($result['_content_match_count'] ?? 0) === 0) {
            return false;
        }

        if ($termCount >= 4 && (float) ($result['_term_coverage'] ?? 0) < 0.4) {
            return false;
        }

        return $termCount === 1 || (int) ($result['relevance_score'] ?? 0) >= 24;
    }

    private function relevanceLabel(int $score, bool $isBroadQuery): string
    {
        if ($isBroadQuery) {
            return $score >= 45 ? 'Broad keyword match' : 'Possible keyword match';
        }

        return match (true) {
            $score >= 80 => 'Strong match',
            $score >= 60 => 'Good match',
            $score >= 40 => 'Related match',
            default => 'Limited match',
        };
    }

    /**
     * @param  list<string>  $terms
     * @return array{is_broad: bool, term_count: int, message: ?string, suggestion: ?string}
     */
    private function queryGuidance(string $query, array $terms): array
    {
        $isBroad = count($terms) < 2;

        return [
            'is_broad' => $isBroad,
            'term_count' => count($terms),
            'message' => $isBroad
                ? '“'.Str::limit(Str::squish($query), 60).'” is too broad to establish research relevance by itself. Results may use the same word in unrelated fields.'
                : null,
            'suggestion' => $isBroad
                ? 'Add a variable, population, method, or setting—for example: community participation in mangrove monitoring Philippines.'
                : null,
        ];
    }

    /** @return list<string> */
    private function queryTerms(string $query): array
    {
        return collect(explode(' ', $this->normalizeSearchText($query)))
            ->filter(fn (string $term): bool => Str::length($term) >= 2)
            ->reject(fn (string $term): bool => in_array($term, self::QUERY_STOP_WORDS, true))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function matchedTerms(array $terms, string $haystack): array
    {
        $haystackTerms = collect(explode(' ', $haystack))
            ->filter()
            ->unique()
            ->all();

        return collect($terms)
            ->filter(fn (string $term): bool => collect($haystackTerms)
                ->contains(fn (string $haystackTerm): bool => $this->termsBelongToSameFamily($term, $haystackTerm)))
            ->values()
            ->all();
    }

    private function termsBelongToSameFamily(string $left, string $right): bool
    {
        $left = $this->canonicalTerm($left);
        $right = $this->canonicalTerm($right);

        if ($left === $right) {
            return true;
        }

        $shorterLength = min(Str::length($left), Str::length($right));

        if ($shorterLength < 6) {
            return false;
        }

        $commonPrefixLength = 0;

        while ($commonPrefixLength < $shorterLength
            && Str::substr($left, $commonPrefixLength, 1) === Str::substr($right, $commonPrefixLength, 1)) {
            $commonPrefixLength++;
        }

        return $commonPrefixLength >= max(6, (int) floor($shorterLength * 0.65));
    }

    private function canonicalTerm(string $term): string
    {
        if (Str::length($term) > 4 && Str::endsWith($term, 'ies')) {
            return Str::substr($term, 0, -3).'y';
        }

        if (Str::length($term) > 6 && Str::endsWith($term, 'ing')) {
            return Str::substr($term, 0, -3);
        }

        if (Str::length($term) > 5 && Str::endsWith($term, 'ed')) {
            return Str::substr($term, 0, -2);
        }

        if (Str::length($term) > 4 && Str::endsWith($term, 's') && ! Str::endsWith($term, ['ss', 'is', 'us'])) {
            return Str::substr($term, 0, -1);
        }

        return $term;
    }

    private function normalizeSearchText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', ' ')
            ->squish()
            ->toString();
    }

    private function recordFailure(string $provider, string $reason): void
    {
        $source = $this->providers[$provider] ?? $provider;
        $this->failedSources[] = $source;
        $this->failureReasons[$source] = $reason;

        Log::warning('Literature search provider failed.', [
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    private function providerNotice(int $resultCount): ?string
    {
        if ($this->failedSources === [] || $resultCount === 0) {
            return null;
        }

        $availableSources = collect($this->providers)
            ->values()
            ->reject(fn (string $source): bool => in_array($source, $this->failedSources, true))
            ->join(', ', ' and ');
        $rateLimitedSources = collect($this->failureReasons)
            ->filter(fn (string $reason): bool => $reason === 'HTTP 429')
            ->keys()
            ->join(', ', ' and ');
        $resultLabel = $resultCount === 1 ? 'result' : 'results';

        if ($rateLimitedSources !== '') {
            return "Showing {$resultCount} {$resultLabel} from {$availableSources}. {$rateLimitedSources} temporarily rate-limited this search.";
        }

        return "Showing {$resultCount} {$resultLabel} from {$availableSources}. ".collect($this->failedSources)->join(', ', ' and ').' did not respond this time.';
    }

    private function description(mixed $value): string
    {
        $description = $this->cleanText($value);

        if ($description === '') {
            return self::DESCRIPTION_FALLBACK;
        }

        return Str::limit($description, 4000);
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
