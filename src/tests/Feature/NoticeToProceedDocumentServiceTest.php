<?php

use App\Services\NoticeToProceedDataService;
use App\Services\NoticeToProceedDocumentService;

test('it fills the official notice template with approved project details', function () {
    $data = [
        'notice_date' => '2025-06-24',
        'researcher_names' => [
            'Asst. Prof. D. IOANNA MARIE V. SALAC',
            'Dr. FROILAN G. DESTREZA',
        ],
        'campus_line' => 'Batangas State University The NEU ARASOF-Nasugbu Campus',
        'project_title' => 'Development of an Online College Research Journal Management System',
        'resolution_number' => '01',
        'resolution_year' => 2025,
        'approved_start_date' => '2025-07-07',
        'approved_end_date' => '2026-07-06',
        'approved_duration_months' => 12,
        'approved_budget' => '147128.00',
        'issuing_officer_name' => 'DR. FROILAN G. DESTREZA',
        'issuing_officer_title' => 'Vice Chancellor for Research, Development and Extension Services',
        'issuing_officer_committee_role' => 'Member, Local Research Evaluation Committee',
        'verifying_officer_name' => 'ASSOC. PROF. ALBERTSON D. AMANTE',
        'verifying_officer_title' => 'Vice President for Research, Development and Extension Services',
        'verifying_officer_committee_role' => 'Chairperson, Local Research Evaluation Committee',
    ];

    $values = app(NoticeToProceedDataService::class)->documentValues($data);
    $document = app(NoticeToProceedDocumentService::class)->generate($values);
    $path = tempnam(sys_get_temp_dir(), 'notice-test-');
    file_put_contents($path, $document);

    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();
    $xml = $archive->getFromName('word/document.xml');
    $footerXml = $archive->getFromName('word/footer1.xml');
    $media = collect(range(0, $archive->numFiles - 1))
        ->map(fn (int $index): string => (string) $archive->getNameIndex($index))
        ->filter(fn (string $name): bool => str_starts_with($name, 'word/media/'));
    $archive->close();
    unlink($path);

    $document = new DOMDocument;
    $document->loadXML((string) $xml);
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    expect($xml)
        ->toContain('Asst. Prof. D. IOANNA MARIE V. SALAC')
        ->toContain('Dr. FROILAN G. DESTREZA')
        ->toContain('Development of an Online College Research Journal Management System')
        ->toContain('July 7, 2025')
        ->toContain('one hundred forty seven thousand one hundred twenty eight pesos')
        ->not->toContain('{{')
        ->and($media)->not->toBeEmpty()
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl)'))->toBe(2)
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl[1]/w:tr)'))->toBe(2)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="Republic of the Philippines"]/w:rPr/w:b)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231"]/w:rPr/w:b)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl[1]/following-sibling::w:p[1]/w:pPr/w:pBdr/w:bottom[@w:color="000000"])'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:p[w:r/w:t="Research Office"]/w:pPr/w:jc[@w:val="left"])'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="July 7, 2025 to July 6, 2026"]/w:rPr/w:b)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(//w:r[w:t="one hundred forty seven thousand one hundred twenty eight pesos (Php 147,128.00)"]/w:rPr/w:b)'))->toBe(1)
        ->and((int) $xpath->evaluate('count(/w:document/w:body/w:tbl[2]//w:shd)'))->toBe(0)
        ->and($xml)->toContain('*a maximum of 3 units must be retained for each faculty with administrative assignment/local designation')
        ->and($xml)->toContain('Note: maximum of two (2) projects at a time are allowed per faculty member')
        ->and($xml)->toContain('Checked and Verified by:')
        ->and($xml)->toContain('Conforme:')
        ->and((string) $footerXml)->toContain('Leading Innovations. Transforming Lives. Building the Nation.')
        ->and($xml)->toContain('w:footer="648"');
});
