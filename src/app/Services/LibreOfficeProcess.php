<?php

namespace App\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\InputStream;

class LibreOfficeProcess
{
    public function run(
        string $sourcePath,
        string $outputDirectory,
        string $profilePath,
        string $filter,
    ): ProcessResult {
        return $this->runMany([$sourcePath], $outputDirectory, $profilePath, $filter);
    }

    /** @param list<string> $sourcePaths */
    public function runMany(
        array $sourcePaths,
        string $outputDirectory,
        string $profilePath,
        string $filter,
    ): ProcessResult {
        return app(LibreOfficePdfWorker::class)->convert(
            $sourcePaths, $outputDirectory, $filter,
            fn (): ProcessResult => $this->runStandalone($sourcePaths, $outputDirectory, $profilePath, $filter),
        );
    }

    /** @param list<string> $sourcePaths */
    private function runStandalone(array $sourcePaths, string $outputDirectory, string $profilePath, string $filter): ProcessResult
    {
        $binary = $this->binary();
        $process = Process::timeout((int) config('document_pdf.timeout_seconds'));

        if (Str::contains($binary, ['/', '\\'])) {
            $process->path(dirname($binary));
        }

        $command = [
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
            ...array_map(fn (string $path): string => $this->fileUri($path), $sourcePaths),
        ];
        if (PHP_OS_FAMILY === 'Linux') {
            $python = config('document_pdf.libreoffice_python_binary');
            if (! is_string($python) || ! is_file($python)) {
                $finder = new ExecutableFinder;
                $python = $finder->find('python3') ?? $finder->find('python');
            }
            // shortcut: hosts without Python keep launcher-only cleanup; install Python for process-group cleanup.
            if ($python !== null) {
                // Symfony's timeout force-kills after 1ms; let the supervisor clean its group before returning.
                $process->forever()->input(new InputStream);
                array_unshift($command, $python, base_path('scripts/libreoffice-worker.py'), '--once', (string) config('document_pdf.timeout_seconds'));
            }
        }

        return $process->run($command);
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
