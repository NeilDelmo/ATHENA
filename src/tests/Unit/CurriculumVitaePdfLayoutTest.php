<?php

use App\Services\CurriculumVitaeDocumentService;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Support\CurriculumVitaeData;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

test('each CV name occupies a centered cell matching its label column', function () {
    $people = [
        ['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => 'Reyes'],
        ['last_name' => 'Cruz', 'first_name' => 'Rafael'],
        ['last_name' => 'Dela Cruz Villanueva', 'first_name' => 'Maria Angelica Isabel', 'middle_name' => 'Reyes'],
    ];
    $contents = app(CurriculumVitaeDocumentService::class)->generate(CurriculumVitaeData::fromValidated(['people' => $people]));
    $path = tempnam(sys_get_temp_dir(), 'athena-cv-columns-');
    expect($path)->not->toBeFalse();

    try {
        file_put_contents($path, $contents);
        $archive = new ZipArchive;
        expect($archive->open($path))->toBeTrue();
        $xml = $archive->getFromName('word/document.xml');
        $archive->close();
        $document = new DOMDocument;
        expect($document->loadXML($xml, LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($people as $personIndex => $person) {
            $table = $xpath->query('//w:body/w:tbl')->item($personIndex * 2);
            $labels = $xpath->query('./w:tr[3]/w:tc', $table);
            $values = $xpath->query('./w:tr[4]/w:tc', $table);
            expect($labels)->toHaveCount(3)
                ->and($values)->toHaveCount(3);

            foreach (['last_name', 'first_name', 'middle_name'] as $column => $key) {
                $label = $labels->item($column);
                $value = $values->item($column);
                expect(trim($value->textContent))->toBe($person[$key] ?? '')
                    ->and($xpath->evaluate('string(./w:p/w:pPr/w:jc/@w:val)', $value))->toBe('center');

                foreach (['w:tcW/@w:w', 'w:tcW/@w:type', 'w:gridSpan/@w:val'] as $property) {
                    expect($xpath->evaluate('string(./w:tcPr/'.$property.')', $value))
                        ->toBe($xpath->evaluate('string(./w:tcPr/'.$property.')', $label));
                }
            }
        }
    } finally {
        unlink($path);
    }
});

test('the converted CV centers each name below its label without shifting the official pages', function (array $person, string $fixture) {
    if (getenv('ATHENA_CV_PDF_LAYOUT') !== '1') {
        $this->markTestSkipped('Set ATHENA_CV_PDF_LAYOUT=1 to verify real LibreOffice PDF coordinates.');
    }

    $docx = app(CurriculumVitaeDocumentService::class)->generate(CurriculumVitaeData::fromValidated(['people' => [$person]]));

    try {
        $pdf = app(LibreOfficeDocumentPdfConverter::class)->convertDocx($docx);
    } catch (RuntimeException $exception) {
        throw new RuntimeException($exception->getPrevious()?->getMessage() ?? $exception->getMessage(), previous: $exception);
    }

    $path = tempnam(sys_get_temp_dir(), 'athena-cv-layout-');
    expect($path)->not->toBeFalse();

    try {
        file_put_contents($path, $pdf);

        if ($qaPath = getenv('ATHENA_CV_PDF_QA_PATH')) {
            file_put_contents($qaPath.'-'.$fixture.'.docx', $docx);
            file_put_contents($qaPath.'-'.$fixture.'.pdf', $pdf);
        }

        $result = Process::timeout(60)->run([
            (string) config('document_pdf.node_binary', 'node'), '--input-type=module', '--eval', <<<'JS'
                import { readFile } from 'node:fs/promises';
                import { getDocument, Util } from 'pdfjs-dist/legacy/build/pdf.mjs';
                const task = getDocument({ data: new Uint8Array(await readFile(process.argv[1])), verbosity: 0, useSystemFonts: true });
                const pdf = await task.promise, pages = [];
                try {
                    for (let index = 1; index <= pdf.numPages; index++) {
                        const page = await pdf.getPage(index), viewport = page.getViewport({ scale: 1 });
                        const content = await page.getTextContent(), lines = [];
                        for (const item of content.items) {
                            if (!item.str?.trim() || item.width <= 0) continue;
                            const transform = Util.transform(viewport.transform, item.transform), baseline = transform[5];
                            let line = lines.find((line) => Math.abs(line.baseline - baseline) < 1);
                            if (!line) { line = { baseline, words: [] }; lines.push(line); }
                            line.words.push({ text: item.str.trim(), x: transform[4], right: transform[4] + item.width });
                        }
                        for (const line of lines) line.words.sort((a, b) => a.x - b.x);
                        pages.push(lines.sort((a, b) => a.baseline - b.baseline));
                        page.cleanup();
                    }
                    process.stdout.write(JSON.stringify(pages));
                } finally { await task.destroy(); }
                JS,
            $path,
        ]);
        expect($result->successful())->toBeTrue($result->errorOutput());
        $pages = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
        expect($pages)->toHaveCount(2);

        $bounds = function (string $text) use ($pages): array {
            foreach ($pages[0] as $line) {
                $words = $line['words'];

                foreach (array_keys($words) as $start) {
                    $candidate = '';

                    for ($end = $start; $end < count($words); $end++) {
                        $candidate = trim($candidate.' '.$words[$end]['text']);

                        if ($candidate === $text) {
                            return [
                                'center' => ($words[$start]['x'] + $words[$end]['right']) / 2,
                                'baseline' => $line['baseline'],
                            ];
                        }
                    }
                }
            }

            throw new RuntimeException('The first CV page is missing: '.$text);
        };

        $baselines = [];

        foreach (['last_name' => 'Last Name', 'first_name' => 'First Name', 'middle_name' => 'Middle Name'] as $key => $label) {
            $labelBounds = $bounds($label);

            if (($person[$key] ?? '') === '') {
                continue;
            }

            $nameBounds = $bounds($person[$key]);
            expect(abs($nameBounds['center'] - $labelBounds['center']))->toBeLessThanOrEqual(2.0, $label.' must center below its own label.');
            expect($nameBounds['baseline'])->toBeGreaterThan($labelBounds['baseline']);
            $baselines[] = $nameBounds['baseline'];
        }

        expect(max($baselines) - min($baselines))->toBeLessThanOrEqual(0.5);
    } finally {
        unlink($path);
    }
})->with([
    'all name fields' => [['last_name' => 'Santos', 'first_name' => 'Maria', 'middle_name' => 'Reyes'], 'full-name'],
    'omitted middle name' => [['last_name' => 'Cruz', 'first_name' => 'Rafael'], 'without-middle'],
    'long multiword names' => [['last_name' => 'Dela Cruz Villanueva', 'first_name' => 'Maria Angelica Isabel', 'middle_name' => 'Reyes'], 'long-name'],
])->group('pdf-layout');
