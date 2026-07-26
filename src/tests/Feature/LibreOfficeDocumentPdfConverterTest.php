<?php

use App\Services\LibreOfficeDocumentPdfConverter;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

test('it converts supported office documents to PDF through an isolated LibreOffice process', function (
    string $method,
    string $extension,
    string $filter,
) {
    $conversionDirectory = null;
    $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'athena-pdf-conversions-'.Str::uuid();
    config(['document_pdf.temporary_directory' => $temporaryRoot]);
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$conversionDirectory, $extension) {
        expect($process->command)->toBeArray();

        $outDirectoryIndex = array_search('--outdir', $process->command, true);
        expect($outDirectoryIndex)->not->toBeFalse();
        $conversionDirectory = $process->command[$outDirectoryIndex + 1];
        expect($process->command[array_key_last($process->command)])
            ->toEndWith('/source.'.$extension);
        File::put($conversionDirectory.DIRECTORY_SEPARATOR.'source.pdf', "%PDF-1.7\nconverted");

        return Process::result();
    });

    $pdf = app(LibreOfficeDocumentPdfConverter::class)->{$method}('office document contents');

    expect($pdf)->toBe("%PDF-1.7\nconverted")
        ->and($conversionDirectory)->not->toBeNull()
        ->and(File::exists($conversionDirectory))->toBeFalse();

    File::deleteDirectory($temporaryRoot);

    Process::assertRan(fn (PendingProcess $process): bool => $process->timeout === 120
        && is_array($process->command)
        && in_array('--headless', $process->command, true)
        && in_array($filter, $process->command, true));
})->with([
    'Word document' => ['convertDocx', 'docx', 'pdf:writer_pdf_Export'],
    'Excel workbook' => ['convertXlsx', 'xlsx', 'pdf:calc_pdf_Export'],
]);

test('it uses the synchronous LibreOffice console launcher on Windows', function () {
    if (PHP_OS_FAMILY !== 'Windows') {
        $this->markTestSkipped('LibreOffice uses a separate console launcher on Windows only.');
    }

    $binaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'athena-libreoffice-binary-'.Str::uuid();
    $configuredBinary = $binaryDirectory.DIRECTORY_SEPARATOR.'soffice.exe';
    $invokedBinary = null;

    File::makeDirectory($binaryDirectory);
    File::put($configuredBinary, '');
    File::put($binaryDirectory.DIRECTORY_SEPARATOR.'soffice.com', '');
    config(['document_pdf.libreoffice_binary' => $configuredBinary]);

    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$invokedBinary) {
        expect($process->command)->toBeArray();
        $invokedBinary = $process->command[0];
        expect($process->options)->not->toHaveKey('create_new_console');

        $outDirectoryIndex = array_search('--outdir', $process->command, true);
        expect($outDirectoryIndex)->not->toBeFalse();
        $conversionDirectory = $process->command[$outDirectoryIndex + 1];
        File::put($conversionDirectory.DIRECTORY_SEPARATOR.'source.pdf', "%PDF-1.7\nconverted");

        return Process::result();
    });

    try {
        app(LibreOfficeDocumentPdfConverter::class)->convertDocx('office document contents');
    } finally {
        File::deleteDirectory($binaryDirectory);
    }

    expect($invokedBinary)->toBe($binaryDirectory.DIRECTORY_SEPARATOR.'soffice.com');
});

test('it preserves LibreOffice process diagnostics in the reported exception chain', function () {
    Process::preventStrayProcesses();
    Process::fake([
        '*' => Process::result(
            output: 'No export was created.',
            errorOutput: 'The user profile could not be opened.',
            exitCode: 1,
        ),
    ]);

    try {
        app(LibreOfficeDocumentPdfConverter::class)->convertDocx('office document contents');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe(
            'PDF conversion is unavailable. Install LibreOffice and configure the PDF converter binary correctly.',
        )->and($exception->getPrevious()?->getMessage())
            ->toContain('exit code: 1')
            ->toContain('output: No export was created.')
            ->toContain('error output: The user profile could not be opened.')
            ->toContain('PDF created: no.');

        return;
    }

    $this->fail('The failed LibreOffice process did not throw an exception.');
});

test('it delegates web-runtime conversion to the PHP CLI with an application-owned temp directory', function () {
    $temporaryRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        .DIRECTORY_SEPARATOR.'athena-pdf-conversions-'.Str::uuid();
    $phpTemporaryDirectory = $temporaryRoot.DIRECTORY_SEPARATOR.'php-cli';
    $phpBinary = $temporaryRoot.DIRECTORY_SEPARATOR.'php.exe';
    config([
        'document_pdf.delegate_to_php_cli' => true,
        'document_pdf.php_cli_binary' => $phpBinary,
        'document_pdf.php_cli_temporary_directory' => $phpTemporaryDirectory,
        'document_pdf.temporary_directory' => $temporaryRoot.DIRECTORY_SEPARATOR.'documents',
    ]);
    $processTemporaryDirectory = null;

    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (
        $phpBinary,
        $phpTemporaryDirectory,
        &$processTemporaryDirectory,
    ) {
        $processTemporaryDirectory = $process->environment['TEMP'];

        expect($process->command)->toBeArray()
            ->and($process->command[0])->toBe($phpBinary)
            ->and($process->command[1])->toBe(base_path('artisan'))
            ->and($process->command[2])->toBe('document-pdf:convert')
            ->and($process->path)->toBe(base_path())
            ->and($process->options)->not->toHaveKey('create_new_console')
            ->and($process->environment)->toMatchArray([
                'TEMP' => $processTemporaryDirectory,
                'TMP' => $processTemporaryDirectory,
            ])
            ->and(dirname($processTemporaryDirectory))->toBe($phpTemporaryDirectory)
            ->and(File::isDirectory($processTemporaryDirectory))->toBeTrue();

        $outputDirectory = $process->command[4];
        File::put($outputDirectory.DIRECTORY_SEPARATOR.'source.pdf', "%PDF-1.7\ndelegated");

        return Process::result();
    });

    try {
        $pdf = app(LibreOfficeDocumentPdfConverter::class)->convertDocx('office document contents');
    } finally {
        File::deleteDirectory($temporaryRoot);
    }

    expect($pdf)->toBe("%PDF-1.7\ndelegated")
        ->and($processTemporaryDirectory)->not->toBeNull()
        ->and(File::exists($processTemporaryDirectory))->toBeFalse();
});
