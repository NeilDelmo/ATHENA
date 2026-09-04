<?php

use App\Exceptions\LiteratureHarvestException;
use App\Jobs\HarvestLiteraturePage;
use App\Models\HarvestedLiteratureSource;
use App\Services\LiteratureSearchService;
use App\Services\LiteratureWebFetcher;
use App\Services\LiteratureWebHarvester;
use App\Services\LiteratureWebMetadataParser;
use App\Services\LiteratureWebSearchService;
use App\Support\LiteratureRobotsRules;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'literature.web_harvest.enabled' => true,
        'literature.web_harvest.repositories' => [
            'test-repository' => [
                'name' => 'Test University',
                'origin' => 'https://repository.example.org',
                'sitemap' => 'https://repository.example.org/sitemap',
                'sitemap_pattern' => '~^/sitemap(?:\?map=\d+)?$~D',
                'item_pattern' => '~^/handle/1234/\d+$~D',
            ],
        ],
    ]);
    Http::preventStrayRequests();
    Sleep::fake();
    Cache::flush();
});

afterEach(function () {
    if ($this->usesHarvestDatabase ?? false) {
        HarvestedLiteratureSource::where('repository_key', 'test-repository')->delete();
    }
});

function publicHarvestFetcher(array $addresses = ['93.184.216.34']): LiteratureWebFetcher
{
    $fetcher = Mockery::mock(LiteratureWebFetcher::class, [new LiteratureRobotsRules])
        ->makePartial()->shouldAllowMockingProtectedMethods();
    $fetcher->shouldReceive('resolveAddresses')->andReturn($addresses);
    app()->instance(LiteratureWebFetcher::class, $fetcher);

    return $fetcher;
}

function harvestCitationHtml(): string
{
    return <<<'HTML'
        <html><head>
        <meta name="citation_title" content="Library management research">
        <meta name="citation_author" content="Reyes, José">
        <meta name="citation_author" content="Cruz, Ana">
        <meta name="citation_date" content="2024-03-10">
        <meta name="citation_doi" content="https://doi.org/10.1234/LIBRARY">
        <meta name="DC.description" content="This library management research examines cataloging and information retrieval in academic libraries.">
        <meta name="citation_journal_title" content="Journal of Library Research">
        <meta name="DC.rights" content="CC BY 4.0">
        <meta name="DC.type" content="Journal article">
        <meta name="citation_pdf_url" content="https://repository.example.org/private.pdf">
        </head><body><script>untrusted()</script><p>Navigation, not evidence</p></body></html>
        HTML;
}

function prepareHarvestTestDatabase(): void
{
    if (DB::connection()->getDatabaseName() !== 'athena_testing') {
        throw new RuntimeException('Harvest tests must use the isolated athena_testing database.');
    }

    if (! Schema::hasTable('harvested_literature_sources')) {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/2026_08_31_114508_create_harvested_literature_sources_table.php',
            '--force' => true,
            '--no-interaction' => true,
        ]);
    }

    test()->usesHarvestDatabase = true;
}

function fakeHarvestPages(string $itemBody = '', int $itemStatus = 200, array $itemHeaders = []): void
{
    Http::fake([
        'repository.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /discover\n", 200, ['Content-Type' => 'text/plain']),
        'repository.example.org/sitemap' => Http::response('<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>https://repository.example.org/sitemap?map=0</loc></sitemap></sitemapindex>', 200, ['Content-Type' => 'application/xml']),
        'repository.example.org/sitemap?map=0' => Http::response('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://repository.example.org/handle/1234/1</loc></url><url><loc>https://repository.example.org/handle/1234/1</loc></url><url><loc>https://repository.example.org/handle/1234/2</loc></url><url><loc>https://evil.example/handle/1234/3</loc></url><url><loc>https://repository.example.org/login</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'repository.example.org/handle/*' => Http::response($itemBody ?: harvestCitationHtml(), $itemStatus, ['Content-Type' => 'text/html', ...$itemHeaders]),
    ]);
}

test('scholarly metadata is extracted without downloading or claiming full text', function () {
    $metadata = app(LiteratureWebMetadataParser::class)->parse(harvestCitationHtml());

    expect($metadata)->toMatchArray([
        'title' => 'Library management research',
        'authors' => 'Reyes, José; Cruz, Ana',
        'publication_year' => 2024,
        'doi' => '10.1234/library',
    ])->and($metadata['metadata']['license'])->toBe('CC BY 4.0')
        ->and($metadata['abstract'])->not->toContain('Navigation')
        ->and($metadata)->not->toHaveKey('full_text_url');
    Http::assertNothingSent();
});

test('Dublin Core metadata is supported and ordinary pages are not treated as papers', function () {
    $parser = app(LiteratureWebMetadataParser::class);

    expect($parser->parse('<meta name="DC.title" content="Local thesis"><meta name="DC.creator" content="Ana Cruz"><meta name="DCTERMS.issued" content="2022">')['publication_year'])->toBe(2022)
        ->and($parser->parse('<title>University login</title><meta property="og:title" content="University">'))->toBeNull();
});

test('metadata extraction refuses robots restrictions authentication and entities', function (string $extra) {
    expect(fn () => app(LiteratureWebMetadataParser::class)->parse($extra.harvestCitationHtml()))
        ->toThrow(LiteratureHarvestException::class);
})->with([
    '<meta name="robots" content="noindex,follow">',
    '<meta name="robots" content="noarchive">',
    '<input type="password">',
    '<!ENTITY external SYSTEM "file:///etc/passwd">',
]);

test('robots rules use matching groups longest paths allow ties and crawl delay', function () {
    $rules = new LiteratureRobotsRules;
    $body = "User-agent: *\nDisallow: /\nUser-agent: ATHENA-LiteratureHarvester\nDisallow: /handle/*\nAllow: /handle/1234/1$\nCrawl-delay: 7.5\nUser-agent: ATHENA-LiteratureHarvester\nAllow: /handle/1234/2\n";

    expect($rules->evaluate($body, 'https://repository.example.org/handle/1234/1'))->toBe(['allowed' => true, 'delay' => 8])
        ->and($rules->evaluate($body, 'https://repository.example.org/handle/1234/1?x=1')['allowed'])->toBeFalse()
        ->and($rules->evaluate($body, 'https://repository.example.org/handle/1234/2')['allowed'])->toBeTrue()
        ->and($rules->evaluate($body, 'https://repository.example.org/handle/1234/3')['allowed'])->toBeFalse()
        ->and($rules->evaluate("User-agent: *\nDisallow: /x\nAllow: /x", 'https://example.org/x')['allowed'])->toBeTrue();
});

test('only exact allowlisted HTTPS item URLs are fetched', function (string $url) {
    expect(fn () => publicHarvestFetcher()->get('test-repository', $url))->toThrow(LiteratureHarvestException::class);
    Http::assertNothingSent();
})->with([
    'http://repository.example.org/handle/1234/1',
    'https://repository.example.org.evil.example/handle/1234/1',
    'https://user@repository.example.org/handle/1234/1',
    'https://repository.example.org:8080/handle/1234/1',
    'https://127.0.0.1/handle/1234/1',
    'https://repository.example.org/handle/1234/%2e%2e/login',
    'https://repository.example.org/handle/1234/1?redirect=http://127.0.0.1',
    'https://repository.example.org/handle/1234/1#fragment',
    'https://repository.example.org/discover',
    'https://repository.example.org\@127.0.0.1/handle/1234/1',
]);

test('private mixed and unresolved DNS answers are rejected before HTTP', function (array $addresses) {
    expect(fn () => publicHarvestFetcher($addresses)->get('test-repository', 'https://repository.example.org/handle/1234/1'))
        ->toThrow(LiteratureHarvestException::class);
    Http::assertNothingSent();
})->with([[['127.0.0.1']], [['10.0.0.1']], [['::1']], [['169.254.169.254']], [['93.184.216.34', '192.168.1.1']], [[]]]);

test('robots disallow is enforced before downloading an item', function () {
    Http::fake(['repository.example.org/robots.txt' => Http::response("User-agent: *\nDisallow: /handle", 200)]);

    expect(fn () => publicHarvestFetcher()->get('test-repository', 'https://repository.example.org/handle/1234/1'))
        ->toThrow(LiteratureHarvestException::class, 'robots.txt');
    Http::assertSentCount(1);
});

test('fetches are DNS pinned identify the crawler cache robots and pace requests', function () {
    $optionsSeen = [];
    Http::fake(function (Request $request, array $options) use (&$optionsSeen) {
        $optionsSeen[] = $options;

        return Http::response(str_ends_with($request->url(), '/robots.txt') ? "User-agent: *\nAllow: /" : harvestCitationHtml(), 200, ['Content-Type' => 'text/html']);
    });
    $fetcher = publicHarvestFetcher();
    $fetcher->get('test-repository', 'https://repository.example.org/handle/1234/1');
    $fetcher->get('test-repository', 'https://repository.example.org/handle/1234/2');

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => str_contains($request->header('User-Agent')[0], 'ATHENA-LiteratureHarvester'));
    expect($optionsSeen[0]['curl'][CURLOPT_RESOLVE])->toBe(['repository.example.org:443:93.184.216.34'])
        ->and($optionsSeen[0]['allow_redirects'])->toBeFalse()
        ->and($optionsSeen[0]['decode_content'])->toBeFalse()
        ->and($optionsSeen[0]['proxy'])->toBe('');
    Sleep::assertSleptTimes(2);
});

test('redirects are not followed even if they point to a private address', function () {
    fakeHarvestPages(itemStatus: 302, itemHeaders: ['Location' => 'http://127.0.0.1/admin']);

    expect(fn () => publicHarvestFetcher()->get('test-repository', 'https://repository.example.org/handle/1234/1'))
        ->toThrow(LiteratureHarvestException::class, 'redirected');
    Http::assertSentCount(2);
});

test('unavailable or blocked robots files fail closed', function (int $status) {
    Http::fake(['repository.example.org/robots.txt' => Http::response('', $status)]);

    expect(fn () => publicHarvestFetcher()->get('test-repository', 'https://repository.example.org/handle/1234/1'))
        ->toThrow(LiteratureHarvestException::class);
    Http::assertSentCount(1);
})->with([403, 429, 500]);

test('download limits and response indexing restrictions are enforced', function (array $headers, string $body) {
    config(['literature.web_harvest.maximum_bytes' => 1000]);
    fakeHarvestPages($body, itemHeaders: $headers);

    expect(fn () => publicHarvestFetcher()->get('test-repository', 'https://repository.example.org/handle/1234/1'))
        ->toThrow(LiteratureHarvestException::class);
})->with([
    [[], str_repeat('x', 1001)],
    [['X-Robots-Tag' => 'noarchive'], '<html>restricted indexing</html>'],
    [['Content-Encoding' => 'gzip'], '<html>compressed response</html>'],
]);

test('Retry-After puts the entire repository into cooldown', function () {
    fakeHarvestPages(itemStatus: 429, itemHeaders: ['Retry-After' => '120']);
    $fetcher = publicHarvestFetcher();

    expect(fn () => $fetcher->get('test-repository', 'https://repository.example.org/handle/1234/1'))->toThrow(LiteratureHarvestException::class);
    expect(fn () => $fetcher->get('test-repository', 'https://repository.example.org/handle/1234/2'))->toThrow(LiteratureHarvestException::class, 'cooling down');
    Http::assertSentCount(2);
});

test('sitemap discovery is bounded deduplicated and excludes offsite and restricted paths', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages();
    $harvester = app(LiteratureWebHarvester::class);
    $records = $harvester->discover('test-repository', 1);

    expect($records)->toHaveCount(1)->and($records[0]->url)->toBe('https://repository.example.org/handle/1234/1')
        ->and($records[0]->status)->toBe('pending');
    $records[0]->update(['queued_at' => now()]);
    $next = $harvester->discover('test-repository', 10);
    expect($next)->toHaveCount(1)->and($next[0]->url)->toBe('https://repository.example.org/handle/1234/2');
    Http::assertSentCount(3);
});

test('malformed or entity-bearing sitemaps are rejected', function (string $xml) {
    publicHarvestFetcher();
    Http::fake([
        'repository.example.org/robots.txt' => Http::response('', 404),
        'repository.example.org/sitemap' => Http::response($xml, 200),
    ]);

    expect(fn () => app(LiteratureWebHarvester::class)->discover('test-repository', 1))->toThrow(LiteratureHarvestException::class);
})->with(['<html>Sign in</html>', '<!DOCTYPE urlset [<!ENTITY x SYSTEM "file:///etc/passwd">]><urlset/>']);

test('harvesting stores metadata provenance and status without a document copy', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages();
    $record = HarvestedLiteratureSource::factory()->create(['url' => 'https://repository.example.org/handle/1234/1', 'status' => 'pending']);
    app(LiteratureWebHarvester::class)->harvest($record);

    expect($record->fresh()->status)->toBe('ready')
        ->and($record->doi)->toBe('10.1234/library')
        ->and($record->fingerprint)->not->toBeNull()
        ->and($record->harvested_at)->not->toBeNull()
        ->and($record->metadata['method'])->toBe('html_metadata')
        ->and($record->getAttributes())->not->toHaveKey('full_text');
});

test('a newly restricted page is removed from the searchable harvest index', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages('<meta name="robots" content="noindex">'.harvestCitationHtml());
    $record = HarvestedLiteratureSource::factory()->create(['url' => 'https://repository.example.org/handle/1234/1']);
    app(LiteratureWebHarvester::class)->harvest($record);

    expect($record->fresh()->status)->toBe('skipped')
        ->and($record->abstract)->toBeNull()
        ->and(app(LiteratureWebSearchService::class)->search('library management'))->toBe([]);
});

test('transient failures are recorded and can be retried by the queue', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages(itemStatus: 503);
    $record = HarvestedLiteratureSource::factory()->create(['url' => 'https://repository.example.org/handle/1234/1']);

    expect(fn () => app(LiteratureWebHarvester::class)->harvest($record))->toThrow(LiteratureHarvestException::class)
        ->and($record->fresh()->status)->toBe('failed')
        ->and($record->failure_reason)->toContain('503');
});

test('web metadata joins RRL results and duplicate DOI matches preserve provenance', function () {
    prepareHarvestTestDatabase();
    HarvestedLiteratureSource::factory()->create(['doi' => '10.1234/library']);
    Http::fake([
        'api.semanticscholar.org/*' => Http::response(['data' => [[
            'paperId' => 'library-1', 'title' => 'Library management research',
            'abstract' => 'This research examines library management and information retrieval in academic institutions.',
            'year' => 2024, 'authors' => [['name' => 'Ana Cruz']],
            'externalIds' => ['DOI' => '10.1234/library'], 'citationCount' => 10,
        ]]]),
        'api.crossref.org/*' => Http::response(['message' => ['items' => []]]),
        'api.openalex.org/*' => Http::response(['results' => []]),
        'www.ebi.ac.uk/*' => Http::response(['resultList' => ['result' => []]]),
        'api.ies.ed.gov/*' => Http::response(['docs' => []]),
        'doaj.org/*' => Http::response(['results' => []]),
        'export.arxiv.org/*' => Http::response('<feed xmlns="http://www.w3.org/2005/Atom"/>'),
    ]);

    $payload = app(LiteratureSearchService::class)->search('library management');
    expect($payload['results'])->toHaveCount(1)
        ->and($payload['sources'])->toContain('Web repositories')
        ->and($payload['results'][0]['provenance'][0]['repository'])->toBe('test-repository')
        ->and($payload['results'][0]['full_text_token'])->toBeNull()
        ->and($payload['results'][0]['access_status'])->toBe('abstract_only');
});

test('harvest command queues bounded unique jobs and validates options', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages();
    Bus::fake();

    $this->artisan('literature:harvest-web', ['--source' => 'test-repository', '--limit' => 1])->assertSuccessful();
    Bus::assertDispatched(HarvestLiteraturePage::class, fn (HarvestLiteraturePage $job): bool => $job->queue === 'literature-harvest' && $job->uniqueId() === (string) $job->recordId);
    Bus::assertDispatchedTimes(HarvestLiteraturePage::class, 1);
    expect(HarvestedLiteratureSource::where('repository_key', 'test-repository')->first()->queued_at)->not->toBeNull();
    $this->artisan('literature:harvest-web', ['--source' => 'https://evil.example'])->assertExitCode(2);
    $this->artisan('literature:harvest-web', ['--limit' => 10000])->assertExitCode(2);
});

test('disabled harvesting performs no network or local search work', function () {
    config(['literature.web_harvest.enabled' => false]);

    expect(app(LiteratureWebSearchService::class)->search('library management'))->toBe([]);
    $this->artisan('literature:harvest-web')->assertFailed();
    Http::assertNothingSent();
});

test('an empty web index does not conceal an outage of all remote indexes', function () {
    $this->mock(LiteratureWebSearchService::class)->shouldReceive('search')->once()->andReturn([]);
    Http::fake(fn () => Http::response([], 503));
    $service = app(LiteratureSearchService::class);
    $payload = $service->search('library management');

    expect($service->allProvidersFailed())->toBeTrue()
        ->and($payload['results'])->toBe([])
        ->and($payload['sources'])->not->toContain('Web repositories');
});

test('harvested matches remain available during remote index outages', function () {
    prepareHarvestTestDatabase();
    HarvestedLiteratureSource::factory()->create();
    Http::fake(fn () => Http::response([], 503));
    $service = app(LiteratureSearchService::class);
    $payload = $service->search('library management');

    expect($service->allProvidersFailed())->toBeFalse()
        ->and($payload['results'])->toHaveCount(1)
        ->and($payload['results'][0]['source'])->toBe('Test University (web)')
        ->and($payload['results'][0]['provenance'][0]['retrieved_at'])->not->toBeNull();
});

test('single page harvesting stays allowlisted and reuses the same stored record', function () {
    prepareHarvestTestDatabase();
    publicHarvestFetcher();
    fakeHarvestPages();
    $options = ['--source' => 'test-repository', '--url' => 'https://repository.example.org/handle/1234/1', '--inline' => true];

    $this->artisan('literature:harvest-web', $options)->assertSuccessful();
    $this->artisan('literature:harvest-web', $options)->assertSuccessful();
    expect(HarvestedLiteratureSource::where('repository_key', 'test-repository')->count())->toBe(1)
        ->and(HarvestedLiteratureSource::where('repository_key', 'test-repository')->first()->status)->toBe('ready');
    Http::assertSentCount(3);
});

test('single page command refuses URLs outside the configured repository', function () {
    publicHarvestFetcher();
    $this->artisan('literature:harvest-web', ['--source' => 'test-repository', '--url' => 'https://evil.example/paper'])->assertFailed();
    $this->artisan('literature:harvest-web', ['--url' => 'https://repository.example.org/handle/1234/1'])->assertExitCode(2);
    Http::assertNothingSent();
});

test('the queued job processes its record and records exhausted failures', function () {
    prepareHarvestTestDatabase();
    $record = HarvestedLiteratureSource::factory()->create();
    $harvester = Mockery::mock(LiteratureWebHarvester::class);
    $harvester->shouldReceive('harvest')->once()->withArgs(fn (HarvestedLiteratureSource $argument): bool => $argument->is($record));
    $job = new HarvestLiteraturePage($record->id);
    $job->handle($harvester);
    $job->failed(new RuntimeException('Test connection failure'));
    $record->refresh();

    expect($record->status)->toBe('failed')
        ->and($record->failure_reason)->toBe('Test connection failure')
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'))
        ->and($job->backoff)->toBe([60, 300, 900]);
});
