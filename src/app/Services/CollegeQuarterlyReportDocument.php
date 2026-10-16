<?php

namespace App\Services;

use App\Models\CollegeQuarterlyReport;
use App\Models\User;
use App\Support\ProposalRichText;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Filesystem\FilesystemManager;
use RuntimeException;
use ZipArchive;

class CollegeQuarterlyReportDocument
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function __construct(private readonly FilesystemManager $filesystem, private readonly ProposalRichText $richText) {}

    /**
     * @param  array{quarter: int, start: CarbonInterface, end: CarbonInterface}  $period
     * @param  array{presentations: list<list<string>>, publications: list<list<string>>, research: list<list<string>>}  $reportData
     */
    public function generate(User $coordinator, array $period, array $reportData, ?CollegeQuarterlyReport $report = null): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-quarterly-report-');
        if ($temporaryPath === false) {
            throw new RuntimeException('A temporary quarterly report could not be created.');
        }

        $archive = new ZipArchive;
        $open = false;

        try {
            if (! copy(resource_path('documents/College-Quarterly-Accomplishment-Report.docx'), $temporaryPath)
                || $archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The quarterly report template could not be opened.');
            }

            $open = true;
            [$document, $xpath] = $this->xml($this->part($archive, 'word/document.xml'));
            $college = (string) ($report?->college ?: $coordinator->college);
            $quarter = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'][$period['quarter']];
            $this->fillSlot($xpath, 'report_period', $period['start']->format('F').' to '.$period['end']->format('F Y').' ('.$quarter.' Quarter)');
            $this->fillSlot($xpath, 'prepared_by', (string) ($report?->snapshot['prepared_by_name'] ?? $report?->preparedBy?->name ?? $coordinator->name));
            $this->fillSlot($xpath, 'prepared_title', 'Research Coordinator'.($college !== '' ? ', '.$college : ''));
            $this->fillSlot($xpath, 'reviewed_by', $report?->reviewed_by ?? '');
            $this->fillSlot($xpath, 'reviewed_title', $report?->reviewed_by_title ?? '');
            $this->fillAccomplishments($xpath, $report?->other_accomplishments ?? '');

            $seminars = $report?->activityRows('seminars') ?? [];

            foreach ([$reportData['presentations'], $reportData['publications'], $seminars, $reportData['research']] as $index => $rows) {
                $this->fillTable($xpath, $index + 1, $rows);
            }
            $this->appendIpAssets($document, $xpath, $report?->activityRows('ip_assets') ?? []);

            [$header, $headerXPath] = $this->xml($this->part($archive, 'word/header2.xml'));
            $this->fillSlot($headerXPath, 'college_name', $college);
            $evidence = [];
            foreach ($report?->evidence ?? [] as $index => $image) {
                $evidence[] = [...$image, 'caption' => $report->evidenceLabel($image),
                    'url' => $image['url'] ?? ($report->exists ? route('college-quarterly-reports.evidence', ['report' => $report, 'index' => $index]) : null),
                ];
            }
            $this->appendEvidence($archive, $document, $xpath, $evidence);
            $this->write($archive, 'word/document.xml', $document->saveXML());
            $this->write($archive, 'word/header2.xml', $header->saveXML());

            $open = false;
            if (! $archive->close()) {
                throw new RuntimeException('The quarterly report could not be saved.');
            }

            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException('The quarterly report could not be read.');
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

    /** @param list<list<string>> $rows */
    private function appendIpAssets(DOMDocument $document, DOMXPath $xpath, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $anchor = $xpath->query('//w:bookmarkStart[@w:name="prepared_by"]/parent::w:p/preceding-sibling::w:p[1]')->item(0);
        if (! $anchor instanceof DOMElement) {
            throw new RuntimeException('The quarterly report signatory field is missing.');
        }
        $heading = $document->createElementNS(self::W, 'w:p');
        $this->setText($xpath, $heading, 'Intellectual Property Assets');
        $properties = $document->createElementNS(self::W, 'w:rPr');
        $properties->appendChild($document->createElementNS(self::W, 'w:b'));
        $heading->firstChild->insertBefore($properties, $heading->firstChild->firstChild);
        $anchor->parentNode->insertBefore($heading, $anchor);

        foreach ($rows as [$title, $type, $inventors, $date, $details]) {
            $paragraph = $document->createElementNS(self::W, 'w:p');
            $this->setText($xpath, $paragraph, implode("\n", array_filter([
                $title, 'Type: '.$type, 'Inventors / authors: '.$inventors, 'Date Submitted: '.$date, $details,
            ], fn (string $value): bool => filled($value))));
            $anchor->parentNode->insertBefore($paragraph, $anchor);
        }
    }

    /** @param list<list<string>> $rows */
    private function fillTable(DOMXPath $xpath, int $index, array $rows): void
    {
        $table = $xpath->query('/w:document/w:body/w:tbl['.$index.']')->item(0);
        if (! $table instanceof DOMElement) {
            throw new RuntimeException('A quarterly report table is missing.');
        }

        $blankRow = $xpath->query('./w:tr[2]', $table)->item(0);
        if (! $blankRow instanceof DOMElement) {
            throw new RuntimeException('A quarterly report table row is missing.');
        }

        foreach ($rows as $values) {
            $row = $blankRow->cloneNode(true);
            $cells = $xpath->query('./w:tc', $row);
            if ($cells->length !== count($values)) {
                throw new RuntimeException('A quarterly report row has invalid columns.');
            }

            foreach ($values as $column => $value) {
                $paragraph = $xpath->query('./w:p[1]', $cells->item($column))->item(0);
                if (! $paragraph instanceof DOMElement) {
                    throw new RuntimeException('A quarterly report cell is missing.');
                }

                $this->setText($xpath, $paragraph, $value);
            }

            $table->appendChild($row);
        }

        if ($rows !== []) {
            $table->removeChild($blankRow);
        }
    }

    private function fillSlot(DOMXPath $xpath, string $name, string $value): void
    {
        $paragraph = $xpath->query('//w:bookmarkStart[@w:name="'.$name.'"]/parent::w:p')->item(0);
        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('A quarterly report field is missing: '.$name.'.');
        }

        $this->setText($xpath, $paragraph, $value);
    }

    private function fillAccomplishments(DOMXPath $xpath, string $value): void
    {
        $anchor = $xpath->query('//w:bookmarkStart[@w:name="other_accomplishments"]/parent::w:p')->item(0);
        if (! $anchor instanceof DOMElement) {
            throw new RuntimeException('A quarterly report field is missing: other_accomplishments.');
        }

        foreach (iterator_to_array($xpath->query('./w:pPr/w:numPr', $anchor)) as $numbering) {
            $numbering->parentNode->removeChild($numbering);
        }

        if (! preg_match('/<\/?(?:p|div|br|b|strong|i|em|u|ol|ul|li|script|style)\b/i', $value)) {
            $this->setText($xpath, $anchor, $value);

            return;
        }

        $properties = $xpath->query('./w:r[1]/w:rPr', $anchor)->item(0);
        $paragraphProperties = $xpath->query('./w:pPr', $anchor)->item(0);
        $orderedIndex = 0;

        foreach ($this->richText->blocks($value) as $block) {
            $paragraph = $anchor->ownerDocument->createElementNS(self::W, 'w:p');
            if ($paragraphProperties !== null) {
                $paragraph->appendChild($paragraphProperties->cloneNode(true));
            }

            $orderedIndex = $block['type'] === 'ordered' ? $orderedIndex + 1 : 0;
            $prefix = match ($block['type']) {
                'ordered' => $orderedIndex.'. ',
                'unordered' => '• ',
                default => '',
            };
            if ($prefix !== '') {
                $this->appendRichTextRun($paragraph, $properties, ['text' => $prefix, 'bold' => false, 'italic' => false, 'underline' => false, 'break' => false]);
            }

            foreach ($block['runs'] as $run) {
                $this->appendRichTextRun($paragraph, $properties, $run);
            }

            $anchor->parentNode->insertBefore($paragraph, $anchor);
        }

        $anchor->parentNode->removeChild($anchor);
    }

    /** @param array{text: string, bold: bool, italic: bool, underline: bool, break: bool} $content */
    private function appendRichTextRun(DOMElement $paragraph, ?DOMNode $properties, array $content): void
    {
        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $properties?->cloneNode(true) ?? $document->createElementNS(self::W, 'w:rPr');
        foreach (iterator_to_array($runProperties->childNodes) as $property) {
            if ($property instanceof DOMElement && in_array($property->localName, ['b', 'i', 'u'], true)) {
                $runProperties->removeChild($property);
            }
        }
        foreach (['bold' => 'b', 'italic' => 'i', 'underline' => 'u'] as $format => $tag) {
            if ($content[$format]) {
                $property = $document->createElementNS(self::W, 'w:'.$tag);
                if ($format === 'underline') {
                    $property->setAttributeNS(self::W, 'w:val', 'single');
                }
                $runProperties->appendChild($property);
            }
        }
        $run->appendChild($runProperties);
        if ($content['break']) {
            $run->appendChild($document->createElementNS(self::W, 'w:br'));
        } else {
            $text = $document->createElementNS(self::W, 'w:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($document->createTextNode($content['text']));
            $run->appendChild($text);
        }
        $paragraph->appendChild($run);
    }

    private function setText(DOMXPath $xpath, DOMElement $paragraph, string $value): void
    {
        $properties = $xpath->query('./w:r[1]/w:rPr', $paragraph)->item(0)?->cloneNode(true);
        foreach (iterator_to_array($xpath->query('./w:r', $paragraph)) as $run) {
            $paragraph->removeChild($run);
        }

        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        if ($properties !== null) {
            $run->appendChild($properties);
        }

        foreach (preg_split('/\R/u', $value) ?: [''] as $index => $line) {
            if ($index > 0) {
                $run->appendChild($document->createElementNS(self::W, 'w:br'));
            }

            $text = $document->createElementNS(self::W, 'w:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($document->createTextNode($line));
            $run->appendChild($text);
        }

        $paragraph->appendChild($run);
    }

    /** @param list<array{path: string, caption?: string}> $evidence */
    private function appendEvidence(ZipArchive $archive, DOMDocument $document, DOMXPath $xpath, array $evidence): void
    {
        if ($evidence === []) {
            return;
        }

        $anchor = $xpath->query('//w:bookmarkStart[@w:name="supporting_documentation"]/parent::w:p')->item(0);
        if (! $anchor instanceof DOMElement) {
            throw new RuntimeException('The supporting documentation field is missing.');
        }

        [$relationships] = $this->xml($this->part($archive, 'word/_rels/document.xml.rels'));
        [$contentTypes] = $this->xml($this->part($archive, '[Content_Types].xml'));
        $disk = $this->filesystem->disk('local');

        foreach ($evidence as $index => $item) {
            if (($item['path'] ?? '') === '' || ! $disk->exists($item['path'])) {
                throw new RuntimeException('A supporting documentation image is unavailable.');
            }

            $contents = $disk->get($item['path']);
            $number = $index + 1;
            if (($item['source'] ?? null) === 'faculty_report' && ! in_array($item['mime_type'], ['image/jpeg', 'image/png'], true)) {
                $caption = $anchor->cloneNode(true);
                foreach (iterator_to_array($xpath->query('./w:bookmarkStart | ./w:bookmarkEnd', $caption)) as $bookmark) {
                    $caption->removeChild($bookmark);
                }
                $this->setText($xpath, $caption, $item['caption'] ?: $item['original_filename']);
                $url = $item['url'] ?? null;
                if (is_string($url) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                    $relationshipId = 'quarterlyEvidenceLink'.$number;
                    $relationship = $relationships->createElementNS(self::R, 'Relationship');
                    $relationship->setAttribute('Id', $relationshipId);
                    $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink');
                    $relationship->setAttribute('Target', $url);
                    $relationship->setAttribute('TargetMode', 'External');
                    $relationships->documentElement->appendChild($relationship);
                    $link = $document->createElementNS(self::W, 'w:hyperlink');
                    $link->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'r:id', $relationshipId);
                    foreach (iterator_to_array($xpath->query('./w:r', $caption)) as $run) {
                        $link->appendChild($run);
                    }
                    $caption->appendChild($link);
                }
                $anchor->parentNode->insertBefore($caption, $anchor);

                continue;
            }
            $dimensions = getimagesizefromstring($contents);
            if ($dimensions === false || ! in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
                throw new RuntimeException('A supporting documentation image is invalid.');
            }

            $extension = $dimensions[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
            $relationshipId = 'quarterlyEvidence'.$number;
            $target = 'media/quarterly-evidence-'.$number.'.'.$extension;
            $relationship = $relationships->createElementNS(self::R, 'Relationship');
            $relationship->setAttribute('Id', $relationshipId);
            $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
            $relationship->setAttribute('Target', $target);
            $relationships->documentElement->appendChild($relationship);

            $type = $contentTypes->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Override');
            $type->setAttribute('PartName', '/word/'.$target);
            $type->setAttribute('ContentType', $extension === 'png' ? 'image/png' : 'image/jpeg');
            $contentTypes->documentElement->appendChild($type);

            $scale = min(1, 5715000 / ($dimensions[0] * 9525), 6629400 / ($dimensions[1] * 9525));
            $width = (int) round($dimensions[0] * 9525 * $scale);
            $height = (int) round($dimensions[1] * 9525 * $scale);
            $anchor->parentNode->insertBefore($this->imageParagraph($document, $relationshipId, $number, $width, $height), $anchor);
            $caption = $anchor->cloneNode(true);
            foreach (iterator_to_array($xpath->query('./w:bookmarkStart | ./w:bookmarkEnd', $caption)) as $bookmark) {
                $caption->removeChild($bookmark);
            }

            $this->setText($xpath, $caption, (string) ($item['caption'] ?? ''));
            $anchor->parentNode->insertBefore($caption, $anchor);
            $this->write($archive, 'word/'.$target, $contents);
        }

        $this->write($archive, 'word/_rels/document.xml.rels', $relationships->saveXML());
        $this->write($archive, '[Content_Types].xml', $contentTypes->saveXML());
    }

    private function imageParagraph(DOMDocument $document, string $relationshipId, int $number, int $width, int $height): DOMElement
    {
        $image = new DOMDocument;
        $image->loadXML(<<<XML
            <w:p xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
                xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"
                xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
                xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
                xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
                <w:pPr><w:keepNext/><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">
                    <wp:extent cx="{$width}" cy="{$height}"/><wp:docPr id="100{$number}" name="Supporting documentation {$number}"/>
                    <wp:cNvGraphicFramePr/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>
                        <pic:nvPicPr><pic:cNvPr id="0" name="Evidence {$number}"/><pic:cNvPicPr/></pic:nvPicPr>
                        <pic:blipFill><a:blip r:embed="{$relationshipId}"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
                        <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="{$width}" cy="{$height}"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>
                    </pic:pic></a:graphicData></a:graphic>
                </wp:inline></w:drawing></w:r>
            </w:p>
            XML, LIBXML_NONET);

        return $document->importNode($image->documentElement, true);
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function xml(string $xml): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The quarterly report contains invalid XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        return [$document, $xpath];
    }

    private function part(ZipArchive $archive, string $name): string
    {
        $contents = $archive->getFromName($name);
        if ($contents === false) {
            throw new RuntimeException('The quarterly report template is incomplete.');
        }

        return $contents;
    }

    private function write(ZipArchive $archive, string $name, string|false $contents): void
    {
        if ($contents === false || ! $archive->addFromString($name, $contents)) {
            throw new RuntimeException('The quarterly report could not be written.');
        }
    }
}
