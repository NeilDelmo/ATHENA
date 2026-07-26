<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LibreOfficeDocumentPdfConverter implements DocumentPdfConverter
{
    public function __construct(private readonly LibreOfficeProcess $libreOffice) {}

    public function convertDocx(string $contents): string
    {
        return $this->convert($contents, 'docx', 'pdf:writer_pdf_Export');
    }

    public function convertXlsx(string $contents): string
    {
        return $this->convert($contents, 'xlsx', 'pdf:calc_pdf_Export');
    }

    private function convert(string $contents, string $extension, string $filter): string
    {
        $temporaryDirectory = $this->makeTemporaryDirectory();
        $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.'.$extension;
        $pdfPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.pdf';
        $profilePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'libreoffice-profile';

        try {
            if (File::put($sourcePath, $contents) === false) {
                throw new RuntimeException('A temporary document could not be created for PDF conversion.');
            }

            File::makeDirectory($profilePath);
            $binary = $this->libreOffice->binary();
            $result = $this->runConversion(
                $sourcePath,
                $temporaryDirectory,
                $profilePath,
                $filter,
            );

            if ($result->failed() || ! File::isFile($pdfPath)) {
                throw new RuntimeException(sprintf(
                    'LibreOffice could not convert the generated paper to PDF. Binary: %s; exit code: %s; output: %s; error output: %s; PDF created: %s.',
                    $binary,
                    $result->exitCode(),
                    Str::limit(Str::squish($result->output()), 1000),
                    Str::limit(Str::squish($result->errorOutput()), 1000),
                    File::isFile($pdfPath) ? 'yes' : 'no',
                ));
            }

            $pdfContents = File::get($pdfPath);

            if (! Str::startsWith($pdfContents, '%PDF-')) {
                throw new RuntimeException('The generated paper did not produce a valid PDF file.');
            }

            return $pdfContents;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'PDF conversion is unavailable. Install LibreOffice and configure the PDF converter binary correctly.',
                previous: $exception,
            );
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function runConversion(
        string $sourcePath,
        string $outputDirectory,
        string $profilePath,
        string $filter,
    ): ProcessResult {
        if (! config('document_pdf.delegate_to_php_cli')) {
            return $this->libreOffice->run(
                $sourcePath,
                $outputDirectory,
                $profilePath,
                $filter,
            );
        }

        $phpTemporaryRoot = rtrim(
            (string) config('document_pdf.php_cli_temporary_directory'),
            DIRECTORY_SEPARATOR,
        );
        File::ensureDirectoryExists($phpTemporaryRoot, 0700);
        $phpTemporaryDirectory = $phpTemporaryRoot.DIRECTORY_SEPARATOR.'athena-process-'.Str::uuid();

        if (! File::makeDirectory($phpTemporaryDirectory, 0700)) {
            throw new RuntimeException('A temporary PHP CLI directory could not be created for PDF conversion.');
        }

        try {
            $process = Process::timeout((int) config('document_pdf.timeout_seconds'))
                ->path(base_path())
                ->env([
                    'TEMP' => $phpTemporaryDirectory,
                    'TMP' => $phpTemporaryDirectory,
                ]);

            return $process->run([
                (string) config('document_pdf.php_cli_binary'),
                base_path('artisan'),
                'document-pdf:convert',
                $sourcePath,
                $outputDirectory,
                $profilePath,
                $filter,
                '--no-interaction',
            ]);
        } finally {
            File::deleteDirectory($phpTemporaryDirectory);
        }
    }

    private function makeTemporaryDirectory(): string
    {
        $temporaryRoot = rtrim(
            (string) config('document_pdf.temporary_directory', sys_get_temp_dir()),
            DIRECTORY_SEPARATOR,
        );
        File::ensureDirectoryExists($temporaryRoot, 0700);
        $temporaryDirectory = $temporaryRoot.DIRECTORY_SEPARATOR.'athena-pdf-'.Str::uuid();

        if (! File::makeDirectory($temporaryDirectory, 0700)) {
            throw new RuntimeException('A temporary directory could not be created for PDF conversion.');
        }

        return $temporaryDirectory;
    }
}
