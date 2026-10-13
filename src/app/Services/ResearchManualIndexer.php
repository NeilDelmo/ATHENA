<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class ResearchManualIndexer
{
    private const SCHEMA_VERSION = 1;

    private const PARSER_VERSION = 5;

    /**
     * @return array{schema_version: int, version: string, title: string, source_file: string, page_count: int, unindexed_pages: array<int>, chunks: array<array{id: string, section: string, source_kind: string, page_start: int, page_end: int, content: string}>, reused: bool}
     */
    public function index(string $sourcePath, string $title = 'Approved Research Manual (2023)', bool $force = false, ?string $pagesJsonPath = null): array
    {
        if (! is_file($sourcePath) || file_get_contents($sourcePath, false, null, 0, 5) !== '%PDF-') {
            throw new RuntimeException('Provide an existing PDF file to index.');
        }

        $title = trim($title);

        if ($title === '') {
            throw new RuntimeException('The manual title must not be empty.');
        }

        if ($pagesJsonPath !== null && ! is_file($pagesJsonPath)) {
            throw new RuntimeException('The page-preserving JSON file does not exist.');
        }

        $indexPath = (string) config('research_assistant.manual.index_path', storage_path('app/private/research-manual/index.json'));
        $chunkCharacters = max(300, (int) config('research_assistant.manual.chunk_characters', 1800));
        $version = hash('sha256', json_encode([
            self::SCHEMA_VERSION,
            self::PARSER_VERSION,
            hash_file('sha256', $sourcePath),
            $pagesJsonPath !== null ? hash_file('sha256', $pagesJsonPath) : null,
            $title,
            $chunkCharacters,
        ], JSON_THROW_ON_ERROR));
        $directory = dirname($indexPath);
        File::ensureDirectoryExists($directory);
        $lock = fopen($indexPath.'.lock', 'c');

        if ($lock === false) {
            throw new RuntimeException('The research manual index could not be locked.');
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);

            throw new RuntimeException('The research manual index could not be locked.');
        }

        try {
            $existing = $this->existingIndex($indexPath);

            if (! $force && ($existing['version'] ?? null) === $version && is_file($directory.'/manual.pdf')) {
                return [...$existing, 'reused' => true];
            }

            $pages = $pagesJsonPath !== null ? $this->readPages($pagesJsonPath) : $this->extractPages($sourcePath);
            [$chunks, $unindexedPages] = $this->chunks($pages, $title, $chunkCharacters);

            if ($chunks === []) {
                throw new RuntimeException('The PDF contains no indexable text. Supply page-preserving OCR text using --pages-json.');
            }

            $index = [
                'schema_version' => self::SCHEMA_VERSION,
                'version' => $version,
                'title' => $title,
                'source_file' => 'manual.pdf',
                'page_count' => count($pages),
                'unindexed_pages' => $unindexedPages,
                'chunks' => $chunks,
            ];
            $encoded = json_encode($index, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $temporarySource = $directory.'/manual-'.Str::uuid().'.pdf';

            try {
                if (! File::copy($sourcePath, $temporarySource) || ! File::move($temporarySource, $directory.'/manual.pdf')) {
                    throw new RuntimeException('The research manual PDF could not be saved.');
                }

                File::replace($indexPath, $encoded);
            } finally {
                if (is_file($temporarySource)) {
                    File::delete($temporarySource);
                }
            }

            return [...$index, 'reused' => false];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string, mixed> */
    private function existingIndex(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $index = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

            return is_array($index) ? $index : [];
        } catch (JsonException) {
            return [];
        }
    }

    /** @return array<string> */
    private function extractPages(string $path): array
    {
        $binary = (string) config('research_assistant.manual.pdftotext_binary', config('research_assistant.document.pdftotext_binary', 'pdftotext'));

        try {
            $result = Process::timeout((int) config('research_assistant.manual.extraction_timeout', 120))->run([
                $binary, '-layout', '-enc', 'UTF-8', $path, '-',
            ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('PDF text extraction could not run. Check PDFTOTEXT_BINARY or provide --pages-json.', 0, $exception);
        }

        if (! $result->successful()) {
            throw new RuntimeException('PDF text extraction failed. Check that the document is readable and PDFTOTEXT_BINARY is configured.');
        }

        $text = $result->output();
        $pages = explode("\f", $text);

        if (count($pages) > 1 && trim($pages[array_key_last($pages)]) === '') {
            array_pop($pages);
        }

        return $pages;
    }

    /** @return array<string> */
    private function readPages(string $path): array
    {
        try {
            $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The page-preserving JSON file is invalid.', 0, $exception);
        }

        $data = is_array($data) && isset($data['pages']) ? $data['pages'] : $data;

        if (! is_array($data) || ! array_is_list($data) || $data === []) {
            throw new RuntimeException('Page JSON must contain a nonempty ordered list of pages.');
        }

        $pages = [];

        foreach ($data as $offset => $page) {
            if (is_string($page)) {
                $pages[] = $page;
            } elseif (is_array($page) && ($page['number'] ?? null) === $offset + 1 && is_string($page['text'] ?? null)) {
                $pages[] = $page['text'];
            } else {
                throw new RuntimeException('Page JSON must preserve every PDF page in order with number and text fields.');
            }
        }

        return $pages;
    }

    /**
     * @param  array<string>  $pages
     * @return array{array<array{id: string, section: string, source_kind: string, page_start: int, page_end: int, content: string}>, array<int>}
     */
    private function chunks(array $pages, string $title, int $maximumCharacters): array
    {
        $headers = $this->recurringHeaders($pages);
        $numberedHeadings = $this->numberedHeadings($pages);
        $chunks = [];
        $unindexedPages = [];
        $section = $title;
        $sourceKind = 'policy';
        $current = null;

        foreach ($pages as $offset => $text) {
            $page = $offset + 1;
            $lines = $this->cleanLines($text, $headers);
            $cleanText = implode("\n", $lines);

            if (trim($cleanText) === '') {
                $unindexedPages[] = $page;

                continue;
            }

            if ($this->isContentsPage($cleanText)) {
                continue;
            }

            $paragraph = [];

            foreach ([...$lines, ''] as $line) {
                $heading = $this->isHeading($line, $numberedHeadings, $sourceKind === 'appendix');

                if (($line === '' || $heading) && $paragraph !== []) {
                    $this->appendParagraph(implode("\n", $paragraph), $section, $sourceKind, $page, $maximumCharacters, $current, $chunks);
                    $paragraph = [];
                }

                if ($heading) {
                    $this->flushChunk($current, $chunks);

                    if (in_array(Str::upper(rtrim(Str::squish($line), ':')), ['APPENDICES', 'ATTACHMENTS'], true)) {
                        $sourceKind = 'appendix';
                    }

                    $section = $this->sectionTitle($line, $numberedHeadings);
                    $this->appendParagraph(trim($line), $section, $sourceKind, $page, $maximumCharacters, $current, $chunks);
                } elseif ($line !== '') {
                    $paragraph[] = $line;
                }
            }
        }

        $this->flushChunk($current, $chunks);

        return [$chunks, $unindexedPages];
    }

    /** @param array<string> $pages @return array<string> */
    private function recurringHeaders(array $pages): array
    {
        $occurrences = [];

        foreach ($pages as $page) {
            $lines = array_values(array_filter(explode("\n", str_replace("\r", '', $page)), fn (string $line): bool => trim($line) !== ''));
            $edgeLines = array_unique([...array_slice($lines, 0, 3), ...array_slice($lines, -3)]);

            foreach ($edgeLines as $line) {
                $line = Str::squish($line);

                if (Str::length($line) <= 160 && ! $this->isHeading($line) && ! preg_match('/^(?:\d+|[ivxlcdm]+)$|^\d+(?:\.\d+)+\s/i', $line)) {
                    $occurrences[$line] = ($occurrences[$line] ?? 0) + 1;
                }
            }
        }

        $threshold = max(3, (int) ceil(count($pages) * 0.2));

        return array_keys(array_filter($occurrences, fn (int $count): bool => $count >= $threshold));
    }

    /** @param array<string> $headers @return array<string> */
    private function cleanLines(string $text, array $headers): array
    {
        $lines = explode("\n", str_replace(["\r", "\0"], '', $text));
        $nonemptyOffsets = array_keys(array_filter($lines, fn (string $line): bool => trim($line) !== ''));
        $edgeOffsets = [...array_slice($nonemptyOffsets, 0, 2), ...array_slice($nonemptyOffsets, -3)];

        foreach ($lines as $offset => &$line) {
            $line = trim($line);

            if (in_array(Str::squish($line), $headers, true) || (in_array($offset, $edgeOffsets, true) && preg_match('/^(?:\d+|[ivxlcdm]+)$/i', $line))) {
                $line = '';
            }
        }

        return $lines;
    }

    private function isContentsPage(string $text): bool
    {
        return preg_match('/^\s*(?:TABLE\s+OF\s+)?CONTENTS\s*$/mi', $text) === 1
            || preg_match_all('/\.{3,}\s*\d+/u', $text) >= 3;
    }

    /** @param array<string, string> $numberedHeadings */
    private function isHeading(string $line, array $numberedHeadings = [], bool $appendix = false): bool
    {
        $line = rtrim(trim($line), ':');

        if ($line === '' || Str::length($line) > 160 || preg_match('/[.!?;:]$/u', $line) || preg_match('/\.{3,}/u', $line)) {
            return false;
        }

        if (in_array($line, ['PROCESS FLOW', 'RESPONSIBILITY', 'DETAILS', 'START', 'END'], true)) {
            return false;
        }

        if ($appendix && preg_match('/^(?:\d+(?:\.\d+)*\.?|[IVXLCDM]+\.)\s+/u', $line)) {
            return false;
        }

        if (preg_match('/^Work Instruction (?:of|for) /u', $line)) {
            return true;
        }

        if (preg_match('/^(\d+(?:\.\d+)*)\.?\s+(.+)$/u', $line, $numbered)) {
            $expected = isset($numberedHeadings[$numbered[1]]) ? Str::lower(rtrim($numberedHeadings[$numbered[1]], ':')) : null;
            $actual = Str::lower($numbered[2]);

            if ($expected !== null && (Str::startsWith($expected, $actual) || Str::startsWith($actual, $expected))) {
                return true;
            }

            if ($numberedHeadings !== [] && ! str_contains($numbered[1], '.')) {
                return false;
            }

            return substr_count($numbered[1], '.') < 2
                && preg_match('/^(?!An?\s|The\s|Each\s|Any\s|All\s)[A-Z]/u', $numbered[2]) === 1;
        }

        return preg_match('/^(?:[IVXLCDM]+\.\s+)?[A-Z][A-Z\s\p{Pd}\/,()&\d]+$/u', $line) === 1
            && preg_match_all('/[A-Z]/', $line) >= 4
            && (str_word_count($line) > 1 || in_array($line, ['APPENDICES', 'ATTACHMENTS', 'REFERENCES', 'PREFACE', 'INTRODUCTION', 'ACKNOWLEDGMENTS'], true))
            && str_word_count($line) <= 16;
    }

    /** @param array<string> $pages @return array<string, string> */
    private function numberedHeadings(array $pages): array
    {
        $headings = [];

        foreach ($pages as $page) {
            if (! $this->isContentsPage($page)) {
                continue;
            }

            foreach (explode("\n", $page) as $line) {
                if (preg_match('/^\s*(\d+(?:\.\d+)*)\.?\s+(.+?)\s*\.{3,}\s*\d+\s*$/u', $line, $heading)) {
                    $headings[$heading[1]] = Str::squish($heading[2]);
                }
            }
        }

        return $headings;
    }

    /** @param array<string, string> $numberedHeadings */
    private function sectionTitle(string $line, array $numberedHeadings): string
    {
        if (! preg_match('/^(\d+(?:\.\d+)*)\.?\s+(.+)$/u', trim($line), $heading)) {
            return trim($line);
        }

        $levels = explode('.', $heading[1]);
        $sections = [];

        foreach ($levels as $level => $value) {
            $number = implode('.', array_slice($levels, 0, $level + 1));

            if (isset($numberedHeadings[$number])) {
                $label = count($levels) === 1 && Str::startsWith(trim($line), $number.'. ') ? $number.'.' : $number;
                $sections[] = $label.' '.$numberedHeadings[$number];
            } elseif ($level === array_key_last($levels)) {
                $sections[] = trim($line);
            }
        }

        return implode(' / ', $sections);
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<array<string, mixed>>  $chunks
     */
    private function appendParagraph(string $paragraph, string $section, string $sourceKind, int $page, int $maximumCharacters, ?array &$current, array &$chunks): void
    {
        while ($paragraph !== '') {
            $piece = $this->boundedPiece($paragraph, $maximumCharacters);
            $paragraph = ltrim(mb_substr($paragraph, Str::length($piece)));

            if ($current !== null && Str::length($current['content']) + Str::length($piece) + 2 > $maximumCharacters) {
                $this->flushChunk($current, $chunks);
            }

            if ($current === null) {
                $current = ['section' => $section, 'source_kind' => $sourceKind, 'page_start' => $page, 'page_end' => $page, 'content' => $piece];
            } else {
                $current['content'] .= "\n\n".$piece;
                $current['page_end'] = $page;
            }
        }
    }

    private function boundedPiece(string $paragraph, int $maximumCharacters): string
    {
        if (Str::length($paragraph) <= $maximumCharacters) {
            return $paragraph;
        }

        $window = mb_substr($paragraph, 0, $maximumCharacters);
        preg_match_all('/[.!?;](?:\s|$)|\n/u', $window, $boundaries, PREG_OFFSET_CAPTURE);
        $boundary = $boundaries[0] !== [] ? $boundaries[0][array_key_last($boundaries[0])] : null;
        $length = $boundary !== null ? Str::length(substr($window, 0, $boundary[1] + strlen($boundary[0]))) : 0;

        if ($length < $maximumCharacters / 2) {
            $length = mb_strrpos($window, ' ') ?: $maximumCharacters;
        }

        return mb_substr($paragraph, 0, $length);
    }

    /** @param array<string, mixed>|null $current @param array<array<string, mixed>> $chunks */
    private function flushChunk(?array &$current, array &$chunks): void
    {
        if ($current !== null) {
            $current['content'] = trim($current['content']);
            $current['id'] = 'manual-'.substr(hash('sha256', json_encode($current, JSON_THROW_ON_ERROR)), 0, 24);
            $chunks[] = $current;
            $current = null;
        }
    }
}
