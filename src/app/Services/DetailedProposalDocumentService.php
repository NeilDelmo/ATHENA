<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class DetailedProposalDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** @param array<string, mixed> $proposal */
    public function generate(array $proposal): string
    {
        $templatePath = (string) config('detailed_proposal.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Detailed Research Proposal template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-detailed-proposal-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Detailed Research Proposal document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Detailed Research Proposal template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The Detailed Research Proposal document body is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $proposal))) {
                throw new RuntimeException('The generated Detailed Research Proposal could not be written.');
            }

            $settingsXml = $archive->getFromName('word/settings.xml');

            if ($settingsXml !== false
                && ! $archive->addFromString('word/settings.xml', $this->renderSettingsXml($settingsXml))) {
                throw new RuntimeException('The Detailed Research Proposal field settings could not be written.');
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Detailed Research Proposal could not be read.');
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

    /** @param array<string, mixed> $proposal */
    private function renderDocumentXml(string $documentXml, array $proposal): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The Detailed Research Proposal template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('w14', self::W14);
        $table = $this->firstElement($xpath, '//w:body/w:tbl[1]');
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 40) {
            throw new RuntimeException('The official Detailed Research Proposal table structure has changed.');
        }

        $this->appendValueOnNewLineToFirstParagraph($xpath, $rows[2], $proposal['project_title']);
        $this->appendValueToFirstParagraph($xpath, $rows[3], $proposal['research_agenda']);
        $this->setSdgCheckboxes($xpath, $rows, $proposal['sdgs']);
        $this->fillPeople($xpath, $rows[14], $proposal);
        $this->fillAgency($xpath, $rows[15], $proposal);
        $this->appendValueToFirstParagraph($xpath, $rows[16], $proposal['cooperating_agency'] ?: 'None', false);
        $this->fillNarrativeRow($xpath, $rows[17], $proposal['executive_brief']);
        $this->fillNarrativeRow($xpath, $rows[18], $proposal['rationale']);
        $this->fillNarrativeRow($xpath, $rows[19], $proposal['objectives']);
        $this->fillExpectedOutputs($xpath, $rows[20], $proposal['expected_outputs']);
        $this->fillNarrativeRow($xpath, $rows[21], $proposal['related_literature']);
        $this->fillMethodology($xpath, $rows[22], $proposal['methodology']);
        $this->fillResponsibilities($xpath, $rows[23], $proposal['responsibilities']);
        $this->fillBudget($xpath, $rows[26], (float) $proposal['mooe_total']);
        $this->fillBudget($xpath, $rows[27], (float) $proposal['co_total']);
        $this->fillNarrativeRow($xpath, $rows[28], $proposal['references']);
        $this->fillPreparedBy($xpath, $rows, $proposal);

        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Detailed Research Proposal XML could not be serialized.');
        }

        return $renderedXml;
    }

    private function renderSettingsXml(string $settingsXml): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($settingsXml, LIBXML_NONET)) {
            throw new RuntimeException('The Detailed Research Proposal settings contain invalid XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $settings = $this->firstElement($xpath, '/w:settings');
        $updateFields = $xpath->query('./w:updateFields', $settings)->item(0);

        if (! $updateFields instanceof DOMElement) {
            $updateFields = $document->createElementNS(self::W, 'w:updateFields');
            $settings->appendChild($updateFields);
        }

        $updateFields->setAttributeNS(self::W, 'w:val', 'true');
        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Detailed Research Proposal settings could not be serialized.');
        }

        return $renderedXml;
    }

    /** @param list<int> $selectedSdgs @param array<int, DOMElement> $rows */
    private function setSdgCheckboxes(DOMXPath $xpath, array $rows, array $selectedSdgs): void
    {
        $checkboxes = [];

        foreach (array_slice($rows, 5, 9) as $row) {
            array_push($checkboxes, ...$this->elements($xpath, './/w14:checkbox', $row));
        }

        if (count($checkboxes) !== 17) {
            throw new RuntimeException('The official Sustainable Development Goal checkboxes are incomplete.');
        }

        $sdgOrder = [1, 10, 2, 11, 3, 12, 4, 13, 5, 14, 6, 15, 7, 16, 8, 17, 9];

        foreach ($checkboxes as $index => $checkbox) {
            $sdg = $sdgOrder[$index];
            $isSelected = in_array($sdg, $selectedSdgs, true);
            $checked = $this->elements($xpath, './w14:checked', $checkbox)[0] ?? null;

            if ($checked instanceof DOMElement) {
                $checked->setAttributeNS(self::W14, 'w14:val', $isSelected ? '1' : '0');
            }

            $checkedState = $this->elements($xpath, './w14:checkedState', $checkbox)[0] ?? null;

            if ($checkedState instanceof DOMElement) {
                $checkedState->setAttributeNS(self::W14, 'w14:val', '2612');
            }

            $displayText = $xpath->query('ancestor::w:sdt[1]/w:sdtContent//w:t', $checkbox)->item(0);

            if ($displayText instanceof DOMElement) {
                $displayText->nodeValue = $isSelected ? "\u{2612}" : "\u{2610}";
            }
        }
    }

    /** @param array<string, mixed> $proposal */
    private function fillPeople(DOMXPath $xpath, DOMElement $row, array $proposal): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if (count($paragraphs) < 9) {
            throw new RuntimeException('The project leader and staff section is incomplete.');
        }

        $templates = [$paragraphs[0], $paragraphs[1], $paragraphs[2], $paragraphs[3], $paragraphs[4]];
        $this->removeParagraphs($cell, $paragraphs);
        $cell->appendChild($templates[0]->cloneNode(true));
        $cell->appendChild($this->labeledParagraph($templates[1], 'IV. Project Leader:', $proposal['project_leader']));
        $cell->appendChild($this->labeledParagraph($templates[2], '       Email Address:', $proposal['leader_email'], false));
        $cell->appendChild($this->labeledParagraph($templates[3], '       Contact Number:', $proposal['leader_contact'], false));
        $cell->appendChild($templates[4]->cloneNode(true));

        foreach ($proposal['staff'] as $member) {
            $cell->appendChild($this->labeledParagraph($templates[1], '       Project Staff (s):', $member['name']));
            $cell->appendChild($this->labeledParagraph($templates[2], '       Email Address:', $member['email'], false));
            $cell->appendChild($this->labeledParagraph($templates[3], '       Contact Number:', $member['contact'], false));
            $cell->appendChild($templates[4]->cloneNode(true));
        }
    }

    /** @param array<string, mixed> $proposal */
    private function fillAgency(DOMXPath $xpath, DOMElement $row, array $proposal): void
    {
        $paragraphs = $this->paragraphs($xpath, $this->onlyCell($xpath, $row));

        if (count($paragraphs) !== 4) {
            throw new RuntimeException('The proponent agency section is incomplete.');
        }

        $this->replaceWithLabelValue($paragraphs[0], 'V.  Proponent Agency:', $proposal['proponent_agency']);
        $this->replaceWithLabelValue($paragraphs[1], '        Department:', $proposal['proponent_department']);
        $this->replaceWithLabelValue($paragraphs[2], '        College:', $proposal['proponent_college']);
        $this->replaceWithLabelValue($paragraphs[3], '        Campus:', $proposal['proponent_campus']);
    }

    private function fillNarrativeRow(DOMXPath $xpath, DOMElement $row, string $value): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);
        $this->removeParagraphs($cell, array_slice($paragraphs, 1));

        foreach ($this->textBlocks($value) as $block) {
            $cell->appendChild($this->bodyParagraph($cell->ownerDocument, $block));
        }
    }

    /** @param array<string, string> $outputs */
    private function fillExpectedOutputs(DOMXPath $xpath, DOMElement $row, array $outputs): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);

        if (count($paragraphs) !== 9) {
            throw new RuntimeException('The expected output section is incomplete.');
        }

        foreach (array_values(config('detailed_proposal.expected_outputs')) as $index => $label) {
            $key = array_keys(config('detailed_proposal.expected_outputs'))[$index];
            $this->replaceWithLabelValue($paragraphs[$index + 1], $label.':', $outputs[$key] ?? '', false);
        }
    }

    /** @param array<string, string> $methodology */
    private function fillMethodology(DOMXPath $xpath, DOMElement $row, array $methodology): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);

        if (count($paragraphs) !== 4) {
            throw new RuntimeException('The methodology section is incomplete.');
        }

        $sectionParagraph = $paragraphs[0];
        $headingTemplate = $paragraphs[1];
        $this->removeParagraphs($cell, array_slice($paragraphs, 1));

        foreach (config('detailed_proposal.methodology') as $key => $label) {
            $heading = $headingTemplate->cloneNode(true);

            if (! $heading instanceof DOMElement) {
                throw new RuntimeException('A methodology heading could not be created.');
            }

            $this->replaceParagraphText($heading, $label);
            $cell->appendChild($heading);

            foreach ($this->textBlocks($methodology[$key]) as $block) {
                $cell->appendChild($this->bodyParagraph($sectionParagraph->ownerDocument, $block));
            }
        }
    }

    /** @param list<array{name: string, duties: string}> $responsibilities */
    private function fillResponsibilities(DOMXPath $xpath, DOMElement $row, array $responsibilities): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);
        $this->removeParagraphs($cell, array_slice($paragraphs, 1));

        foreach ($responsibilities as $index => $responsibility) {
            $role = $index === 0 ? 'Project Leader' : 'Project Staff (s)';

            $cell->appendChild($this->simpleParagraph(
                $cell->ownerDocument,
                $role.': '.$responsibility['name'],
                italic: true,
            ));

            foreach ($this->textBlocks($responsibility['duties']) as $block) {
                $cell->appendChild($this->bodyParagraph($cell->ownerDocument, $block));
            }
        }
    }

    private function fillBudget(DOMXPath $xpath, DOMElement $row, float $amount): void
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== 2) {
            throw new RuntimeException('The Detailed Research Proposal budget section is incomplete.');
        }

        $paragraph = $this->paragraphs($xpath, $cells[1])[0] ?? null;

        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('A Detailed Research Proposal budget value slot is missing.');
        }

        $this->replaceParagraphText($paragraph, 'Php '.number_format($amount, 2));
    }

    /** @param array<int, DOMElement> $rows @param array<string, mixed> $proposal */
    private function fillPreparedBy(DOMXPath $xpath, array $rows, array $proposal): void
    {
        $preparedCells = $this->elements($xpath, './w:tc', $rows[31]);
        $collegeCells = $this->elements($xpath, './w:tc', $rows[32]);
        $campusCells = $this->elements($xpath, './w:tc', $rows[33]);

        if (count($preparedCells) !== 2 || count($collegeCells) !== 2 || count($campusCells) !== 2) {
            throw new RuntimeException('The Detailed Research Proposal prepared-by section is incomplete.');
        }

        $signatureParagraphs = $this->paragraphs($xpath, $preparedCells[0]);

        if (! isset($signatureParagraphs[4])) {
            throw new RuntimeException('The Detailed Research Proposal project leader signature slot is missing.');
        }

        $this->replaceParagraphText($signatureParagraphs[4], $proposal['project_leader'], true, 'center');
        $this->replaceWithLabelValue($this->paragraphs($xpath, $preparedCells[1])[0], 'Department:', $proposal['proponent_department'], false);
        $this->replaceWithLabelValue($this->paragraphs($xpath, $collegeCells[1])[0], 'College:', $proposal['proponent_college'], false);
        $this->replaceWithLabelValue($this->paragraphs($xpath, $campusCells[1])[0], 'Campus:', $proposal['proponent_campus'], false);
    }

    private function appendValueToFirstParagraph(
        DOMXPath $xpath,
        DOMElement $row,
        string $value,
        bool $bold = true,
    ): void {
        $paragraph = $this->paragraphs($xpath, $this->onlyCell($xpath, $row))[0] ?? null;

        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('A Detailed Research Proposal value slot is missing.');
        }

        $this->appendRun($paragraph, ' '.$value, $bold);
    }

    private function appendValueOnNewLineToFirstParagraph(DOMXPath $xpath, DOMElement $row, string $value): void
    {
        $paragraph = $this->paragraphs($xpath, $this->onlyCell($xpath, $row))[0] ?? null;

        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('A Detailed Research Proposal value slot is missing.');
        }

        $this->appendLineBreak($paragraph);
        $this->appendRun($paragraph, $value, true);
    }

    private function labeledParagraph(
        DOMElement $template,
        string $label,
        string $value,
        bool $bold = true,
    ): DOMElement {
        $paragraph = $template->cloneNode(true);

        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('A Detailed Research Proposal labeled paragraph could not be created.');
        }

        $this->replaceWithLabelValue($paragraph, $label, $value, $bold);

        return $paragraph;
    }

    private function replaceWithLabelValue(
        DOMElement $paragraph,
        string $label,
        string $value,
        bool $bold = true,
    ): void {
        $this->clearParagraphContent($paragraph);
        $this->appendRun($paragraph, $label, $bold);

        if ($value !== '') {
            $this->appendRun($paragraph, '  '.$value, $bold);
        }
    }

    private function replaceParagraphText(
        DOMElement $paragraph,
        string $text,
        bool $bold = false,
        ?string $alignment = null,
    ): void {
        $this->clearParagraphContent($paragraph);

        if ($alignment !== null) {
            $this->setParagraphAlignment($paragraph, $alignment);
        }

        $this->appendRun($paragraph, $text, $bold);
    }

    private function appendRun(
        DOMElement $paragraph,
        string $text,
        bool $bold = false,
        bool $italic = false,
    ): void {
        if ($text === '') {
            return;
        }

        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $document->createElementNS(self::W, 'w:rPr');
        $fonts = $document->createElementNS(self::W, 'w:rFonts');
        $fonts->setAttributeNS(self::W, 'w:ascii', 'Times New Roman');
        $fonts->setAttributeNS(self::W, 'w:hAnsi', 'Times New Roman');
        $fonts->setAttributeNS(self::W, 'w:eastAsia', 'Times New Roman');
        $runProperties->appendChild($fonts);

        if ($bold) {
            $runProperties->appendChild($document->createElementNS(self::W, 'w:b'));
        }

        if ($italic) {
            $runProperties->appendChild($document->createElementNS(self::W, 'w:i'));
        }

        $size = $document->createElementNS(self::W, 'w:sz');
        $size->setAttributeNS(self::W, 'w:val', '22');
        $runProperties->appendChild($size);
        $run->appendChild($runProperties);
        $textNode = $document->createElementNS(self::W, 'w:t');

        if ($text !== trim($text)) {
            $textNode->setAttributeNS(self::XML, 'xml:space', 'preserve');
        }

        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
    }

    private function appendLineBreak(DOMElement $paragraph): void
    {
        $run = $paragraph->ownerDocument->createElementNS(self::W, 'w:r');
        $run->appendChild($paragraph->ownerDocument->createElementNS(self::W, 'w:br'));
        $paragraph->appendChild($run);
    }

    private function bodyParagraph(DOMDocument $document, string $text): DOMElement
    {
        return $this->simpleParagraph($document, $text, false, 'both', true);
    }

    private function simpleParagraph(
        DOMDocument $document,
        string $text,
        bool $bold = false,
        string $alignment = 'left',
        bool $bodySpacing = false,
        bool $italic = false,
    ): DOMElement {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
        $justification = $document->createElementNS(self::W, 'w:jc');
        $justification->setAttributeNS(self::W, 'w:val', $alignment);
        $paragraphProperties->appendChild($justification);

        if ($bodySpacing) {
            $spacing = $document->createElementNS(self::W, 'w:spacing');
            $spacing->setAttributeNS(self::W, 'w:after', '120');
            $spacing->setAttributeNS(self::W, 'w:line', '240');
            $spacing->setAttributeNS(self::W, 'w:lineRule', 'auto');
            $paragraphProperties->appendChild($spacing);
        }

        $paragraph->appendChild($paragraphProperties);
        $this->appendRun($paragraph, $text, $bold, $italic);

        return $paragraph;
    }

    /** @return list<string> */
    private function textBlocks(string $text): array
    {
        return collect(preg_split('/\R+/u', $text) ?: [])
            ->map(fn (string $block): string => trim($block))
            ->filter()
            ->values()
            ->all();
    }

    private function clearParagraphContent(DOMElement $paragraph): void
    {
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'pPr') {
                $paragraph->removeChild($child);
            }
        }
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

    /** @param array<int, DOMElement> $paragraphs */
    private function removeParagraphs(DOMElement $cell, array $paragraphs): void
    {
        foreach ($paragraphs as $paragraph) {
            $cell->removeChild($paragraph);
        }
    }

    /** @return array<int, DOMElement> */
    private function paragraphs(DOMXPath $xpath, DOMElement $cell): array
    {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Detailed Research Proposal cell is missing its paragraph.');
        }

        return $paragraphs;
    }

    private function onlyCell(DOMXPath $xpath, DOMElement $row): DOMElement
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== 1) {
            throw new RuntimeException('A Detailed Research Proposal section has an unexpected cell structure.');
        }

        return $cells[0];
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
    {
        $element = $xpath->query($query, $context)->item(0);

        if (! $element instanceof DOMElement) {
            throw new RuntimeException('The Detailed Research Proposal template structure is incomplete.');
        }

        return $element;
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
