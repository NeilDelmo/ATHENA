<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use App\Services\CollegeQuarterlyReportDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

function quarterlyDocumentParts(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'quarterly-docx-test-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;

    try {
        expect($archive->open($path))->toBeTrue();
        $parts = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            $parts[$name] = $archive->getFromName($name);
        }

        return $parts;
    } finally {
        $archive->close();
        unlink($path);
    }
}

function quarterlyDocumentXPath(string $xml): DOMXPath
{
    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    return $xpath;
}

function quarterlyDocumentPeriod(): array
{
    return ['quarter' => 2, 'year' => 2026, 'start' => Carbon::parse('2026-04-01'), 'end' => Carbon::parse('2026-06-30')];
}

test('the reusable quarterly template has empty report fields and no sample media', function () {
    $parts = quarterlyDocumentParts(file_get_contents(resource_path('documents/College-Quarterly-Accomplishment-Report.docx')));
    $xpath = quarterlyDocumentXPath($parts['word/document.xml']);
    $allXml = implode('', array_filter($parts, fn ($value, $name) => str_ends_with($name, '.xml'), ARRAY_FILTER_USE_BOTH));
    $media = array_keys(array_filter($parts, fn ($value, $name) => str_starts_with($name, 'word/media/'), ARRAY_FILTER_USE_BOTH));

    expect($allXml)->toContain('ACCOMPLISHMENT REPORT IN RESEARCH', 'Participation in Research Activities', 'Research of Faculty Members', 'Other Accomplishments')
        ->not->toContain('DJOANNA', 'LORISSA', 'BoatBox', 'CPEEE', 'cics.nasugbu', 'January to March 2026', 'College of Informatics and Computing Sciences')
        ->and($media)->toEqualCanonicalizing(['word/media/image17.png', 'word/media/image200.png'])
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl)'))->toBe(4)
        ->and((int) $xpath->evaluate('count(/w:document/w:body//w:drawing)'))->toBe(0)
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl/w:tr/w:trPr/w:tblHeader)'))->toBe(4);

    foreach (range(1, 4) as $table) {
        expect((int) $xpath->evaluate("count(/w:document/w:body/w:tbl[{$table}]/w:tr)"))->toBe(2)
            ->and(trim($xpath->evaluate("string(/w:document/w:body/w:tbl[{$table}]/w:tr[2])")))->toBe('');
    }
});

test('quarterly documents populate existing records and the selected college', function () {
    $coordinator = User::factory()->make(['name' => 'Ada Coordinator', 'college' => User::COLLEGES['CTE']]);
    $parts = quarterlyDocumentParts(app(CollegeQuarterlyReportDocument::class)->generate($coordinator, quarterlyDocumentPeriod(), [
        'presentations' => [['Research Forum', 'Learning & Teaching', 'April 8, 2026', 'Nasugbu', "Ada\nBen"]],
        'publications' => [['Education Journal', 'School Research', 'May 4, 2026', 'Ada']],
        'research' => [['Faculty Project', '12 months', 'Ada (Project Leader)', 'On-going']],
    ]));
    $xpath = quarterlyDocumentXPath($parts['word/document.xml']);

    expect($parts['word/document.xml'])->toContain('April to June 2026 (2nd Quarter)', 'Research Forum', 'Learning &amp; Teaching', 'Education Journal', 'Faculty Project', 'Ada Coordinator')
        ->not->toContain('January to March', 'DJOANNA', 'CICS')
        ->and($parts['word/header2.xml'])->toContain(User::COLLEGES['CTE'])
        ->not->toContain('cics.nasugbu', $coordinator->email)
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl[1]/w:tr[2]/w:tc[5]//w:br)'))->toBe(1);
});

test('quarterly print previews retain the template labels and empty table cells', function () {
    $coordinator = User::factory()->make(['name' => 'Ada Coordinator', 'college' => User::COLLEGES['CTE']]);
    $preview = $this->view('research_coordinator.report-print', [
        'coordinator' => $coordinator, 'period' => quarterlyDocumentPeriod(), 'year' => 2026, 'quarter' => 2,
        'report' => null, 'reportData' => ['presentations' => [], 'publications' => [], 'research' => []],
    ]);

    $preview->assertSee('April to June 2026 (2nd Quarter)')
        ->assertSee('Tel Nos.: +63 43 416 0350 local 207')
        ->assertSee('Website Address: http://www.batstate-u.edu.ph')
        ->assertSee('IV. Supporting Documentation')
        ->assertSee('Research Coordinator, '.User::COLLEGES['CTE'])
        ->assertDontSee('None reported.');

    $document = new DOMDocument;
    @$document->loadHTML((string) $preview);
    $xpath = new DOMXPath($document);

    foreach ([1 => 5, 2 => 4, 3 => 4, 4 => 4] as $table => $columns) {
        expect($xpath->query('(//main//table)['.$table.']/tbody/tr/td')->length)->toBe($columns);
    }

    expect($xpath->query('//p[normalize-space(.)="Date Signed:"]')->length)->toBe(2);
});

test('saved quarterly documents preserve signatories and safely embed manual text and evidence', function () {
    Storage::fake('local');
    $image = UploadedFile::fake()->image('workshop.png', 800, 600);
    Storage::disk('local')->put('quarterly-reports/evidence.png', file_get_contents($image->getPathname()));
    $coordinator = User::factory()->make(['name' => 'Current Coordinator', 'college' => User::COLLEGES['CICS']]);
    $report = new CollegeQuarterlyReport;
    $report->forceFill([
        'college' => User::COLLEGES['CAS'],
        'snapshot' => ['prepared_by_name' => 'Original Coordinator'],
        'seminars' => [['event' => 'Writing <Workshop>', 'date' => 'May 12, 2026', 'place' => 'Room A & B', 'personnel' => "Ann\nBen"]],
        'other_accomplishments' => "First & second\n<Additional achievement>",
        'reviewed_by' => 'Dean & Reviewer',
        'reviewed_by_title' => 'Dean, CAS',
        'evidence' => [['path' => 'quarterly-reports/evidence.png', 'caption' => 'Workshop & participants']],
    ]);
    $parts = quarterlyDocumentParts(app(CollegeQuarterlyReportDocument::class)->generate($coordinator, quarterlyDocumentPeriod(), ['presentations' => [], 'publications' => [], 'research' => []], $report));
    $xpath = quarterlyDocumentXPath($parts['word/document.xml']);

    expect($parts['word/document.xml'])->toContain('Original Coordinator', 'Writing &lt;Workshop&gt;', 'Room A &amp; B', 'First &amp; second', '&lt;Additional achievement&gt;', 'Dean &amp; Reviewer', 'Workshop &amp; participants')
        ->not->toContain('Current Coordinator')
        ->and($parts['word/header2.xml'])->toContain(User::COLLEGES['CAS'])
        ->and($parts)->toHaveKey('word/media/quarterly-evidence-1.png')
        ->and($parts['word/_rels/document.xml.rels'])->toContain('media/quarterly-evidence-1.png')
        ->and((int) $xpath->evaluate('count(/w:document/w:body//w:drawing)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:p[w:r/w:t="Date Signed:"])'))->toBe(2);
});

test('quarterly document export reports missing evidence instead of silently dropping it', function () {
    Storage::fake('local');
    $report = new CollegeQuarterlyReport;
    $report->forceFill(['evidence' => [['path' => 'missing.png']], 'snapshot' => ['prepared_by_name' => 'Coordinator']]);

    expect(fn () => app(CollegeQuarterlyReportDocument::class)->generate(User::factory()->make(), quarterlyDocumentPeriod(), ['presentations' => [], 'publications' => [], 'research' => []], $report))
        ->toThrow(RuntimeException::class, 'A supporting documentation image is unavailable.');
});

test('quarterly evidence captions identify the manual activity row they prove', function () {
    Storage::fake('local');
    $image = UploadedFile::fake()->image('conference.png');
    Storage::disk('local')->put('quarterly-reports/conference.png', file_get_contents($image->getPathname()));
    $report = new CollegeQuarterlyReport;
    $report->forceFill([
        'presentations' => [[
            'id' => 'acfa7f25-48e4-45a4-8fcb-78d105e40a16', 'event' => 'STEM Conference',
            'research_title' => 'iCARE', 'date' => '2026-05-12', 'place' => 'Online', 'personnel' => 'Faculty Member',
        ]],
        'evidence' => [[
            'path' => 'quarterly-reports/conference.png', 'caption' => 'Presentation certificate',
            'section' => 'presentations', 'row_id' => 'acfa7f25-48e4-45a4-8fcb-78d105e40a16', 'folder' => 'iCARE',
        ]],
    ]);
    $parts = quarterlyDocumentParts(app(CollegeQuarterlyReportDocument::class)->generate(User::factory()->make(), quarterlyDocumentPeriod(), [
        'presentations' => [['STEM Conference', 'iCARE', 'May 12, 2026', 'Online', 'Faculty Member']],
        'publications' => [], 'research' => [],
    ], $report));
    $xpath = quarterlyDocumentXPath($parts['word/document.xml']);
    $caption = $xpath->evaluate('string(//w:p[w:r/w:drawing]/following-sibling::w:p[1])');

    expect($caption)->toContain('a. Presentation', 'Row 1', 'iCARE', 'Presentation certificate');
});

test('quarterly documents preserve safe narrative formatting and list text without inherited numbering', function () {
    $report = new CollegeQuarterlyReport;
    $report->forceFill([
        'other_accomplishments' => '<p onclick="alert(1)"><strong>Bold &amp; clear</strong> <em>Italic</em> <u>Underlined</u><br>Next &lt;line&gt;</p><ol><li>First</li><li><strong>Second</strong></li></ol><ul><li>Bullet</li></ul><p>Restart</p><ol><li>Again</li></ol><script>alert(1)</script><img src="x" onerror="alert(2)">',
    ]);
    $parts = quarterlyDocumentParts(app(CollegeQuarterlyReportDocument::class)->generate(User::factory()->make(), quarterlyDocumentPeriod(), ['presentations' => [], 'publications' => [], 'research' => []], $report));
    $xpath = quarterlyDocumentXPath($parts['word/document.xml']);

    expect($parts['word/document.xml'])->toContain('Bold &amp; clear', 'Next &lt;line&gt;', '1. ', '2. ', '• ')
        ->not->toContain('&lt;strong&gt;', 'onclick', 'onerror', 'alert(1)', 'alert(2)')
        ->and((int) $xpath->evaluate('count(//w:r[w:t="Bold & clear"]/w:rPr/w:b)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="Italic"]/w:rPr/w:i)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="Underlined"]/w:rPr/w:u[@w:val="single"])'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:p[w:r/w:t="Next <line>"]/w:r/w:br)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:p[w:r/w:t="First"]/w:pPr/w:numPr)'))->toBe(0)
        ->and((int) $xpath->evaluate('count(//w:p[w:r/w:t="Again"]/w:r[w:t="1. "])'))->toBe(1);

    if (getenv('ATHENA_EXPORT_QUARTERLY_REPORT') === '1') {
        File::ensureDirectoryExists(storage_path('framework/testing'));
        File::put(storage_path('framework/testing/quarterly-report-rich.docx'), app(CollegeQuarterlyReportDocument::class)->generate(User::factory()->make(), quarterlyDocumentPeriod(), ['presentations' => [], 'publications' => [], 'research' => []], $report));
    }
});
