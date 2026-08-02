<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ProgressReportDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    private const PACKAGE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const CONTENT_TYPES = 'http://schemas.openxmlformats.org/package/2006/content-types';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    private const MAX_IMAGE_WIDTH_EMU = 5715000;

    private const MAX_IMAGE_HEIGHT_EMU = 6629400;

    public function __construct(
        private readonly FilesystemManager $filesystem,
    ) {}

    public function generate(ProjectNarrativeReport $report): string
    {
        $report->loadMissing(['topic.user', 'submitter']);
        $templatePath = (string) config('progress_report.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Progress Report template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-progress-report-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Progress Report document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Progress Report template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');
            $footerXml = $archive->getFromName('word/footer1.xml');
            $relationshipsXml = $archive->getFromName('word/_rels/document.xml.rels');
            $contentTypesXml = $archive->getFromName('[Content_Types].xml');

            if ($documentXml === false
                || $footerXml === false
                || $relationshipsXml === false
                || $contentTypesXml === false) {
                throw new RuntimeException('The Progress Report package is incomplete.');
            }

            $figures = $this->loadFigures($report);
            [$relationshipsXml, $figures] = $this->registerFigureRelationships($relationshipsXml, $figures);
            $contentTypesXml = $this->registerFigureContentTypes($contentTypesXml, $figures);

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $report, $figures))
                || ! $archive->addFromString('word/footer1.xml', $this->renderFooterXml($footerXml, $report))
                || ! $archive->addFromString('word/_rels/document.xml.rels', $relationshipsXml)
                || ! $archive->addFromString('[Content_Types].xml', $contentTypesXml)) {
                throw new RuntimeException('The generated Progress Report could not be written.');
            }

            foreach ($figures as $figure) {
                if (! $archive->addFromString('word/'.$figure['target'], $figure['contents'])) {
                    throw new RuntimeException('A Progress Report figure could not be embedded.');
                }
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Progress Report could not be read.');
            }

            return $contents;
        } finally {
            if ($archiveIsOpen) {
                $archive->close();
            }

            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $figures
     */
    private function renderDocumentXml(
        string $xml,
        ProjectNarrativeReport $report,
        array $figures,
    ): string {
        [$document, $xpath] = $this->documentAndXPath($xml, 'document');
        $table = $xpath->query('/w:document/w:body/w:tbl[1]')->item(0);

        if (! $table instanceof DOMElement) {
            throw new RuntimeException('The Progress Report table is missing.');
        }

        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 26) {
            throw new RuntimeException('The Progress Report table structure has changed.');
        }

        $duration = $report->topic->estimated_duration_months
            ? $report->topic->estimated_duration_months.' months '
            : '';
        $values = [
            2 => 'Submission Date: '.$report->submission_date->format('F j, Y'),
            4 => $report->topic->title,
            6 => $report->researchers,
            8 => $duration.'('.$report->implementation_start->format('F j, Y').' - '.$report->implementation_end->format('F j, Y').')',
            10 => 'P '.number_format((float) $report->budget, 2),
            12 => $report->funding_agency,
            18 => $report->objectives,
            20 => $report->methodology,
            22 => $report->results_discussion,
        ];

        foreach ($values as $rowIndex => $value) {
            $cells = $this->cells($xpath, $rows[$rowIndex], 1);
            $this->replaceCellText($xpath, $cells[0], $value);
        }

        $this->fillAccomplishments($xpath, $rows[14], $report);
        $this->fillNarrativeBelowHeading($xpath, $rows[15], $report->introduction);
        $this->fillNarrativeBelowHeading($xpath, $rows[16], $report->rationale ?? '');

        $figureNumber = 1;
        $this->appendFigures($xpath, $rows[20], $figures, 'methodology', $figureNumber);
        $this->appendFigures($xpath, $rows[22], $figures, 'results_discussion', $figureNumber);
        $this->fillPreparedBy($xpath, $rows[24], $report);
        $this->splitSignOffPage($xpath, $table, $rows);

        return $this->serialized($document, 'document');
    }

    /** @param list<DOMElement> $rows */
    private function splitSignOffPage(DOMXPath $xpath, DOMElement $table, array $rows): void
    {
        $tableProperties = $xpath->query('./w:tblPr', $table)->item(0);
        $tableGrid = $xpath->query('./w:tblGrid', $table)->item(0);
        $parent = $table->parentNode;

        if (! $tableProperties instanceof DOMElement
            || ! $tableGrid instanceof DOMElement
            || ! $parent instanceof DOMNode) {
            throw new RuntimeException('The Progress Report sign-off structure is incomplete.');
        }

        $document = $table->ownerDocument;
        $signOffTable = $document->createElementNS(self::W, 'w:tbl');
        $signOffTable->appendChild($tableProperties->cloneNode(true));
        $signOffTable->appendChild($tableGrid->cloneNode(true));

        foreach (array_slice($rows, 23) as $row) {
            $signOffTable->appendChild($row);
        }

        $pageBreak = $document->createElementNS(self::W, 'w:p');
        $run = $document->createElementNS(self::W, 'w:r');
        $break = $document->createElementNS(self::W, 'w:br');
        $break->setAttributeNS(self::W, 'w:type', 'page');
        $run->appendChild($break);
        $pageBreak->appendChild($run);

        $insertionPoint = $table->nextSibling;
        $parent->insertBefore($pageBreak, $insertionPoint);
        $parent->insertBefore($signOffTable, $insertionPoint);
    }

    private function fillAccomplishments(DOMXPath $xpath, DOMElement $row, ProjectNarrativeReport $report): void
    {
        $cell = $this->cells($xpath, $row, 1)[0];
        $table = $xpath->query('./w:tbl[1]', $cell)->item(0);

        if (! $table instanceof DOMElement) {
            throw new RuntimeException('The Progress Report accomplishment table is missing.');
        }

        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) < 2) {
            throw new RuntimeException('The Progress Report accomplishment table is incomplete.');
        }

        $templateRow = $rows[1]->cloneNode(true);

        foreach (array_slice($rows, 1) as $dataRow) {
            $table->removeChild($dataRow);
        }

        $accomplishments = $report->accomplishments ?: [[
            'objective' => '',
            'target' => '',
            'actual' => $report->accomplishment_summary,
        ]];

        foreach ($accomplishments as $accomplishment) {
            $dataRow = $templateRow->cloneNode(true);

            if (! $dataRow instanceof DOMElement) {
                throw new RuntimeException('A Progress Report accomplishment row could not be created.');
            }

            $cells = $this->cells($xpath, $dataRow, 3);
            $this->replaceCellText($xpath, $cells[0], (string) ($accomplishment['objective'] ?? ''));
            $this->replaceCellText($xpath, $cells[1], (string) ($accomplishment['target'] ?? ''));
            $this->replaceCellText($xpath, $cells[2], (string) ($accomplishment['actual'] ?? ''));
            $table->appendChild($dataRow);
        }
    }

    private function fillNarrativeBelowHeading(DOMXPath $xpath, DOMElement $row, string $text): void
    {
        $cell = $this->cells($xpath, $row, 1)[0];
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if (count($paragraphs) < 2) {
            throw new RuntimeException('A Progress Report narrative heading is incomplete.');
        }

        $this->replaceParagraphText($xpath, $paragraphs[1], $text);

        foreach (array_slice($paragraphs, 2) as $paragraph) {
            $cell->removeChild($paragraph);
        }
    }

    private function fillPreparedBy(DOMXPath $xpath, DOMElement $row, ProjectNarrativeReport $report): void
    {
        $cells = $this->cells($xpath, $row, 1);
        $paragraphs = $this->elements($xpath, './w:p', $cells[0]);

        if (count($paragraphs) < 8) {
            throw new RuntimeException('The Progress Report prepared-by block is incomplete.');
        }

        $leader = $report->submitter?->name ?? $report->topic->user?->name ?? '';
        $dateSigned = $report->prepared_by_date_signed?->format('F j, Y') ?? '';
        $this->replaceParagraphText($xpath, $paragraphs[0], 'Prepared by:', true);
        $this->replaceParagraphText($xpath, $paragraphs[5], Str::upper($leader), true);
        $this->replaceParagraphText($xpath, $paragraphs[7], 'Date Signed: '.$dateSigned, true);
    }

    /**
     * @param  list<array<string, mixed>>  $figures
     */
    private function appendFigures(
        DOMXPath $xpath,
        DOMElement $row,
        array $figures,
        string $section,
        int &$figureNumber,
    ): void {
        $cell = $this->cells($xpath, $row, 1)[0];

        foreach ($figures as $figure) {
            if ($figure['section'] !== $section) {
                continue;
            }

            $cell->appendChild($this->figureParagraph($cell->ownerDocument, $figure, $figureNumber));
            $cell->appendChild($this->captionParagraph(
                $cell->ownerDocument,
                'Figure '.$figureNumber.'. '.$figure['caption'],
            ));
            $figureNumber++;
        }
    }

    /**
     * @param  array<string, mixed>  $figure
     */
    private function figureParagraph(DOMDocument $document, array $figure, int $figureNumber): DOMElement
    {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
        $justification = $document->createElementNS(self::W, 'w:jc');
        $justification->setAttributeNS(self::W, 'w:val', 'center');
        $paragraphProperties->appendChild($justification);
        $paragraph->appendChild($paragraphProperties);

        $run = $document->createElementNS(self::W, 'w:r');
        $drawing = $document->createElementNS(self::W, 'w:drawing');
        $inline = $document->createElementNS(self::WP, 'wp:inline');
        $inline->setAttribute('distT', '0');
        $inline->setAttribute('distB', '0');
        $inline->setAttribute('distL', '0');
        $inline->setAttribute('distR', '0');

        $extent = $document->createElementNS(self::WP, 'wp:extent');
        $extent->setAttribute('cx', (string) $figure['width']);
        $extent->setAttribute('cy', (string) $figure['height']);
        $inline->appendChild($extent);

        $effectExtent = $document->createElementNS(self::WP, 'wp:effectExtent');
        foreach (['l', 't', 'r', 'b'] as $side) {
            $effectExtent->setAttribute($side, '0');
        }
        $inline->appendChild($effectExtent);

        $documentProperties = $document->createElementNS(self::WP, 'wp:docPr');
        $documentProperties->setAttribute('id', (string) (1000 + $figureNumber));
        $documentProperties->setAttribute('name', 'Progress report figure '.$figureNumber);
        $inline->appendChild($documentProperties);
        $inline->appendChild($document->createElementNS(self::WP, 'wp:cNvGraphicFramePr'));

        $graphic = $document->createElementNS(self::A, 'a:graphic');
        $graphicData = $document->createElementNS(self::A, 'a:graphicData');
        $graphicData->setAttribute('uri', self::PIC);
        $picture = $document->createElementNS(self::PIC, 'pic:pic');

        $nonVisual = $document->createElementNS(self::PIC, 'pic:nvPicPr');
        $nonVisualProperties = $document->createElementNS(self::PIC, 'pic:cNvPr');
        $nonVisualProperties->setAttribute('id', '0');
        $nonVisualProperties->setAttribute('name', basename((string) $figure['target']));
        $nonVisual->appendChild($nonVisualProperties);
        $nonVisual->appendChild($document->createElementNS(self::PIC, 'pic:cNvPicPr'));
        $picture->appendChild($nonVisual);

        $blipFill = $document->createElementNS(self::PIC, 'pic:blipFill');
        $blip = $document->createElementNS(self::A, 'a:blip');
        $blip->setAttributeNS(self::R, 'r:embed', (string) $figure['relationship_id']);
        $blipFill->appendChild($blip);
        $stretch = $document->createElementNS(self::A, 'a:stretch');
        $stretch->appendChild($document->createElementNS(self::A, 'a:fillRect'));
        $blipFill->appendChild($stretch);
        $picture->appendChild($blipFill);

        $shapeProperties = $document->createElementNS(self::PIC, 'pic:spPr');
        $transform = $document->createElementNS(self::A, 'a:xfrm');
        $offset = $document->createElementNS(self::A, 'a:off');
        $offset->setAttribute('x', '0');
        $offset->setAttribute('y', '0');
        $transformExtent = $document->createElementNS(self::A, 'a:ext');
        $transformExtent->setAttribute('cx', (string) $figure['width']);
        $transformExtent->setAttribute('cy', (string) $figure['height']);
        $transform->appendChild($offset);
        $transform->appendChild($transformExtent);
        $shapeProperties->appendChild($transform);
        $geometry = $document->createElementNS(self::A, 'a:prstGeom');
        $geometry->setAttribute('prst', 'rect');
        $geometry->appendChild($document->createElementNS(self::A, 'a:avLst'));
        $shapeProperties->appendChild($geometry);
        $picture->appendChild($shapeProperties);

        $graphicData->appendChild($picture);
        $graphic->appendChild($graphicData);
        $inline->appendChild($graphic);
        $drawing->appendChild($inline);
        $run->appendChild($drawing);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    private function captionParagraph(DOMDocument $document, string $caption): DOMElement
    {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
        $justification = $document->createElementNS(self::W, 'w:jc');
        $justification->setAttributeNS(self::W, 'w:val', 'center');
        $paragraphProperties->appendChild($justification);
        $spacing = $document->createElementNS(self::W, 'w:spacing');
        $spacing->setAttributeNS(self::W, 'w:before', '80');
        $spacing->setAttributeNS(self::W, 'w:after', '160');
        $paragraphProperties->appendChild($spacing);
        $paragraph->appendChild($paragraphProperties);

        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $document->createElementNS(self::W, 'w:rPr');
        $runProperties->appendChild($document->createElementNS(self::W, 'w:i'));
        $runProperties->appendChild($document->createElementNS(self::W, 'w:iCs'));
        $run->appendChild($runProperties);
        $text = $document->createElementNS(self::W, 'w:t');
        $text->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $text->appendChild($document->createTextNode($caption));
        $run->appendChild($text);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadFigures(ProjectNarrativeReport $report): array
    {
        $disk = $this->filesystem->disk('local');
        $figures = [];

        foreach ($report->photos ?? [] as $index => $photo) {
            $path = is_array($photo) ? ($photo['path'] ?? '') : '';

            if ($path === '' || ! $disk->exists($path)) {
                throw new RuntimeException('A submitted Progress Report figure is unavailable.');
            }

            $contents = $disk->get($path);
            $dimensions = getimagesizefromstring($contents);

            if ($dimensions === false || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                throw new RuntimeException('A submitted Progress Report figure is invalid.');
            }

            $extension = $dimensions[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
            $contentType = $dimensions[2] === IMAGETYPE_PNG ? 'image/png' : 'image/jpeg';
            [$width, $height] = $this->scaledImageDimensions($dimensions[0], $dimensions[1]);

            $figures[] = [
                'contents' => $contents,
                'target' => 'media/progress-figure-'.($index + 1).'.'.$extension,
                'extension' => $extension,
                'content_type' => $contentType,
                'width' => $width,
                'height' => $height,
                'caption' => (string) ($photo['caption'] ?? ''),
                'section' => in_array($photo['section'] ?? null, ['methodology', 'results_discussion'], true)
                    ? $photo['section']
                    : 'results_discussion',
            ];
        }

        return $figures;
    }

    /** @return array{int, int} */
    private function scaledImageDimensions(int $pixelWidth, int $pixelHeight): array
    {
        $width = $pixelWidth * 9525;
        $height = $pixelHeight * 9525;
        $scale = min(
            1,
            self::MAX_IMAGE_WIDTH_EMU / $width,
            self::MAX_IMAGE_HEIGHT_EMU / $height,
        );

        return [(int) round($width * $scale), (int) round($height * $scale)];
    }

    /**
     * @param  list<array<string, mixed>>  $figures
     * @return array{string, list<array<string, mixed>>}
     */
    private function registerFigureRelationships(string $xml, array $figures): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The Progress Report relationships are invalid.');
        }

        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            throw new RuntimeException('The Progress Report relationships are missing.');
        }

        $nextId = 1;

        foreach ($document->getElementsByTagNameNS(self::PACKAGE_RELATIONSHIPS, 'Relationship') as $relationship) {
            if (preg_match('/^rId(\d+)$/', $relationship->getAttribute('Id'), $matches) === 1) {
                $nextId = max($nextId, ((int) $matches[1]) + 1);
            }
        }

        foreach ($figures as &$figure) {
            $relationshipId = 'rId'.$nextId++;
            $relationship = $document->createElementNS(self::PACKAGE_RELATIONSHIPS, 'Relationship');
            $relationship->setAttribute('Id', $relationshipId);
            $relationship->setAttribute('Type', self::R.'/image');
            $relationship->setAttribute('Target', (string) $figure['target']);
            $root->appendChild($relationship);
            $figure['relationship_id'] = $relationshipId;
        }
        unset($figure);

        return [$this->serialized($document, 'relationships'), $figures];
    }

    /**
     * @param  list<array<string, mixed>>  $figures
     */
    private function registerFigureContentTypes(string $xml, array $figures): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The Progress Report content types are invalid.');
        }

        $root = $document->documentElement;

        if (! $root instanceof DOMElement) {
            throw new RuntimeException('The Progress Report content types are missing.');
        }

        $registered = [];

        foreach ($document->getElementsByTagNameNS(self::CONTENT_TYPES, 'Default') as $default) {
            $registered[Str::lower($default->getAttribute('Extension'))] = true;
        }

        foreach ($figures as $figure) {
            $extension = (string) $figure['extension'];

            if (isset($registered[$extension])) {
                continue;
            }

            $default = $document->createElementNS(self::CONTENT_TYPES, 'Default');
            $default->setAttribute('Extension', $extension);
            $default->setAttribute('ContentType', (string) $figure['content_type']);
            $root->appendChild($default);
            $registered[$extension] = true;
        }

        return $this->serialized($document, 'content types');
    }

    private function renderFooterXml(string $xml, ProjectNarrativeReport $report): string
    {
        [$document, $xpath] = $this->documentAndXPath($xml, 'footer');
        $replacement = 'Tracking No. '.($report->tracking_number ?: '__________________________').' ';

        foreach ($xpath->query('//w:t') as $text) {
            if ($text instanceof DOMElement && Str::contains($text->textContent, 'Tracking No.')) {
                $text->setAttributeNS(self::XML, 'xml:space', 'preserve');
                $text->nodeValue = $replacement;

                return $this->serialized($document, 'footer');
            }
        }

        throw new RuntimeException('The Progress Report tracking number slot is missing.');
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function documentAndXPath(string $xml, string $part): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException("The Progress Report template contains invalid {$part} XML.");
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        return [$document, $xpath];
    }

    /** @return list<DOMElement> */
    private function cells(DOMXPath $xpath, DOMElement $row, int $expected): array
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== $expected) {
            throw new RuntimeException('A Progress Report table row is malformed.');
        }

        return $cells;
    }

    private function replaceCellText(DOMXPath $xpath, DOMElement $cell, string $text): void
    {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Progress Report table cell has no paragraph.');
        }

        $this->replaceParagraphText($xpath, $paragraphs[0], $text);

        foreach (array_slice($paragraphs, 1) as $paragraph) {
            $cell->removeChild($paragraph);
        }
    }

    private function replaceParagraphText(
        DOMXPath $xpath,
        DOMElement $paragraph,
        string $text,
        bool $preserveBold = false,
    ): void {
        $runProperties = $xpath->query('./w:r[1]/w:rPr', $paragraph)->item(0)
            ?? $xpath->query('./w:pPr/w:rPr', $paragraph)->item(0);
        $runProperties = $runProperties?->cloneNode(true);

        if (! $preserveBold && $runProperties instanceof DOMElement) {
            $boldNodes = [];

            foreach ($xpath->query('./w:b | ./w:bCs', $runProperties) as $boldNode) {
                $boldNodes[] = $boldNode;
            }

            foreach ($boldNodes as $boldNode) {
                $runProperties->removeChild($boldNode);
            }
        }

        $runs = [];

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            $runs[] = $run;
        }

        foreach ($runs as $run) {
            $paragraph->removeChild($run);
        }

        if ($text === '') {
            return;
        }

        $run = $paragraph->ownerDocument->createElementNS(self::W, 'w:r');

        if ($runProperties instanceof DOMNode) {
            $run->appendChild($runProperties);
        }

        $lines = preg_split('/\R/u', $text) ?: [$text];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $run->appendChild($paragraph->ownerDocument->createElementNS(self::W, 'w:br'));
            }

            $textElement = $paragraph->ownerDocument->createElementNS(self::W, 'w:t');
            $textElement->setAttributeNS(self::XML, 'xml:space', 'preserve');
            $textElement->appendChild($paragraph->ownerDocument->createTextNode($line));
            $run->appendChild($textElement);
        }

        $paragraph->appendChild($run);
    }

    /** @return list<DOMElement> */
    private function elements(DOMXPath $xpath, string $query, DOMElement $context): array
    {
        $elements = [];

        foreach ($xpath->query($query, $context) as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function serialized(DOMDocument $document, string $part): string
    {
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException("The Progress Report {$part} XML could not be serialized.");
        }

        return $xml;
    }
}
