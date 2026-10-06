<?php

namespace App\Services;

use App\Contracts\BatchDocumentPdfConverter;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LibreOfficeDocumentPdfConverter implements BatchDocumentPdfConverter
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

    public function convertDocuments(array $documents): array
    {
        $groups = [];

        foreach ($documents as $key => $document) {
            if (! in_array($document['format'], ['docx', 'xlsx'], true)) {
                throw new RuntimeException('The generated paper format cannot be converted to PDF.');
            }

            $groups[$document['format']][$key] = $document['contents'];
        }

        $converted = [];

        foreach ($groups as $format => $contents) {
            $filter = $format === 'xlsx' ? 'pdf:calc_pdf_Export' : 'pdf:writer_pdf_Export';
            $converted = [...$converted, ...$this->convertBatch($contents, $format, $filter)];
        }

        return $converted;
    }

    private function convert(string $contents, string $extension, string $filter): string
    {
        return $this->convertBatch(['source' => $contents], $extension, $filter)['source'];
    }

    /**
     * @param  array<string, string>  $documents
     * @return array<string, string>
     */
    private function convertBatch(array $documents, string $extension, string $filter): array
    {
        $temporaryDirectory = $this->makeTemporaryDirectory();
        $profilePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'libreoffice-profile';

        try {
            $sourcePaths = [];
            $pdfPaths = [];

            foreach ($documents as $key => $contents) {
                $basename = count($documents) === 1 ? 'source' : 'source-'.count($sourcePaths);
                $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.$basename.'.'.$extension;

                if (File::put($sourcePath, $contents) === false) {
                    throw new RuntimeException('A temporary document could not be created for PDF conversion.');
                }

                $sourcePaths[] = $sourcePath;
                $pdfPaths[$key] = $temporaryDirectory.DIRECTORY_SEPARATOR.$basename.'.pdf';
            }

            File::makeDirectory($profilePath);
            $binary = $this->libreOffice->binary();
            $result = $this->runConversion(
                $sourcePaths,
                $temporaryDirectory,
                $profilePath,
                $filter,
            );

            $converted = [];

            foreach ($pdfPaths as $key => $pdfPath) {
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

                $converted[$key] = $pdfContents;
            }

            return $converted;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'PDF conversion is unavailable. Install LibreOffice and configure the PDF converter binary correctly.',
                previous: $exception,
            );
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    /** @param list<string> $sourcePaths */
    private function runConversion(
        array $sourcePaths,
        string $outputDirectory,
        string $profilePath,
        string $filter,
    ): ProcessResult {
        if (! config('document_pdf.delegate_to_php_cli')) {
            return $this->libreOffice->runMany(
                $sourcePaths,
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
                $sourcePaths[0],
                $outputDirectory,
                $profilePath,
                $filter,
                ...array_slice($sourcePaths, 1),
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
