<?php

use App\Services\LibreOfficeDocumentPdfConverter;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('it converts supported office documents to PDF through an isolated LibreOffice process', function (
    string $method,
    string $extension,
    string $filter,
) {
    $conversionDirectory = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$conversionDirectory, $extension) {
        expect($process->command)->toBeArray();

        $outDirectoryIndex = array_search('--outdir', $process->command, true);
        expect($outDirectoryIndex)->not->toBeFalse();
        $conversionDirectory = $process->command[$outDirectoryIndex + 1];
        expect($process->command)->toContain($conversionDirectory.DIRECTORY_SEPARATOR.'source.'.$extension);
        File::put($conversionDirectory.DIRECTORY_SEPARATOR.'source.pdf', "%PDF-1.7\nconverted");

        return Process::result();
    });

    $pdf = (new LibreOfficeDocumentPdfConverter)->{$method}('office document contents');

    expect($pdf)->toBe("%PDF-1.7\nconverted")
        ->and($conversionDirectory)->not->toBeNull()
        ->and(File::exists($conversionDirectory))->toBeFalse();

    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 120
        && is_array($process->command)
        && in_array('--headless', $process->command, true)
        && in_array($filter, $process->command, true));
})->with([
    'Word document' => ['convertDocx', 'docx', 'pdf:writer_pdf_Export'],
    'Excel workbook' => ['convertXlsx', 'xlsx', 'pdf:calc_pdf_Export'],
]);
