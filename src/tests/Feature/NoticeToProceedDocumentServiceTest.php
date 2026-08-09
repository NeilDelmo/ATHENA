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
    $media = collect(range(0, $archive->numFiles - 1))
        ->map(fn (int $index): string => (string) $archive->getNameIndex($index))
        ->filter(fn (string $name): bool => str_starts_with($name, 'word/media/'));
    $archive->close();
    unlink($path);

    expect($xml)
        ->toContain('Asst. Prof. D. IOANNA MARIE V. SALAC')
        ->toContain('Dr. FROILAN G. DESTREZA')
        ->toContain('Development of an Online College Research Journal Management System')
        ->toContain('July 7, 2025')
        ->toContain('one hundred forty seven thousand one hundred twenty eight pesos')
        ->not->toContain('{{')
        ->and($media)->not->toBeEmpty();
});
