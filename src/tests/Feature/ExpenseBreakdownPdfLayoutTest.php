<?php

use App\Services\ExpenseBreakdownDocumentService;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Support\ExpenseBreakdownData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('real expense PDFs preserve wrapped text and the live preview table layout', function () {
    if (getenv('ATHENA_PDF_LAYOUT') !== '1') {
        $this->markTestSkipped('Set ATHENA_PDF_LAYOUT=1 to verify the rendered expense PDF with LibreOffice.');
    }

    $title = 'TITLESTART '.str_repeat('Community research ', 10).'TITLEEND';
    $details = 'DETAILSTART '.str_repeat('Wide evidence ', 20).str_repeat('W', 72).' DETAILEND';
    $purpose = 'PURPOSESTART '.str_repeat('Preserve every project outcome ', 14).'PURPOSEEND';
    expect(mb_strlen($title))->toBeLessThanOrEqual(255)
        ->and(mb_strlen($details))->toBeLessThanOrEqual(500)
        ->and(mb_strlen($purpose))->toBeLessThanOrEqual(500);

    $item = [
        'category' => 'mooe',
        'account' => 'Supplies and Materials Expenses',
        'sub_account' => 'Office Supplies Expenses',
        'particulars' => 'LONGROW',
        'details' => $details,
        'purpose' => $purpose,
        'unit' => 'set',
        'quantity' => 2,
        'unit_cost' => 125,
    ];
    $data = ExpenseBreakdownData::fromValidated([
        'project_title' => $title,
        'items' => [
            $item,
            [...$item, 'particulars' => 'NEXTROW', 'details' => 'Short description', 'purpose' => 'Short purpose', 'quantity' => 1, 'unit_cost' => 50],
            [...$item, 'account' => 'Contingency', 'sub_account' => 'none', 'purpose' => 'For unexpected/unforeseen expenses', 'quantity' => 1, 'unit_cost' => 25],
        ],
    ]);
    $directory = storage_path('framework/testing/expense-breakdown-layout');
    File::ensureDirectoryExists($directory);
    $pdfPath = $directory.'/wrapped-expenses.pdf';
    $pdf = app(LibreOfficeDocumentPdfConverter::class)->convertXlsx(
        app(ExpenseBreakdownDocumentService::class)->generate($data),
    );
    File::put($pdfPath, $pdf);

    $result = Process::timeout(120)->run([
        (string) config('document_pdf.node_binary'),
        resource_path('js/pdf-section-coordinates.mjs'),
        $pdfPath,
    ]);
    $this->assertTrue($result->successful(), $result->errorOutput());
    File::put($directory.'/wrapped-expenses.xml', $result->output());
    $document = new DOMDocument;
    $this->assertTrue($document->loadXML($result->output()));
    $xpath = new DOMXPath($document);
    $words = array_map(fn (DOMNode $word): string => $word->textContent, iterator_to_array($xpath->query('//word')));
    $text = implode(' ', $words);
    $compact = preg_replace('/\s+/u', '', $text);

    expect($text)->not->toContain('*please remove the blank rows')
        ->and($compact)->toContain('EstimatedBreakdownandDetailsofExpenses', 'ProjectTitle:', 'TITLEEND',
            'TotalforOfficeSuppliesExpenses', 'TotalforContingency', 'TOTALMOOE:', 'TOTALCAPITALOUTLAY:',
            'TOTALMOOEandCAPITALOUTLAY:', '325.00', 'DETAILEND', 'PURPOSEEND')
        ->and(substr_count($text, 'W'))->toBeGreaterThanOrEqual(72);

    $lines = [];
    foreach ($xpath->query('//page') as $pageNumber => $page) {
        expect((float) $page->getAttribute('width'))->toBeBetween(611, 613)
            ->and((float) $page->getAttribute('height'))->toBeBetween(935, 937);
        foreach ($xpath->query('./line', $page) as $line) {
            $lineText = implode(' ', array_map(fn (DOMNode $word): string => $word->textContent,
                iterator_to_array($xpath->query('./word', $line))));
            $lines[] = ['page' => $pageNumber, 'text' => $lineText,
                'top' => (float) $line->getAttribute('yMin'), 'bottom' => (float) $line->getAttribute('yMax')];
            expect((float) $line->getAttribute('xMin'))->toBeGreaterThanOrEqual(16)
                ->and((float) $line->getAttribute('xMax'))->toBeLessThanOrEqual(596)
                ->and((float) $line->getAttribute('yMin'))->toBeGreaterThanOrEqual(52)
                ->and((float) $line->getAttribute('yMax'))->toBeLessThanOrEqual(884);
        }
    }

    $locate = function (string $marker) use ($lines): array {
        $matches = array_values(array_filter($lines, fn (array $line): bool => str_contains($line['text'], $marker)));
        $this->assertCount(1, $matches, 'The PDF must contain one visible '.$marker.' marker.');

        return $matches[0];
    };
    $titleStart = $locate('TITLESTART');
    $titleEnd = $locate('TITLEEND');
    $headers = $locate('Particular/s');
    $longRow = $locate('LONGROW');
    $nextRow = $locate('NEXTROW');

    $headerText = implode(' ', array_column(array_filter($lines, fn (array $line): bool => $line['page'] === $headers['page']
        && $line['top'] >= $headers['top'] - 30 && $line['bottom'] <= $headers['bottom'] + 30), 'text'));
    foreach (['Account', 'Sub-account', 'Particular/s', 'Descriptions/', 'Specifications/', 'Details', 'Purpose', 'project', 'Unit', 'Qty.', 'Cost', 'Total', '(Php)'] as $header) {
        expect($headerText)->toContain($header);
    }
    expect(preg_match_all('/\bUnit\b/', $headerText))->toBe(3)
        ->and(preg_match_all('/\bCost\b/', $headerText))->toBe(2);

    expect($titleStart['page'])->toBe($titleEnd['page'])
        ->and($titleEnd['bottom'] - $titleStart['top'])->toBeGreaterThan(18)
        ->and($titleEnd['bottom'])->toBeLessThan($headers['top'])
        ->and($nextRow['page'])->toBe($longRow['page'])
        ->and($nextRow['top'] - $longRow['top'])->toBeGreaterThan(48);
    foreach (['DETAIL', 'PURPOSE'] as $field) {
        $start = $locate($field.'START');
        $end = $locate($field.'END');
        expect($start['page'])->toBe($end['page'])
            ->and($end['bottom'] - $start['top'])->toBeGreaterThan(48)
            ->and($end['bottom'])->toBeLessThan($nextRow['top']);
    }

    $this->withoutVite();
    $html = str_replace('</head>', '<style>'.File::get(resource_path('css/expense-breakdown-print.css')).'</style></head>',
        view('faculty.expense-breakdowns.preview', ['expenseBreakdown' => $data])->render());
    $htmlPath = $directory.'/live-preview.html';
    File::put($htmlPath, $html);
    $comparison = Process::timeout(120)->path(base_path())->run([
        (string) config('document_pdf.node_binary'), '--input-type=module', '-e', <<<'JS'
import { readFile } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { chromium } from '@playwright/test';
import { getDocument, OPS, Util } from 'pdfjs-dist/legacy/build/pdf.mjs';
const edge = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const browser = await chromium.launch({ headless: true,
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || (existsSync(edge) ? edge : undefined) });
const task = getDocument({ data: new Uint8Array(await readFile(process.argv[2])), verbosity: 0 });
try {
    const page = await browser.newPage({ viewport: { width: 1100, height: 1400 } });
    await page.setContent(await readFile(process.argv[1], 'utf8'));
    const html = await page.evaluate(() => {
        const table = document.querySelector('table'), width = table.getBoundingClientRect().width;
        const font = (selector) => Number.parseFloat(getComputedStyle(document.querySelector(selector)).fontSize);
        return { width, columns: [...table.tHead.rows[0].cells].map((cell) => cell.getBoundingClientRect().width / width),
            titleFont: font('h1'), projectFont: font('.expense-breakdown-project-title'), bodyFont: font('td') };
    });
    await page.screenshot({ path: process.argv[3], fullPage: true });
    const pdf = await task.promise, pdfPage = await pdf.getPage(1), operators = await pdfPage.getOperatorList();
    const viewport = pdfPage.getViewport({ scale: 1 }), positions = [], stack = [];
    let matrix = [1, 0, 0, 1, 0, 0];
    for (let index = 0; index < operators.fnArray.length; index++) {
        const operation = operators.fnArray[index], args = operators.argsArray[index];
        if (operation === OPS.save) stack.push([...matrix]);
        else if (operation === OPS.restore) matrix = stack.pop() || [1, 0, 0, 1, 0, 0];
        else if (operation === OPS.transform) matrix = Util.transform(matrix, args);
        else if (operation === OPS.constructPath && args[2]?.length === 4) {
            const [left, top, right, bottom] = args[2];
            if (Math.abs(right - left) < 0.2 && bottom - top > 20) {
                const transform = Util.transform(viewport.transform, matrix);
                const x = transform[0] * left + transform[2] * top + transform[4];
                if (!positions.some((value) => Math.abs(value - x) < 0.2)) positions.push(x);
            }
        }
    }
    positions.sort((a, b) => a - b);
    const width = positions.at(-1) - positions[0];
    process.stdout.write(JSON.stringify({ html, pdf: { width,
        columns: positions.slice(1).map((value, index) => (value - positions[index]) / width) } }));
} finally {
    await task.destroy();
    await browser.close();
}
JS,
        $htmlPath, $pdfPath, $directory.'/live-preview.png',
    ]);
    $this->assertTrue($comparison->successful(), $comparison->errorOutput());
    $metrics = json_decode($comparison->output(), true, flags: JSON_THROW_ON_ERROR);
    expect($metrics['pdf']['columns'])->toHaveCount(9)
        ->and(abs($metrics['pdf']['width'] - $metrics['html']['width'] * 0.75))->toBeLessThan(6)
        ->and(abs(($locate('Estimated Breakdown')['bottom'] - $locate('Estimated Breakdown')['top']) - $metrics['html']['titleFont'] * 0.75))->toBeLessThan(0.5)
        ->and(abs(($titleStart['bottom'] - $titleStart['top']) - $metrics['html']['projectFont'] * 0.75))->toBeLessThan(0.5)
        ->and(abs(($longRow['bottom'] - $longRow['top']) - $metrics['html']['bodyFont'] * 0.75))->toBeLessThan(0.5);
    foreach ($metrics['html']['columns'] as $index => $proportion) {
        expect(abs($metrics['pdf']['columns'][$index] - $proportion))->toBeLessThan(0.01);
    }
})->group('pdf-layout');

test('real expense PDFs repeat their table headers and retain maximum text across pages', function () {
    if (getenv('ATHENA_PDF_LAYOUT') !== '1') {
        $this->markTestSkipped('Set ATHENA_PDF_LAYOUT=1 to verify the multipage expense PDF with LibreOffice.');
    }

    $maximumText = fn (string $start, string $end, string $filler): string => str_pad($start.' ', 499 - strlen($end), $filler).' '.$end;
    $items = [];
    foreach (range(1, 12) as $index) {
        $items[] = [
            'category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Office Supplies Expenses',
            'particulars' => 'ITEM'.$index,
            'details' => $maximumText('DETAIL'.$index.'START', 'DETAIL'.$index.'END', 'Wide project evidence '),
            'purpose' => $maximumText('PURPOSE'.$index.'START', 'PURPOSE'.$index.'END', 'Preserve the research outcomes '),
            'unit' => 'set', 'quantity' => 1, 'unit_cost' => 100,
        ];
        expect(mb_strlen($items[array_key_last($items)]['details']))->toBe(500)
            ->and(mb_strlen($items[array_key_last($items)]['purpose']))->toBe(500);
    }
    $directory = storage_path('framework/testing/expense-breakdown-layout');
    File::ensureDirectoryExists($directory);
    $pdfPath = $directory.'/multipage-expenses.pdf';
    File::put($pdfPath, app(LibreOfficeDocumentPdfConverter::class)->convertXlsx(
        app(ExpenseBreakdownDocumentService::class)->generate(ExpenseBreakdownData::fromValidated([
            'project_title' => 'Maximum length expense descriptions', 'items' => $items,
        ])),
    ));
    $result = Process::timeout(120)->run([
        (string) config('document_pdf.node_binary'), resource_path('js/pdf-section-coordinates.mjs'), $pdfPath,
    ]);
    $this->assertTrue($result->successful(), $result->errorOutput());
    File::put($directory.'/multipage-expenses.xml', $result->output());
    $document = new DOMDocument;
    $this->assertTrue($document->loadXML($result->output()));
    $xpath = new DOMXPath($document);
    $pages = $xpath->query('//page');
    expect($pages->length)->toBeGreaterThan(1);
    $allText = '';
    foreach ($pages as $page) {
        $pageText = implode(' ', array_map(fn (DOMNode $word): string => $word->textContent,
            iterator_to_array($xpath->query('.//word', $page))));
        expect($pageText)->toContain('Account', 'Sub-account', 'Particular/s', 'Qty.')
            ->not->toContain('*please remove the blank rows');
        $allText .= $pageText;
        foreach ($xpath->query('./line', $page) as $line) {
            expect((float) $line->getAttribute('xMin'))->toBeGreaterThanOrEqual(16)
                ->and((float) $line->getAttribute('xMax'))->toBeLessThanOrEqual(596)
                ->and((float) $line->getAttribute('yMin'))->toBeGreaterThanOrEqual(52)
                ->and((float) $line->getAttribute('yMax'))->toBeLessThanOrEqual(884);
        }
    }
    foreach (range(1, 12) as $index) {
        expect($allText)->toContain('DETAIL'.$index.'START', 'DETAIL'.$index.'END', 'PURPOSE'.$index.'START', 'PURPOSE'.$index.'END');
    }
    $lastPageText = preg_replace('/\s+/u', '', $pages->item($pages->length - 1)->textContent);
    expect($lastPageText)->toContain('TOTALMOOEandCAPITALOUTLAY:', '1,200.00');
})->group('pdf-layout');
