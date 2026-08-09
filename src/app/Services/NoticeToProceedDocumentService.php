<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class NoticeToProceedDocumentService
{
    /** @param array<string, string> $values */
    public function generate(array $values): string
    {
        $templatePath = (string) config('notice_to_proceed.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Notice to Proceed template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-notice-to-proceed-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Notice to Proceed document could not be created.');
        }

        $archive = new ZipArchive;
        $open = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The Notice to Proceed template could not be opened.');
            }

            $open = true;
            $xml = $archive->getFromName('word/document.xml');

            if ($xml === false) {
                throw new RuntimeException('The Notice to Proceed template body is missing.');
            }

            foreach ($values as $key => $value) {
                $replacement = $key === 'RESEARCHER_NAMES'
                    ? $this->multilineText($value)
                    : $this->xmlText($value);
                $xml = str_replace('{{'.$key.'}}', $replacement, $xml);
            }

            if (preg_match('/\{\{[A-Z0-9_]+\}\}/', $xml) === 1) {
                throw new RuntimeException('The Notice to Proceed contains an unresolved field.');
            }

            if (! $archive->addFromString('word/document.xml', $xml)) {
                throw new RuntimeException('The generated Notice to Proceed could not be written.');
            }

            $archive->close();
            $open = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Notice to Proceed could not be read.');
            }

            return $contents;
        } finally {
            if ($open) {
                $archive->close();
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function multilineText(string $value): string
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): string => $this->xmlText($line))
            ->implode('</w:t><w:br/><w:t>');
    }
}
