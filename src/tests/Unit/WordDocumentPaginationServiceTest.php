<?php

use App\Services\LineItemBudgetDocumentService;
use App\Services\WordDocumentPaginationService;
use App\Support\LineItemBudgetData;
use Tests\TestCase;

uses(TestCase::class);

test('document generation cleans up its temporary file when pagination cannot reopen it', function () {
    $pagination = new class extends WordDocumentPaginationService
    {
        public ?string $path = null;

        public function addPageNumbers(ZipArchive $archive, ?string $repeatFooterContaining = null): void
        {
            $this->path = $archive->filename;
            $archive->close();
            throw new RuntimeException('The edited Word document could not be reopened for page numbering.');
        }
    };

    try {
        expect(fn () => (new LineItemBudgetDocumentService($pagination))->generate(LineItemBudgetData::fromValidated([
            'project_title' => 'Updated project',
            'project_leader' => 'Faculty Owner',
        ])))->toThrow(RuntimeException::class, 'could not be reopened');
        expect($pagination->path)->not->toBeNull()
            ->and(is_file($pagination->path))->toBeFalse();
    } finally {
        if ($pagination->path !== null && is_file($pagination->path)) {
            unlink($pagination->path);
        }
    }
});

test('page numbering preserves freshly edited footer text and document settings', function () {
    $path = tempnam(sys_get_temp_dir(), 'pagination-test-');
    copy(dirname(__DIR__, 2).'/resources/documents/BatStateU-FO-RES-02-Line-Item-Budget.docx', $path);
    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();

    try {
        $footer = $archive->getFromName('word/footer1.xml');
        $settings = $archive->getFromName('word/settings.xml');
        $footer = str_replace('</w:ftr>', '<w:p><w:r><w:t>Updated Version 3 project title</w:t></w:r></w:p></w:ftr>', $footer);
        $settings = str_replace('</w:settings>', '<w:doNotAutoHyphenate/></w:settings>', $settings);
        expect($archive->addFromString('word/footer1.xml', $footer))->toBeTrue()
            ->and($archive->addFromString('word/settings.xml', $settings))->toBeTrue();

        (new WordDocumentPaginationService)->addPageNumbers($archive);
        expect($archive->close())->toBeTrue()
            ->and($archive->open($path))->toBeTrue();
        $footer = $archive->getFromName('word/footer1.xml');
        $settings = $archive->getFromName('word/settings.xml');

        expect($footer)->toContain('Updated Version 3 project title', 'PAGE', 'NUMPAGES')
            ->and($settings)->toContain('doNotAutoHyphenate', 'updateFields');
    } finally {
        $archive->close();
        unlink($path);
    }
});
