<?php

use App\Services\InitialScreeningNarrativeExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Process::preventStrayProcesses();
});

test('screening PDF extraction uses the configured executable path and reads only the evaluation', function () {
    $binary = 'C:/Program Files/PDF Tools/pdftotext.exe';
    config(['research_assistant.document.pdftotext_binary' => $binary]);
    Process::fake(function (PendingProcess $process) use ($binary) {
        expect($process->command[0])->toBe($binary);

        return Process::result(output: "Initial Screening Form\nNarrative Evaluation:\nThe methodology is suitable for endorsement.\nPrepared by: Dr. Santos");
    });

    expect(app(InitialScreeningNarrativeExtractor::class)->extract(
        UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
    ))->toBe('The methodology is suitable for endorsement.');
});

test('screening PDF errors distinguish missing converter from unreadable or protected documents', function (int $exitCode, string $errorOutput, string $expectedMessage) {
    Process::fake([Process::result(errorOutput: $errorOutput, exitCode: $exitCode)]);

    expect(fn () => app(InitialScreeningNarrativeExtractor::class)->extract(
        UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
    ))->toThrow(RuntimeException::class, $expectedMessage);
})->with([
    'Windows executable missing' => [1, "'pdftotext' is not recognized as an internal or external command", 'PDFTOTEXT_BINARY'],
    'Unix executable missing' => [127, 'pdftotext: command not found', 'PDFTOTEXT_BINARY'],
    'executable cannot run' => [126, 'Permission denied', 'PDFTOTEXT_BINARY'],
    'configured directory missing' => [1, 'The system cannot find the path specified.', 'PDFTOTEXT_BINARY'],
    'password-protected file' => [1, 'Command Line Error: Incorrect password', 'password-protected'],
    'PDF permission restriction' => [3, 'Copying of text from this document is not allowed.', 'blocks text extraction'],
    'invalid PDF' => [1, 'Syntax Error: Could not read xref table', 'could not be read'],
    'scanned PDF' => [0, '', 'no selectable text'],
]);

test('screening PDF process launch failures identify server configuration', function () {
    Process::fake(fn () => throw new RuntimeException('The process could not be launched.'));

    expect(fn () => app(InitialScreeningNarrativeExtractor::class)->extract(
        UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
    ))->toThrow(RuntimeException::class, 'PDFTOTEXT_BINARY');
});

test('screening DOCX extraction works without launching a PDF converter', function () {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-screening-docx-');
    $archive = new ZipArchive;

    try {
        $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('word/document.xml', <<<'XML'
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:r><w:t>Narrative Evaluation:</w:t></w:r></w:p>
<w:p><w:r><w:t>The project can proceed to presentation.</w:t><w:br/><w:t>Include the recruitment criteria.</w:t></w:r></w:p>
<w:p><w:r><w:t>Explain the consent procedure.</w:t></w:r></w:p>
<w:p><w:r><w:t>Prepared by: Dr. Santos</w:t></w:r></w:p>
</w:body></w:document>
XML);
        $archive->close();
        $file = UploadedFile::fake()->createWithContent('screening.docx', file_get_contents($temporaryPath));

        expect(app(InitialScreeningNarrativeExtractor::class)->extract($file))->toBe("The project can proceed to presentation.\nInclude the recruitment criteria.\nExplain the consent procedure.");
        Process::assertNothingRan();
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
});

test('screening PDF extraction preserves wrapped lines and paragraphs across page breaks', function () {
    Process::fake([Process::result(output: "Narrative\nEvaluation:\nClarify the sampling plan.\nInclude the recruitment criteria.\n\n\fComments continued on the next page.\nPrepared by: Dr. Santos")]);

    expect(app(InitialScreeningNarrativeExtractor::class)->extract(
        UploadedFile::fake()->create('screening.pdf', 10, 'application/pdf'),
    ))->toBe("Clarify the sampling plan.\nInclude the recruitment criteria.\n\n\nComments continued on the next page.");
});
