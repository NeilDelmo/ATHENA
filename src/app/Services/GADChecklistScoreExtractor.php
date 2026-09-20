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

class GADChecklistScoreExtractor
{
    /**
     * @return array{gad_score: float, gad_rating: string, gad_interpretation: string, gad_outcome: string, gad_signature_detected: bool, gad_signature_detection_method: string|null}
     */
    public function extract(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The completed GAD Checklist could not be read.');
        }

        $extension = Str::lower($file->getClientOriginalExtension());
        [$text, $signatureEvidence] = match ($extension) {
            'pdf' => $this->pdfData($path),
            'docx' => $this->docxData($path),
            default => throw new RuntimeException('Upload the completed GAD Checklist as a searchable PDF or DOCX file.'),
        };
        $score = $this->finalGadScore($text);

        return [
            'gad_score' => $score,
            ...$this->interpretationFor($score),
            'gad_signature_detected' => $signatureEvidence !== null,
            'gad_signature_detection_method' => $signatureEvidence,
        ];
    }

    /**
     * @return array{gad_rating: string, gad_interpretation: string, gad_outcome: string}
     */
    public function interpretationFor(float $score): array
    {
        if ($score < 0 || $score > 20) {
            throw new RuntimeException('The extracted Total GAD Score must be between 0 and 20.');
        }

        return match (true) {
            $score < 4 => [
                'gad_rating' => 'GAD-invisible',
                'gad_interpretation' => 'GAD is invisible in the project (proposal is returned).',
                'gad_outcome' => 'returned',
            ],
            $score < 8 => [
                'gad_rating' => 'Promising GAD prospects',
                'gad_interpretation' => 'Proposed project has promising GAD prospects (proposal earns a "conditional pass," pending identification of gender issues and strategies and activities to address these, and inclusion of the collection of sex-disaggregated data in the monitoring and evaluation plan).',
                'gad_outcome' => 'conditional_pass',
            ],
            $score < 15 => [
                'gad_rating' => 'Gender-sensitive',
                'gad_interpretation' => 'Proposed project is gender-sensitive (proposal passes the GAD test).',
                'gad_outcome' => 'passed',
            ],
            default => [
                'gad_rating' => 'Gender-responsive',
                'gad_interpretation' => 'Proposed project is gender-responsive (proponent is commended).',
                'gad_outcome' => 'commended',
            ],
        };
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
                throw new RuntimeException('The completed GAD Checklist could not be converted to readable text.');
            }

            return $result->output();
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('PDF text extraction is unavailable on this server. Upload a DOCX copy or enable pdftotext.');
        }
    }

    /** @return array{string, string|null} */
    private function pdfData(string $path): array
    {
        $text = $this->pdfText($path);
        $rawPdf = file_get_contents($path);

        if (is_string($rawPdf) && preg_match('/\/ByteRange\s*\[/i', $rawPdf) === 1) {
            return [$text, 'pdf_digital_signature'];
        }

        return [$text, $this->signatureMarkerInText($this->signatureTextRegion($text))
            ? 'electronic_signature_marker'
            : null];
    }

    /** @return array{string, string|null} */
    private function docxData(string $path): array
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException('The completed GAD Checklist DOCX could not be opened.');
        }

        try {
            $documentXml = $archive->getFromName('word/document.xml');

            if (! is_string($documentXml)) {
                throw new RuntimeException('The completed GAD Checklist DOCX has no readable document body.');
            }

            $document = $this->wordDocument($documentXml);
            $text = $this->wordDocumentText($document);

            return [$text, $this->wordSignatureEvidence($document)];
        } finally {
            $archive->close();
        }
    }

    private function wordDocument(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new RuntimeException('The completed GAD Checklist DOCX contains invalid document data.');
        }

        return $document;
    }

    private function wordDocumentText(DOMDocument $document): string
    {
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

    private function wordSignatureEvidence(DOMDocument $document): ?string
    {
        $paragraphs = $document->getElementsByTagNameNS(
            'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'p',
        );

        foreach ($paragraphs as $index => $paragraph) {
            if (! Str::contains(Str::lower($paragraph->textContent), 'checked and verified by')) {
                continue;
            }

            $signatureText = '';

            for ($offset = 1; $offset <= 4; $offset++) {
                $candidate = $paragraphs->item($index + $offset);

                if (! $candidate instanceof DOMNode) {
                    break;
                }

                foreach ($candidate->getElementsByTagName('*') as $node) {
                    if (in_array($node->localName, ['drawing', 'pict', 'object', 'shape', 'imagedata'], true)) {
                        return 'embedded_signature_object';
                    }
                }

                $signatureText .= ' '.$candidate->textContent;
            }

            if ($this->signatureMarkerInText($signatureText)) {
                return 'electronic_signature_marker';
            }

            return null;
        }

        return $this->signatureMarkerInText($this->signatureTextRegion($this->wordDocumentText($document)))
            ? 'electronic_signature_marker'
            : null;
    }

    private function signatureTextRegion(string $text): string
    {
        $parts = preg_split('/checked\s+and\s+verified\s+by\s*:?/iu', $text, 2);

        return is_array($parts) && count($parts) === 2
            ? Str::substr($parts[1], 0, 600)
            : '';
    }

    private function signatureMarkerInText(string $text): bool
    {
        return preg_match('/(?:\/s\/|\bsgd\.?\b|electronically\s+signed|digitally\s+signed|signed\s+by)/iu', $text) === 1;
    }

    private function finalGadScore(string $text): float
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\0", "\u{00A0}"], ["\n", "\n", '', ' '], $text);
        $text = preg_replace('/[\t ]+/u', ' ', $text) ?? $text;
        $parts = preg_split(
            '/TOTAL\s+GAD\s+SCORE\s+FOR\s+THE\s+PROJECT\s+IDENTIFICATION/iu',
            $text,
            2,
        );

        if (! is_array($parts) || count($parts) !== 2) {
            throw new RuntimeException('The uploaded paper does not contain the final Total GAD Score for the Project Identification and Design Stages. Upload a searchable PDF or DOCX copy.');
        }

        $beforeInterpretation = preg_split('/Interpretation\s+of\s+GAD\s+Scores/iu', $parts[1], 2);
        $scoreRegion = Str::substr((string) ($beforeInterpretation[0] ?? ''), 0, 300);

        if (! preg_match('/(?<![\d.])(-?\d+(?:\.\d+)?)(?![\d.])/u', $scoreRegion, $matches)) {
            throw new RuntimeException('The final Total GAD Score is blank or has no selectable text. Upload a searchable PDF or DOCX copy.');
        }

        $score = round((float) $matches[1], 2);

        if ($score < 0 || $score > 20) {
            throw new RuntimeException('The extracted Total GAD Score must be between 0 and 20.');
        }

        return $score;
    }
}
