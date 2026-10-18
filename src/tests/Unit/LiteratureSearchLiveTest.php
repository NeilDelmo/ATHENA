<?php

use App\Services\LiteratureSearchService;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

if (getenv('ATHENA_TEST_LIVE_LITERATURE') !== '1') {
    test('live literature search checks require explicit opt in')->skip('Set ATHENA_TEST_LIVE_LITERATURE=1 to search the academic indexes with public research topics.');

    return;
}

uses(TestCase::class);

beforeAll(function () {
    $path = __DIR__.'/../../storage/framework/testing/literature-search-live-results.json';
    if (is_file($path)) {
        unlink($path);
    }
});

beforeEach(function () {
    expect(app()->environment('testing'))->toBeTrue();
    config(['literature.web_harvest.enabled' => false, 'cache.default' => 'array']);
    $developmentValues = Dotenv::createArrayBacked(base_path())->safeLoad();
    foreach (['OPENALEX_API_KEY' => 'services.openalex.key', 'SEMANTIC_SCHOLAR_API_KEY' => 'services.semantic_scholar.key'] as $variable => $setting) {
        if (array_key_exists($variable, $developmentValues)) {
            config([$setting => $developmentValues[$variable]]);
        }
    }
    if (array_key_exists('OPENALEX_SEMANTIC_SEARCH', $developmentValues)) {
        config(['services.openalex.semantic_search' => filter_var($developmentValues['OPENALEX_SEMANTIC_SEARCH'], FILTER_VALIDATE_BOOL)]);
    }
    unset($developmentValues);
    Http::preventStrayRequests();
    Http::allowStrayRequests([
        'https://api.semanticscholar.org/graph/v1/paper/search*',
        'https://api.crossref.org/works*',
        'https://api.openalex.org/works*',
        'https://www.ebi.ac.uk/europepmc/webservices/rest/search*',
        'https://api.ies.ed.gov/eric/*',
        'https://doaj.org/api/search/articles/*',
        'https://export.arxiv.org/api/query*',
    ]);
});

afterEach(function () {
    if (! isset($this->liveSearchResult)) {
        return;
    }
    $path = storage_path('framework/testing/literature-search-live-results.json');
    File::ensureDirectoryExists(dirname($path));
    $results = File::isFile($path) ? json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR) : [];
    $results[] = $this->liveSearchResult;
    File::put($path, json_encode($results, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
});

test('live academic search returns identifiable relevant papers with accurate availability notices', function (string $query, string $context) {
    $started = microtime(true);
    $service = app(LiteratureSearchService::class);
    $payload = $service->search($query, [], $context);
    $this->liveSearchResult = [
        'query' => $query,
        'searched_at' => now()->toIso8601String(),
        'elapsed_seconds' => round(microtime(true) - $started, 2),
        'semantic_search_enabled' => (bool) config('services.openalex.semantic_search'),
        'result_count' => count($payload['results']),
        'failed_sources' => $payload['failed_sources'],
        'provider_notice' => $payload['provider_notice'],
        'search_notice' => $payload['search_notice'],
        'search_keywords' => $payload['search_keywords'],
        'results' => collect($payload['results'])->map(fn (array $result): array => [
            'title' => $result['title'],
            'url' => $result['url'],
            'doi' => $result['doi'],
            'source' => $result['source'],
            'relevance_score' => $result['relevance_score'],
            'relevance_label' => $result['relevance_label'],
            'matched_terms' => $result['matched_terms'],
        ])->all(),
    ];

    expect($payload['results'])->not->toBeEmpty()
        ->and($service->allProvidersFailed())->toBeFalse();

    foreach ($payload['results'] as $result) {
        expect(trim($result['title']))->not->toBeEmpty()
            ->and(filter_var($result['url'], FILTER_VALIDATE_URL))->not->toBeFalse()
            ->and(parse_url($result['url'], PHP_URL_SCHEME))->toBeIn(['https', 'http'])
            ->and(count(array_intersect($payload['search_keywords'], $result['matched_terms'])) >= 2 || $result['relevance_label'] === 'Conceptual match')->toBeTrue();
    }

    $dois = collect($payload['results'])->pluck('doi')->filter()->map(fn (string $doi): string => strtolower($doi));
    expect($dois->unique()->count())->toBe($dois->count());
    if ($payload['failed_sources'] === []) {
        expect($payload['provider_notice'])->toBeNull();
    } else {
        expect($payload['provider_notice'])->toBeString()->not->toBeEmpty();
        foreach ($payload['failed_sources'] as $source) {
            expect($payload['provider_notice'])->toContain($source);
        }
    }
})->with([
    'flood warning sensors' => [
        'IoT flood monitoring early warning',
        'Compare connected water level sensors and community flood alerts for a public research proposal.',
    ],
    'university chatbot services' => [
        'natural language processing university student services chatbot',
        'Evaluate how conversational systems answer student service questions in higher education.',
    ],
]);
