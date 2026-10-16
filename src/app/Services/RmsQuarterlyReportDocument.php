<?php

namespace App\Services;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class RmsQuarterlyReportDocument
{
    private const S = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @param  array<string, list<array{values: array<string, string>}>>  $reportData
     * @param  array{year: int, quarter: int, campus: string, signatories: array<string, array{name: string, position: string, office: string}>}  $metadata
     */
    public function generate(array $reportData, array $metadata): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-rms-report-');
        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary RMS report could not be created.');
        }

        $archive = new ZipArchive;
        $open = false;

        try {
            if (! copy(resource_path('documents/RMS-Quarterly-Monitoring-Report.xlsx'), $temporaryPath)
                || $archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The RMS report template could not be opened.');
            }

            $open = true;
            [$styles, $styleXPath] = $this->xml($this->part($archive, 'xl/styles.xml'));
            [$workbook, $workbookXPath] = $this->xml($this->part($archive, 'xl/workbook.xml'));
            $dateStyles = [];
            $signatureStyles = [];

            foreach (['presentations' => [1, 13], 'publications' => [2, 13], 'ip_assets' => [3, 12]] as $section => [$sheet, $firstRow]) {
                [$document, $xpath] = $this->xml($this->part($archive, 'xl/worksheets/sheet'.$sheet.'.xml'));
                $rows = $reportData[$section] ?? [];
                $extraRows = max(0, count($rows) - 10);
                $this->expandRows($xpath, $firstRow, $extraRows);

                for ($index = 0; $index < max(10, count($rows)); $index++) {
                    $rowNumber = $firstRow + $index;
                    $lines = 1;
                    $this->setCell($this->cell($xpath, 'A'.$rowNumber), isset($rows[$index]) ? $index + 1 : '');
                    foreach (array_keys(RmsQuarterlyReportData::FIELDS[$section]) as $column => $field) {
                        $cell = $this->cell($xpath, chr(ord('B') + $column).$rowNumber);
                        $value = $rows[$index]['values'][$field] ?? '';
                        $date = $field === 'date' ? $this->date($value) : null;
                        $this->setCell($cell, $date ?? $value);
                        if ($date !== null) {
                            $cell->setAttribute('s', (string) $this->dateStyle($styleXPath, (int) $cell->getAttribute('s'), $dateStyles));
                        }
                        $width = (float) $xpath->evaluate('string(/s:worksheet/s:cols/s:col[@min <= '.($column + 2).' and @max >= '.($column + 2).']/@width)');
                        $lines = max($lines, array_sum(array_map(
                            fn (string $line): int => max(1, (int) ceil(mb_strwidth($line) / max(1, $width * 0.85))),
                            explode("\n", $value),
                        )));
                    }
                    $this->cell($xpath, 'A'.$rowNumber)->parentNode->setAttribute('ht', (string) (18 * $lines));
                }

                $this->setCell($this->cell($xpath, 'C'.($firstRow + 10 + $extraRows)), count($rows));
                $this->setCell($this->cell($xpath, 'A5'), 'Fiscal Year: '.$metadata['year'].' Quarter: '.$metadata['quarter']);
                $this->setCell($this->cell($xpath, 'A6'), 'Constituent Campus: '.$metadata['campus']);
                foreach (['name' => 30, 'position' => 31, 'office' => 32] as $field => $baseRow) {
                    $row = $baseRow + $extraRows;
                    $lines = 1;
                    $merges = $xpath->query('/s:worksheet/s:mergeCells')->item(0);
                    foreach (iterator_to_array($xpath->query('./s:mergeCell', $merges)) as $merge) {
                        if (preg_match('/^[A-H]'.$row.':[A-H]'.$row.'$/', $merge->getAttribute('ref'))) {
                            $merges->removeChild($merge);
                        }
                    }
                    foreach (['prepared' => ['A', 'C'], 'reviewed' => ['D', 'E'], 'approved' => ['F', 'H']] as $role => [$first, $last]) {
                        $cell = $this->cell($xpath, $first.$row);
                        $value = $metadata['signatories'][$role][$field] ?? '';
                        $this->setCell($cell, $value);
                        $cell->setAttribute('s', (string) $this->signatureStyle($styleXPath, (int) $cell->getAttribute('s'), $signatureStyles));
                        $merge = $document->createElementNS(self::S, 'mergeCell');
                        $merge->setAttribute('ref', $first.$row.':'.$last.$row);
                        $merges->appendChild($merge);
                        $width = 0;
                        foreach (range(ord($first), ord($last)) as $column) {
                            $number = $column - ord('A') + 1;
                            $width += (float) $xpath->evaluate('string(/s:worksheet/s:cols/s:col[@min <= '.$number.' and @max >= '.$number.']/@width)');
                        }
                        $lines = max($lines, array_sum(array_map(
                            fn (string $line): int => max(1, (int) ceil(mb_strwidth($line) / max(1, $width * 0.85))),
                            explode("\n", $value),
                        )));
                    }
                    $this->cell($xpath, 'A'.$row)->parentNode->setAttribute('ht', (string) (18 * $lines));
                    $merges->setAttribute('count', (string) $xpath->query('./s:mergeCell', $merges)->length);
                }

                $lastColumn = $sheet === 3 ? 'H' : 'R';
                $pageSetup = $xpath->query('/s:worksheet/s:pageSetup')->item(0);
                $pageSetup->setAttribute('fitToWidth', '1');
                $pageSetup->setAttribute('fitToHeight', '0');
                $printArea = $workbook->createElementNS(self::S, 'definedName');
                $printArea->setAttribute('name', '_xlnm.Print_Area');
                $printArea->setAttribute('localSheetId', (string) ($sheet - 1));
                $sheetName = $workbookXPath->evaluate('string(/s:workbook/s:sheets/s:sheet['.$sheet.']/@name)');
                $printArea->appendChild($workbook->createTextNode("'".$sheetName."'!\$A\$1:\$".$lastColumn.'$'.(33 + $extraRows)));
                $workbookXPath->query('/s:workbook/s:definedNames')->item(0)->appendChild($printArea);
                $this->write($archive, 'xl/worksheets/sheet'.$sheet.'.xml', $document->saveXML());
            }

            $this->write($archive, 'xl/styles.xml', $styles->saveXML());
            $this->write($archive, 'xl/workbook.xml', $workbook->saveXML());
            $open = false;
            if (! $archive->close()) {
                throw new RuntimeException('The RMS report could not be saved.');
            }

            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException('The RMS report could not be read.');
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

    private function expandRows(DOMXPath $xpath, int $firstRow, int $extraRows): void
    {
        if ($extraRows === 0) {
            return;
        }

        $totalRow = $firstRow + 10;
        $blankRow = $this->cell($xpath, 'A'.$firstRow)->parentNode;
        $total = $this->cell($xpath, 'A'.$totalRow)->parentNode;
        foreach ($xpath->query('/s:worksheet/s:sheetData/s:row') as $row) {
            $number = (int) $row->getAttribute('r');
            if ($number >= $totalRow) {
                $row->setAttribute('r', (string) ($number + $extraRows));
                foreach ($xpath->query('./s:c', $row) as $cell) {
                    $cell->setAttribute('r', $this->shiftReference($cell->getAttribute('r'), $totalRow, $extraRows));
                }
            }
        }

        for ($index = 0; $index < $extraRows; $index++) {
            $row = $blankRow->cloneNode(true);
            $number = $totalRow + $index;
            $row->setAttribute('r', (string) $number);
            foreach ($xpath->query('./s:c', $row) as $cell) {
                $cell->setAttribute('r', $this->shiftReference($cell->getAttribute('r'), $firstRow, $number - $firstRow));
            }
            $total->parentNode->insertBefore($row, $total);
        }

        foreach ($xpath->query('//s:mergeCell | //s:dimension | //s:hyperlink | //s:autoFilter') as $element) {
            $element->setAttribute('ref', $this->shiftReference($element->getAttribute('ref'), $totalRow, $extraRows));
        }
        foreach ($xpath->query('//s:dataValidation') as $validation) {
            $validation->setAttribute('sqref', $this->shiftReference($validation->getAttribute('sqref'), $totalRow - 1, $extraRows));
        }
    }

    private function shiftReference(string $reference, int $from, int $offset): string
    {
        return preg_replace_callback('/(\$?[A-Z]+\$?)(\d+)/', fn (array $match): string => $match[1].((int) $match[2] >= $from ? (int) $match[2] + $offset : $match[2]), $reference);
    }

    private function cell(DOMXPath $xpath, string $reference): DOMElement
    {
        $cell = $xpath->query('//s:c[@r="'.$reference.'"]')->item(0);
        if (! $cell instanceof DOMElement) {
            throw new RuntimeException('An RMS report cell is missing: '.$reference.'.');
        }

        return $cell;
    }

    private function setCell(DOMElement $cell, string|int $value): void
    {
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');
        if (is_int($value)) {
            $number = $cell->ownerDocument->createElementNS(self::S, 'v');
            $number->appendChild($cell->ownerDocument->createTextNode((string) $value));
            $cell->appendChild($number);

            return;
        }
        if ($value === '') {
            return;
        }

        $cell->setAttribute('t', 'inlineStr');
        $inline = $cell->ownerDocument->createElementNS(self::S, 'is');
        $text = $cell->ownerDocument->createElementNS(self::S, 't');
        $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $text->appendChild($cell->ownerDocument->createTextNode($value));
        $inline->appendChild($text);
        $cell->appendChild($inline);
    }

    private function date(string $value): ?int
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value || $date < new DateTimeImmutable('1900-03-01')) {
            return null;
        }

        return (int) $date->diff(new DateTimeImmutable('1899-12-30'))->days;
    }

    /** @param array<int, int> $cache */
    private function signatureStyle(DOMXPath $xpath, int $baseStyle, array &$cache): int
    {
        if (isset($cache[$baseStyle])) {
            return $cache[$baseStyle];
        }
        $styles = $xpath->query('/s:styleSheet/s:cellXfs')->item(0);
        $style = $xpath->query('./s:xf['.($baseStyle + 1).']', $styles)->item(0)->cloneNode(true);
        $alignment = $xpath->query('./s:alignment', $style)->item(0);
        if (! $alignment instanceof DOMElement) {
            $alignment = $xpath->document->createElementNS(self::S, 'alignment');
            $style->appendChild($alignment);
        }
        $alignment->setAttribute('wrapText', '1');
        $alignment->setAttribute('vertical', 'top');
        $style->setAttribute('applyAlignment', '1');
        $number = $xpath->query('./s:xf', $styles)->length;
        $styles->appendChild($style);
        $styles->setAttribute('count', (string) ($number + 1));

        return $cache[$baseStyle] = $number;
    }

    /** @param array<int, int> $dateStyles */
    private function dateStyle(DOMXPath $xpath, int $baseStyle, array &$dateStyles): int
    {
        if (isset($dateStyles[$baseStyle])) {
            return $dateStyles[$baseStyle];
        }

        $document = $xpath->document;
        $formats = $xpath->query('/s:styleSheet/s:numFmts')->item(0);
        if (! $formats instanceof DOMElement) {
            $formats = $document->createElementNS(self::S, 'numFmts');
            $document->documentElement->insertBefore($formats, $document->documentElement->firstChild);
        }
        $format = $xpath->query('/s:styleSheet/s:numFmts/s:numFmt[@formatCode="mm/dd/yyyy"]')->item(0);
        if (! $format instanceof DOMElement) {
            $formatId = 164;
            foreach ($xpath->query('/s:styleSheet/s:numFmts/s:numFmt') as $existing) {
                $formatId = max($formatId, (int) $existing->getAttribute('numFmtId') + 1);
            }
            $format = $document->createElementNS(self::S, 'numFmt');
            $format->setAttribute('numFmtId', (string) $formatId);
            $format->setAttribute('formatCode', 'mm/dd/yyyy');
            $formats->appendChild($format);
            $formats->setAttribute('count', (string) $formats->childNodes->length);
        }
        $styles = $xpath->query('/s:styleSheet/s:cellXfs')->item(0);
        $style = $xpath->query('./s:xf['.($baseStyle + 1).']', $styles)->item(0)->cloneNode(true);
        $style->setAttribute('numFmtId', $format->getAttribute('numFmtId'));
        $style->setAttribute('applyNumberFormat', '1');
        $number = $xpath->query('./s:xf', $styles)->length;
        $styles->appendChild($style);
        $styles->setAttribute('count', (string) ($number + 1));

        return $dateStyles[$baseStyle] = $number;
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function xml(string $xml): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The RMS report contains invalid XML.');
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', self::S);

        return [$document, $xpath];
    }

    private function part(ZipArchive $archive, string $name): string
    {
        $contents = $archive->getFromName($name);
        if ($contents === false) {
            throw new RuntimeException('The RMS report template is incomplete.');
        }

        return $contents;
    }

    private function write(ZipArchive $archive, string $name, string|false $contents): void
    {
        if ($contents === false || ! $archive->addFromString($name, $contents)) {
            throw new RuntimeException('The RMS report could not be written.');
        }
    }
}
