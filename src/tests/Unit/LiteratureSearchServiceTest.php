<?php

use App\Services\LiteratureSearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config(['literature.web_harvest.enabled' => false, 'services.openalex.semantic_search' => false]);
    Http::preventStrayRequests();
    Cache::flush();
});

function fakeLiteratureIndexes(array $overrides = []): void
{
    Http::fake([
        ...[
            'api.semanticscholar.org/*' => Http::response(['data' => []]),
            'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
            'api.openalex.org/*' => Http::response(['results' => []]),
            'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
            'api.ies.ed.gov/*' => Http::response(['response' => ['docs' => []]]),
            'doaj.org/*' => Http::response(['results' => []]),
            'export.arxiv.org/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"/>'),
        ],
        ...$overrides,
    ]);
}

test('arxiv search results receive numeric provider ranks and retain complete abstracts', function () {
    config(['literature.web_harvest.enabled' => false]);
    Http::preventStrayRequests();
    $abstract = trim(str_repeat('Complete source abstract. ', 250));

    Http::fake([
        'api.semanticscholar.org/*' => Http::response(['data' => []]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/*' => Http::response(['results' => []]),
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
        'api.ies.ed.gov/*' => Http::response(['response' => ['docs' => []]]),
        'doaj.org/*' => Http::response(['results' => []]),
        'export.arxiv.org/*' => Http::response(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <feed xmlns="http://www.w3.org/2005/Atom">
                <entry>
                    <id>http://arxiv.org/abs/2608.12345</id>
                    <updated>2026-08-11T00:00:00Z</updated>
                    <published>2026-08-11T00:00:00Z</published>
                    <title>Book Management System Research</title>
                    <summary>{$abstract}</summary>
                    <author><name>Jane Researcher</name></author>
                    <link href="http://arxiv.org/abs/2608.12345" rel="alternate" type="text/html"/>
                </entry>
            </feed>
            XML, 200, ['Content-Type' => 'application/atom+xml']),
    ]);

    $search = app(LiteratureSearchService::class)->search('book management');

    expect($search['results'])
        ->toHaveCount(1)
        ->and($search['results'][0]['title'])->toBe('Book Management System Research')
        ->and($search['results'][0]['description'])->toBe($abstract)
        ->and($search['results'][0]['source'])->toBe('arXiv');
});

test('long proposal searches use focused and broader retrieval instead of requiring an exact sentence', function () {
    fakeLiteratureIndexes();

    app(LiteratureSearchService::class)->search('Book Management System automated cataloging indexing circulation borrowing');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.openalex.org/works')
        && $request['search'] === 'book management system automated');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.openalex.org/works')
        && str_contains($request['search'], '(book AND management) AND (system OR automated'));
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://export.arxiv.org/')
        && str_contains($request['search_query'], 'all:book AND all:management')
        && str_contains($request['search_query'], 'OR all:cataloging')
        && ! str_contains($request['search_query'], 'all:"'));
});

test('long proposal searches retain useful studies about individual RRL subtopics', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Automated Book Cataloging in Libraries',
            'abstract' => 'Automated book cataloging improves circulation and borrowing in university libraries.',
            'authors' => [['name' => 'Library Researcher']],
            'year' => 2024,
            'url' => 'https://example.org/cataloging',
        ]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search('Book Management System automated cataloging module rapid indexing searching circulation borrowing');

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['matched_terms'])->toContain('cataloging', 'circulation', 'borrowing');
});

test('index rank and publication names cannot admit unrelated papers', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Clinical Tumor Detection',
            'abstract' => 'A clinical oncology evaluation of diagnostic imaging.',
            'authors' => [['name' => 'Researcher']],
            'year' => 2024,
            'venue' => 'Book Management Journal',
            'externalIds' => ['DOI' => '10.1000/unrelated'],
            'citationCount' => 900,
            'url' => 'https://example.org/unrelated',
        ]]]),
    ]);

    expect(app(LiteratureSearchService::class)->search('book management')['results'])->toBeEmpty();
});

test('shared prefixes do not make classification match classical literature', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Classification of Music Signals',
            'abstract' => 'Signal classification for music recognition.',
            'authors' => [['name' => 'Researcher']],
            'year' => 2024,
            'url' => 'https://example.org/classification',
        ]]]),
    ]);

    expect(app(LiteratureSearchService::class)->search('classical music')['results'])->toBeEmpty();
});

test('semantic results with different terminology are explicitly labeled conceptual matches', function () {
    config(['services.openalex.semantic_search' => true]);
    fakeLiteratureIndexes([
        'api.openalex.org/*' => function ($request) {
            return Http::response(['results' => isset($request['search.semantic']) ? [[
                'id' => 'https://openalex.org/W123',
                'display_name' => 'QSAR for Computational Toxicology',
                'abstract_inverted_index' => [
                    'Computational' => [0], 'toxicology' => [1], 'evaluates' => [2], 'molecular' => [3],
                    'properties' => [4], 'with' => [5], 'quantitative' => [6], 'structure' => [7],
                    'activity' => [8], 'relationships' => [9], 'and' => [10], 'experimental' => [11], 'validation.' => [12],
                ],
                'authorships' => [['author' => ['display_name' => 'Researcher']]],
                'publication_year' => 2024,
                'doi' => 'https://doi.org/10.1000/qsar',
            ]] : []]);
        },
    ]);

    $search = app(LiteratureSearchService::class)->search('drug toxicity', [], 'Predict adverse reactions from the chemical composition of pharmaceutical compounds.');

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['relevance_label'])->toBe('Conceptual match')
        ->and($search['results'][0]['match_reason'])->toContain('Related by meaning')
        ->and($search['results'][0])->not->toHaveKey('_semantic_match');
});

test('duplicates without a DOI merge before citation and access filters are applied', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Community Mangrove Monitoring',
            'abstract' => 'Community mangrove monitoring supports coastal stewardship.',
            'authors' => [['name' => 'Maria Santos']],
            'year' => 2024,
            'url' => 'https://example.org/monitoring',
            'openAccessPdf' => ['url' => 'https://example.org/monitoring.pdf'],
        ]]]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => [[
            'title' => ['Community Mangrove Monitoring'],
            'author' => [['given' => 'Maria', 'family' => 'Santos']],
            'published-online' => ['date-parts' => [[2024]]],
            'DOI' => '10.1000/monitoring',
            'URL' => 'https://doi.org/10.1000/monitoring',
            'is-referenced-by-count' => 24,
        ]]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search('community mangrove monitoring', ['min_citations' => 10, 'open_access' => true]);

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['doi'])->toBe('10.1000/monitoring')
        ->and($search['results'][0]['citation_count'])->toBe(24)
        ->and($search['results'][0]['full_text_provider'])->toBe('Semantic Scholar');
});

test('different papers with the same title and distinct DOIs remain separate', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => collect(['one', 'two'])->map(fn ($id): array => [
            'title' => 'Community Mangrove Monitoring',
            'abstract' => 'Community mangrove monitoring evidence.',
            'authors' => [['name' => 'Researcher']],
            'year' => 2024,
            'externalIds' => ['DOI' => '10.1000/'.$id],
        ])->all()]),
    ]);

    expect(app(LiteratureSearchService::class)->search('community mangrove monitoring')['results'])->toHaveCount(2);
});

test('a failed focused query does not discard a successful broader query from the same index', function () {
    fakeLiteratureIndexes([
        'api.openalex.org/*' => function ($request) {
            return str_contains($request['search'], ' OR ')
                ? Http::response(['results' => [[
                    'display_name' => 'Book Cataloging and Circulation',
                    'abstract_inverted_index' => ['Book' => [0], 'cataloging' => [1], 'circulation' => [2]],
                    'authorships' => [['author' => ['display_name' => 'Researcher']]],
                    'publication_year' => 2024,
                ]]])
                : Http::response([], 429);
        },
    ]);

    $search = app(LiteratureSearchService::class)->search('book management system cataloging circulation borrowing');

    expect($search['results'])->toHaveCount(1)
        ->and($search['failed_sources'])->not->toContain('OpenAlex')
        ->and($search['search_notice'])->toContain('Some OpenAlex searches were unavailable');
});

test('repeated searches reuse valid cached metadata', function () {
    fakeLiteratureIndexes();
    app(LiteratureSearchService::class)->search('community monitoring');
    Http::assertSentCount(7);
    Http::fake(['*' => Http::response([], 500)]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');

    expect($search['failed_sources'])->toBeEmpty();
    Http::assertNothingSent();
});

test('malformed successful responses are reported and not cached', function () {
    fakeLiteratureIndexes(['api.openalex.org/*' => Http::sequence()
        ->push(['error' => 'Unexpected upstream response'])
        ->push(['results' => []])]);
    $search = app(LiteratureSearchService::class)->search('community monitoring');
    expect($search['failed_sources'])->toContain('OpenAlex')
        ->and($search['provider_notice'])->toContain('Some indexes were unavailable');

    $search = app(LiteratureSearchService::class)->search('community monitoring');
    expect($search['failed_sources'])->toBeEmpty();
    Http::assertSentCount(8);
});
