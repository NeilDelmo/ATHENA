<?php

use App\Services\CurriculumVitaeDocumentService;
use App\Support\CurriculumVitaeData;
use App\Support\CurriculumVitaeRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('CV cellphone rules validate local numbers in complete and draft CVs', function (?string $cellphone, bool $valid) {
    foreach ([false, true] as $allowDraft) {
        $validator = Validator::make(
            ['people' => [['first_name' => 'Faculty', 'last_name' => 'Researcher', 'cellphone' => $cellphone]]],
            CurriculumVitaeRules::rules($allowDraft),
        );

        expect($validator->passes())->toBe($valid);
    }
})->with([
    'local number' => ['09123456789', true],
    'blank optional number' => ['', true],
    'null optional number' => [null, true],
    'short number' => ['0912345678', false],
    'extra digit' => ['091234567890', false],
    'extra leading zero' => ['009123456789', false],
    'wrong prefix' => ['08123456789', false],
    'non-digit' => ['0912345678A', false],
]);

test('CV preview and Word output replace the local zero with the country code', function () {
    $data = CurriculumVitaeData::fromValidated(['people' => [['first_name' => 'Faculty', 'last_name' => 'Researcher', 'cellphone' => '09123456789']]]);
    $this->withoutVite();
    $this->view('faculty.curriculum-vitae.preview', ['curriculumVitae' => $data])
        ->assertSee('(+63) 9123456789')
        ->assertDontSee('(+63) 09123456789');

    $path = tempnam(sys_get_temp_dir(), 'cv-contact-');

    try {
        file_put_contents($path, app(CurriculumVitaeDocumentService::class)->generate($data));
        $archive = new ZipArchive;
        expect($archive->open($path))->toBeTrue();
        $document = new DOMDocument;
        $document->loadXML($archive->getFromName('word/document.xml'), LIBXML_NONET);
        $archive->close();

        expect($document->textContent)->toContain('(+63) 9123456789')->not->toContain('(+63) 09123456789');
    } finally {
        unlink($path);
    }
});
