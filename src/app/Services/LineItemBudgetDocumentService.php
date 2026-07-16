<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class LineItemBudgetDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** @param array<string, mixed> $budget */
    public function generate(array $budget): string
    {
        $templatePath = (string) config('line_item_budget.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Line-Item Budget template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-line-item-budget-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Line-Item Budget document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Line-Item Budget template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');
            $footerXml = $archive->getFromName('word/footer1.xml');
            $settingsXml = $archive->getFromName('word/settings.xml');

            if ($documentXml === false || $footerXml === false || $settingsXml === false) {
                throw new RuntimeException('The Line-Item Budget template structure is incomplete.');
            }

            $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $budget));
            $archive->addFromString('word/footer1.xml', $this->renderFooterXml($footerXml, $budget['project_title']));
            $archive->addFromString('word/settings.xml', $this->enableFieldUpdates($settingsXml));
            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Line-Item Budget could not be read.');
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

    /** @param array<string, mixed> $budget */
    private function renderDocumentXml(string $xml, array $budget): string
    {
        [$document, $xpath] = $this->loadXml($xml);
        $table = $this->firstElement($xpath, '//w:body/w:tbl[1]');
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 45) {
            throw new RuntimeException('The Line-Item Budget table does not match the official form.');
        }

        $this->fillMetadata($xpath, $rows, $budget);
        $this->replaceStaffRows($xpath, $table, $rows, $budget['staff']);
        $this->fillFixedAmounts($xpath, $rows, $budget);
        $this->insertCustomBudgetRows($xpath, $table, $rows[29], $rows[30], $budget['custom_mooe_items']);
        $this->insertCustomBudgetRows($xpath, $table, $rows[36], $rows[37], $budget['custom_co_items']);
        $this->fillTotals($xpath, $rows, $budget);
        $this->fillPreparedBy($xpath, $rows[40], $budget);
        $this->fillResearchOffice($xpath, $rows[42], $rows[43], $budget);
        $this->fillCertifiedCorrect($xpath, $rows[44], $budget);

        return $document->saveXML() ?: throw new RuntimeException('The Line-Item Budget could not be serialized.');
    }

    /** @param array<int, DOMElement> $rows @param array<string, mixed> $budget */
    private function fillMetadata(DOMXPath $xpath, array $rows, array $budget): void
    {
        $programCells = $this->elements($xpath, './w:tc', $rows[0]);
        $projectCells = $this->elements($xpath, './w:tc', $rows[1]);
        $headingCells = $this->elements($xpath, './w:tc', $rows[2]);
        $leaderCells = $this->elements($xpath, './w:tc', $rows[3]);
        $durationCells = $this->elements($xpath, './w:tc', $rows[5]);

        $this->replaceCellText($xpath, $programCells[1], '');
        $this->replaceCellText($xpath, $projectCells[1], $budget['project_title'], false, false, 'left');
        $this->removeParagraphFormatting($xpath, $this->firstElement($xpath, './w:p', $projectCells[1]), ['b', 'bCs']);
        $this->setCellVerticalAlignment($xpath, $projectCells[1], 'center');
        $this->replaceCellText($xpath, $headingCells[1], 'Name', false, false, 'left');
        $this->replaceCellText($xpath, $headingCells[2], 'Campus', false, false, 'left');
        $this->replaceCellText($xpath, $headingCells[3], 'College', false, false, 'left');
        $this->replaceCellText($xpath, $leaderCells[1], $budget['project_leader'], false, false, 'left');
        $this->replaceCellText($xpath, $leaderCells[2], $budget['leader_campus'], false, false, 'left');
        $this->replaceCellText($xpath, $leaderCells[3], $budget['leader_college'], false, false, 'left');
        $this->replaceCellText($xpath, $durationCells[1], $budget['duration'], false, true, 'left');
    }

    /** @param array<int, DOMElement> $rows @param array<int, array<string, string>> $staff */
    private function replaceStaffRows(DOMXPath $xpath, DOMElement $table, array $rows, array $staff): void
    {
        $template = $rows[4];
        $anchor = $rows[5];
        $table->removeChild($template);
        $staffRows = $staff === [] ? [['name' => '', 'campus' => '', 'college' => '']] : $staff;

        foreach ($staffRows as $index => $member) {
            $row = $template->cloneNode(true);

            if (! $row instanceof DOMElement) {
                throw new RuntimeException('A project staff row could not be created.');
            }

            $this->removeWordIdentityAttributes($xpath, $row);
            $cells = $this->elements($xpath, './w:tc', $row);
            $this->replaceCellText($xpath, $cells[0], $index === 0 ? 'Project Staff:' : '', false, false, 'left');
            $this->replaceCellText($xpath, $cells[1], $member['name'], false, false, 'left');
            $this->replaceCellText($xpath, $cells[2], $member['campus'], false, false, 'left');
            $this->replaceCellText($xpath, $cells[3], $member['college'], false, false, 'left');
            $table->insertBefore($row, $anchor);
        }
    }

    /** @param array<int, DOMElement> $rows @param array<string, mixed> $budget */
    private function fillFixedAmounts(DOMXPath $xpath, array $rows, array $budget): void
    {
        $mooeItems = config('line_item_budget.sections.mooe.items');
        $coItems = config('line_item_budget.sections.co.items');

        foreach ($mooeItems as $index => $item) {
            $this->fillAmountCell($xpath, $rows[8 + $index], $budget['amounts'][$item['key']] ?? null);
        }

        foreach ($coItems as $index => $item) {
            $this->fillAmountCell($xpath, $rows[32 + $index], $budget['amounts'][$item['key']] ?? null);
        }
    }

    /** @param array<int, array{particular: string, amount: ?float}> $items */
    private function insertCustomBudgetRows(
        DOMXPath $xpath,
        DOMElement $table,
        DOMElement $template,
        DOMElement $anchor,
        array $items,
    ): void {
        foreach ($items as $item) {
            $row = $template->cloneNode(true);

            if (! $row instanceof DOMElement) {
                throw new RuntimeException('A custom budget row could not be created.');
            }

            $this->removeWordIdentityAttributes($xpath, $row);
            $cells = $this->elements($xpath, './w:tc', $row);
            $this->replaceCellText($xpath, $cells[0], '');
            $this->replaceCellText($xpath, $cells[1], $item['particular'], false, false, 'left');
            $this->replaceCellText($xpath, $cells[2], $this->formatOptionalAmount($item['amount']), false, false, 'right');
            $table->insertBefore($row, $anchor);
        }
    }

    /** @param array<int, DOMElement> $rows @param array<string, mixed> $budget */
    private function fillTotals(DOMXPath $xpath, array $rows, array $budget): void
    {
        $this->fillAmountCell($xpath, $rows[30], $budget['mooe_total'], true);
        $this->fillAmountCell($xpath, $rows[37], $budget['co_total'], true);
        $this->fillAmountCell($xpath, $rows[39], $budget['project_total'], true);
    }

    /** @param array<string, mixed> $budget */
    private function fillPreparedBy(DOMXPath $xpath, DOMElement $row, array $budget): void
    {
        $paragraphs = $this->elements($xpath, './/w:p', $row);
        $this->replaceParagraphByExactText($xpath, $paragraphs, 'NAME', $budget['project_leader'], true);
        $this->replaceParagraphByPrefix($xpath, $paragraphs, 'Date Signed:', 'Date Signed:');
    }

    /** @param array<string, mixed> $budget */
    private function fillResearchOffice(DOMXPath $xpath, DOMElement $levelRow, DOMElement $approvalRow, array $budget): void
    {
        $checkboxes = $this->elements($xpath, './/w:checkBox', $levelRow);

        foreach ($checkboxes as $index => $checkbox) {
            $default = $this->elements($xpath, './w:default', $checkbox)[0] ?? null;

            if ($default instanceof DOMElement) {
                $selected = ($index === 0 && $budget['level_of_call'] === 'central_agency')
                    || ($index === 1 && $budget['level_of_call'] === 'constituent_campus');
                $default->setAttributeNS(self::W, 'w:val', $selected ? '1' : '0');
            }
        }

        if ($budget['approval_body'] === null
            && $budget['resolution_number'] === ''
            && $budget['resolution_year'] === '') {
            return;
        }

        $body = $budget['approval_body'] === 'lrec'
            ? 'Local Research Evaluation Committee as per LREC'
            : 'Research Council as per Research Council';
        $number = $budget['resolution_number'] !== '' ? $budget['resolution_number'] : '_____';
        $year = $budget['resolution_year'] !== '' ? $budget['resolution_year'] : '_____';
        $paragraph = $this->firstElement($xpath, './/w:p', $approvalRow);
        $this->replaceParagraphText($paragraph, "Approved by the {$body} Resolution No. {$number}, S. {$year}");
    }

    /** @param array<string, mixed> $budget */
    private function fillCertifiedCorrect(DOMXPath $xpath, DOMElement $row, array $budget): void
    {
        $paragraphs = $this->elements($xpath, './/w:p', $row);
        $nameIndex = $this->paragraphIndex($xpath, $paragraphs, 'NAME');

        if ($nameIndex === null || ! isset($paragraphs[$nameIndex + 2])) {
            throw new RuntimeException('The certified-correct signature block is incomplete.');
        }

        $this->replaceParagraphText($paragraphs[$nameIndex], $budget['certified_by'], true);
        $this->replaceParagraphText($paragraphs[$nameIndex + 1], $budget['certified_role']);
        $this->replaceParagraphText($paragraphs[$nameIndex + 2], '');
        $this->replaceParagraphByPrefix($xpath, $paragraphs, 'Date Signed:', 'Date Signed:');
    }

    private function fillAmountCell(DOMXPath $xpath, DOMElement $row, mixed $amount, bool $always = false): void
    {
        $cells = $this->elements($xpath, './w:tc', $row);
        $cell = $cells[array_key_last($cells)];
        $text = $always ? number_format((float) $amount, 2) : $this->formatOptionalAmount($amount);
        $this->replaceCellText($xpath, $cell, $text, $always, false, 'right');
    }

    private function formatOptionalAmount(mixed $amount): string
    {
        return $amount === null || $amount === '' ? '' : number_format((float) $amount, 2);
    }

    /** @param array<int, DOMElement> $paragraphs */
    private function replaceParagraphByExactText(DOMXPath $xpath, array $paragraphs, string $needle, string $text, bool $bold = false): void
    {
        $index = $this->paragraphIndex($xpath, $paragraphs, $needle);

        if ($index === null) {
            throw new RuntimeException('A Line-Item Budget text placeholder is missing.');
        }

        $this->replaceParagraphText($paragraphs[$index], $text, $bold);
    }

    /** @param array<int, DOMElement> $paragraphs */
    private function replaceParagraphByPrefix(DOMXPath $xpath, array $paragraphs, string $prefix, string $text): void
    {
        foreach ($paragraphs as $paragraph) {
            if (str_starts_with(trim((string) $xpath->evaluate('string(.)', $paragraph)), $prefix)) {
                $this->replaceParagraphText($paragraph, $text);

                return;
            }
        }
    }

    /** @param array<int, DOMElement> $paragraphs */
    private function paragraphIndex(DOMXPath $xpath, array $paragraphs, string $text): ?int
    {
        foreach ($paragraphs as $index => $paragraph) {
            if (trim((string) $xpath->evaluate('string(.)', $paragraph)) === $text) {
                return $index;
            }
        }

        return null;
    }

    private function replaceCellText(
        DOMXPath $xpath,
        DOMElement $cell,
        string $text,
        bool $bold = false,
        bool $italic = false,
        ?string $alignment = null,
    ): void {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Line-Item Budget cell is missing its paragraph.');
        }

        $this->replaceParagraphText($paragraphs[0], $text, $bold, $italic, $alignment);

        foreach (array_slice($paragraphs, 1) as $paragraph) {
            $cell->removeChild($paragraph);
        }
    }

    private function replaceParagraphText(
        DOMElement $paragraph,
        string $text,
        bool $bold = false,
        bool $italic = false,
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
        $run = $document->createElementNS(self::W, 'w:r');

        if ($bold || $italic) {
            $runProperties = $document->createElementNS(self::W, 'w:rPr');

            if ($bold) {
                $runProperties->appendChild($document->createElementNS(self::W, 'w:b'));
            }

            if ($italic) {
                $runProperties->appendChild($document->createElementNS(self::W, 'w:i'));
            }

            $run->appendChild($runProperties);
        }

        $textNode = $document->createElementNS(self::W, 'w:t');
        $textNode->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
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
            $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
            $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
        }

        foreach (iterator_to_array($paragraphProperties->childNodes) as $child) {
            if ($child instanceof DOMElement && $child->localName === 'jc') {
                $paragraphProperties->removeChild($child);
            }
        }

        $justification = $document->createElementNS(self::W, 'w:jc');
        $justification->setAttributeNS(self::W, 'w:val', $alignment);
        $paragraphProperties->appendChild($justification);
    }

    private function setCellVerticalAlignment(DOMXPath $xpath, DOMElement $cell, string $alignment): void
    {
        $properties = $this->firstElement($xpath, './w:tcPr', $cell);
        $element = $this->elements($xpath, './w:vAlign', $properties)[0] ?? null;

        if (! $element instanceof DOMElement) {
            $element = $cell->ownerDocument->createElementNS(self::W, 'w:vAlign');
            $properties->appendChild($element);
        }

        $element->setAttributeNS(self::W, 'w:val', $alignment);
    }

    /** @param list<string> $propertyNames */
    private function removeParagraphFormatting(DOMXPath $xpath, DOMElement $paragraph, array $propertyNames): void
    {
        foreach ($this->elements($xpath, './/w:pPr/w:rPr/*', $paragraph) as $property) {
            if (in_array($property->localName, $propertyNames, true)) {
                $property->parentNode?->removeChild($property);
            }
        }
    }

    private function renderFooterXml(string $xml, string $projectTitle): string
    {
        [$document, $xpath] = $this->loadXml($xml);
        $paragraph = $this->firstElement($xpath, '//w:p[1]');
        $textNodes = $xpath->query('.//w:t', $paragraph);

        if ($textNodes->length < 2) {
            throw new RuntimeException('The Line-Item Budget footer title is missing.');
        }

        $textNodes->item(0)->nodeValue = $projectTitle;
        $textNodes->item(1)->nodeValue = '';

        return $document->saveXML() ?: throw new RuntimeException('The Line-Item Budget footer could not be serialized.');
    }

    private function enableFieldUpdates(string $xml): string
    {
        [$document, $xpath] = $this->loadXml($xml);
        $settings = $this->firstElement($xpath, '/w:settings');
        $updateFields = $this->elements($xpath, './w:updateFields', $settings)[0] ?? null;

        if (! $updateFields instanceof DOMElement) {
            $updateFields = $document->createElementNS(self::W, 'w:updateFields');
            $settings->appendChild($updateFields);
        }

        $updateFields->setAttributeNS(self::W, 'w:val', 'true');

        return $document->saveXML() ?: throw new RuntimeException('The document settings could not be serialized.');
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function loadXml(string $xml): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The official Line-Item Budget contains invalid XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('w14', self::W14);

        return [$document, $xpath];
    }

    private function removeWordIdentityAttributes(DOMXPath $xpath, DOMElement $row): void
    {
        foreach ($this->elements($xpath, './/*', $row) as $element) {
            foreach ([[self::W14, 'paraId'], [self::W14, 'textId'], [self::W, 'rsidR'], [self::W, 'rsidRDefault'], [self::W, 'rsidTr']] as [$namespace, $attribute]) {
                $element->removeAttributeNS($namespace, $attribute);
            }
        }
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
    {
        $node = $xpath->query($query, $context)->item(0);

        if (! $node instanceof DOMElement) {
            throw new RuntimeException('The official Line-Item Budget structure is incomplete.');
        }

        return $node;
    }

    /** @return array<int, DOMElement> */
    private function elements(DOMXPath $xpath, string $query, ?DOMNode $context = null): array
    {
        return array_values(array_filter(
            iterator_to_array($xpath->query($query, $context)),
            fn (DOMNode $node): bool => $node instanceof DOMElement,
        ));
    }
}
