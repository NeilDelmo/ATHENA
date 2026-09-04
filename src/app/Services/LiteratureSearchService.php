<?php

namespace App\Services;

use App\Models\LiteratureSource;
use App\Support\LiteratureFullTextToken;
use Illuminate\Database\QueryException;
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
        'europe_pmc' => 'Europe PMC',
        'eric' => 'ERIC',
        'doaj' => 'DOAJ',
        'arxiv' => 'arXiv',
    ];

    /**
     * @var list<string>
     */
    private array $failedSources = [];

    /** @var array<string, string> */
    private array $failureReasons = [];

    public function __construct(private LiteratureWebSearchService $webSearch) {}

    /**
     * @param  array{year_from?: int|null, year_to?: int|null, min_citations?: int|null, open_access?: bool|null}  $filters
     * @return array{results: list<array<string, mixed>>, failed_sources: list<string>, sources: list<string>, query_guidance: array{is_broad: bool, term_count: int, message: ?string, suggestion: ?string}, provider_notice: ?string}
     */
    public function search(string $query, array $filters = []): array
    {
        $this->failedSources = [];
        $this->failureReasons = [];
        unset($this->providers['web_repositories']);
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
            ...$this->europePmcResults($this->successfulResponse($responses['europe_pmc'] ?? null, 'europe_pmc')),
            ...$this->ericResults($this->successfulResponse($responses['eric'] ?? null, 'eric')),
            ...$this->doajResults($this->successfulResponse($responses['doaj'] ?? null, 'doaj')),
            ...$this->arxivResults($this->successfulResponse($responses['arxiv'] ?? null, 'arxiv')),
            ...$this->webRepositoryResults($query),
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

                $result['access_status'] = $this->normalizeAccessStatus($result);
                $result['access_label'] = LiteratureSource::accessLabel($result['access_status']);
                $result['full_text_provider'] = filled($result['full_text_url'] ?? null)
                    ? ($result['_full_text_provider'] ?? $result['source'])
                    : null;
                $result['full_text_token'] = filled($result['full_text_url'] ?? null)
                    ? LiteratureFullTextToken::issue([
                        'url' => (string) $result['full_text_url'],
                        'provider' => (string) $result['full_text_provider'],
                        'identifier' => (string) ($result['provider_identifier'] ?? $result['doi'] ?? $result['url'] ?? ''),
                    ])
                    : null;
                unset($result['_full_text_provider']);

                return $result;
            })
            ->values()
            ->all();

        if (collect($results)->contains(fn (array $result): bool => ($result['provenance'] ?? []) !== [])) {
            $this->providers['web_repositories'] = 'Web repositories';
        }

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

    /** @return list<array<string, mixed>> */
    private function webRepositoryResults(string $query): array
    {
        try {
            return array_map(fn (array $result): array => $this->normalizedResult($result), $this->webSearch->search($query));
        } catch (QueryException $exception) {
            $this->providers['web_repositories'] = 'Web repositories';
            $this->recordFailure('web_repositories', $exception::class);

            return [];
        }
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
            'select' => 'display_name,abstract_inverted_index,authorships,publication_year,publication_date,primary_location,best_oa_location,doi,id,cited_by_count,open_access,type',
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
            'fields' => 'paperId,title,abstract,authors,year,venue,url,externalIds,citationCount,openAccessPdf,publicationTypes,publicationDate,journal',
        ];

        $semanticScholarYear = $this->semanticScholarYear($filters);

        if ($semanticScholarYear !== null) {
            $semanticScholarParameters['year'] = $semanticScholarYear;
        }

        if ($filters['open_access']) {
            $semanticScholarParameters['openAccessPdf'] = 'true';
        }

        $semanticScholarApiKey = (string) config('services.semantic_scholar.key');

        return Http::pool(function (Pool $pool) use ($query, $crossrefParameters, $openAlexParameters, $semanticScholarApiKey, $semanticScholarParameters): array {
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
                $pool->as('europe_pmc')
                    ->acceptJson()
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://www.ebi.ac.uk/europepmc/webservices/rest/search', [
                        'query' => $query,
                        'format' => 'json',
                        'resultType' => 'core',
                        'pageSize' => self::PROVIDER_RESULT_LIMIT,
                    ]),
                $pool->as('eric')
                    ->acceptJson()
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://api.ies.ed.gov/eric/', [
                        'search' => $query,
                        'format' => 'json',
                        'rows' => self::PROVIDER_RESULT_LIMIT,
                    ]),
                $pool->as('doaj')
                    ->acceptJson()
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://doaj.org/api/search/articles/'.rawurlencode($query), [
                        'pageSize' => self::PROVIDER_RESULT_LIMIT,
                    ]),
                $pool->as('arxiv')
                    ->withHeaders(['User-Agent' => 'Athena Research Support ('.config('mail.from.address', 'hello@example.com').')'])
                    ->connectTimeout(6)
                    ->timeout(12)
                    ->get('https://export.arxiv.org/api/query', [
                        'search_query' => 'all:"'.$query.'"',
                        'start' => 0,
                        'max_results' => self::PROVIDER_RESULT_LIMIT,
                        'sortBy' => 'relevance',
                        'sortOrder' => 'descending',
                    ]),
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
                'publication_date' => $paper['publicationDate'] ?? null,
                'venue' => $this->cleanText($paper['venue'] ?? null),
                'volume' => $this->cleanText(data_get($paper, 'journal.volume')) ?: null,
                'issue' => null,
                'pages' => $this->cleanText(data_get($paper, 'journal.pages')) ?: null,
                'publisher' => null,
                'doi' => $this->normalizeDoi($paper['externalIds']['DOI'] ?? null),
                'url' => $paper['url'] ?? $this->doiUrl($paper['externalIds']['DOI'] ?? null),
                'full_text_url' => data_get($paper, 'openAccessPdf.url'),
                'provider_identifier' => $paper['paperId'] ?? null,
                'source' => $this->providers['semantic_scholar'],
                'citation_count' => $paper['citationCount'] ?? null,
                'is_open_access' => filled($paper['openAccessPdf']['url'] ?? null),
                'access_status' => filled($paper['openAccessPdf']['url'] ?? null)
                    ? LiteratureSource::ACCESS_OPEN
                    : (filled($paper['abstract'] ?? null) ? LiteratureSource::ACCESS_ABSTRACT_ONLY : LiteratureSource::ACCESS_UNKNOWN),
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
            ->map(function (array $work, int $index): array {
                $fullTextUrl = $this->crossrefOpenAccessUrl($work);

                return [
                    'title' => $this->cleanText($work['title'][0] ?? null),
                    'description' => $this->description($work['abstract'] ?? null),
                    'authors' => $this->formatCrossrefAuthors($work['author'] ?? []),
                    'year' => $this->crossrefYear($work),
                    'publication_date' => $this->crossrefDate($work),
                    'venue' => $this->cleanText($work['container-title'][0] ?? null),
                    'volume' => $this->cleanText($work['volume'] ?? null) ?: null,
                    'issue' => $this->cleanText($work['issue'] ?? null) ?: null,
                    'pages' => $this->cleanText($work['page'] ?? null) ?: null,
                    'publisher' => $this->cleanText($work['publisher'] ?? null) ?: null,
                    'doi' => $this->normalizeDoi($work['DOI'] ?? null),
                    'url' => $work['URL'] ?? $this->doiUrl($work['DOI'] ?? null),
                    'full_text_url' => $fullTextUrl,
                    'provider_identifier' => $work['DOI'] ?? null,
                    'source' => $this->providers['crossref'],
                    'citation_count' => $work['is-referenced-by-count'] ?? null,
                    'is_open_access' => filled($fullTextUrl),
                    'access_status' => filled($fullTextUrl)
                        ? LiteratureSource::ACCESS_OPEN
                        : (filled($work['abstract'] ?? null) ? LiteratureSource::ACCESS_ABSTRACT_ONLY : LiteratureSource::ACCESS_UNKNOWN),
                    'type' => $this->cleanText($work['type'] ?? null),
                    '_provider_rank' => $index + 1,
                ];
            })
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
                'publication_date' => $work['publication_date'] ?? null,
                'venue' => $this->cleanText(data_get($work, 'primary_location.source.display_name')),
                'volume' => null,
                'issue' => null,
                'pages' => null,
                'publisher' => null,
                'doi' => $this->normalizeDoi($work['doi'] ?? null),
                'url' => data_get($work, 'primary_location.landing_page_url') ?? $this->doiUrl($work['doi'] ?? null) ?? ($work['id'] ?? null),
                'full_text_url' => data_get($work, 'best_oa_location.pdf_url') ?? data_get($work, 'primary_location.pdf_url'),
                'provider_identifier' => $work['id'] ?? null,
                'source' => $this->providers['openalex'],
                'citation_count' => $work['cited_by_count'] ?? null,
                'is_open_access' => (bool) data_get($work, 'open_access.is_oa', false),
                'access_status' => $this->openAlexAccessStatus($work),
                'type' => $this->cleanText($work['type'] ?? null),
                '_provider_rank' => $index + 1,
            ])
            ->filter(fn (array $result) => filled($result['title']))
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function europePmcResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('resultList.result', []))
            ->map(function (array $paper, int $index): array {
                $identifier = $paper['pmcid'] ?? $paper['id'] ?? null;
                $isOpen = in_array(Str::lower((string) ($paper['isOpenAccess'] ?? '')), ['true', 'yes', 'y', '1'], true)
                    && filled($paper['pmcid'] ?? null);
                $fullTextUrl = $isOpen ? 'https://europepmc.org/articles/'.$paper['pmcid'].'?pdf=render' : null;

                return $this->normalizedResult([
                    'title' => $paper['title'] ?? null,
                    'description' => $paper['abstractText'] ?? null,
                    'authors' => $paper['authorString'] ?? null,
                    'year' => $paper['pubYear'] ?? null,
                    'publication_date' => $paper['firstPublicationDate'] ?? null,
                    'venue' => $paper['journalTitle'] ?? null,
                    'volume' => $paper['journalVolume'] ?? null,
                    'issue' => $paper['issue'] ?? null,
                    'pages' => $paper['pageInfo'] ?? null,
                    'publisher' => null,
                    'doi' => $paper['doi'] ?? null,
                    'url' => $identifier ? 'https://europepmc.org/article/'.($paper['source'] ?? 'MED').'/'.$identifier : null,
                    'full_text_url' => $fullTextUrl,
                    'provider_identifier' => $identifier,
                    'source' => $this->providers['europe_pmc'],
                    'citation_count' => $paper['citedByCount'] ?? null,
                    'is_open_access' => $isOpen,
                    'access_status' => $isOpen ? LiteratureSource::ACCESS_OPEN : null,
                    'type' => $paper['pubType'] ?? 'journal-article',
                    '_provider_rank' => $index + 1,
                ]);
            })
            ->filter(fn (array $result) => filled($result['title']))
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function ericResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('response.docs', []))
            ->map(function (array $paper, int $index): array {
                $identifier = $paper['id'] ?? null;
                $isOpen = in_array(Str::lower((string) ($paper['fullTextAvailable'] ?? '')), ['true', 'yes', 'y', '1'], true)
                    && filled($identifier);

                return $this->normalizedResult([
                    'title' => $paper['title'] ?? null,
                    'description' => $paper['description'] ?? null,
                    'authors' => is_array($paper['author'] ?? null) ? implode(', ', $paper['author']) : ($paper['author'] ?? null),
                    'year' => $this->yearFromDate($paper['publicationdateyear'] ?? $paper['publicationdate'] ?? null),
                    'publication_date' => $paper['publicationdate'] ?? null,
                    'venue' => $paper['source'] ?? null,
                    'publisher' => $paper['publisher'] ?? null,
                    'doi' => $paper['doi'] ?? null,
                    'url' => $paper['url'] ?? ($identifier ? 'https://eric.ed.gov/?id='.$identifier : null),
                    'full_text_url' => $isOpen ? 'https://files.eric.ed.gov/fulltext/'.$identifier.'.pdf' : null,
                    'provider_identifier' => $identifier,
                    'source' => $this->providers['eric'],
                    'is_open_access' => $isOpen,
                    'access_status' => $isOpen ? LiteratureSource::ACCESS_OPEN : null,
                    'type' => $paper['publicationtype'] ?? null,
                    '_provider_rank' => $index + 1,
                ]);
            })
            ->filter(fn (array $result) => filled($result['title']))
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function doajResults(?Response $response): array
    {
        if (! $response) {
            return [];
        }

        return collect($response->json('results', []))
            ->map(function (array $record, int $index): array {
                $paper = $record['bibjson'] ?? [];
                $fullTextUrl = data_get(collect($paper['link'] ?? [])->firstWhere('type', 'fulltext'), 'url');
                $doi = data_get(collect($paper['identifier'] ?? [])->firstWhere('type', 'doi'), 'id');
                $journal = $paper['journal'] ?? [];

                return $this->normalizedResult([
                    'title' => $paper['title'] ?? null,
                    'description' => $paper['abstract'] ?? null,
                    'authors' => $this->formatAuthorNames(collect($paper['author'] ?? [])->pluck('name')->filter()->all()),
                    'year' => $paper['year'] ?? null,
                    'publication_date' => filled($paper['year'] ?? null) ? $paper['year'].'-01-01' : null,
                    'venue' => $journal['title'] ?? null,
                    'volume' => $journal['volume'] ?? null,
                    'issue' => $journal['number'] ?? null,
                    'pages' => $this->pageRange($paper['start_page'] ?? null, $paper['end_page'] ?? null),
                    'publisher' => data_get($paper, 'publisher.name'),
                    'doi' => $doi,
                    'url' => $this->doiUrl($doi) ?? $fullTextUrl,
                    'full_text_url' => $fullTextUrl,
                    'provider_identifier' => $record['id'] ?? null,
                    'source' => $this->providers['doaj'],
                    'is_open_access' => filled($fullTextUrl),
                    'access_status' => filled($fullTextUrl) ? LiteratureSource::ACCESS_OPEN : null,
                    'type' => 'journal-article',
                    '_provider_rank' => $index + 1,
                ]);
            })
            ->filter(fn (array $result) => filled($result['title']))
            ->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function arxivResults(?Response $response): array
    {
        if (! $response || trim($response->body()) === '') {
            return [];
        }

        $xml = simplexml_load_string($response->body());

        if ($xml === false) {
            $this->recordFailure('arxiv', 'Invalid XML response');

            return [];
        }

        return collect($xml->entry)
            ->values()
            ->map(function (\SimpleXMLElement $entry, int $index): array {
                $links = collect($entry->link)->map(fn (\SimpleXMLElement $link): array => [
                    'href' => (string) $link['href'],
                    'type' => (string) $link['type'],
                    'title' => (string) $link['title'],
                ]);
                $pdfUrl = data_get($links->first(fn (array $link): bool => $link['title'] === 'pdf' || $link['type'] === 'application/pdf'), 'href');
                $namespaces = $entry->getNameSpaces(true);
                $arxiv = isset($namespaces['arxiv']) ? $entry->children($namespaces['arxiv']) : null;
                $identifier = basename((string) $entry->id);

                return $this->normalizedResult([
                    'title' => (string) $entry->title,
                    'description' => (string) $entry->summary,
                    'authors' => $this->formatAuthorNames(collect($entry->author)->map(fn (\SimpleXMLElement $author): string => (string) $author->name)->filter()->all()),
                    'year' => $this->yearFromDate((string) $entry->published),
                    'publication_date' => substr((string) $entry->published, 0, 10),
                    'venue' => ($arxiv ? trim((string) $arxiv->journal_ref) : '') ?: 'arXiv preprint',
                    'doi' => $arxiv ? (string) $arxiv->doi : null,
                    'url' => (string) $entry->id,
                    'full_text_url' => $pdfUrl,
                    'provider_identifier' => $identifier,
                    'source' => $this->providers['arxiv'],
                    'is_open_access' => filled($pdfUrl),
                    'access_status' => filled($pdfUrl) ? LiteratureSource::ACCESS_OPEN : null,
                    'type' => 'preprint',
                    '_provider_rank' => $index + 1,
                ]);
            })
            ->filter(fn (array $result) => filled($result['title']))
            ->values()->all();
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
            $merged['provenance'] = array_values(array_unique([
                ...($merged['provenance'] ?? []),
                ...($duplicate['provenance'] ?? []),
            ], SORT_REGULAR));
            if (blank($merged['full_text_url'] ?? null) && filled($duplicate['full_text_url'] ?? null)) {
                $merged['_full_text_provider'] = $duplicate['_full_text_provider'] ?? $duplicate['source'];
            }

            foreach ([
                'description', 'authors', 'year', 'publication_date', 'venue', 'volume', 'issue',
                'pages', 'publisher', 'doi', 'url', 'full_text_url', 'provider_identifier', 'type',
            ] as $field) {
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
            $merged['access_status'] = $this->preferredAccessStatus(
                $merged['access_status'] ?? null,
                $duplicate['access_status'] ?? null,
            );
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

        if ($filters['open_access'] && ! (bool) ($result['is_open_access'] ?? false)
            && $this->normalizeAccessStatus($result) !== LiteratureSource::ACCESS_OPEN) {
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

        // Keep every character supplied by the academic index. The interface can
        // collapse a long abstract for reading comfort, but it must not silently
        // discard the remainder of the source text.
        return $description;
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

    private function crossrefDate(array $work): ?string
    {
        $parts = $work['published-print']['date-parts'][0]
            ?? $work['published-online']['date-parts'][0]
            ?? $work['published']['date-parts'][0]
            ?? null;

        if (! is_array($parts) || ! is_numeric($parts[0] ?? null)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', (int) $parts[0], (int) ($parts[1] ?? 1), (int) ($parts[2] ?? 1));
    }

    private function crossrefOpenAccessUrl(array $work): ?string
    {
        $hasOpenLicense = collect($work['license'] ?? [])->contains(function (array $license): bool {
            $url = Str::lower((string) ($license['URL'] ?? ''));

            return Str::contains($url, ['creativecommons.org', 'creativecommons.net', 'publicdomain']);
        });

        if (! $hasOpenLicense) {
            return null;
        }

        $link = collect($work['link'] ?? [])->first(function (array $link): bool {
            $contentType = Str::lower((string) ($link['content-type'] ?? ''));

            return filled($link['URL'] ?? null) && Str::contains($contentType, ['pdf', 'html', 'text/plain']);
        });

        return is_array($link) ? ($link['URL'] ?? null) : null;
    }

    private function openAlexAccessStatus(array $work): string
    {
        $fullTextUrl = data_get($work, 'best_oa_location.pdf_url') ?? data_get($work, 'primary_location.pdf_url');

        if (filled($fullTextUrl) && data_get($work, 'open_access.is_oa') === true) {
            return LiteratureSource::ACCESS_OPEN;
        }

        if (data_get($work, 'open_access.is_oa') === false) {
            return LiteratureSource::ACCESS_RESTRICTED;
        }

        return filled($this->openAlexAbstract($work['abstract_inverted_index'] ?? null))
            ? LiteratureSource::ACCESS_ABSTRACT_ONLY
            : LiteratureSource::ACCESS_UNKNOWN;
    }

    /** @param array<string, mixed> $result */
    private function normalizedResult(array $result): array
    {
        $description = $this->description($result['description'] ?? null);
        $fullTextUrl = filled($result['full_text_url'] ?? null) ? (string) $result['full_text_url'] : null;
        $accessStatus = $result['access_status'] ?? null;

        if (! in_array($accessStatus, [
            LiteratureSource::ACCESS_OPEN,
            LiteratureSource::ACCESS_ABSTRACT_ONLY,
            LiteratureSource::ACCESS_RESTRICTED,
            LiteratureSource::ACCESS_UNKNOWN,
        ], true)) {
            $accessStatus = $fullTextUrl
                ? LiteratureSource::ACCESS_OPEN
                : ($description !== self::DESCRIPTION_FALLBACK ? LiteratureSource::ACCESS_ABSTRACT_ONLY : LiteratureSource::ACCESS_UNKNOWN);
        }

        return [
            'title' => $this->cleanText($result['title'] ?? null),
            'description' => $description,
            'authors' => $this->cleanText($result['authors'] ?? null) ?: 'Authors not listed',
            'year' => is_numeric($result['year'] ?? null) ? (int) $result['year'] : null,
            'publication_date' => $this->normalizePublicationDate($result['publication_date'] ?? null),
            'venue' => $this->cleanText($result['venue'] ?? null) ?: null,
            'volume' => $this->cleanText($result['volume'] ?? null) ?: null,
            'issue' => $this->cleanText($result['issue'] ?? null) ?: null,
            'pages' => $this->cleanText($result['pages'] ?? null) ?: null,
            'publisher' => $this->cleanText($result['publisher'] ?? null) ?: null,
            'doi' => $this->normalizeDoi($result['doi'] ?? null),
            'url' => filled($result['url'] ?? null) ? (string) $result['url'] : null,
            'full_text_url' => $fullTextUrl,
            'provider_identifier' => filled($result['provider_identifier'] ?? null) ? (string) $result['provider_identifier'] : null,
            'source' => (string) $result['source'],
            'citation_count' => is_numeric($result['citation_count'] ?? null) ? (int) $result['citation_count'] : null,
            'is_open_access' => $accessStatus === LiteratureSource::ACCESS_OPEN,
            'access_status' => $accessStatus,
            'type' => $this->cleanText($result['type'] ?? null) ?: null,
            '_provider_rank' => (int) ($result['_provider_rank'] ?? 999),
            'provenance' => $result['provenance'] ?? [],
        ];
    }

    /** @param array<string, mixed> $result */
    private function normalizeAccessStatus(array $result): string
    {
        if (filled($result['full_text_url'] ?? null)) {
            return LiteratureSource::ACCESS_OPEN;
        }

        $status = $result['access_status'] ?? null;

        if (in_array($status, [LiteratureSource::ACCESS_ABSTRACT_ONLY, LiteratureSource::ACCESS_RESTRICTED, LiteratureSource::ACCESS_UNKNOWN], true)) {
            return $status;
        }

        return ($result['description'] ?? null) !== self::DESCRIPTION_FALLBACK
            ? LiteratureSource::ACCESS_ABSTRACT_ONLY
            : LiteratureSource::ACCESS_UNKNOWN;
    }

    private function preferredAccessStatus(mixed $left, mixed $right): string
    {
        $weights = [
            LiteratureSource::ACCESS_OPEN => 4,
            LiteratureSource::ACCESS_ABSTRACT_ONLY => 3,
            LiteratureSource::ACCESS_RESTRICTED => 2,
            LiteratureSource::ACCESS_UNKNOWN => 1,
        ];

        $left = isset($weights[$left]) ? $left : LiteratureSource::ACCESS_UNKNOWN;
        $right = isset($weights[$right]) ? $right : LiteratureSource::ACCESS_UNKNOWN;

        return $weights[$left] >= $weights[$right] ? $left : $right;
    }

    private function yearFromDate(mixed $date): ?int
    {
        if (! is_scalar($date) || ! preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', (string) $date, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function normalizePublicationDate(mixed $date): ?string
    {
        if (! is_scalar($date)) {
            return null;
        }

        $date = trim((string) $date);

        if (preg_match('/^(1[5-9]\d{2}|20\d{2})$/', $date) === 1) {
            return $date.'-01-01';
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($date, 0, 10)) === 1
            ? substr($date, 0, 10)
            : null;
    }

    private function pageRange(mixed $start, mixed $end): ?string
    {
        $start = $this->cleanText($start);
        $end = $this->cleanText($end);

        return match (true) {
            $start !== '' && $end !== '' => $start.'-'.$end,
            $start !== '' => $start,
            $end !== '' => $end,
            default => null,
        };
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
