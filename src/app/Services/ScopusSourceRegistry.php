<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use XMLReader;
use ZipArchive;

class ScopusSourceRegistry
{
    private ?array $registry = null;

    public function path(): string
    {
        return config('services.scopus.source_list_path') ?: storage_path('app/private/journals/scopus-sources.json');
    }

    /** @return array<string, mixed>|null */
    public function metadata(): ?array
    {
        $registry = $this->load();

        return $registry ? array_diff_key($registry, ['sources' => true]) : null;
    }

    /** @return array<string, mixed>|null */
    public function find(?string $issn): ?array
    {
        $registry = $this->load();
        $source = $registry['sources'][$this->normalizeIssn($issn ?? '')] ?? null;
        if (! $source) {
            return null;
        }

        return $source + [
            'label' => match ($source['status']) {
                'active' => 'Scopus covered · '.$registry['edition'],
                'discontinued' => 'Scopus coverage discontinued',
                default => 'Scopus source inactive',
            },
            'url' => 'https://www.scopus.com/sourceid/'.$source['source_id'],
            'checked_at' => $registry['synced_at'],
            'edition' => $registry['edition'],
            'as_of' => $registry['as_of'],
            'stale' => CarbonImmutable::parse($registry['as_of'])->lt(now()->subMonths(4)),
        ];
    }

    /** @return array<string, mixed> */
    public function import(string $path, string $sourceUrl): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The source list must be an XLSX workbook.');
        }
        try {
            $strings = [];
            $shared = simplexml_load_string($zip->getFromName('xl/sharedStrings.xml') ?: '<sst/>', options: LIBXML_NONET);
            foreach ($shared->si as $item) {
                $item->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $strings[] = implode('', array_map(fn ($text): string => (string) $text, $item->xpath('.//s:t') ?: []));
            }
            $workbook = simplexml_load_string($zip->getFromName('xl/workbook.xml') ?: '<workbook/>', options: LIBXML_NONET);
            $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels') ?: '<Relationships/>', options: LIBXML_NONET);
            $sheetId = $edition = null;
            foreach ($workbook->sheets->sheet as $sheet) {
                if (Str::startsWith((string) $sheet['name'], 'Scopus Sources')) {
                    $edition = (string) $sheet['name'];
                    $sheetId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                    break;
                }
            }
            $sheetPath = null;
            foreach ($relationships->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $sheetId) {
                    $sheetPath = 'xl/'.ltrim((string) $relationship['Target'], '/');
                }
            }
            if (! $sheetPath || ! preg_match('~^xl/worksheets/sheet\d+\.xml$~', $sheetPath) || ! preg_match('/Scopus Sources\s+(.+\d{4})$/', $edition ?? '', $date)) {
                throw new RuntimeException('The workbook does not contain a dated Scopus Sources sheet.');
            }
            $asOf = CarbonImmutable::parse('1 '.str_replace('.', '', $date[1]))->startOfMonth()->toDateString();
            $reader = new XMLReader;
            if (! $reader->open('zip://'.realpath($path).'#'.$sheetPath, flags: LIBXML_NONET)) {
                throw new RuntimeException('The Scopus Sources sheet could not be read.');
            }
            $columns = $sources = [];
            try {
                while ($reader->read()) {
                    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                        continue;
                    }
                    $row = simplexml_load_string($reader->readOuterXml(), options: LIBXML_NONET);
                    $cells = [];
                    foreach ($row->c as $cell) {
                        $column = preg_replace('/\d+/', '', (string) $cell['r']);
                        $cells[$column] = match ((string) $cell['t']) {
                            's' => $strings[(int) $cell->v] ?? '',
                            'inlineStr' => (string) $cell->is->t,
                            default => (string) $cell->v,
                        };
                    }
                    if ($columns === []) {
                        foreach ($cells as $column => $name) {
                            $columns[preg_replace('/[^a-z]/', '', strtolower($name))] = $column;
                        }
                        foreach (['sourcerecordid', 'sourcetitle', 'issn', 'eissn', 'activeorinactive', 'coverage', 'titlesdiscontinuedbyscopus', 'sourcetype'] as $required) {
                            if (! isset($columns[$required])) {
                                throw new RuntimeException('The Scopus workbook column format has changed. No existing registry was replaced.');
                            }
                        }

                        continue;
                    }
                    $value = fn (string $name): string => trim($cells[$columns[$name]] ?? '');
                    if (strtolower($value('sourcetype')) !== 'journal' || ! ctype_digit($value('sourcerecordid'))) {
                        continue;
                    }
                    $status = $value('titlesdiscontinuedbyscopus') !== '' ? 'discontinued' : (strtolower($value('activeorinactive')) === 'active' ? 'active' : 'inactive');
                    $source = ['source_id' => $value('sourcerecordid'), 'title' => $value('sourcetitle'), 'status' => $status, 'coverage' => $value('coverage')];
                    foreach (['issn', 'eissn'] as $field) {
                        $issn = $this->normalizeIssn($value($field));
                        if (preg_match('/^\d{7}[\dX]$/', $issn) && (! isset($sources[$issn]) || $status === 'active')) {
                            $sources[$issn] = $source;
                        }
                    }
                }
            } finally {
                $reader->close();
            }
            if ($sources === []) {
                throw new RuntimeException('The workbook contained no journal ISSNs. No existing registry was replaced.');
            }
            $registry = ['edition' => $edition, 'as_of' => $asOf, 'synced_at' => now()->toIso8601String(), 'source_url' => $sourceUrl, 'sources' => $sources];
            File::ensureDirectoryExists(dirname($this->path()));
            $temporaryPath = $this->path().'.tmp';
            File::put($temporaryPath, json_encode($registry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            File::move($temporaryPath, $this->path());
            $this->registry = $registry;

            return $this->metadata();
        } finally {
            $zip->close();
        }
    }

    private function normalizeIssn(string $issn): string
    {
        return strtoupper(str_replace(['-', ' '], '', $issn));
    }

    /** @return array<string, mixed>|null */
    private function load(): ?array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }
        if (! File::exists($this->path())) {
            return null;
        }
        $registry = json_decode(File::get($this->path()), true);
        if (! is_array($registry) || ! isset($registry['sources'], $registry['as_of'], $registry['edition'], $registry['synced_at'])) {
            return null;
        }

        return $this->registry = $registry;
    }
}
