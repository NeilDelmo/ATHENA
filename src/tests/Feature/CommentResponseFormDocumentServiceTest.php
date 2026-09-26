<?php

use App\Services\CommentResponseFormDocumentService;

test('generated Comment-Response Forms preserve the official template layout', function () {
    $contents = app(CommentResponseFormDocumentService::class)->generate([
        'project_title' => 'Coastal Habitat Restoration',
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

        expect($generatedXPath->query('/w:document/w:body/w:p[.//w:t[contains(., "Coastal Habitat Restoration")]]/w:pPr/w:tabs/w:tab[@w:val = "right" and @w:leader = "underscore" and @w:pos = "10224"]')->length)->toBe(1)
            ->and($generatedXPath->query('/w:document/w:body/w:p[.//w:t[contains(., "Coastal Habitat Restoration")]]/w:r/w:tab')->length)->toBe(1)
            ->and($generatedXPath->query('/w:document/w:body/w:p[.//w:t[contains(., "Coastal Habitat Restoration")]]/w:r/w:rPr/w:u[@w:val = "single"]')->length)->toBe(1);

        $generatedSection = $generatedXPath->query('/w:document/w:body/w:sectPr')->item(0);
        $templateSection = $templateXPath->query('/w:document/w:body/w:sectPr')->item(0);
        expect($generatedSection?->C14N())->toBe($templateSection?->C14N());

        $generatedTables = $generatedXPath->query('/w:document/w:body/w:tbl');
        $templateTables = $templateXPath->query('/w:document/w:body/w:tbl');
        expect($generatedTables->length)->toBe($templateTables->length);

        for ($index = 0; $index < $templateTables->length; $index++) {
            $generatedTable = $generatedTables->item($index);
            $templateTable = $templateTables->item($index);
            expect($generatedXPath->query('./w:tblPr | ./w:tblGrid', $generatedTable)->item(0)?->C14N())
                ->toBe($templateXPath->query('./w:tblPr | ./w:tblGrid', $templateTable)->item(0)?->C14N())
                ->and($generatedXPath->query('./w:tblGrid', $generatedTable)->item(0)?->C14N())
                ->toBe($templateXPath->query('./w:tblGrid', $templateTable)->item(0)?->C14N());
        }

        for ($index = 0; $index < $template->numFiles; $index++) {
            $entry = $template->statIndex($index);
            $name = $entry['name'];

            if (! in_array($name, ['word/document.xml', 'word/footer1.xml', 'word/settings.xml'], true)) {
                expect($generated->getFromName($name))->toBe($template->getFromName($name));
            }
        }
    } finally {
        $generated->close();
        $template->close();
        unlink($temporaryPath);
    }
});
