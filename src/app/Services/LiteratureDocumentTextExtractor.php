<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class LiteratureDocumentTextExtractor
{
    /** @return array{pages: list<array{number: int, text: string}>, coverage: array<string, mixed>, notice: string} */
    public function extract(string $path): array
    {
        $pageLimit = (int) config('literature.evidence.maximum_pdf_pages');
        $characterLimit = (int) config('literature.evidence.maximum_characters');
        $pages = [];
        $totalPages = null;
        $characters = 0;
        $truncated = false;
        $unreadablePages = [];
        $reason = null;

        if (File::size($path) > (int) config('literature.evidence.maximum_pdf_kilobytes') * 1024) {
            $reason = 'The PDF exceeds the extraction file-size limit.';
        } else {
            try {
                $info = Process::timeout(10)->run([(string) config('literature.evidence.pdfinfo_binary'), $path]);

                if ($info->successful() && preg_match('/^Pages:\s+(\d+)/m', $info->output(), $match) === 1 && (int) $match[1] > 0) {
                    $totalPages = (int) $match[1];
                }
            } catch (Throwable) {
                $totalPages = null;
            }

            try {
                $result = Process::timeout((int) config('literature.evidence.extraction_timeout'))->run([
                    (string) config('literature.full_text.pdftotext_binary'), '-f', '1', '-l', (string) $pageLimit, '-layout', '-enc', 'UTF-8', $path, '-',
                ]);

                if ($result->successful()) {
                    $output = rtrim($result->output(), "\r\n");
                    $pageTexts = explode("\f", $output);

                    if (str_ends_with($output, "\f")) {
                        array_pop($pageTexts);
                    }

                    foreach (array_slice($pageTexts, 0, $pageLimit) as $index => $text) {
                        $text = trim($text);
                        if ($text === '') {
                            $unreadablePages[] = $index + 1;
                        }
                        $remaining = $characterLimit - $characters;
                        if ($remaining <= 0) {
                            $truncated = true;
                            break;
                        }
                        if (Str::length($text) > $remaining) {
                            $text = Str::substr($text, 0, $remaining);
                            $truncated = true;
                        }
                        $characters += Str::length($text);
                        $pages[] = ['number' => $index + 1, 'text' => $text];
                    }

                    $reason = match (true) {
                        $totalPages === null => 'The PDF total page count could not be verified.',
                        $totalPages > $pageLimit => 'The PDF exceeds the '.$pageLimit.'-page extraction limit.',
                        $truncated => 'The PDF exceeds the '.$characterLimit.'-character extraction limit.',
                        count($pages) !== $totalPages => 'Not every PDF page was returned by text extraction.',
                        $unreadablePages !== [] => 'One or more PDF pages have no readable text; scanned or blank pages need a readable source PDF. ATHENA does not perform OCR.',
                        default => null,
                    };
                } else {
                    $reason = 'PDF text extraction failed. The original PDF remains available to read.';
                }
            } catch (Throwable) {
                $reason = 'PDF text extraction is unavailable. The original PDF remains available to read.';
            }
        }

        $complete = $reason === null;

        return [
            'pages' => $pages,
            'coverage' => [
                'total_pages' => $totalPages,
                'extracted_pages' => count($pages),
                'characters' => $characters,
                'page_limit' => $pageLimit,
                'character_limit' => $characterLimit,
                'truncated' => $truncated,
                'unreadable_pages' => $unreadablePages,
                'complete' => $complete,
                'limited' => ! $complete,
                'partial_or_unknown' => ! $complete,
                'reason' => $reason,
            ],
            'notice' => ($complete ? 'All '.$totalPages.' PDF pages were processed without text truncation. ' : $reason.' Whole-paper generation is unavailable; any extracted pages remain reading material. ')
                .'Page numbers follow the source PDF. Check the original paper for tables, figures, scanned content, and extraction errors; processing all extractable text does not verify the paper.',
        ];
    }
}
