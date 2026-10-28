<?php

use App\Services\RmsQuarterlyReportDocument;
use Tests\TestCase;

uses(TestCase::class);

function rmsXlsxParts(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'rms-xlsx-test-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;

    try {
        expect($archive->open($path, ZipArchive::CHECKCONS))->toBeTrue();
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

function rmsXlsxXPath(string $xml): DOMXPath
{
    $document = new DOMDocument;
    expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    return $xpath;
}

function rmsXlsxText(DOMXPath $xpath, string $cell): string
{
    return $xpath->evaluate('string(//s:c[@r="'.$cell.'"]/s:is/s:t)');
}

function rmsXlsxMetadata(): array
{
    return [
        'year' => 2026, 'quarter' => 2, 'campus' => 'Nasugbu',
        'signatories' => [
            'prepared' => ['name' => 'Research Head', 'position' => 'Head', 'office' => 'Research Office'],
            'reviewed' => ['name' => 'Reviewer', 'position' => 'Vice Chancellor', 'office' => 'Nasugbu'],
            'approved' => ['name' => 'Approver', 'position' => 'Chancellor', 'office' => 'Campus'],
        ],
    ];
}

test('RMS export maps all accomplishment fields into the three supplied template sheets', function () {
    $presentations = [
        'presentation_title' => 'Presentation & Title', 'presenter' => "Ada\nBen", 'college' => 'CICS',
        'project_title' => 'Project P', 'authors' => 'Authors P', 'source_of_fund' => 'Externally-funded',
        'project_status' => 'Completed', 'sdgs' => '4, 9', 'support_requested' => 'Yes',
        'event_title' => 'Forum', 'theme' => 'Research', 'date' => '2026-04-08', 'venue' => 'Manila',
        'classification' => 'National', 'organizer' => 'Organizer <org@example.com>',
        'website' => 'https://example.com/forum', 'evidence_url' => 'https://drive.google.com/folder/p',
    ];
    $publications = [
        'article_title' => 'Article', 'project_title' => 'Project J', 'authors' => 'Authors J', 'college' => 'CTE',
        'source_of_fund' => 'Institutionally-funded', 'project_status' => 'On-going', 'sdgs' => '4',
        'support_requested' => 'Incentive only', 'journal_title' => 'Journal', 'publication_type' => 'Journal',
        'issn_isbn' => '0012-3456', 'indexing' => 'Scopus', 'date' => '2026-05-09',
        'volume_issue' => 'Volume 1, Issue 2', 'pages' => '001–012', 'publisher' => 'Publisher',
        'evidence_url' => 'https://drive.google.com/folder/j',
    ];
    $ip = ['title' => 'Teaching Asset', 'type' => 'Copyright', 'inventors' => 'IP Authors', 'college' => 'CAS',
        'date' => '2026-06-10', 'remarks' => 'Submitted', 'evidence_url' => 'https://drive.google.com/folder/ip'];
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => [['key' => 'p', 'college' => 'CICS', 'values' => $presentations]],
        'publications' => [['key' => 'j', 'college' => 'CTE', 'values' => $publications]],
        'ip_assets' => [['key' => 'ip', 'college' => 'CAS', 'values' => $ip]],
    ], rmsXlsxMetadata()));

    foreach ([
        1 => [13, ['B' => 'Presentation & Title', 'C' => "Ada\nBen", 'D' => 'CICS', 'E' => 'Project P', 'F' => 'Authors P',
            'G' => 'Externally-funded', 'H' => 'Completed', 'I' => '4, 9', 'J' => 'Yes', 'K' => 'Forum', 'L' => 'Research',
            'N' => 'Manila', 'O' => 'National', 'P' => 'Organizer <org@example.com>', 'Q' => 'https://example.com/forum', 'R' => 'https://drive.google.com/folder/p']],
        2 => [13, ['B' => 'Article', 'C' => 'Project J', 'D' => 'Authors J', 'E' => 'CTE', 'F' => 'Institutionally-funded',
            'G' => 'On-going', 'H' => '4', 'I' => 'Incentive only', 'J' => 'Journal', 'K' => 'Journal', 'L' => '0012-3456',
            'M' => 'Scopus', 'O' => 'Volume 1, Issue 2', 'P' => '001–012', 'Q' => 'Publisher', 'R' => 'https://drive.google.com/folder/j']],
        3 => [12, ['B' => 'Teaching Asset', 'C' => 'Copyright', 'D' => 'IP Authors', 'E' => 'CAS', 'G' => 'Submitted', 'H' => 'https://drive.google.com/folder/ip']],
    ] as $sheet => [$row, $cells]) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        foreach ($cells as $column => $value) {
            expect(rmsXlsxText($xpath, $column.$row))->toBe($value);
        }
        expect($xpath->evaluate('string(//s:c[@r="A'.$row.'"]/s:v)'))->toBe('1');
    }

    $styles = rmsXlsxXPath($parts['xl/styles.xml']);
    $presentation = rmsXlsxXPath($parts['xl/worksheets/sheet1.xml']);
    expect((float) $presentation->evaluate('string(//s:row[@r="13"]/@ht)'))->toBeGreaterThanOrEqual(36);
    foreach ([1 => ['M13', '46120'], 2 => ['N13', '46151'], 3 => ['F12', '46183']] as $sheet => [$cell, $serial]) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        $style = (int) $xpath->evaluate('string(//s:c[@r="'.$cell.'"]/@s)') + 1;
        $format = $styles->evaluate('string(/s:styleSheet/s:cellXfs/s:xf['.$style.']/@numFmtId)');
        expect($xpath->evaluate('string(//s:c[@r="'.$cell.'"]/s:v)'))->toBe($serial)
            ->and($styles->evaluate('string(/s:styleSheet/s:numFmts/s:numFmt[@numFmtId="'.$format.'"]/@formatCode)'))->toBe('mm/dd/yyyy');
    }
});

test('RMS exports fill report metadata signatures and actual record totals without modifying the template', function () {
    $template = resource_path('documents/RMS-Quarterly-Monitoring-Report.xlsx');
    $before = file_get_contents($template);
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => [['values' => ['presentation_title' => 'Paper']]], 'publications' => [], 'ip_assets' => [],
    ], rmsXlsxMetadata()));
    $original = rmsXlsxParts($before);

    foreach ([1, 2, 3] as $sheet) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        expect(rmsXlsxText($xpath, 'A5'))->toBe('Fiscal Year: 2026 Quarter: 2')
            ->and(rmsXlsxText($xpath, 'A6'))->toBe('Constituent Campus: Nasugbu')
            ->and(rmsXlsxText($xpath, 'A30'))->toBe('Research Head')
            ->and(rmsXlsxText($xpath, 'A31'))->toBe('Head')
            ->and(rmsXlsxText($xpath, 'A32'))->toBe('Research Office')
            ->and(rmsXlsxText($xpath, 'D30'))->toBe('Reviewer')
            ->and(rmsXlsxText($xpath, 'D31'))->toBe('Vice Chancellor')
            ->and(rmsXlsxText($xpath, 'D32'))->toBe('Nasugbu')
            ->and(rmsXlsxText($xpath, 'F30'))->toBe('Approver')
            ->and(rmsXlsxText($xpath, 'F31'))->toBe('Chancellor')
            ->and(rmsXlsxText($xpath, 'F32'))->toBe('Campus')
            ->and($xpath->evaluate('string(//s:c[@r="C'.($sheet === 3 ? 22 : 23).'"]/s:v)'))->toBe($sheet === 1 ? '1' : '0');
    }

    foreach (['xl/media/image1.png', 'xl/sharedStrings.xml', 'xl/drawings/drawing1.xml'] as $part) {
        expect($parts[$part])->toBe($original[$part]);
    }
    expect(file_get_contents($template))->toBe($before);
});

test('RMS letterheads retain their rich multiline text without a whole cell hyperlink', function () {
    $template = rmsXlsxParts(file_get_contents(resource_path('documents/RMS-Quarterly-Monitoring-Report.xlsx')));
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([], rmsXlsxMetadata()));
    expect($parts['xl/sharedStrings.xml'])->toBe($template['xl/sharedStrings.xml']);
    $strings = rmsXlsxXPath($parts['xl/sharedStrings.xml']);

    foreach ([1, 2, 3] as $sheet) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        $index = (int) $xpath->evaluate('string(//s:c[@r="A1"]/s:v)') + 1;
        $header = $strings->query('/s:sst/s:si['.$index.']')->item(0);
        expect($header->textContent)->toContain("Republic of the Philippines\nBATANGAS STATE UNIVERSITY\nThe National Engineering University\n")
            ->and($xpath->query('/s:worksheet/s:hyperlinks/s:hyperlink[@ref="A1"]')->length)->toBe(0)
            ->and($xpath->query('/s:worksheet/s:hyperlinks[not(s:hyperlink)]')->length)->toBe(0)
            ->and($parts['xl/drawings/drawing'.$sheet.'.xml'])->toBe($template['xl/drawings/drawing'.$sheet.'.xml']);
    }
});

test('RMS exports grow beyond ten records and move the total notes signatures and dropdowns', function () {
    $rows = array_map(fn (int $number) => ['values' => ['presentation_title' => 'Paper '.$number, 'article_title' => 'Article '.$number, 'title' => 'Asset '.$number]], range(1, 12));
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => $rows, 'publications' => $rows, 'ip_assets' => $rows,
    ], rmsXlsxMetadata()));

    foreach ([1 => ['Paper 12', 24, 25], 2 => ['Article 12', 24, 25], 3 => ['Asset 12', 23, 24]] as $sheet => [$title, $lastRow, $totalRow]) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        expect(rmsXlsxText($xpath, 'B'.$lastRow))->toBe($title)
            ->and($xpath->evaluate('string(//s:c[@r="A'.$lastRow.'"]/s:v)'))->toBe('12')
            ->and($xpath->evaluate('string(//s:c[@r="C'.$totalRow.'"]/s:v)'))->toBe('12')
            ->and(rmsXlsxText($xpath, 'A32'))->toBe('Research Head')
            ->and(rmsXlsxText($xpath, 'A34'))->toBe('Research Office')
            ->and((int) $xpath->evaluate('count(//s:mergeCell[@ref="F32:H32"])'))->toBe(1)
            ->and((int) $xpath->evaluate('count(//s:mergeCell[@ref="A'.$totalRow.':B'.$totalRow.'"])'))->toBe(1)
            ->and((int) $xpath->evaluate('count(//s:row[@r="'.$totalRow.'"])'))->toBe(1);
    }

    expect($parts['xl/worksheets/sheet1.xml'])->toContain('sqref="G13:G24"', 'sqref="O13:O24"', 'ref="A26:E26"')
        ->and($parts['xl/worksheets/sheet2.xml'])->toContain('sqref="F13:F24"', 'sqref="M13:M24"', 'ref="A26:F26"')
        ->and($parts['xl/worksheets/sheet3.xml'])->toContain('sqref="C12:C23"', 'ref="A25:E25"');
    $workbook = rmsXlsxXPath($parts['xl/workbook.xml']);
    expect($workbook->evaluate('string(//s:definedName[@name="_xlnm.Print_Area"][@localSheetId="0"])'))->toBe("'RMS-Presentation'!\$A\$1:\$R\$35")
        ->and($workbook->evaluate('string(//s:definedName[@name="_xlnm.Print_Area"][@localSheetId="2"])'))->toBe("'RMS-IP Assets'!\$A\$1:\$H\$35");
});

test('RMS exports leave empty form rows blank and write zero totals', function () {
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => [], 'publications' => [], 'ip_assets' => [],
    ], rmsXlsxMetadata()));

    foreach ([1, 2, 3] as $sheet) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        $first = $sheet === 3 ? 12 : 13;
        expect((int) $xpath->evaluate('count(//s:row[@r >= '.$first.' and @r < '.($first + 10).'])'))->toBe(10)
            ->and((int) $xpath->evaluate('count(//s:c[@r="A'.$first.'"]/s:v)'))->toBe(0)
            ->and($xpath->evaluate('string(//s:c[@r="C'.($first + 10).'"]/s:v)'))->toBe('0');
    }
});

test('RMS exports preserve period text and formula-like input as literal strings', function () {
    $formula = '=HYPERLINK("https://example.com", "Open")';
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => [['values' => ['presentation_title' => $formula, 'date' => 'April 8–10, 2026', 'evidence_url' => '=1+1']]],
        'publications' => [], 'ip_assets' => [],
    ], rmsXlsxMetadata()));
    $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet1.xml']);

    expect(rmsXlsxText($xpath, 'B13'))->toBe($formula)
        ->and(rmsXlsxText($xpath, 'M13'))->toBe('April 8–10, 2026')
        ->and(rmsXlsxText($xpath, 'R13'))->toBe('=1+1')
        ->and($xpath->evaluate('string(//s:c[@r="B13"]/@t)'))->toBe('inlineStr')
        ->and((int) $xpath->evaluate('count(//s:f)'))->toBe(0);
});

test('RMS signature blocks fit long names and multiline positions without overlapping the next row', function () {
    $metadata = rmsXlsxMetadata();
    foreach (['prepared', 'reviewed', 'approved'] as $role) {
        $metadata['signatories'][$role] = [
            'name' => str_repeat('Research Professor ', 13).'Ph.D.  ',
            'position' => "Vice Chancellor for Research\nInnovation and Community Development\nUniversity Research Office",
            'office' => str_repeat('Research and Innovation Office ', 8).'Campus ',
        ];
    }
    $parts = rmsXlsxParts(app(RmsQuarterlyReportDocument::class)->generate([
        'presentations' => [], 'publications' => [], 'ip_assets' => [],
    ], $metadata));

    foreach ([1, 2, 3] as $sheet) {
        $xpath = rmsXlsxXPath($parts['xl/worksheets/sheet'.$sheet.'.xml']);
        foreach (['name' => 30, 'position' => 31, 'office' => 32] as $field => $row) {
            foreach (['prepared' => ['A', 'C'], 'reviewed' => ['D', 'E'], 'approved' => ['F', 'H']] as $role => [$first, $last]) {
                expect(rmsXlsxText($xpath, $first.$row))->toBe($metadata['signatories'][$role][$field])
                    ->and((int) $xpath->evaluate('count(//s:mergeCell[@ref="'.$first.$row.':'.$last.$row.'"])'))->toBe(1);
            }
            expect((float) $xpath->evaluate('string(//s:row[@r="'.$row.'"]/@ht)'))->toBeGreaterThanOrEqual($field === 'position' ? 54 : 72)
                ->and((int) $xpath->evaluate('count(//s:mergeCell[@ref="A'.$row.':B'.$row.'"])'))->toBe(0);
        }
    }
});
