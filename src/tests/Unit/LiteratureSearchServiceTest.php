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

test('DOI link variants from separate indexes merge into one search result', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => 'Community Mangrove Monitoring', 'authors' => [['name' => 'Maria Santos']],
            'year' => 2024, 'externalIds' => ['DOI' => 'https://dx.doi.org/10.1000/MONITORING'],
        ]]]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => [[
            'title' => ['Community Mangrove Monitoring'], 'author' => [['given' => 'Maria', 'family' => 'Santos']],
            'published-online' => ['date-parts' => [[2024]]], 'DOI' => '10.1000/monitoring',
        ]]]]),
    ]);

    $results = app(LiteratureSearchService::class)->search('community mangrove monitoring')['results'];

    expect($results)->toHaveCount(1)
        ->and($results[0]['doi'])->toBe('10.1000/monitoring');
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

test('Semantic Scholar requests share a rate limit across different searches', function () {
    $this->freezeTime();
    fakeLiteratureIndexes();

    app(LiteratureSearchService::class)->search('community monitoring');
    $limited = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($limited['failed_sources'])->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(1);

    $this->travel(1)->seconds();
    $available = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($available['failed_sources'])->not->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(2);
});

test('Semantic Scholar overlapping searches reuse cached metadata without duplicate provider requests', function () {
    $nested = null;
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => function () use (&$nested) {
            if ($nested === null) {
                $nested = false;
                $nested = app(LiteratureSearchService::class)->search('community monitoring');
            }

            return Http::response(['data' => []]);
        },
    ]);

    $outer = app(LiteratureSearchService::class)->search('community monitoring');
    $cached = app(LiteratureSearchService::class)->search('community monitoring');

    expect($nested['failed_sources'])->toContain('Semantic Scholar')
        ->and($outer['failed_sources'])->not->toContain('Semantic Scholar')
        ->and($cached['failed_sources'])->not->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(1);
});

test('Semantic Scholar transient server failures retry with increasing delays and cache recovery', function () {
    $delays = [];
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => function ($request, $options) use (&$delays) {
            $delays[] = $options['delay'] ?? 0;

            return count($delays) < 3 ? Http::response([], 503) : Http::response(['data' => []]);
        },
    ]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');

    expect($search['failed_sources'])->not->toContain('Semantic Scholar')
        ->and($delays)->toBe([0, 1000, 2000]);

    Http::fake(['*' => Http::response([], 500)]);
    app(LiteratureSearchService::class)->search('community monitoring');
    Http::assertNothingSent();
});

test('Semantic Scholar authentication failures are not retried and the API key stays in its header', function () {
    config(['services.semantic_scholar.key' => ' semantic-test-key ']);
    fakeLiteratureIndexes(['api.semanticscholar.org/*' => Http::response([], 401)]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');
    $requests = Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org'));

    expect($search['failed_sources'])->toContain('Semantic Scholar')
        ->and($requests)->toHaveCount(1)
        ->and($requests[0][0]->header('x-api-key'))->toBe(['semantic-test-key'])
        ->and($requests[0][0]->url())->not->toContain('semantic-test-key');
});

test('Semantic Scholar cooldown respects Retry-After seconds and dates across searches', function (int $status, bool $dateHeader) {
    $this->freezeTime();
    $retryAfter = $dateHeader ? now()->addSeconds(120)->toRfc7231String() : '120';
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::sequence()->push([], $status, ['Retry-After' => $retryAfter])->push(['data' => []]),
    ]);

    app(LiteratureSearchService::class)->search('community monitoring');
    $this->travel(119)->seconds();
    $limited = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($limited['failed_sources'])->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(1);

    $this->travel(2)->seconds();
    $recovered = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($recovered['failed_sources'])->not->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(2);
})->with([[429, false], [429, true], [503, false], [503, true]]);

test('Semantic Scholar exhausted retries fall back and briefly pause new attempts', function () {
    $this->freezeTime();
    $unavailable = true;
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => function () use (&$unavailable) {
            return $unavailable ? Http::response([], 503) : Http::response(['data' => []]);
        },
        'api.crossref.org/*' => Http::response(['message' => ['items' => [[
            'title' => ['Community Monitoring Programs'], 'published' => ['date-parts' => [[2024]]],
            'abstract' => 'Community monitoring programs improve local participation and environmental monitoring.',
            'author' => [['given' => 'A.', 'family' => 'Researcher']], 'DOI' => '10.1000/community-monitoring',
        ]]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');
    app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($search['results'])->not->toBeEmpty()
        ->and($search['failed_sources'])->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(3);

    $this->travel(31)->seconds();
    $unavailable = false;
    $recovered = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($recovered['failed_sources'])->not->toContain('Semantic Scholar');
    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'api.semanticscholar.org')))->toHaveCount(4);
});

test('Semantic Scholar connection failures release the request lock and recover after cooldown', function () {
    $this->freezeTime();
    $attempts = 0;
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => function () use (&$attempts) {
            $attempts++;

            return $attempts <= 3 ? Http::failedConnection() : Http::response(['data' => []]);
        },
    ]);

    $failed = app(LiteratureSearchService::class)->search('community monitoring');
    app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($failed['failed_sources'])->toContain('Semantic Scholar')
        ->and($attempts)->toBe(3);

    $this->travel(31)->seconds();
    $recovered = app(LiteratureSearchService::class)->search('coastal biodiversity');

    expect($recovered['failed_sources'])->not->toContain('Semantic Scholar')
        ->and($attempts)->toBe(4);
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

test('later proposal subtopics receive a bounded search with the original filters', function () {
    fakeLiteratureIndexes([
        'api.openalex.org/*' => function ($request) {
            return Http::response(['results' => ($request['search'] ?? '') === 'book cataloging indexing circulation borrowing' ? [[
                'display_name' => 'Book Circulation and Borrowing in University Libraries',
                'authorships' => [['author' => ['display_name' => 'Library Researcher']]],
                'publication_year' => 2024, 'doi' => 'https://doi.org/10.1000/borrowing',
                'cited_by_count' => 20, 'open_access' => ['is_oa' => true],
            ]] : []]);
        },
    ]);

    $search = app(LiteratureSearchService::class)->search('Book management system automated cataloging indexing circulation borrowing', [
        'year_from' => 2022, 'year_to' => 2025, 'min_citations' => 5, 'open_access' => true,
    ]);

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['matched_terms'])->toContain('book', 'circulation', 'borrowing');
    Http::assertSent(fn ($request): bool => ($request['search'] ?? '') === 'book cataloging indexing circulation borrowing'
        && str_contains($request['filter'], 'publication_year:2022-2025')
        && str_contains($request['filter'], 'cited_by_count:>4')
        && str_contains($request['filter'], 'is_oa:true'));
    Http::assertSentCount(9);
});

test('short searches can retrieve selected context without replacing the main topic', function () {
    fakeLiteratureIndexes();

    app(LiteratureSearchService::class)->search('flood monitoring', [], 'Evaluate warning accuracy and community response.');

    Http::assertSent(fn ($request): bool => ($request['search'] ?? '') === 'flood monitoring');
    Http::assertSent(fn ($request): bool => ($request['search'] ?? '') === 'flood monitoring warning accuracy');
    Http::assertSentCount(8);
});

test('abbreviations and their full phrases count as one matching research concept', function (string $query, string $title) {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => $title, 'authors' => [['name' => 'Researcher']],
            'year' => 2024, 'externalIds' => ['DOI' => '10.1000/abbreviation'],
        ]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search($query);

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['matched_terms'])->toHaveCount(2);
})->with([
    ['IoT flood', 'Internet of Things for Flood Warning'],
    ['Internet of Things flood', 'IoT Flood Warning'],
    ['NLP chatbot', 'Natural Language Processing for Chatbots'],
    ['large language models education', 'LLM Applications in Education'],
]);

test('partial abbreviation phrases cannot admit unrelated sources', function (string $query, string $title) {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => $title, 'authors' => [['name' => 'Researcher']], 'year' => 2024,
        ]]]),
    ]);

    expect(app(LiteratureSearchService::class)->search($query)['results'])->toBeEmpty();
})->with([
    ['IoT flood', 'Internet Networking During Flood Response'],
    ['Internet of Things flood', 'IoT Networking Architecture'],
    ['NLP chatbot', 'Language Chatbot Interfaces'],
]);

test('abbreviation searches send full phrases to indexes and retain the original topic', function () {
    fakeLiteratureIndexes();

    app(LiteratureSearchService::class)->search('IoT flood monitoring');

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.semanticscholar.org/')
        && $request['query'] === 'internet of things flood monitoring');
    Http::assertSent(fn ($request): bool => ($request['search'] ?? '') === 'iot flood monitoring');
    Http::assertSent(fn ($request): bool => ($request['search'] ?? '') === 'internet of things flood monitoring');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://export.arxiv.org/')
        && str_contains($request['search_query'], '(all:iot OR all:"internet of things") AND all:flood'));
    Http::assertSentCount(8);
});

test('availability notices identify both rate limited and otherwise failed indexes', function () {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response([], 429),
        'api.crossref.org/*' => Http::response([], 503),
        'api.openalex.org/*' => Http::response(['results' => [[
            'display_name' => 'Community Monitoring Programs',
            'authorships' => [['author' => ['display_name' => 'Researcher']]],
            'publication_year' => 2024,
        ]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');

    expect($search['provider_notice'])->toContain('Semantic Scholar', 'rate-limited', 'Crossref', 'did not respond');
});

test('research subject nouns remain searchable even when they can describe objectives', function (string $query) {
    fakeLiteratureIndexes();

    $search = app(LiteratureSearchService::class)->search($query);

    expect($search['search_keywords'])->toHaveCount(2)
        ->and($search['query_guidance']['is_broad'])->toBeFalse();
    Http::assertSentCount(7);
})->with(['formative assessment', 'program evaluation', 'measure theory']);

test('Europe PMC links use the namespace matching the available identifier', function (?string $pmcid, string $expectedUrl) {
    fakeLiteratureIndexes([
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => [[
            'title' => 'Community Monitoring Programs', 'authorString' => 'Researcher',
            'pubYear' => '2024', 'source' => 'MED', 'id' => '12345678', 'pmcid' => $pmcid,
        ]]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search('community monitoring');

    expect($search['results'])->toHaveCount(1)
        ->and($search['results'][0]['url'])->toBe($expectedUrl);
})->with([
    ['PMC1234567', 'https://europepmc.org/articles/PMC1234567'],
    [null, 'https://europepmc.org/article/MED/12345678'],
]);

test('complete technical concepts remain searchable when normalized to an abbreviation', function (string $query) {
    fakeLiteratureIndexes([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'title' => $query.' Applications', 'authors' => [['name' => 'Researcher']], 'year' => 2024,
        ]]]),
    ]);

    $search = app(LiteratureSearchService::class)->search($query);

    expect($search['results'])->toHaveCount(1)
        ->and($search['query_guidance']['message'])->not->toContain('too broad');
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.semanticscholar.org/'));
})->with(['Internet of Things', 'natural language processing', 'IoT']);

test('generic single words still request a more specific research topic', function () {
    fakeLiteratureIndexes();

    expect(app(LiteratureSearchService::class)->search('education')['results'])->toBeEmpty();
    Http::assertNothingSent();
});
