<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
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
        $binary = (string) config('research_assistant.document.pdftotext_binary');
        $unavailableMessage = 'PDF text extraction is unavailable on this server. Ask the administrator to configure pdftotext (PDFTOTEXT_BINARY), or upload the completed DOCX form.';

        try {
            $result = Process::timeout((int) config('research_assistant.document.extraction_timeout'))->run([
                $binary,
                '-layout',
                '-nopgbrk',
                $path,
                '-',
            ]);
        } catch (ProcessTimedOutException $exception) {
            Log::warning('Initial Screening PDF text extraction timed out.', ['binary' => $binary]);

            throw new RuntimeException('Reading the Initial Screening PDF took too long. Try again with a smaller PDF or upload the completed DOCX form.', previous: $exception);
        } catch (Throwable $exception) {
            Log::warning('Initial Screening PDF text extraction could not start.', ['binary' => $binary, 'error' => $exception->getMessage()]);

            throw new RuntimeException($unavailableMessage, previous: $exception);
        }

        if ($result->failed()) {
            $errorOutput = Str::lower($result->errorOutput());
            Log::warning('Initial Screening PDF text extraction failed.', [
                'binary' => $binary,
                'exit_code' => $result->exitCode(),
                'error_output' => Str::limit($result->errorOutput(), 1000),
            ]);

            if (in_array($result->exitCode(), [126, 127, 9009], true)
                || Str::contains($errorOutput, [
                    'is not recognized', 'command not found', 'not found as an executable',
                    'the system cannot find the path specified', 'the system cannot find the file specified',
                ])) {
                throw new RuntimeException($unavailableMessage);
            }

            if ($result->exitCode() === 3 || Str::contains($errorOutput, ['incorrect password', 'permission error', 'encrypted'])) {
                throw new RuntimeException('The Initial Screening PDF is password-protected or blocks text extraction. Upload an unlocked PDF with selectable text, or the completed DOCX form.');
            }

            throw new RuntimeException('The Initial Screening PDF could not be read. Re-export the completed form as a PDF with selectable text, or upload its DOCX copy.');
        }

        if (trim($result->output()) === '') {
            throw new RuntimeException('The Initial Screening PDF has no selectable text. For a photo or scanned form, upload the completed DOCX form or a PDF processed with text recognition (OCR).');
        }

        return $result->output();
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
        $text = str_replace(["\r\n", "\r", "\f", "\0"], ["\n", "\n", "\n", ''], $text);
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
