<?php

use App\Models\TopicProposal;
use App\Services\CommentResponseFormDocumentService;

test('evaluation boxes contain crosses beside the reference labels without added stage text', function (array $stages, array $checkedBoxes) {
    $contents = app(CommentResponseFormDocumentService::class)->generate([
        'project_title' => 'Coastal Habitat Restoration', 'project_leader' => 'Dr. Aurora Reyes',
        'leader_campus' => 'Alangilan', 'leader_college' => 'CICS', 'leader_department' => '', 'staff' => [],
        'evaluation_stages' => $stages,
        'feedback' => array_map(fn (string $stage): array => [
            'reviewer' => 'Reviewer', 'location' => 'Page 2', 'comment' => 'Clarify the sampling plan.',
            'stage' => $stage, 'response' => 'Revised the sampling plan.',
        ], $stages),
    ]);
    $path = tempnam(sys_get_temp_dir(), 'athena-stage-form-');
    file_put_contents($path, $contents);
    $archive = new ZipArchive;
    try {
        expect($archive->open($path))->toBeTrue();
        $document = new DOMDocument;
        expect($document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $boxes = $xpath->query('/w:document/w:body/w:tbl[1]/w:tr/w:tc/w:tcPr/w:shd');
        expect($boxes->length)->toBe(0);
        foreach ([1, 2] as $box) {
            expect($xpath->query('/w:document/w:body/w:tbl[1]/w:tr['.$box.']/w:tc[1]')->item(0)->textContent)->toBe(in_array($box, $checkedBoxes, true) ? '×' : '')
                ->and($xpath->query('/w:document/w:body/w:tbl[1]/w:tr['.$box.']/w:tc[1]/w:p/w:pPr/w:jc[@w:val="center"]')->length)->toBe(1);
        }
        expect($document->textContent)->toContain('LEVEL OF EVALUATION DONE:', 'Initial Screening', 'Local Research Evaluation')
            ->not->toContain('PREVIOUS', 'Feedback stage:', 'Stage:', 'Evaluation by the Local Research Evaluation Committee')
            ->and($xpath->query('//w:shd[@w:fill="7A0019" or @w:fill="FCE7ED"]')->length)->toBe(0)
            ->and($xpath->query('/w:document/w:body/w:tbl[1]/w:tblPr/w:tblpPr')->length)->toBe(0)
            ->and($xpath->query('/w:document/w:body/w:tbl[1]/w:tr[1]/w:tc[2]')->item(0)?->textContent)->toBe('Initial Screening')
            ->and($xpath->query('/w:document/w:body/w:tbl[1]/w:tr[2]/w:tc[2]')->item(0)?->textContent)->toBe('Local Research Evaluation');
        expect($document->textContent)->not->toContain('Reviewer', 'LREC committee');
        if ($stages !== []) {
            expect($document->textContent)->toContain('Revised the sampling plan.');
        }
    } finally {
        $archive->close();
        unlink($path);
    }
})->with([
    'Research Head' => [['research_head'], [1]], 'GAD' => [['gad'], [1]],
    'Co-Evaluator' => [['co_evaluator'], [1]], 'LREC' => [['lrec'], [2]],
    'Mixed stages' => [['research_head', 'lrec'], [1, 2]],
    'No evaluation' => [[], []],
    'Unknown stage' => [['unknown'], []],
]);

test('generated Comment-Response Forms match the requested title and project staff layout while preserving the matrix', function (string $projectTitle) {
    $contents = app(CommentResponseFormDocumentService::class)->generate([
        'project_title' => $projectTitle,
        'project_leader' => 'Dr. Aurora Reyes',
        'leader_campus' => 'Alangilan',
        'leader_college' => 'CICS',
        'leader_department' => 'Department of Computing Sciences',
        'staff' => [
            [
                'name' => 'Bea Santos',
                'campus' => 'Alangilan',
                'college' => 'CICS',
                'department' => 'Department of Computing Sciences',
            ],
            [
                'name' => 'Carlos Lim',
                'campus' => 'Lipa',
                'college' => 'CTE',
                'department' => 'Department of Teacher Education',
            ],
        ],
        'feedback' => [
            [
                'reviewer' => 'Dr. Maria Santos',
                'location' => 'Narrative Evaluation',
                'comment' => 'Clarify the scope of the coastal habitat sampling.',
                'response' => 'The scope was revised to match the sampling plan.',
                'remarks' => 'Page 4, paragraph 2',
            ],
        ],
    ]);

    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-comment-response-layout-test-');
    expect($temporaryPath)->not->toBeFalse();
    file_put_contents($temporaryPath, $contents);
    $generated = new ZipArchive;
    $template = new ZipArchive;

    try {
        expect($generated->open($temporaryPath))->toBeTrue()
            ->and($template->open(config('comment_response_form.template_path')))->toBeTrue();

        $generatedDocument = new DOMDocument;
        $templateDocument = new DOMDocument;
        expect($generatedDocument->loadXML($generated->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue()
            ->and($templateDocument->loadXML($template->getFromName('word/document.xml'), LIBXML_NONET))->toBeTrue();

        $generatedXPath = new DOMXPath($generatedDocument);
        $templateXPath = new DOMXPath($templateDocument);
        $wordNamespace = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $generatedXPath->registerNamespace('w', $wordNamespace);
        $templateXPath->registerNamespace('w', $wordNamespace);

        $title = $generatedXPath->query('/w:document/w:body/w:p[.//w:t[contains(., "Coastal Habitat Restoration")]]')->item(0);
        expect($generatedDocument->textContent)->not->toContain('Dr. Maria Santos')
            ->and($generatedDocument->textContent)->toContain('Narrative Evaluation', 'Clarify the scope of the coastal habitat sampling.', 'The scope was revised to match the sampling plan.', 'Page 4, paragraph 2');
        expect($title?->textContent)->toBe('TITLE OF RESEARCH PROPOSAL: '.$projectTitle)
            ->and($generatedXPath->query('.//w:tab | .//w:u | .//w:pBdr', $title)->length)->toBe(0);
        expect($generatedXPath->query('./w:r[w:t[contains(., "Coastal Habitat Restoration")]]/w:rPr/w:b', $title)->length)->toBe(1);
        $researchers = $generatedXPath->query('/w:document/w:body/w:p[.//w:t[text() = "PROJECT STAFF:"]]')->item(0);
        expect($researchers?->textContent)->toBe('PROJECT STAFF: Dr. Aurora Reyes, Bea Santos, Carlos Lim')
            ->and($generatedXPath->query('/w:document/w:body/w:tbl[.//w:t[text() = "POSITION"]]')->length)->toBe(0);
        expect($generatedXPath->query('./w:r[w:t[contains(., "Dr. Aurora Reyes")]]/w:rPr/w:b', $researchers)->length)->toBe(1);
        $headerDocument = new DOMDocument;
        expect($headerDocument->loadXML($generated->getFromName('word/header1.xml'), LIBXML_NONET))->toBeTrue()
            ->and($headerDocument->textContent)->not->toContain('MATRIX', 'COMMENTS AND SUGGESTIONS', 'Constituent/Extension Campus');
        $matrixHeading = $generatedXPath->query('/w:document/w:body/w:p[1]')->item(0);
        expect($matrixHeading?->textContent)->toBe('MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS')
            ->and($generatedXPath->query('.//w:br', $matrixHeading)->length)->toBe(0);
        $footerDocument = new DOMDocument;
        expect($footerDocument->loadXML($generated->getFromName('word/footer1.xml'), LIBXML_NONET))->toBeTrue()
            ->and($footerDocument->textContent)->not->toContain('Comment-Response Form |', $projectTitle);

        $generatedSection = $generatedXPath->query('/w:document/w:body/w:sectPr')->item(0);
        $templateSection = $templateXPath->query('/w:document/w:body/w:sectPr')->item(0);
        expect($generatedSection?->C14N())->toBe($templateSection?->C14N());

        $generatedTables = $generatedXPath->query('/w:document/w:body/w:tbl');
        $templateTables = $templateXPath->query('/w:document/w:body/w:tbl');
        expect($generatedTables->length)->toBe($templateTables->length - 1);

        foreach ([1 => 2, 2 => 3] as $generatedIndex => $templateIndex) {
            $generatedTable = $generatedTables->item($generatedIndex);
            $templateTable = $templateTables->item($templateIndex);
            expect($generatedXPath->query('./w:tblPr | ./w:tblGrid', $generatedTable)->item(0)?->C14N())
                ->toBe($templateXPath->query('./w:tblPr | ./w:tblGrid', $templateTable)->item(0)?->C14N())
                ->and($generatedXPath->query('./w:tblGrid', $generatedTable)->item(0)?->C14N())
                ->toBe($templateXPath->query('./w:tblGrid', $templateTable)->item(0)?->C14N());
        }

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/header1.xml', 'word/footer1.xml', 'word/settings.xml'], true)) {
                expect($generated->getFromName($name))->toBe($template->getFromName($name));
            }
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
})->with([
    'Short title' => ['Coastal Habitat Restoration'],
    'Long title with XML characters' => ['Coastal Habitat Restoration & Resilience: A Comparative Study of Mangrove Ecosystems <Across Batangas Province>'],
]);

test('the revision workspace uses the same two crossed evaluation boxes as the paper', function (array $stages, array $selectedLevels) {
    $view = $this->blade('<x-comment-response-stages :stages="$stages" />', ['stages' => $stages]);
    $view->assertSeeText('LEVEL OF EVALUATION DONE:')
        ->assertSeeText('Initial Screening')
        ->assertSeeText('Local Research Evaluation')
        ->assertDontSeeText('Feedback stage');

    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.(string) $view);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//li[@data-evaluation-level]')->length)->toBe(2);
    foreach ([0, 1] as $level) {
        $selected = in_array($level, $selectedLevels, true);
        $row = $xpath->query('//li[@data-evaluation-level="'.$level.'"]')->item(0);
        expect($row->getAttribute('data-stage-active'))->toBe($selected ? 'true' : 'false')
            ->and($xpath->query('./span[1]', $row)->item(0)->getAttribute('class'))->toContain('bg-white')
            ->and(trim($xpath->query('./span[1]', $row)->item(0)->textContent))->toBe($selected ? '×' : '');
    }
})->with([
    'Initial Screening' => [['research_head', 'gad', 'co_evaluator'], [0]],
    'Local Research Evaluation' => [['lrec'], [1]],
    'Both evaluations' => [['research_head', 'lrec'], [0, 1]],
    'No evaluation' => [[], []],
]);

test('the HTML matrix preview has an inline title and bold project staff names without commenter names', function (string $stage) {
    $this->withoutVite();
    $view = $this->view('faculty.comment-response-form.preview', [
        'topic' => (new TopicProposal)->forceFill(['id' => 1]),
        'commentResponseForm' => [
            'form_label' => 'Research Head Comment Response', 'form_source' => 'research_head', 'review_id' => null,
            'project_title' => 'Coastal Habitat Restoration', 'project_leader' => 'Dr. Aurora Reyes',
            'staff' => [['name' => 'Bea Santos']], 'evaluation_stages' => ['lrec'],
            'feedback' => [[
                'reviewer' => 'Dr. Maria Santos', 'stage' => $stage, 'location' => 'Page 2',
                'comment' => 'Clarify the sampling plan.', 'response' => 'Revised the sampling plan.', 'remarks' => 'Page 4',
            ]],
        ],
    ]);
    $view->assertSeeText('MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS')
        ->assertSeeText('LEVEL OF EVALUATION DONE:')
        ->assertSeeText('PROJECT STAFF: Dr. Aurora Reyes, Bea Santos')
        ->assertDontSeeText('PREVIOUS')
        ->assertDontSeeText('REVIEW SOURCE:')
        ->assertDontSeeText('Feedback stage')
        ->assertDontSeeText('Dr. Maria Santos')
        ->assertSeeText('Page 2')
        ->assertSeeText('Clarify the sampling plan.')
        ->assertSeeText('Revised the sampling plan.')
        ->assertSeeText('Page 4');

    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML((string) $view);
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);
    $title = $xpath->query('//p[strong[text() = "TITLE OF RESEARCH PROPOSAL:"]]')->item(0);
    expect($title?->textContent)->toBe('TITLE OF RESEARCH PROPOSAL: Coastal Habitat Restoration')
        ->and($xpath->query('./br | .//u', $title)->length)->toBe(0)
        ->and($xpath->query('./strong[2]', $title)->item(0)?->textContent)->toBe('Coastal Habitat Restoration')
        ->and($xpath->query('//footer')->length)->toBe(0)
        ->and($xpath->query('//table')->length)->toBe(1)
        ->and($xpath->query('//ul[@class="evaluation-levels"]/li[1]/span[contains(@class, "is-checked")]')->length)->toBe(0)
        ->and($xpath->query('//ul[@class="evaluation-levels"]/li[2]/span[contains(@class, "is-checked")]')->length)->toBe(1);
})->with(['research_head', 'gad', 'co_evaluator', 'lrec']);
