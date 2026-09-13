<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class InitialScreeningNarrativeExtractor
{
    public function extract(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The completed Initial Screening Form could not be read.');
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        $text = match ($extension) {
            'pdf' => $this->pdfText($path),
            'docx' => $this->docxText($path),
            default => throw new RuntimeException('Upload the completed Initial Screening Form as a PDF or DOCX file.'),
        };

        return $this->narrativeEvaluation($text);
    }

    private function pdfText(string $path): string
    {
        try {
            $result = Process::timeout((int) config('research_assistant.document.extraction_timeout'))->run([
                (string) config('research_assistant.document.pdftotext_binary'),
                '-layout',
                '-nopgbrk',
                $path,
                '-',
            ]);

            if ($result->failed()) {
                throw new RuntimeException('The completed Initial Screening Form could not be converted to readable text.');
            }

            return $result->output();
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('PDF text extraction is unavailable on this server. Upload a DOCX copy or enable pdftotext.');
        }
    }

    private function docxText(string $path): string
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('The completed Initial Screening DOCX could not be opened.');
        }

        try {
            $documentXml = $archive->getFromName('word/document.xml');

            if (! is_string($documentXml)) {
                throw new RuntimeException('The completed Initial Screening DOCX has no readable document body.');
            }

            return $this->wordXmlText($documentXml);
        } finally {
            $archive->close();
        }
    }

    private function wordXmlText(string $xml): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('The completed Initial Screening DOCX contains invalid document data.');
        }

        $lines = [];
        $paragraphs = $document->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'p',
        );

        foreach ($paragraphs as $paragraph) {
            $line = '';

            foreach ($paragraph->getElementsByTagNameNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                '*',
            ) as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $line .= match ($node->localName) {
                    't' => $node->textContent,
                    'tab' => "\t",
                    'br', 'cr' => "\n",
                    default => '',
                };
            }

            if (filled(trim($line))) {
                $lines[] = trim($line);
            }
        }

        return implode("\n", $lines);
    }

    private function narrativeEvaluation(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;

        $parts = preg_split('/Narrative\s+Evaluation\s*:/iu', $text, 2);

        if (! is_array($parts) || count($parts) !== 2) {
            throw new RuntimeException('The uploaded paper does not contain the “Narrative Evaluation” section.');
        }

        $ending = preg_split(
            '/(?:Pursuant\s+to\s+Republic\s+Act\s+No\.\s*10173|Prepared\s+by\s*:)/iu',
            $parts[1],
            2,
        );
        $narrative = trim((string) ($ending[0] ?? ''), " \t\n_");
        $narrative = trim(preg_replace('/ *\n */u', "\n", $narrative) ?? $narrative);

        if (Str::length($narrative) < 3) {
            throw new RuntimeException('The “Narrative Evaluation” section is blank or has no selectable text.');
        }

        if (Str::length($narrative) > 5000) {
            throw new RuntimeException('The extracted Narrative Evaluation exceeds 5,000 characters. Shorten it in the completed form and upload it again.');
        }

        return $narrative;
    }
}
