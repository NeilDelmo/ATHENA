<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LibreOfficeDocumentPdfConverter implements DocumentPdfConverter
{
    public function convertDocx(string $contents): string
    {
        $temporaryDirectory = $this->makeTemporaryDirectory();
        $sourcePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.docx';
        $pdfPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'source.pdf';
        $profilePath = $temporaryDirectory.DIRECTORY_SEPARATOR.'libreoffice-profile';

        try {
            if (File::put($sourcePath, $contents) === false) {
                throw new RuntimeException('A temporary Word document could not be created for PDF conversion.');
            }

            File::makeDirectory($profilePath);
            $result = Process::timeout((int) config('document_pdf.timeout_seconds'))
                ->run([
                    (string) config('document_pdf.libreoffice_binary'),
                    '--headless',
                    '--nologo',
                    '--nodefault',
                    '--nolockcheck',
                    '-env:UserInstallation='.$this->fileUri($profilePath),
                    '--convert-to',
                    'pdf:writer_pdf_Export',
                    '--outdir',
                    $temporaryDirectory,
                    $sourcePath,
                ]);

            if ($result->failed() || ! File::isFile($pdfPath)) {
                throw new RuntimeException('LibreOffice could not convert the generated paper to PDF.');
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

    private function makeTemporaryDirectory(): string
    {
        $temporaryDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.'athena-pdf-'.Str::uuid();

        if (! File::makeDirectory($temporaryDirectory, 0700)) {
            throw new RuntimeException('A temporary directory could not be created for PDF conversion.');
        }

        return $temporaryDirectory;
    }

    private function fileUri(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        return PHP_OS_FAMILY === 'Windows'
            ? 'file:///'.ltrim($normalizedPath, '/')
            : 'file://'.$normalizedPath;
    }
}
