<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class LibreOfficeProcess
{
    public function run(
        string $sourcePath,
        string $outputDirectory,
        string $profilePath,
        string $filter,
    ): ProcessResult {
        $binary = $this->binary();
        $process = Process::timeout((int) config('document_pdf.timeout_seconds'));

        if (Str::contains($binary, ['/', '\\'])) {
            $process->path(dirname($binary));
        }

        return $process->run([
            $binary,
            '--headless',
            '--nologo',
            '--nodefault',
            '--nolockcheck',
            '-env:UserInstallation='.$this->fileUri($profilePath),
            '--convert-to',
            $filter,
            '--outdir',
            $outputDirectory,
            $this->fileUri($sourcePath),
        ]);
    }

    public function binary(): string
    {
        $configuredBinary = (string) config('document_pdf.libreoffice_binary');

        if (PHP_OS_FAMILY !== 'Windows' || ! Str::endsWith(Str::lower($configuredBinary), '.exe')) {
            return $configuredBinary;
        }

        return Str::substr($configuredBinary, 0, -4).'.com';
    }

    private function fileUri(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', $path);

        return PHP_OS_FAMILY === 'Windows'
            ? 'file:///'.ltrim($normalizedPath, '/')
            : 'file://'.$normalizedPath;
    }
}
