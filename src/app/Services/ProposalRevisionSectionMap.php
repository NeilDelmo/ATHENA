<?php

namespace App\Services;

use App\Contracts\DocumentPdfConverter;
use App\Models\ProposalVersionFile;
use App\Support\ProposalRevisionSectionCatalog;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProposalRevisionSectionMap
{
    public function __construct(private readonly ProposalRevisionSectionCatalog $catalog) {}

    public function pdfContents(ProposalVersionFile $file): string
    {
        $disk = Storage::disk('local');
        $contents = $disk->get($file->file_path);
        if ($file->isPdf()) {
            return $contents;
        }
        $path = 'revision-section-maps/'.hash('sha256', $contents).'.pdf';
        if (! $disk->exists($path)) {
            $disk->put($path, app(DocumentPdfConverter::class)->convertDocx($contents));
        }

        return $disk->get($path);
    }

    /** @return list<array<string, mixed>> */
    public function forFile(ProposalVersionFile $file): array
    {
        $source = $file->source_data ?? [];
        if ($this->catalog->forType($file->document_type, $source) === [] || ! Storage::disk('local')->exists($file->file_path)) {
            return [];
        }
        $contents = $this->pdfContents($file);
        $checksum = hash('sha256', $contents);
        $saved = $source['_revision_sections'] ?? null;
        if (is_array($saved) && ($saved['checksum'] ?? null) === $checksum && ($saved['version'] ?? null) === 1) {
            return $saved['regions'] ?? [];
        }
        $key = hash('sha256', $file->document_type.$checksum.json_encode($this->catalog->forType($file->document_type, $source)));
        $path = 'revision-section-maps/v1-'.$key.'.json';
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        }
        try {
            $regions = $this->fromPdf($contents, $file->document_type, $source);
        } catch (RuntimeException $exception) {
            report($exception);

            return [];
        }
        $disk->put($path, json_encode($regions, JSON_THROW_ON_ERROR));

        return $regions;
    }

    /** @return list<array<string, mixed>> */
    public function fromPdf(string $contents, string $type, array $source = []): array
    {
        if ($this->catalog->forType($type, $source) === []) {
            return [];
        }
        $path = tempnam(sys_get_temp_dir(), 'athena-sections-');
        if ($path === false) {
            throw new RuntimeException('A temporary PDF section map could not be created.');
        }
        try {
            file_put_contents($path, $contents);
            $result = Process::timeout(60)->run([
                (string) config('document_pdf.node_binary', 'node'),
                resource_path('js/pdf-section-coordinates.mjs'), $path,
            ]);
            if ($result->failed()) {
                throw new RuntimeException('The PDF section map could not be read.');
            }

            return $this->fromBbox($result->output(), $type, $source);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function fromBbox(string $xml, string $type, array $source = []): array
    {
        $document = new DOMDocument;
        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The PDF section coordinates are invalid.');
        }
        $xpath = new DOMXPath($document);
        $definitions = $this->catalog->forType($type, $source);
        $regions = [];
        $current = null;
        $person = 0;
        foreach ($xpath->query('//*[local-name()="page"]') as $pageIndex => $page) {
            $width = (float) $page->getAttribute('width');
            $height = (float) $page->getAttribute('height');
            if ($width <= 0 || $height <= 0) {
                continue;
            }
            $boxes = [];
            foreach ($xpath->query('./box', $page) as $box) {
                $boxes[] = ['x' => (float) $box->getAttribute('xMin'), 'right' => (float) $box->getAttribute('xMax'),
                    'y' => (float) $box->getAttribute('yMin'), 'bottom' => (float) $box->getAttribute('yMax')];
            }
            $lines = [];
            foreach ($xpath->query('.//*[local-name()="line"]', $page) as $line) {
                $words = [];
                foreach ($xpath->query('.//*[local-name()="word"]', $line) as $word) {
                    $words[] = $word->textContent;
                }
                $text = preg_replace('/\s+/u', ' ', trim(implode(' ', $words)));
                $y = (float) $line->getAttribute('yMin');
                if ($text === '' || preg_match('/^(?:Page\s+)?\d+(?:\s+(?:of|\/)\s+\d+)?$/i', $text)
                    || preg_match('/Reference No|Effectivity Date|Revision No|BatStateU-FO-RES-/i', $text)) {
                    continue;
                }
                $lines[] = ['text' => $text, 'x' => (float) $line->getAttribute('xMin'),
                    'right' => (float) $line->getAttribute('xMax'), 'y' => $y,
                    'bottom' => (float) $line->getAttribute('yMax')];
            }
            if ($lines === []) {
                continue;
            }
            usort($lines, fn (array $a, array $b): int => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);
            $left = max(0, min(array_column($boxes ?: $lines, 'x')) - ($boxes ? 0 : 5));
            $right = min($width, max(array_column($boxes ?: $lines, 'right')) + ($boxes ? 0 : 5));
            $top = max(0, min($lines[0]['y'] - 3, $boxes ? min(array_column($boxes, 'y')) : $lines[0]['y'] - 3));
            $bottom = min($height, max(array_column([...$boxes, ...$lines], 'bottom')) + 5);
            foreach ($lines as $line) {
                if ($type === ProposalVersionFile::TYPE_CURRICULUM_VITAE && preg_match('/^PERSONAL INFORMATION$/i', $line['text'])) {
                    $person++;
                }
                $match = null;
                foreach ($definitions as $definition) {
                    if ($type === ProposalVersionFile::TYPE_CURRICULUM_VITAE && ! str_starts_with($definition['value'], 'section-cv-'.$person.'-')) {
                        continue;
                    }
                    if (preg_match($definition['pattern'], $line['text'])) {
                        $match = $definition;
                        break;
                    }
                }
                if (! $match || ($current && $match['value'] === $current['value'])) {
                    continue;
                }
                $containers = array_values(array_filter($boxes, fn (array $box): bool => $box['y'] <= $line['y'] + 1 && $box['bottom'] >= $line['bottom'] - 1));
                usort($containers, fn (array $a, array $b): int => ($a['bottom'] - $a['y']) <=> ($b['bottom'] - $b['y']));
                $boundary = max($top, $containers[0]['y'] ?? $line['y'] - 3);
                if ($current && $boundary > $top) {
                    $regions[] = $this->region($current, $pageIndex + 1, $left, $top, $right, $boundary, $width, $height);
                }
                $current = $match;
                $top = $boundary;
            }
            if ($current && $bottom > $top) {
                $regions[] = $this->region($current, $pageIndex + 1, $left, $top, $right, $bottom, $width, $height);
            }
        }

        return $regions;
    }

    /** @return array<string, mixed> */
    private function region(array $section, int $page, float $left, float $top, float $right, float $bottom, float $width, float $height): array
    {
        return ['id' => $section['value'], 'label' => $section['label'], 'pageNumber' => $page,
            'x' => $left / $width, 'y' => $top / $height,
            'width' => ($right - $left) / $width, 'height' => ($bottom - $top) / $height];
    }

    /** @param list<array<string, mixed>> $regions @param list<array<string, float>> $rectangles */
    public function match(array $regions, int $page, array $rectangles): ?string
    {
        $scores = [];
        foreach ($regions as $region) {
            if ($region['pageNumber'] !== $page) {
                continue;
            }
            foreach ($rectangles as $rectangle) {
                $width = max(0, min($region['x'] + $region['width'], $rectangle['x'] + $rectangle['width']) - max($region['x'], $rectangle['x']));
                $height = max(0, min($region['y'] + $region['height'], $rectangle['y'] + $rectangle['height']) - max($region['y'], $rectangle['y']));
                $scores[$region['id']] = ($scores[$region['id']] ?? 0) + $width * $height;
            }
        }
        arsort($scores);
        $id = array_key_first($scores);

        return $id !== null && $scores[$id] > 0 ? $id : null;
    }
}
