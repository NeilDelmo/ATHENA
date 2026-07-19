<?php

use App\Services\LibreOfficeDocumentPdfConverter;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('it converts a Word document to PDF through an isolated LibreOffice process', function () {
    $conversionDirectory = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$conversionDirectory) {
        expect($process->command)->toBeArray();

        $outDirectoryIndex = array_search('--outdir', $process->command, true);
        expect($outDirectoryIndex)->not->toBeFalse();
        $conversionDirectory = $process->command[$outDirectoryIndex + 1];
        File::put($conversionDirectory.DIRECTORY_SEPARATOR.'source.pdf', "%PDF-1.7\nconverted");

        return Process::result();
    });

    $pdf = (new LibreOfficeDocumentPdfConverter)->convertDocx('word document contents');

    expect($pdf)->toBe("%PDF-1.7\nconverted")
        ->and($conversionDirectory)->not->toBeNull()
        ->and(File::exists($conversionDirectory))->toBeFalse();

    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 120
        && is_array($process->command)
        && in_array('--headless', $process->command, true)
        && in_array('pdf:writer_pdf_Export', $process->command, true));
});
