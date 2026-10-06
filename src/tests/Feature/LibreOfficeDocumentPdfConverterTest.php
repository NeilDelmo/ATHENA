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

test('batch conversion groups office formats and preserves each document through the web and CLI runtimes', function (bool $delegate) {
    config(['document_pdf.delegate_to_php_cli' => $delegate]);
    $directories = [];
    $filters = [];
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use ($delegate, &$directories, &$filters) {
        $command = $process->command;
        if ($delegate) {
            expect($command[2])->toBe('document-pdf:convert');
            $outputDirectory = $command[4];
            $sources = [$command[3], ...array_slice($command, 7, -1)];
            $filters[] = $command[6];
            $directories[] = $process->environment['TEMP'];
        } else {
            $outputIndex = array_search('--outdir', $command, true);
            $outputDirectory = $command[$outputIndex + 1];
            $sources = glob($outputDirectory.DIRECTORY_SEPARATOR.'*.docx')
                ?: glob($outputDirectory.DIRECTORY_SEPARATOR.'*.xlsx');
            expect(array_slice($command, $outputIndex + 2))->toHaveCount(count($sources));
            $filters[] = $command[$outputIndex - 1];
        }
        $directories[] = $outputDirectory;
        foreach ($sources as $source) {
            File::put($outputDirectory.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf',
                "%PDF-1.7\n".File::get($source));
        }

        return Process::result();
    });

    $pdfs = app(LibreOfficeDocumentPdfConverter::class)->convertDocuments([
        'work-plan' => ['contents' => 'Work plan', 'format' => 'docx'],
        'expenses' => ['contents' => 'Expense items', 'format' => 'xlsx'],
        'budget' => ['contents' => 'Line item budget', 'format' => 'docx'],
    ]);

    expect($pdfs)->toMatchArray([
        'work-plan' => "%PDF-1.7\nWork plan",
        'expenses' => "%PDF-1.7\nExpense items",
        'budget' => "%PDF-1.7\nLine item budget",
    ])->and($filters)->toBe(['pdf:writer_pdf_Export', 'pdf:calc_pdf_Export']);
    foreach ($directories as $directory) {
        expect(File::exists($directory))->toBeFalse();
    }
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 2);
})->with(['CLI' => false, 'web runtime' => true]);

test('batch conversion rejects incomplete and invalid exports and cleans its temporary directory', function (?string $secondPdf) {
    $directory = null;
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) use (&$directory, $secondPdf) {
        $outputIndex = array_search('--outdir', $process->command, true);
        $directory = $process->command[$outputIndex + 1];
        File::put($directory.DIRECTORY_SEPARATOR.'source-0.pdf', '%PDF-1.7 valid first paper');
        if ($secondPdf !== null) {
            File::put($directory.DIRECTORY_SEPARATOR.'source-1.pdf', $secondPdf);
        }

        return Process::result();
    });

    expect(fn () => app(LibreOfficeDocumentPdfConverter::class)->convertDocuments([
        'first' => ['contents' => 'First paper', 'format' => 'docx'],
        'second' => ['contents' => 'Second paper', 'format' => 'docx'],
    ]))->toThrow(RuntimeException::class)
        ->and($directory)->not->toBeNull()
        ->and(File::exists($directory))->toBeFalse();
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
})->with(['missing output' => null, 'invalid output' => 'Not a PDF']);

test('the conversion command passes additional source files to the same LibreOffice process', function () {
    Process::preventStrayProcesses();
    Process::fake();

    $this->artisan('document-pdf:convert', [
        'sourcePath' => 'first paper.docx',
        'outputDirectory' => 'output',
        'profilePath' => 'profile',
        'filter' => 'pdf:writer_pdf_Export',
        'additionalSources' => ['second paper.docx', 'third paper.docx'],
    ])->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => str_ends_with($process->command[array_key_last($process->command)], '/third paper.docx')
        && count(array_filter($process->command, fn (string $argument): bool => str_ends_with($argument, '.docx'))) === 3);
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
});
