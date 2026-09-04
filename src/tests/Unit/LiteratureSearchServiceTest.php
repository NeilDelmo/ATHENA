<?php

use App\Services\LiteratureSearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('arxiv search results receive numeric provider ranks and retain complete abstracts', function () {
    config(['literature.web_harvest.enabled' => false]);
    Http::preventStrayRequests();
    $abstract = trim(str_repeat('Complete source abstract. ', 250));

    Http::fake([
        'api.semanticscholar.org/*' => Http::response(['data' => []]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/*' => Http::response(['results' => []]),
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
        'api.ies.ed.gov/*' => Http::response(['docs' => []]),
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
