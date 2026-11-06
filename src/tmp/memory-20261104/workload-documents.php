<?php

use App\Models\CollegeQuarterlyReport;
use App\Models\ProjectNarrativeReport;
use App\Models\TopicProposal;
use App\Models\User;
use App\Services\CollegeQuarterlyReportDocument;
use App\Services\DetailedProposalDocumentService;
use App\Services\ExpenseBreakdownDocumentService;
use App\Services\LibreOfficeDocumentPdfConverter;
use App\Services\ProgressReportDocumentService;
use App\Services\RmsQuarterlyReportData;
use App\Services\RmsQuarterlyReportDocument;
use App\Support\DetailedProposalData;
use App\Support\DetailedProposalRules;
use App\Support\ExpenseBreakdownData;
use App\Support\ExpenseBreakdownRules;
use App\Support\TerminalReportRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

function capacityDocAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function capacityDocWrite(string $path, string $contents): void
{
    capacityDocAssert(file_put_contents($path, $contents) === strlen($contents), 'Could not write '.$path);
}

function capacityDocNarrative(string $marker): string
{
    return str_repeat('<p>'.str_repeat('Validated research evidence supports community outcomes. ', 25).'</p>', 12)
        .'<p>'.$marker.'</p>';
}

function capacityDocImages(array $context): array
{
    $paths = $context['image_paths'] ?? [];
    $expected = $context['expected_image_count'] ?? 12;
    $expectedDimensions = $context['expected_image_dimensions'] ?? [2400, 1600];
    capacityDocAssert(count($paths) === $expected && count(array_unique($paths)) === $expected, 'Incorrect unique image count.');
    $bytes = 0;
    foreach ($paths as $path) {
        capacityDocAssert(is_string($path) && Storage::disk('local')->exists($path), 'Missing benchmark image.');
        $size = Storage::disk('local')->size($path);
        capacityDocAssert($size > 0 && $size <= 5 * 1024 * 1024, 'Image exceeds the common report upload limit.');
        $dimensions = getimagesize(Storage::disk('local')->path($path));
        capacityDocAssert($dimensions !== false && $dimensions[2] === IMAGETYPE_JPEG, 'Benchmark image must be JPEG.');
        capacityDocAssert([$dimensions[0], $dimensions[1]] === $expectedDimensions, 'Benchmark image dimensions changed.');
        $bytes += $size;
    }

    return ['paths' => array_values($paths), 'bytes' => $bytes];
}

function capacityDocWrappedTableMarker(string $pdfPath, string $layoutText, string $marker, int $pages): ?array
{
    if (preg_match('/^T\d+R\d+C([1-6])END$/', $marker, $column) !== 1) {
        return null;
    }
    $prefix = substr($marker, 0, -1);
    foreach (explode("\f", $layoutText) as $index => $pageText) {
        $page = $index + 1;
        if (! str_contains($pageText, $prefix) || $page >= $pages) {
            continue;
        }
        $bbox = Process::timeout(120)->run(['/usr/bin/pdftotext', '-bbox', '-f', (string) $page, '-l', (string) $page, $pdfPath, '-']);
        capacityDocAssert($bbox->successful(), 'Could not inspect wrapped PDF marker coordinates.');
        $document = new DOMDocument;
        capacityDocAssert($document->loadXML($bbox->output(), LIBXML_NONET), 'Invalid PDF word coordinates.');
        $xpath = new DOMXPath($document);
        $pageElement = $xpath->query('//*[local-name()="page"]')->item(0);
        $words = $xpath->query('//*[local-name()="word"]');
        $fragment = null;
        $headers = [];
        foreach ($words as $word) {
            if ($word->textContent === $prefix) {
                $fragment = $word;
            }
            if (in_array($word->textContent, ['A', 'B', 'C', 'D', 'E', 'F'], true) && (float) $word->getAttribute('yMin') < 50) {
                $headers[$word->textContent] = $word;
            }
        }
        if ($fragment === null || count($headers) !== 6 || $pageElement === null) {
            continue;
        }
        $heading = $headers[chr(64 + (int) $column[1])];
        $left = (float) $heading->getAttribute('xMin');
        $width = (float) $headers['B']->getAttribute('xMin') - (float) $headers['A']->getAttribute('xMin');
        if (abs((float) $fragment->getAttribute('xMin') - $left) > 1 || (float) $fragment->getAttribute('yMin') < (float) $pageElement->getAttribute('height') * 0.8) {
            continue;
        }
        $x = (int) floor($left);
        $y = (int) ceil((float) $heading->getAttribute('yMax'));
        $height = (int) floor((float) $pageElement->getAttribute('height') - 72) - $y;
        // A table cell can continue after repeated headers on the next page.
        $crop = Process::timeout(120)->run([
            '/usr/bin/pdftotext', '-raw', '-f', (string) $page, '-l', (string) ($page + 1),
            '-x', (string) $x, '-y', (string) $y, '-W', (string) (int) floor($width), '-H', (string) $height, $pdfPath, '-',
        ]);
        capacityDocAssert($crop->successful(), 'Could not read wrapped PDF table column.');
        if (str_contains((string) preg_replace('/\s+/u', '', $crop->output()), $marker)) {
            return ['method' => 'exact_column_text', 'pages' => [$page, $page + 1], 'crop' => [$x, $y, (int) floor($width), $height]];
        }
    }

    return null;
}

function capacityDocFinish(string $case, string $contents, string $format, array $markers, array $counts, string $outputDirectory, ?string $mediaPrefix = null, int $mediaCount = 0): array
{
    capacityDocAssert(is_dir($outputDirectory) || mkdir($outputDirectory, 0775, true), 'Could not create benchmark output directory.');
    $sourcePath = $outputDirectory.'/'.$case.'.'.$format;
    $pdfPath = $outputDirectory.'/'.$case.'.pdf';
    $textPath = $outputDirectory.'/'.$case.'.txt';
    capacityDocWrite($sourcePath, $contents);
    $archive = new ZipArchive;
    capacityDocAssert($archive->open($sourcePath, ZipArchive::CHECKCONS) === true, 'Generated source is not a consistent ZIP.');
    try {
        $body = $archive->getFromName($format === 'docx' ? 'word/document.xml' : 'xl/workbook.xml');
        capacityDocAssert(is_string($body), 'Generated source is missing its document body.');
        $document = new DOMDocument;
        capacityDocAssert($document->loadXML($body, LIBXML_NONET), 'Generated source contains invalid XML.');
        $xmlText = '';
        $images = 0;
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index);
            if ($mediaPrefix !== null && str_starts_with($name, $mediaPrefix)) {
                $images++;
            }
            if ($name === 'word/document.xml' || preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name)) {
                $part = $archive->getFromIndex($index);
                capacityDocAssert(is_string($part), 'Could not read generated XML part.');
                $xmlText .= html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        if ($mediaPrefix !== null) {
            capacityDocAssert($images === $mediaCount, 'Generated source has an incorrect embedded image count.');
        }
        foreach ($markers as $marker) {
            capacityDocAssert(str_contains($xmlText, $marker), 'Generated source lost marker '.$marker);
        }
    } finally {
        $archive->close();
    }
    unset($xmlText, $body, $document, $part);

    $conversionStarted = hrtime(true);
    $converter = app(LibreOfficeDocumentPdfConverter::class);
    $pdf = $format === 'docx' ? $converter->convertDocx($contents) : $converter->convertXlsx($contents);
    $conversionSeconds = (hrtime(true) - $conversionStarted) / 1e9;
    capacityDocAssert(str_starts_with($pdf, '%PDF-'), 'Real converter did not produce PDF.');
    capacityDocWrite($pdfPath, $pdf);
    $pdfBytes = strlen($pdf);
    unset($pdf);
    $info = Process::timeout(120)->run(['/usr/bin/pdfinfo', $pdfPath]);
    capacityDocAssert($info->successful(), 'pdfinfo failed: '.$info->errorOutput());
    capacityDocAssert(preg_match('/^Pages:\s+(\d+)/m', $info->output(), $matches) === 1, 'pdfinfo did not report pages.');
    $pages = (int) $matches[1];
    capacityDocAssert($pages > 0, 'PDF has no pages.');
    $text = Process::timeout(240)->run(['/usr/bin/pdftotext', '-layout', $pdfPath, $textPath]);
    capacityDocAssert($text->successful(), 'pdftotext failed: '.$text->errorOutput());
    $extracted = file_get_contents($textPath);
    capacityDocAssert(is_string($extracted), 'Could not read extracted PDF text.');
    $compact = preg_replace('/\s+/u', '', $extracted);
    capacityDocAssert(is_string($compact), 'PDF text is not valid UTF-8.');
    $missing = array_values(array_filter($markers, fn (string $marker): bool => ! str_contains($compact, $marker)));
    $fallbacks = [];
    $rawCompact = '';
    if ($missing !== []) {
        $raw = Process::timeout(240)->run(['/usr/bin/pdftotext', '-raw', $pdfPath, '-']);
        capacityDocAssert($raw->successful(), 'Reading-order PDF extraction failed: '.$raw->errorOutput());
        $rawCompact = preg_replace('/\s+/u', '', $raw->output());
        capacityDocAssert(is_string($rawCompact), 'Reading-order PDF text is not valid UTF-8.');
    }
    foreach ($markers as $marker) {
        if (str_contains($compact, $marker)) {
            continue;
        }
        if (str_contains($rawCompact, $marker)) {
            $fallbacks[$marker] = ['method' => 'exact_reading_order_text'];

            continue;
        }
        $continuation = $case === 'terminal-images-tables' ? capacityDocWrappedTableMarker($pdfPath, $extracted, $marker, $pages) : null;
        capacityDocAssert($continuation !== null, 'Actual PDF lost marker '.$marker);
        $fallbacks[$marker] = $continuation;
    }

    return [
        'case' => $case,
        'conversion_seconds' => $conversionSeconds,
        'source_path' => $sourcePath,
        'pdf_path' => $pdfPath,
        'text_path' => $textPath,
        'pages' => $pages,
        'docx_or_xlsx_bytes' => strlen($contents),
        'pdf_bytes' => $pdfBytes,
        'input_counts' => $counts,
        'verified_markers' => $markers,
        'marker_extraction_fallbacks' => $fallbacks,
    ];
}

function capacityRunDocumentCase(string $case, array $context, string $outputDirectory): array
{
    $caseStarted = hrtime(true);
    $imageCases = ['proposal-large', 'progress-images', 'terminal-images-tables', 'quarterly-evidence'];
    $images = in_array($case, $imageCases, true) ? capacityDocImages($context) : ['paths' => [], 'bytes' => 0];
    $paths = $images['paths'];
    $counts = ['images' => count($paths), 'image_bytes' => $images['bytes']];
    $format = 'docx';
    $mediaPrefix = null;
    $mediaCount = 0;

    if ($case === 'proposal-large') {
        $input = [
            'project_title' => 'Capacity benchmark coastal research',
            'project_leader' => 'Benchmark Leader',
            'research_agenda' => 'Environment, Natural Resources, and Climate Change',
            'sdgs' => [4, 9, 17],
            'leader_email' => 'leader@example.test', 'leader_contact' => '09171234567',
            'proponent_college' => User::COLLEGES['CICS'], 'proponent_campus' => 'ARASOF-Nasugbu',
            'executive_brief' => capacityDocNarrative('BRIEFEND'),
            'rationale' => capacityDocNarrative('RATIONALEEND'),
            'introduction' => capacityDocNarrative('INTROEND'),
            'related_literature' => capacityDocNarrative('LITERATUREEND'),
            'general_objective' => 'Evaluate the intervention.',
            'specific_objectives' => array_map(fn (int $i): array => ['description' => 'OBJECTIVE'.$i.'END'], range(1, 20)),
            'expected_outputs' => collect(config('detailed_proposal.expected_outputs'))->map(fn (): array => [['description' => 'One validated research output.']])->all(),
            'methodology' => [
                'research_design' => capacityDocNarrative('DESIGNEND'),
                'specific_methods' => capacityDocNarrative('METHODSEND'),
                'data_analysis' => capacityDocNarrative('ANALYSISEND'),
            ],
            'specific_method_objectives' => array_map(fn (int $i): array => [
                'heading' => 'Objective '.$i,
                'methods' => array_map(fn (int $j): array => ['description' => str_repeat('Collect verified evidence. ', 35).'METHOD'.$i.'X'.$j.'END'], range(1, 10)),
            ], range(1, 20)),
            'methodology_images' => array_map(fn (string $path, int $i): array => [
                'section' => 'research_design', 'alignment' => 'center', 'size' => 'large',
                'caption' => 'PROPOSALFIGURE'.($i + 1).'END', 'stored_path' => $path,
                'mime_type' => 'image/jpeg', 'original_filename' => basename($path),
            ], $paths, array_keys($paths)),
            'responsibilities' => [['name' => 'Benchmark Leader', 'percentage' => 100, 'duties' => 'Coordinate implementation and validate results.']],
            'references' => capacityDocNarrative('REFERENCESEND'),
        ];
        capacityDocAssert(array_key_exists('research_design', config('detailed_proposal.image_sections')), 'Research design image section is unavailable.');
        $errors = DetailedProposalRules::completionErrors($input);
        capacityDocAssert($errors === [], 'Proposal fixture is incomplete: '.json_encode($errors));
        $counts += ['objectives' => 20, 'method_groups' => 20, 'methods_per_group' => 10, 'serialized_data_bytes' => strlen(json_encode($input, JSON_THROW_ON_ERROR))];
        $generationStarted = hrtime(true);
        $contents = app(DetailedProposalDocumentService::class)->generate(DetailedProposalData::fromValidated($input));
        $markers = ['OBJECTIVE20END', 'METHOD20X10END', 'PROPOSALFIGURE12END', 'REFERENCESEND'];
        $mediaPrefix = 'word/media/methodology-';
        $mediaCount = 12;
    } elseif (in_array($case, ['progress-images', 'terminal-images-tables'], true)) {
        $user = User::factory()->make(['name' => 'Benchmark Leader']);
        $topic = new TopicProposal;
        $topic->forceFill(['title' => 'Capacity benchmark coastal research', 'estimated_duration_months' => 12]);
        $topic->setRelation('user', $user);
        $report = new ProjectNarrativeReport;
        $report->forceFill([
            'report_type' => $case === 'progress-images' ? 'progress' : 'terminal',
            'submission_date' => '2026-10-01', 'reporting_date' => '2026-09-30', 'reporting_quarter' => 3,
            'period_start' => '2026-07-01', 'period_end' => '2026-09-30',
            'implementation_start' => '2025-10-01', 'implementation_end' => '2026-09-30',
            'tracking_number' => 'BENCH001', 'researchers' => 'Benchmark Leader',
            'budget' => 150000, 'funding_agency' => 'Batangas State University',
            'introduction' => capacityDocNarrative('INTROEND'), 'rationale' => capacityDocNarrative('RATIONALEEND'),
            'objectives' => 'Evaluate the intervention.', 'methodology' => capacityDocNarrative('METHODOLOGYEND'),
            'results_discussion' => capacityDocNarrative('RESULTSEND'),
            'accomplishments' => array_map(fn (int $i): array => [
                'objective' => 'Evaluate outcome '.$i, 'target' => str_repeat('Target research output. ', 20),
                'actual' => str_repeat('Verified completed research activity. ', 50).'ACCOMPLISHMENT'.$i.'END',
            ], range(1, 30)),
            'photos' => array_map(fn (string $path, int $i): array => [
                'path' => $path, 'caption' => 'REPORTFIGURE'.($i + 1).'END',
                'section' => $i === 0 ? ($case === 'terminal-images-tables' ? 'cover' : 'methodology') : 'results_discussion',
                'after_paragraph' => 1,
            ], $paths, array_keys($paths)),
        ]);
        $report->setRelation('topic', $topic);
        $report->setRelation('submitter', $user);
        if ($case === 'progress-images') {
            foreach (['introduction', 'rationale', 'methodology', 'results_discussion'] as $field) {
                $report->{$field} = trim(strip_tags(str_replace('</p>', "\n\n", $report->{$field})));
            }
        }
        $counts += ['accomplishments' => 30];
        $markers = ['ACCOMPLISHMENT30END', 'REPORTFIGURE'.count($paths).'END', 'RESULTSEND'];
        if ($case === 'terminal-images-tables') {
            $terminal = [
                'total_expenditure' => 120000,
                'abstract' => implode(' ', array_fill(0, 210, 'research')),
                'literature_review' => capacityDocNarrative('REVIEWEND'), 'conclusions' => capacityDocNarrative('CONCLUSIONSEND'),
                'recommendations' => capacityDocNarrative('RECOMMENDATIONSEND'), 'bibliography' => capacityDocNarrative('BIBLIOGRAPHYEND'),
                'authors' => [['name' => 'Benchmark Leader', 'role' => 'Project Leader']],
                'signatories' => collect(TerminalReportRules::SIGNATORY_ROLES)->map(fn (): array => ['name' => 'Benchmark Reviewer', 'date_signed' => null])->all(),
                'tables' => array_map(fn (int $i): array => [
                    'caption' => 'TABLE'.$i.'END', 'section' => 'results_discussion', 'after_paragraph' => 1,
                    'headers' => ['A', 'B', 'C', 'D', 'E', 'F'],
                    'rows' => array_map(fn (int $row): array => array_map(fn (int $column): string => str_repeat('Verified research finding. ', 11).'T'.$i.'R'.$row.'C'.$column.'END', range(1, 6)), range(1, 100)),
                ], range(1, 6)),
            ];
            $validator = Validator::make(['terminal_data' => $terminal], TerminalReportRules::rules(false, 150000));
            capacityDocAssert($validator->passes(), 'Invalid terminal fixture: '.$validator->errors()->toJson());
            $report->terminal_data = [
                ...$terminal, 'project_title' => $topic->title,
                'approved_start' => '2025-10-01', 'approved_end' => '2026-09-30', 'approved_duration_months' => 12,
            ];
            $counts += ['tables' => 6, 'rows_per_table' => 100, 'columns_per_table' => 6];
            $markers = [...$markers, 'T6R100C6END', 'BIBLIOGRAPHYEND'];
        }
        $counts['serialized_data_bytes'] = strlen(json_encode($report->getAttributes(), JSON_THROW_ON_ERROR));
        $generationStarted = hrtime(true);
        $contents = app(ProgressReportDocumentService::class)->generate($report);
        $mediaPrefix = 'word/media/progress-figure-';
        $mediaCount = count($paths);
    } elseif ($case === 'quarterly-evidence') {
        $coordinator = User::factory()->make(['name' => 'Benchmark Coordinator', 'college' => User::COLLEGES['CICS']]);
        $report = new CollegeQuarterlyReport;
        $report->forceFill([
            'college' => $coordinator->college, 'snapshot' => ['prepared_by_name' => $coordinator->name],
            'other_accomplishments' => capacityDocNarrative('QUARTERLYNARRATIVEEND'),
            'reviewed_by' => 'Benchmark Dean', 'reviewed_by_title' => 'College Dean',
            'evidence' => array_map(fn (string $path, int $i): array => ['path' => $path, 'caption' => 'QUARTERLYFIGURE'.($i + 1).'END'], $paths, array_keys($paths)),
        ]);
        $data = [
            'presentations' => array_map(fn (int $i): array => ['Conference '.$i, 'PRESENTATION'.$i.'END', 'May 9, 2026', 'Nasugbu', "Ada\nBen"], range(1, 100)),
            'publications' => array_map(fn (int $i): array => ['Journal '.$i, 'PUBLICATION'.$i.'END', 'May 9, 2026', "Ada\nBen"], range(1, 100)),
            'research' => array_map(fn (int $i): array => ['RESEARCH'.$i.'END', '12 months', "Ada\nBen", 'On-going'], range(1, 100)),
        ];
        $period = ['quarter' => 2, 'year' => 2026, 'start' => Carbon::parse('2026-04-01'), 'end' => Carbon::parse('2026-06-30')];
        $counts += ['presentations' => 100, 'publications' => 100, 'research' => 100, 'serialized_data_bytes' => strlen(json_encode([$data, $report->getAttributes()], JSON_THROW_ON_ERROR))];
        $generationStarted = hrtime(true);
        $contents = app(CollegeQuarterlyReportDocument::class)->generate($coordinator, $period, $data, $report);
        $markers = ['PRESENTATION100END', 'PUBLICATION100END', 'RESEARCH100END', 'QUARTERLYFIGURE12END'];
        $mediaPrefix = 'word/media/quarterly-evidence-';
        $mediaCount = 12;
    } elseif (in_array($case, ['rms-500', 'rms-1500'], true)) {
        $number = $case === 'rms-500' ? 500 : 1500;
        $data = [];
        foreach (RmsQuarterlyReportData::FIELDS as $section => $fields) {
            $data[$section] = [];
            foreach (range(1, $number) as $i) {
                $values = [];
                foreach ($fields as $field => $definition) {
                    $values[$field] = match (true) {
                        $field === 'sdgs' => '4, 9',
                        $field === 'date' => '2026-05-09',
                        $definition['type'] === 'url' => 'https://example.test/evidence/'.$i,
                        $definition['type'] === 'select' => $definition['options'][0],
                        default => $section.$i.'END '.str_repeat('Verified evidence ', 5),
                    };
                }
                $data[$section][] = ['values' => $values];
            }
        }
        $metadata = ['year' => 2026, 'quarter' => 2, 'campus' => 'Nasugbu', 'signatories' => array_fill_keys(['prepared', 'reviewed', 'approved'], ['name' => 'Benchmark Reviewer', 'position' => 'Research Office', 'office' => 'Nasugbu'])];
        $counts += ['presentations' => $number, 'publications' => $number, 'ip_assets' => $number, 'serialized_data_bytes' => strlen(json_encode($data, JSON_THROW_ON_ERROR))];
        $generationStarted = hrtime(true);
        $contents = app(RmsQuarterlyReportDocument::class)->generate($data, $metadata);
        $format = 'xlsx';
        $markers = ['presentations'.$number.'END', 'publications'.$number.'END', 'ip_assets'.$number.'END'];
    } elseif ($case === 'expense-100') {
        $input = ['project_title' => 'Capacity benchmark expenses', 'items' => array_map(fn (int $i): array => [
            'category' => 'mooe', 'account' => 'Supplies and Materials Expenses', 'sub_account' => 'Office Supplies Expenses',
            'particulars' => 'EXPENSE'.$i.'END', 'details' => str_repeat('Verified research supplies. ', 17).'DETAIL'.$i.'END',
            'purpose' => str_repeat('Support research activity. ', 17).'PURPOSE'.$i.'END', 'unit' => 'set', 'quantity' => 1, 'unit_cost' => 100,
        ], range(1, 100))];
        $validator = Validator::make($input, ExpenseBreakdownRules::rules());
        capacityDocAssert($validator->passes(), 'Invalid expense fixture: '.$validator->errors()->toJson());
        $counts += ['items' => 100, 'serialized_data_bytes' => strlen(json_encode($input, JSON_THROW_ON_ERROR))];
        $generationStarted = hrtime(true);
        $contents = app(ExpenseBreakdownDocumentService::class)->generate(ExpenseBreakdownData::fromValidated($input));
        $format = 'xlsx';
        $markers = ['EXPENSE100END', 'DETAIL100END', 'PURPOSE100END'];
    } else {
        throw new InvalidArgumentException('Unknown document case: '.$case);
    }

    $generationSeconds = (hrtime(true) - $generationStarted) / 1e9;

    return [...capacityDocFinish($case, $contents, $format, $markers, $counts, $outputDirectory, $mediaPrefix, $mediaCount),
        'source_generation_seconds' => $generationSeconds,
        'fixture_and_validation_seconds' => ($generationStarted - $caseStarted) / 1e9];
}
