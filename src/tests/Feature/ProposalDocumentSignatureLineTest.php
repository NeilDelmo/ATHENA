<?php

use App\Services\GADChecklistDocumentService;
use App\Services\InitialScreeningFormDocumentService;

test('proposal document signature lines underline only the printed names', function () {
    $documentXPath = function (string $contents): DOMXPath {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'proposal-signature-test-');
        expect($temporaryPath)->not->toBeFalse();
        file_put_contents($temporaryPath, $contents);
        $archive = new ZipArchive;

        try {
            expect($archive->open($temporaryPath))->toBeTrue();
            $documentXml = $archive->getFromName('word/document.xml');
            expect($documentXml)->not->toBeFalse();
        } finally {
            $archive->close();
            unlink($temporaryPath);
        }

        $document = new DOMDocument;
        expect($document->loadXML($documentXml, LIBXML_NONET))->toBeTrue();
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        return $xpath;
    };

    $gadXPath = $documentXPath(app(GADChecklistDocumentService::class)->generate([
        'project_title' => 'Coastal Habitat Restoration',
        'project_leader' => 'Faculty Owner',
        'verifier_name' => 'Dr. Vera Santos',
        'verifier_role' => 'GAD Verifier',
    ]));

    expect($gadXPath->query('//w:p[normalize-space(.) = "Faculty Owner"]/w:r/w:rPr/w:u[@w:val = "single"]')->length)->toBe(1)
        ->and($gadXPath->query('//w:p[normalize-space(.) = "Dr. Vera Santos"]/w:r/w:rPr/w:u[@w:val = "single"]')->length)->toBe(1);

    $screeningXPath = $documentXPath(app(InitialScreeningFormDocumentService::class)->generate([
        'project_title' => 'Coastal Habitat Restoration',
        'project_leader' => 'Faculty Owner',
        'screening_head' => 'Dr. Helena Cruz',
        'screening_center' => 'Dr. Carlo Reyes',
        'screening_verifier' => 'Dr. Maria Santos',
    ]));

    foreach (['DR. HELENA CRUZ', 'DR. CARLO REYES', 'DR. MARIA SANTOS'] as $name) {
        $nameParagraph = $screeningXPath->query('//w:p[normalize-space(.) = "'.$name.'"]')->item(0);

        expect($nameParagraph)->not->toBeNull()
            ->and($screeningXPath->query('./w:r/w:rPr/w:u[@w:val = "single"]', $nameParagraph)->length)->toBe(1)
            ->and(trim((string) $screeningXPath->evaluate('string(preceding-sibling::w:p[1])', $nameParagraph)))->toBe('');
    }
});
