<?php

namespace App\Console\Commands;

use App\Services\LibreOfficeProcess;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('document-pdf:convert {sourcePath} {outputDirectory} {profilePath} {filter}')]
#[Description('Convert an internal Office document to PDF through LibreOffice')]
class ConvertOfficeDocumentToPdf extends Command
{
    public function handle(LibreOfficeProcess $libreOffice): int
    {
        $result = $libreOffice->run(
            (string) $this->argument('sourcePath'),
            (string) $this->argument('outputDirectory'),
            (string) $this->argument('profilePath'),
            (string) $this->argument('filter'),
        );

        if ($result->output() !== '') {
            $this->output->write($result->output());
        }

        if ($result->errorOutput() !== '') {
            fwrite(STDERR, $result->errorOutput());
        }

        return $result->exitCode();
    }
}
