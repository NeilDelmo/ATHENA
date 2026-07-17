<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class WorkPlanDocumentService
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const WORD_2010_NAMESPACE = 'http://schemas.microsoft.com/office/word/2010/wordml';

    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @param  array<string, mixed>  $workPlan
     */
    public function generate(array $workPlan): string
    {
        $templatePath = (string) config('work_plan.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Work Plan template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-work-plan-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Work Plan document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Work Plan template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The Work Plan template document body is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $workPlan))) {
                throw new RuntimeException('The generated Work Plan could not be written.');
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Work Plan could not be read.');
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
     * @param  array<string, mixed>  $workPlan
     */
    private function renderDocumentXml(string $documentXml, array $workPlan): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The Work Plan template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NAMESPACE);
        $xpath->registerNamespace('w14', self::WORD_2010_NAMESPACE);

        $table = $this->firstElement($xpath, '//w:body/w:tbl[1]');
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) < 12) {
            throw new RuntimeException('The Work Plan template table structure is incomplete.');
        }

        $this->fillMetadata($xpath, $rows, $workPlan);
        $this->replaceObjectiveRows($xpath, $table, $rows, $workPlan['entries']);
        $this->fillSignatures($xpath, $rows[11], $workPlan);

        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Work Plan document XML could not be serialized.');
        }

        return $renderedXml;
    }

    /**
     * @param  array<int, DOMElement>  $rows
     * @param  array<string, mixed>  $workPlan
     */
    private function fillMetadata(DOMXPath $xpath, array $rows, array $workPlan): void
    {
        $titleCells = $this->elements($xpath, './w:tc', $rows[0]);
        $projectTitleCells = $this->elements($xpath, './w:tc', $rows[1]);
        $durationCells = $this->elements($xpath, './w:tc', $rows[2]);

        $this->replaceCellText($xpath, $titleCells[1], '');
        $this->replaceCellText($xpath, $projectTitleCells[1], $workPlan['project_title'], true, 'center');
        $this->setCellVerticalAlignment($xpath, $projectTitleCells[1], 'center');

        $durationParagraphs = $this->elements($xpath, './w:p', $durationCells[0]);

        if (count($durationParagraphs) < 2) {
            throw new RuntimeException('The Work Plan duration slot is missing.');
        }

        $this->replaceParagraphText($durationParagraphs[1], $workPlan['total_duration_label']);
        $this->appendMetadataValue($xpath, $durationCells[1], $workPlan['planned_start']);
        $this->appendMetadataValue($xpath, $durationCells[2], $workPlan['planned_end']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, DOMElement>  $sourceRows
     */
    private function replaceObjectiveRows(
        DOMXPath $xpath,
        DOMElement $table,
        array $sourceRows,
        array $entries,
    ): void {
        $rowTemplate = $sourceRows[5]->cloneNode(true);
        $signatureRow = $sourceRows[11];

        foreach (array_slice($sourceRows, 5, 6) as $placeholderRow) {
            $table->removeChild($placeholderRow);
        }

        foreach ($entries as $entry) {
            $row = $rowTemplate->cloneNode(true);

            if (! $row instanceof DOMElement) {
                throw new RuntimeException('A Work Plan objective row could not be cloned.');
            }

            $this->removeWordIdentityAttributes($xpath, $row);
            $cells = $this->elements($xpath, './w:tc', $row);

            if (count($cells) !== 15) {
                throw new RuntimeException('The Work Plan objective row structure is incomplete.');
            }

            $this->replaceCellText($xpath, $cells[0], $entry['objective'], false, 'center');
            $this->replaceCellText($xpath, $cells[1], $entry['expected_output'], false, 'center');
            $this->replaceCellText($xpath, $cells[2], $entry['activity'], false, 'left');

            for ($month = 1; $month <= 12; $month++) {
                $this->setMonthShading(
                    $xpath,
                    $cells[$month + 2],
                    in_array($month, $entry['months'], true),
                );
            }

            $table->insertBefore($row, $signatureRow);
        }
    }

    /**
     * @param  array<string, mixed>  $workPlan
     */
    private function fillSignatures(DOMXPath $xpath, DOMElement $signatureRow, array $workPlan): void
    {
        $cells = $this->elements($xpath, './w:tc', $signatureRow);

        if (count($cells) !== 2) {
            throw new RuntimeException('The Work Plan signature structure is incomplete.');
        }

        $preparedParagraphs = $this->elements($xpath, './w:p', $cells[0]);
        $verifiedParagraphs = $this->elements($xpath, './w:p', $cells[1]);

        if (count($preparedParagraphs) < 7 || count($verifiedParagraphs) < 7) {
            throw new RuntimeException('The Work Plan signature slots are incomplete.');
        }

        [$preparedName, $preparedRole, $preparedDate] = $this->signatureSlots($xpath, $preparedParagraphs);
        [$verifiedName, $verifiedRole, $verifiedDate] = $this->signatureSlots($xpath, $verifiedParagraphs);

        $this->replaceParagraphText($preparedName, $workPlan['prepared_by'], true);
        $this->replaceParagraphText($preparedRole, 'Project Leader');
        $this->replaceParagraphText(
            $preparedDate,
            $this->dateSignedLabel(),
        );

        $this->replaceParagraphText($verifiedName, $workPlan['verified_by'], true);
        $this->replaceParagraphText($verifiedRole, $workPlan['verified_role']);
        $this->replaceParagraphText(
            $verifiedDate,
            $this->dateSignedLabel(),
        );
    }

    /**
     * @param  array<int, DOMElement>  $paragraphs
     * @return array{DOMElement, DOMElement, DOMElement}
     */
    private function signatureSlots(DOMXPath $xpath, array $paragraphs): array
    {
        $nameIndex = null;
        $dateParagraph = null;

        foreach ($paragraphs as $index => $paragraph) {
            $text = trim((string) $xpath->evaluate('string(.)', $paragraph));

            if ($text === 'NAME') {
                $nameIndex = $index;
            }

            if (str_starts_with($text, 'Date Signed:')) {
                $dateParagraph = $paragraph;
            }
        }

        if ($nameIndex === null || $nameIndex === 0 || ! isset($paragraphs[$nameIndex + 1]) || ! $dateParagraph instanceof DOMElement) {
            throw new RuntimeException('The Work Plan signature placeholders are incomplete.');
        }

        $signatureLine = trim((string) $xpath->evaluate('string(.)', $paragraphs[$nameIndex - 1]));

        if (preg_match('/^_{10,}$/', $signatureLine) !== 1) {
            throw new RuntimeException('The Work Plan handwritten signature line is missing.');
        }

        return [$paragraphs[$nameIndex], $paragraphs[$nameIndex + 1], $dateParagraph];
    }

    private function replaceCellText(
        DOMXPath $xpath,
        DOMElement $cell,
        string $text,
        bool $bold = false,
        ?string $alignment = null,
    ): void {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Work Plan text slot is missing its paragraph.');
        }

        $this->replaceParagraphText($paragraphs[0], $text, $bold, $alignment);

        foreach (array_slice($paragraphs, 1) as $paragraph) {
            $cell->removeChild($paragraph);
        }
    }

    private function appendMetadataValue(DOMXPath $xpath, DOMElement $cell, string $value): void
    {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Work Plan date slot is missing its label.');
        }

        foreach (array_slice($paragraphs, 1) as $paragraph) {
            $cell->removeChild($paragraph);
        }

        $valueParagraph = $paragraphs[0]->cloneNode(true);

        if (! $valueParagraph instanceof DOMElement) {
            throw new RuntimeException('A Work Plan date value slot could not be created.');
        }

        $this->replaceParagraphText($valueParagraph, $value, false, 'center');
        $cell->appendChild($valueParagraph);
    }

    private function replaceParagraphText(
        DOMElement $paragraph,
        string $text,
        bool $bold = false,
        ?string $alignment = null,
    ): void {
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'pPr') {
                $paragraph->removeChild($child);
            }
        }

        if ($alignment !== null) {
            $this->setParagraphAlignment($paragraph, $alignment);
        }

        if ($text === '') {
            return;
        }

        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');

        if ($bold) {
            $runProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:rPr');
            $runProperties->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:b'));
            $run->appendChild($runProperties);
        }

        $lines = preg_split('/\R/u', $text) ?: [$text];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $run->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:br'));
            }

            $textNode = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');

            if ($line !== trim($line)) {
                $textNode->setAttributeNS(self::XML_NAMESPACE, 'xml:space', 'preserve');
            }

            $textNode->appendChild($document->createTextNode($line));
            $run->appendChild($textNode);
        }

        $paragraph->appendChild($run);
    }

    private function setParagraphAlignment(DOMElement $paragraph, string $alignment): void
    {
        $document = $paragraph->ownerDocument;
        $paragraphProperties = null;

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'pPr') {
                $paragraphProperties = $child;
                break;
            }
        }

        if (! $paragraphProperties instanceof DOMElement) {
            $paragraphProperties = $document->createElementNS(self::WORD_NAMESPACE, 'w:pPr');
            $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
        }

        $justification = null;

        foreach ($paragraphProperties->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'jc') {
                $justification = $child;
                break;
            }
        }

        if (! $justification instanceof DOMElement) {
            $justification = $document->createElementNS(self::WORD_NAMESPACE, 'w:jc');
            $paragraphProperties->appendChild($justification);
        }

        $justification->setAttributeNS(self::WORD_NAMESPACE, 'w:val', $alignment);
    }

    private function setCellVerticalAlignment(DOMXPath $xpath, DOMElement $cell, string $alignment): void
    {
        $cellProperties = $this->firstElement($xpath, './w:tcPr', $cell);
        $verticalAlignment = $this->elements($xpath, './w:vAlign', $cellProperties)[0] ?? null;

        if (! $verticalAlignment instanceof DOMElement) {
            $verticalAlignment = $cell->ownerDocument->createElementNS(self::WORD_NAMESPACE, 'w:vAlign');
            $cellProperties->appendChild($verticalAlignment);
        }

        $verticalAlignment->setAttributeNS(self::WORD_NAMESPACE, 'w:val', $alignment);
    }

    private function setMonthShading(DOMXPath $xpath, DOMElement $cell, bool $selected): void
    {
        $cellProperties = $this->firstElement($xpath, './w:tcPr', $cell);
        $shadings = $this->elements($xpath, './w:shd', $cellProperties);

        foreach ($shadings as $shading) {
            $cellProperties->removeChild($shading);
        }

        if (! $selected) {
            return;
        }

        $shading = $cell->ownerDocument->createElementNS(self::WORD_NAMESPACE, 'w:shd');
        $shading->setAttributeNS(self::WORD_NAMESPACE, 'w:val', 'clear');
        $shading->setAttributeNS(self::WORD_NAMESPACE, 'w:color', 'auto');
        $shading->setAttributeNS(self::WORD_NAMESPACE, 'w:fill', (string) config('work_plan.gantt_fill'));
        $cellProperties->appendChild($shading);
    }

    private function removeWordIdentityAttributes(DOMXPath $xpath, DOMElement $row): void
    {
        foreach ($this->elements($xpath, './/*', $row) as $element) {
            foreach ([
                [self::WORD_2010_NAMESPACE, 'paraId'],
                [self::WORD_2010_NAMESPACE, 'textId'],
                [self::WORD_NAMESPACE, 'rsidR'],
                [self::WORD_NAMESPACE, 'rsidRDefault'],
                [self::WORD_NAMESPACE, 'rsidTr'],
            ] as [$namespace, $attribute]) {
                $element->removeAttributeNS($namespace, $attribute);
            }
        }
    }

    private function dateSignedLabel(): string
    {
        return 'Date Signed:     ';
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        if (! $element instanceof DOMElement) {
            throw new RuntimeException('The Work Plan template structure is incomplete.');
        }

        return $element;
    }

    /**
     * @return array<int, DOMElement>
     */
    private function elements(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        return array_values(array_filter(
            iterator_to_array($xpath->query($query, $context)),
            fn (DOMNode $node): bool => $node instanceof DOMElement,
        ));
    }
}
