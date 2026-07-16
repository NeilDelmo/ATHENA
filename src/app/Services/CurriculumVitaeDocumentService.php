<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class CurriculumVitaeDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** @var list<int> */
    private const NAME_TAB_STOPS = [3892, 6989];

    private int $nextContentControlId = 100000000;

    /** @param array{people: array<int, array<string, mixed>>} $curriculumVitae */
    public function generate(array $curriculumVitae): string
    {
        $templatePath = (string) config('curriculum_vitae.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Curriculum Vitae template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-curriculum-vitae-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Curriculum Vitae document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Curriculum Vitae template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The Curriculum Vitae template document body is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $curriculumVitae))) {
                throw new RuntimeException('The generated Curriculum Vitae could not be written.');
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Curriculum Vitae could not be read.');
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

    /** @param array{people: array<int, array<string, mixed>>} $curriculumVitae */
    private function renderDocumentXml(string $xml, array $curriculumVitae): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('The Curriculum Vitae template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('w14', self::W14);
        $this->nextContentControlId = 100000000;
        $body = $this->firstElement($xpath, '//w:body');
        $sectionProperties = $this->firstElement($xpath, './w:sectPr', $body);
        $firstTableTemplate = $this->firstElement($xpath, './w:tbl[1]', $body)->cloneNode(true);
        $secondTableTemplate = $this->firstElement($xpath, './w:tbl[2]', $body)->cloneNode(true);
        $paragraphTemplates = $this->elements($xpath, './w:p', $body);

        if (! $firstTableTemplate instanceof DOMElement
            || ! $secondTableTemplate instanceof DOMElement
            || count($paragraphTemplates) < 2) {
            throw new RuntimeException('The Curriculum Vitae template body is incomplete.');
        }

        foreach (iterator_to_array($body->childNodes) as $child) {
            if ($child !== $sectionProperties) {
                $body->removeChild($child);
            }
        }

        foreach ($curriculumVitae['people'] as $index => $person) {
            if ($index > 0) {
                $body->insertBefore($this->pageBreak($document), $sectionProperties);
            }

            $firstTable = $firstTableTemplate->cloneNode(true);
            $secondTable = $secondTableTemplate->cloneNode(true);

            if (! $firstTable instanceof DOMElement || ! $secondTable instanceof DOMElement) {
                throw new RuntimeException('A Curriculum Vitae member block could not be created.');
            }

            $this->removeWordIdentityAttributes($xpath, $firstTable);
            $this->removeWordIdentityAttributes($xpath, $secondTable);
            $this->fillMainTable($xpath, $firstTable, $person);
            $this->fillPublicationTable($xpath, $secondTable, $person);
            $firstSeparator = $paragraphTemplates[0]->cloneNode(true);
            $secondSeparator = $paragraphTemplates[1]->cloneNode(true);

            if (! $firstSeparator instanceof DOMElement || ! $secondSeparator instanceof DOMElement) {
                throw new RuntimeException('A Curriculum Vitae separator could not be created.');
            }

            $this->removeWordIdentityAttributes($xpath, $firstSeparator);
            $this->removeWordIdentityAttributes($xpath, $secondSeparator);
            $body->insertBefore($firstTable, $sectionProperties);
            $body->insertBefore($firstSeparator, $sectionProperties);
            $body->insertBefore($secondTable, $sectionProperties);
            $body->insertBefore($secondSeparator, $sectionProperties);
        }

        return $document->saveXML() ?: throw new RuntimeException('The Curriculum Vitae could not be serialized.');
    }

    /** @param array<string, mixed> $person */
    private function fillMainTable(DOMXPath $xpath, DOMElement $table, array $person): void
    {
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 51) {
            throw new RuntimeException('The Curriculum Vitae main table does not match the official form.');
        }

        $nameCell = $this->elements($xpath, './w:tc', $rows[3])[0];
        $this->replaceParagraphWithTabs(
            $this->firstElement($xpath, './w:p', $nameCell),
            [$person['last_name'], $person['first_name'], $person['middle_name']],
            self::NAME_TAB_STOPS,
        );

        $personalCells = $this->elements($xpath, './w:tc', $rows[4]);
        $this->replaceCellLabelValue($xpath, $personalCells[0], 'Agency:', $person['agency']);
        $this->setCheckboxes($xpath, $personalCells[1], [
            $person['gender'] === 'male',
            $person['gender'] === 'female',
        ]);
        $this->replaceCellLabelValue($xpath, $personalCells[2], 'Birthday (mm/dd/yyyy):', $person['birthday']);

        $addressCells = $this->elements($xpath, './w:tc', $rows[7]);
        foreach (['street', 'barangay', 'municipality', 'province'] as $index => $key) {
            $this->replaceCellText($xpath, $addressCells[$index], $person[$key]);
        }

        $contactCells = $this->elements($xpath, './w:tc', $rows[8]);
        $this->replaceCellLabelValue($xpath, $contactCells[0], 'Landline no.:', $person['landline']);
        $this->replaceCellLabelValue($xpath, $contactCells[1], 'Cellphone no.: (+63)', $person['cellphone']);
        $this->replaceCellLabelValue($xpath, $contactCells[2], 'Email Address:', $person['email']);

        $this->replaceRows($xpath, $table, array_slice($rows, 12, 4), $rows[12], $rows[16], $person['academic_background'], [
            'degree', 'major_field', 'sector', 'learning_institution', 'status', 'year_start', 'year_end', 'thesis',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 19, 5), $rows[19], $rows[24], $person['scholarships'], [
            'sponsor', 'primary_sponsor', 'period_start', 'period_end', 'extension_start', 'extension_end',
            'item_expenses', 'amount_approved', 'amount_released', 'date_released',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 27, 5), $rows[27], $rows[32], $person['employment'], [
            'agency', 'plantilla_position', 'appointment_status', 'start_date', 'end_date', 'monthly_salary',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 34, 4), $rows[34], $rows[38], $person['specializations'], [
            'field', 'primary_field',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 40, 3), $rows[40], $rows[43], $person['awards'], [
            'title', 'rank', 'category', 'granting_institution', 'year_granted',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 46, 5), $rows[46], null, $person['projects'], [
            'title', 'designation', 'sector', 'current_status', 'year_from', 'year_to',
        ]);
    }

    /** @param array<string, mixed> $person */
    private function fillPublicationTable(DOMXPath $xpath, DOMElement $table, array $person): void
    {
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 14) {
            throw new RuntimeException('The Curriculum Vitae publication table does not match the official form.');
        }

        $this->replaceRows($xpath, $table, array_slice($rows, 2, 5), $rows[2], $rows[7], $person['publications'], [
            'title', 'year_published', 'place', 'publication_group', 'authoring_type',
        ]);
        $this->replaceRows($xpath, $table, array_slice($rows, 9, 5), $rows[9], null, $person['presentations'], [
            'title', 'conference_title', 'category', 'date', 'venue', 'sponsor',
        ]);
    }

    /**
     * @param  array<int, DOMElement>  $placeholderRows
     * @param  array<int, array<string, string>>  $entries
     * @param  list<string>  $fieldKeys
     */
    private function replaceRows(
        DOMXPath $xpath,
        DOMElement $table,
        array $placeholderRows,
        DOMElement $template,
        ?DOMElement $anchor,
        array $entries,
        array $fieldKeys,
    ): void {
        foreach ($placeholderRows as $placeholderRow) {
            $table->removeChild($placeholderRow);
        }

        $rows = $entries === [] ? [array_fill_keys($fieldKeys, '')] : $entries;

        foreach ($rows as $entry) {
            $row = $template->cloneNode(true);

            if (! $row instanceof DOMElement) {
                throw new RuntimeException('A Curriculum Vitae detail row could not be created.');
            }

            $this->removeWordIdentityAttributes($xpath, $row);
            $this->removeRowHeight($xpath, $row);
            $cells = $this->elements($xpath, './w:tc', $row);

            if (count($cells) !== count($fieldKeys)) {
                throw new RuntimeException('A Curriculum Vitae detail row has an unexpected number of cells.');
            }

            foreach ($fieldKeys as $index => $key) {
                $this->replaceCellText($xpath, $cells[$index], (string) ($entry[$key] ?? ''));
            }

            if ($anchor instanceof DOMElement) {
                $table->insertBefore($row, $anchor);
            } else {
                $table->appendChild($row);
            }
        }
    }

    /** @param list<bool> $selected */
    private function setCheckboxes(DOMXPath $xpath, DOMElement $cell, array $selected): void
    {
        $checkboxes = $this->elements($xpath, './/w14:checkbox', $cell);

        foreach ($checkboxes as $index => $checkbox) {
            $isSelected = $selected[$index] ?? false;
            $checked = $this->elements($xpath, './w14:checked', $checkbox)[0] ?? null;

            if ($checked instanceof DOMElement) {
                $checked->setAttributeNS(self::W14, 'w14:val', $isSelected ? '1' : '0');
            }

            $displayText = $xpath->query('ancestor::w:sdt[1]/w:sdtContent//w:t', $checkbox)->item(0);

            if ($displayText instanceof DOMElement) {
                $displayText->nodeValue = $isSelected ? '☒' : '☐';
            }
        }
    }

    /**
     * @param  list<string>  $values
     * @param  list<int>  $tabStops
     */
    private function replaceParagraphWithTabs(DOMElement $paragraph, array $values, array $tabStops = []): void
    {
        $this->clearParagraphContent($paragraph);
        $this->setParagraphTabStops($paragraph, $tabStops);
        $document = $paragraph->ownerDocument;

        foreach ($values as $index => $value) {
            if ($index > 0) {
                $tabRun = $document->createElementNS(self::W, 'w:r');
                $runProperties = $this->paragraphRunProperties($paragraph);

                if ($runProperties instanceof DOMElement) {
                    $tabRun->appendChild($runProperties);
                }

                $tabRun->appendChild($document->createElementNS(self::W, 'w:tab'));
                $paragraph->appendChild($tabRun);
            }

            $this->appendRun($paragraph, $value);
        }
    }

    /** @param list<int> $positions */
    private function setParagraphTabStops(DOMElement $paragraph, array $positions): void
    {
        if ($positions === []) {
            return;
        }

        $paragraphProperties = null;

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::W && $child->localName === 'pPr') {
                $paragraphProperties = $child;

                break;
            }
        }

        if (! $paragraphProperties instanceof DOMElement) {
            return;
        }

        foreach (iterator_to_array($paragraphProperties->childNodes) as $property) {
            if ($property instanceof DOMElement && $property->namespaceURI === self::W && $property->localName === 'tabs') {
                $paragraphProperties->removeChild($property);
            }
        }

        $document = $paragraph->ownerDocument;
        $tabs = $document->createElementNS(self::W, 'w:tabs');

        foreach ($positions as $position) {
            $tab = $document->createElementNS(self::W, 'w:tab');
            $tab->setAttributeNS(self::W, 'w:val', 'left');
            $tab->setAttributeNS(self::W, 'w:pos', (string) $position);
            $tabs->appendChild($tab);
        }

        $anchor = null;

        foreach ($paragraphProperties->childNodes as $property) {
            if ($property instanceof DOMElement
                && $property->namespaceURI === self::W
                && in_array($property->localName, ['ind', 'jc', 'rPr'], true)) {
                $anchor = $property;

                break;
            }
        }

        if ($anchor instanceof DOMElement) {
            $paragraphProperties->insertBefore($tabs, $anchor);

            return;
        }

        $paragraphProperties->appendChild($tabs);
    }

    private function replaceCellLabelValue(DOMXPath $xpath, DOMElement $cell, string $label, string $value): void
    {
        $paragraph = $this->firstElement($xpath, './w:p', $cell);
        $this->clearParagraphContent($paragraph);
        $this->appendRun($paragraph, $label);
        $this->appendRun($paragraph, ' '.$value);

        foreach (array_slice($this->elements($xpath, './w:p', $cell), 1) as $extraParagraph) {
            $cell->removeChild($extraParagraph);
        }
    }

    private function replaceCellText(DOMXPath $xpath, DOMElement $cell, string $text): void
    {
        $paragraph = $this->firstElement($xpath, './w:p', $cell);
        $this->clearParagraphContent($paragraph);
        $this->appendRun($paragraph, $text);

        foreach (array_slice($this->elements($xpath, './w:p', $cell), 1) as $extraParagraph) {
            $cell->removeChild($extraParagraph);
        }
    }

    private function clearParagraphContent(DOMElement $paragraph): void
    {
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'pPr') {
                $paragraph->removeChild($child);
            }
        }
    }

    private function appendRun(DOMElement $paragraph, string $text): void
    {
        if ($text === '') {
            return;
        }

        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $this->paragraphRunProperties($paragraph);

        if ($runProperties instanceof DOMElement) {
            $run->appendChild($runProperties);
        }

        $textNode = $document->createElementNS(self::W, 'w:t');
        $textNode->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textNode->appendChild($document->createTextNode($text));
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
    }

    private function paragraphRunProperties(DOMElement $paragraph): ?DOMElement
    {
        foreach ($paragraph->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::W || $child->localName !== 'pPr') {
                continue;
            }

            foreach ($child->childNodes as $property) {
                if ($property instanceof DOMElement
                    && $property->namespaceURI === self::W
                    && $property->localName === 'rPr') {
                    $clone = $property->cloneNode(true);

                    return $clone instanceof DOMElement ? $clone : null;
                }
            }
        }

        return null;
    }

    private function pageBreak(DOMDocument $document): DOMElement
    {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $run = $document->createElementNS(self::W, 'w:r');
        $break = $document->createElementNS(self::W, 'w:br');
        $break->setAttributeNS(self::W, 'w:type', 'page');
        $run->appendChild($break);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    private function removeRowHeight(DOMXPath $xpath, DOMElement $row): void
    {
        foreach ($this->elements($xpath, './w:trPr/w:trHeight', $row) as $height) {
            $height->parentNode?->removeChild($height);
        }
    }

    private function removeWordIdentityAttributes(DOMXPath $xpath, DOMElement $element): void
    {
        foreach ([$element, ...$this->elements($xpath, './/*', $element)] as $descendant) {
            foreach ([[self::W14, 'paraId'], [self::W14, 'textId'], [self::W, 'rsidR'], [self::W, 'rsidRDefault'], [self::W, 'rsidTr']] as [$namespace, $attribute]) {
                $descendant->removeAttributeNS($namespace, $attribute);
            }
        }

        foreach ($this->elements($xpath, './/w:sdtPr/w:id', $element) as $contentControlId) {
            $contentControlId->setAttributeNS(self::W, 'w:val', (string) $this->nextContentControlId++);
        }
    }

    private function firstElement(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
    {
        $node = $xpath->query($query, $context)->item(0);

        if (! $node instanceof DOMElement) {
            throw new RuntimeException('The official Curriculum Vitae structure is incomplete.');
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
