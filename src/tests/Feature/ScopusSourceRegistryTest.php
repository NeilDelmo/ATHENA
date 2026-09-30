<?php

use App\Services\JournalRecommendationService;
use App\Services\ScopusSourceRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->directory = storage_path('framework/testing/scopus-'.uniqid());
    File::ensureDirectoryExists($this->directory);
    config(['services.scopus.source_list_path' => $this->directory.'/sources.json', 'services.scopus.key' => null]);
    Http::preventStrayRequests();
});

afterEach(function () {
    File::delete([$this->directory.'/sources.json', $this->directory.'/sources.json.tmp', $this->directory.'/sources.xlsx']);
    rmdir($this->directory);
});

function scopusTestWorkbook(string $path, string $edition, array $rows, bool $validHeaders = true): void
{
    $headers = $validHeaders ? ['Sourcerecord ID', 'Source Title', 'ISSN', 'EISSN', 'Active or Inactive', 'Coverage', 'Titles Discontinued by Scopus', 'Source Type'] : ['Unknown column'];
    $sheet = '<worksheet><sheetData>';
    foreach ([$headers, ...$rows] as $index => $row) {
        $number = $index + 1;
        $sheet .= '<row r="'.$number.'">';
        foreach ($row as $column => $value) {
            $sheet .= '<c r="'.chr(65 + $column).$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES).'</t></is></c>';
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Scopus Sources '.$edition.'" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();
}

function scopusRegistryWork(string $id, string $source = 'S101', string $issn = '1234-5678'): array
{
    return [
        'id' => 'https://openalex.org/'.$id, 'display_name' => 'Diabetes prevention in community health',
        'publication_year' => now()->year - 1, 'topics' => [['display_name' => 'Public health and diabetes']],
        'primary_location' => ['source' => [
            'id' => 'https://openalex.org/'.$source, 'display_name' => 'Health Journal '.$source,
            'type' => 'journal', 'issn_l' => $issn, 'homepage_url' => 'https://example.org/'.$source,
        ]],
    ];
}

test('official source registry matches print and electronic ISSNs and distinguishes active discontinued and inactive journals', function () {
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->format('M. Y'), [
        ['101', 'Active Health Journal', '11111111', '22222222', 'Active', '2020-2026', '', 'Journal'],
        ['102', 'Discontinued Journal', '33333333', '', 'Active', '2020-2024', 'Discontinued', 'Journal'],
        ['103', 'Closed Journal', '44444444', '', 'Inactive', '2000-2010', '', 'Journal'],
        ['104', 'Book Series', '55555555', '', 'Active', '2020-2026', '', 'Book Series'],
    ]);
    $registry = app(ScopusSourceRegistry::class);
    $metadata = $registry->import($this->directory.'/sources.xlsx', 'https://example.org/official-source-list.xlsx');
    expect($metadata['as_of'])->toBe(now()->startOfMonth()->toDateString())
        ->and($registry->find('1111-1111')['status'])->toBe('active')
        ->and($registry->find('2222-2222')['source_id'])->toBe('101')
        ->and($registry->find('3333-3333')['status'])->toBe('discontinued')
        ->and($registry->find('4444-4444')['status'])->toBe('inactive')
        ->and($registry->find('5555-5555'))->toBeNull();
});

test('Scopus preferred searches exclude discontinued and inactive sources while prioritizing dated coverage without API credentials', function () {
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->format('M. Y'), [
        ['101', 'Inactive', '11111111', '', 'Inactive', '2000-2010', '', 'Journal'],
        ['102', 'Active', '22222222', '', 'Active', '2020-2026', '', 'Journal'],
        ['103', 'Discontinued', '33333333', '', 'Active', '2020-2024', 'Discontinued', 'Journal'],
    ]);
    app(ScopusSourceRegistry::class)->import($this->directory.'/sources.xlsx', 'https://example.org/source-list.xlsx');
    Http::fake(['api.openalex.org/works*' => Http::response(['results' => [
        scopusRegistryWork('W101', 'S101', '1111-1111'), scopusRegistryWork('W102', 'S102', '2222-2222'), scopusRegistryWork('W103', 'S103', '3333-3333'),
    ]])]);
    $service = app(JournalRecommendationService::class);
    $result = $service->recommend('diabetes health');
    expect($result['results'])->toHaveCount(1)->and($result['results'][0]['id'])->toBe('S102')
        ->and($result['results'][0]['scopus']['status'])->toBe('active');
    expect($service->recommend('diabetes health', indexing: 'scopus_only')['results'])->toHaveCount(1);
    expect($service->recommend('diabetes health', indexing: 'any')['results'])->toHaveCount(3);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'api.elsevier.com'));
});

test('stale source list coverage is downgraded and malformed workbooks cannot replace a working registry', function () {
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->subMonths(6)->format('M. Y'), [
        ['101', 'Health Journal', '12345678', '', 'Active', '2020-2026', '', 'Journal'],
    ]);
    $registry = app(ScopusSourceRegistry::class);
    $registry->import($this->directory.'/sources.xlsx', 'https://example.org/source-list.xlsx');
    Http::fake(['api.openalex.org/works*' => Http::response(['results' => [scopusRegistryWork('W101')]])]);
    $result = app(JournalRecommendationService::class)->recommend('diabetes health', indexing: 'scopus_only');
    expect($result['results'])->toBeEmpty()->and(implode(' ', $result['warnings']))->toContain('needs refreshing');
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->format('M. Y'), [], false);
    expect(fn () => $registry->import($this->directory.'/sources.xlsx', 'https://example.org/bad.xlsx'))->toThrow(RuntimeException::class);
    expect($registry->find('1234-5678')['stale'])->toBeTrue();
});

test('the synchronization command imports an official workbook and rejects untrusted download hosts', function () {
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->format('M. Y'), [
        ['101', 'Health Journal', '12345678', '', 'Active', '2020-2026', '', 'Journal'],
    ]);
    $this->artisan('journals:sync-scopus', ['--file' => $this->directory.'/sources.xlsx'])->assertSuccessful();
    $this->artisan('journals:sync-scopus', ['--url' => 'http://localhost/private'])->assertFailed();
    Http::assertNothingSent();
});

test('the source synchronizer discovers the current official download and preserves existing coverage on a download failure', function () {
    scopusTestWorkbook($this->directory.'/sources.xlsx', now()->format('M. Y'), [
        ['101', 'Health Journal', '12345678', '', 'Active', '2020-2026', '', 'Journal'],
    ]);
    $registry = app(ScopusSourceRegistry::class);
    $registry->import($this->directory.'/sources.xlsx', 'https://www.elsevier.com/products/scopus/content');
    config(['services.scopus.source_list_url' => null]);
    Http::fake([
        'www.elsevier.com/products/scopus/content' => Http::response('<a href="//downloads.ctfassets.net/space/list/hash/ext_list_Sep_2026.xlsx">Download the Source title list</a>'),
        'downloads.ctfassets.net/*' => Http::response([], 503),
    ]);
    $this->artisan('journals:sync-scopus')->assertFailed();
    expect($registry->find('1234-5678')['status'])->toBe('active');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://downloads.ctfassets.net/space/list/hash/ext_list_Sep_2026.xlsx');
});
