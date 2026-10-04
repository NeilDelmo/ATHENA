<?php

namespace App\Services;

use App\Models\ProposalSignatory;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class CommentResponseFormDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    private const MATRIX_HEADING = 'MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS';

    public function __construct(
        private readonly WordDocumentPaginationService $paginationService,
    ) {}

    /**
     * @param  array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
     *     feedback?: list<array{reviewer: string, location: string, comment: string, stage?: string, response?: string, remarks?: string}>,
     *     evaluation_stages?: list<string>,
     *     comment_response_head?: string,
     *     comment_response_vice_chancellor?: string,
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }  $commentResponseForm
     */
    public function generate(array $commentResponseForm): string
    {
        $templatePath = (string) config('comment_response_form.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Comment-Response Form template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-comment-response-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Comment-Response Form document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Comment-Response Form template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');
            $headerXml = $archive->getFromName('word/header1.xml');
            $footerXml = $archive->getFromName('word/footer1.xml');

            if ($documentXml === false || $headerXml === false || $footerXml === false) {
                throw new RuntimeException('The Comment-Response Form body or footer is missing.');
            }

            if (! $archive->addFromString(
                'word/document.xml',
                $this->renderDocumentXml($documentXml, $commentResponseForm),
            ) || ! $archive->addFromString(
                'word/header1.xml',
                $this->renderHeaderXml($headerXml),
            ) || ! $archive->addFromString(
                'word/footer1.xml',
                $this->renderFooterXml($footerXml),
            )) {
                throw new RuntimeException('The generated Comment-Response Form could not be written.');
            }

            $this->paginationService->addPageNumbers($archive);
            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Comment-Response Form could not be read.');
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
     * @param  array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
     *     feedback?: list<array{reviewer: string, location: string, comment: string, stage?: string, response?: string, remarks?: string}>,
     *     evaluation_stages?: list<string>,
     *     comment_response_head?: string,
     *     comment_response_vice_chancellor?: string,
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }  $commentResponseForm
     */
    private function renderDocumentXml(string $documentXml, array $commentResponseForm): string
    {
        [$document, $xpath] = $this->documentAndXPath($documentXml, 'document');
        $this->addMatrixHeading($xpath);
        $this->fillProjectTitle($xpath, $commentResponseForm['project_title']);
        $this->fillResearchers($xpath, $commentResponseForm);
        $this->fillEvaluationStages($xpath, $commentResponseForm['evaluation_stages'] ?? []);
        $this->fillFeedbackTable($xpath, $commentResponseForm['feedback'] ?? []);
        $this->fillPreparedBy($xpath, $commentResponseForm['project_leader']);
        $this->fillReviewSignatories($xpath, $commentResponseForm);

        return $this->serialized($document, 'document');
    }

    private function renderHeaderXml(string $headerXml): string
    {
        [$document, $xpath] = $this->documentAndXPath($headerXml, 'header');
        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = trim($this->paragraphText($paragraph));
            if (in_array($text, ['MATRIX ON THE ACTIONS MADE FOR THE', 'COMMENTS AND SUGGESTIONS', '(Constituent/Extension Campus)'], true)) {
                foreach ($this->elements($xpath, './w:r[w:t]', $paragraph) as $run) {
                    $paragraph->removeChild($run);
                }
            }
        }

        return $this->serialized($document, 'header');
    }

    private function addMatrixHeading(DOMXPath $xpath): void
    {
        $body = $xpath->query('/w:document/w:body')->item(0);
        $sourceParagraph = $xpath->query('./w:p[1]', $body)->item(0);
        $paragraph = $body->ownerDocument->createElementNS(self::W, 'w:p');
        $properties = $body->ownerDocument->createElementNS(self::W, 'w:pPr');
        $alignment = $body->ownerDocument->createElementNS(self::W, 'w:jc');
        $alignment->setAttributeNS(self::W, 'w:val', 'center');
        $properties->appendChild($alignment);
        $spacing = $body->ownerDocument->createElementNS(self::W, 'w:spacing');
        $spacing->setAttributeNS(self::W, 'w:after', '360');
        $properties->appendChild($spacing);
        $properties->appendChild($body->ownerDocument->createElementNS(self::W, 'w:keepNext'));
        $paragraph->appendChild($properties);
        $this->appendRun($paragraph, self::MATRIX_HEADING, $this->boldRunProperties($xpath, $sourceParagraph, 22));
        $body->insertBefore($paragraph, $body->firstChild);
    }

    private function renderFooterXml(string $footerXml): string
    {
        [$document, $xpath] = $this->documentAndXPath($footerXml, 'footer');
        foreach ($this->elements($xpath, '//w:p[.//w:t[contains(., "Comment-Response Form |")]]', $document->documentElement) as $paragraph) {
            $paragraph->parentNode->removeChild($paragraph);
        }
        $this->updateFooterPageFieldCache($xpath);

        return $this->serialized($document, 'footer');
    }

    /** @param list<string> $stages */
    private function fillEvaluationStages(DOMXPath $xpath, array $stages): void
    {
        $stages = array_values(array_intersect(array_keys(CommentResponseFeedback::STAGE_LABELS), $stages));
        $heading = $xpath->query('/w:document/w:body/w:p[.//w:t[contains(., "EVALUATION DONE")]]')->item(0);
        $checkboxTable = $heading instanceof DOMElement ? $xpath->query('following-sibling::w:tbl[1]', $heading)->item(0) : null;
        $cells = $checkboxTable instanceof DOMElement ? $this->elements($xpath, './w:tr/w:tc', $checkboxTable) : [];

        if (count($cells) !== 2) {
            throw new RuntimeException('The Comment-Response Form evaluation boxes are missing.');
        }

        $this->replaceParagraphText($xpath, $heading, 'LEVEL OF EVALUATION DONE:');
        $labels = ['Initial Screening', 'Local Research Evaluation'];
        $labelParagraphs = $this->elements($xpath, 'following-sibling::w:p[position() <= 3]', $checkboxTable);
        $tableProperties = $xpath->query('./w:tblPr', $checkboxTable)->item(0);

        foreach ($this->elements($xpath, './w:tblpPr | ./w:tblOverlap', $tableProperties) as $property) {
            $tableProperties->removeChild($property);
        }

        $borders = $checkboxTable->ownerDocument->createElementNS(self::W, 'w:tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $edge) {
            $border = $checkboxTable->ownerDocument->createElementNS(self::W, 'w:'.$edge);
            $border->setAttributeNS(self::W, 'w:val', 'nil');
            $borders->appendChild($border);
        }
        $tableProperties->appendChild($borders);
        $indent = $checkboxTable->ownerDocument->createElementNS(self::W, 'w:tblInd');
        $indent->setAttributeNS(self::W, 'w:w', '720');
        $indent->setAttributeNS(self::W, 'w:type', 'dxa');
        $tableProperties->appendChild($indent);
        $grid = $xpath->query('./w:tblGrid', $checkboxTable)->item(0);
        $column = $checkboxTable->ownerDocument->createElementNS(self::W, 'w:gridCol');
        $column->setAttributeNS(self::W, 'w:w', '5000');
        $grid->appendChild($column);

        $checked = [count(array_diff($stages, ['lrec'])) > 0, in_array('lrec', $stages, true)];
        foreach ($cells as $index => $cell) {
            $properties = $xpath->query('./w:tcPr', $cell)->item(0);
            foreach ($this->elements($xpath, './w:shd', $properties) as $shade) {
                $properties->removeChild($shade);
            }

            $shade = $cell->ownerDocument->createElementNS(self::W, 'w:shd');
            $shade->setAttributeNS(self::W, 'w:val', 'clear');
            $shade->setAttributeNS(self::W, 'w:color', 'auto');
            $shade->setAttributeNS(self::W, 'w:fill', $checked[$index] ? '000000' : 'FFFFFF');
            $properties->appendChild($shade);

            $cellBorders = $cell->ownerDocument->createElementNS(self::W, 'w:tcBorders');
            foreach (['top', 'left', 'bottom', 'right'] as $edge) {
                $border = $cell->ownerDocument->createElementNS(self::W, 'w:'.$edge);
                $border->setAttributeNS(self::W, 'w:val', 'single');
                $border->setAttributeNS(self::W, 'w:sz', '4');
                $border->setAttributeNS(self::W, 'w:color', '000000');
                $cellBorders->appendChild($border);
            }
            $properties->appendChild($cellBorders);

            $boxParagraph = $xpath->query('./w:p', $cell)->item(0);
            $this->replaceParagraphText($xpath, $boxParagraph, '');
            $paragraphProperties = $xpath->query('./w:pPr', $boxParagraph)->item(0);
            foreach ($this->elements($xpath, './w:jc | ./w:spacing', $paragraphProperties) as $property) {
                $paragraphProperties->removeChild($property);
            }
            $alignment = $cell->ownerDocument->createElementNS(self::W, 'w:jc');
            $alignment->setAttributeNS(self::W, 'w:val', 'center');
            $paragraphProperties->appendChild($alignment);
            $spacing = $cell->ownerDocument->createElementNS(self::W, 'w:spacing');
            foreach (['before' => '0', 'after' => '0', 'line' => '360', 'lineRule' => 'exact'] as $attribute => $value) {
                $spacing->setAttributeNS(self::W, 'w:'.$attribute, $value);
            }
            $paragraphProperties->appendChild($spacing);
            $margins = $cell->ownerDocument->createElementNS(self::W, 'w:tcMar');
            foreach (['left', 'right'] as $edge) {
                $margin = $cell->ownerDocument->createElementNS(self::W, 'w:'.$edge);
                $margin->setAttributeNS(self::W, 'w:w', '0');
                $margin->setAttributeNS(self::W, 'w:type', 'dxa');
                $margins->appendChild($margin);
            }
            $properties->appendChild($margins);

            $labelCell = $cell->ownerDocument->createElementNS(self::W, 'w:tc');
            $labelProperties = $cell->ownerDocument->createElementNS(self::W, 'w:tcPr');
            $width = $cell->ownerDocument->createElementNS(self::W, 'w:tcW');
            $width->setAttributeNS(self::W, 'w:w', '5000');
            $width->setAttributeNS(self::W, 'w:type', 'dxa');
            $labelProperties->appendChild($width);
            $labelCell->appendChild($labelProperties);
            $label = $labelParagraphs[$index]->cloneNode(true);
            foreach ($this->elements($xpath, './w:pPr/w:ind', $label) as $labelIndent) {
                $labelIndent->parentNode->removeChild($labelIndent);
            }
            $this->replaceParagraphText($xpath, $label, $labels[$index]);
            $labelCell->appendChild($label);
            $cell->parentNode->appendChild($labelCell);
        }

        foreach ($labelParagraphs as $paragraph) {
            $paragraph->parentNode->removeChild($paragraph);
        }
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function documentAndXPath(string $xml, string $part): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException("The Comment-Response Form template contains invalid {$part} XML.");
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        return [$document, $xpath];
    }

    private function fillProjectTitle(DOMXPath $xpath, string $projectTitle): void
    {
        $paragraphs = $xpath->query('/w:document/w:body/w:p');

        foreach ($paragraphs as $index => $paragraph) {
            if (! $paragraph instanceof DOMElement || trim($this->paragraphText($paragraph)) !== 'TITLE OF RESEARCH PROPOSAL:') {
                continue;
            }

            $titleParagraph = $paragraphs->item($index + 1);

            if (! $titleParagraph instanceof DOMElement || ! str_contains($this->paragraphText($titleParagraph), '___')) {
                break;
            }

            $this->appendRun($paragraph, ' '.$projectTitle, $this->boldRunProperties($xpath, $paragraph));
            $titleParagraph->parentNode->removeChild($titleParagraph);

            return;
        }

        throw new RuntimeException('The Comment-Response Form project title slot is missing.');
    }

    /**
     * @param  array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
     *     feedback?: list<array{reviewer: string, location: string, comment: string}>,
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }  $commentResponseForm
     */
    private function fillResearchers(DOMXPath $xpath, array $commentResponseForm): void
    {
        foreach ($xpath->query('/w:document/w:body/w:tbl') as $table) {
            if (! $table instanceof DOMElement) {
                continue;
            }

            $rows = $this->elements($xpath, './w:tr', $table);

            if (count($rows) !== 4 || $this->rowText($xpath, $rows[0]) !== [
                'POSITION',
                'NAME',
                'CAMPUS',
                'COLLEGE',
                'DEPARTMENT',
            ]) {
                continue;
            }

            $heading = $xpath->query('preceding-sibling::w:p[1]', $table)->item(0);
            if (! $heading instanceof DOMElement || trim($this->paragraphText($heading)) !== 'RESEARCHERS:') {
                throw new RuntimeException('The Comment-Response Form researchers label is missing.');
            }

            $names = array_filter([
                $commentResponseForm['project_leader'],
                ...array_column($commentResponseForm['staff'], 'name'),
            ], static fn (string $name): bool => trim($name) !== '');
            $this->replaceParagraphText($xpath, $heading, 'PROJECT STAFF:');
            $this->appendRun($heading, ' '.implode(', ', $names), $this->boldRunProperties($xpath, $heading));
            $table->parentNode->removeChild($table);

            return;
        }

        throw new RuntimeException('The Comment-Response Form researcher table is missing.');
    }

    /** @param list<array{reviewer: string, location: string, comment: string, stage?: string, response?: string, remarks?: string}> $feedback */
    private function fillFeedbackTable(DOMXPath $xpath, array $feedback): void
    {
        if ($feedback === []) {
            return;
        }

        foreach ($xpath->query('/w:document/w:body/w:tbl') as $table) {
            $rows = $this->elements($xpath, './w:tr', $table);

            if (count($rows) < 2 || ! in_array('COMMENTS AND SUGGESTIONS', $this->rowText($xpath, $rows[0]), true)) {
                continue;
            }

            $template = $rows[1]->cloneNode(true);
            $headerProperties = $xpath->query('./w:trPr', $rows[0])->item(0);

            if (! $headerProperties instanceof DOMElement) {
                $headerProperties = $table->ownerDocument->createElementNS(self::W, 'w:trPr');
                $rows[0]->insertBefore($headerProperties, $rows[0]->firstChild);
            }

            if ($xpath->query('./w:tblHeader', $headerProperties)->length === 0) {
                $headerProperties->appendChild($table->ownerDocument->createElementNS(self::W, 'w:tblHeader'));
            }

            foreach (array_slice($rows, 1) as $row) {
                $table->removeChild($row);
            }

            foreach ($feedback as $index => $item) {
                $row = $template->cloneNode(true);
                $table->appendChild($row);

                foreach ($this->elements($xpath, './w:trPr/w:trHeight | ./w:trPr/w:cantSplit | .//w:pPr/w:keepNext | .//w:pPr/w:keepLines', $row) as $constraint) {
                    $constraint->parentNode->removeChild($constraint);
                }

                $cells = $this->elements($xpath, './w:tc', $row);
                $values = [($index + 1).'.', $item['comment'], $item['response'] ?? '', $item['remarks'] ?? ''];

                foreach ($cells as $offset => $cell) {
                    $paragraphs = $this->elements($xpath, './w:p', $cell);
                    $paragraphTemplate = $paragraphs[0]->cloneNode(true);

                    foreach ($paragraphs as $paragraph) {
                        $cell->removeChild($paragraph);
                    }

                    foreach (preg_split('/\R/u', $values[$offset]) as $line) {
                        $paragraph = $paragraphTemplate->cloneNode(true);
                        $cell->appendChild($paragraph);
                        $this->replaceParagraphText($xpath, $paragraph, $line);
                    }
                }
            }

            return;
        }

        throw new RuntimeException('The Comment-Response Form feedback table is missing.');
    }

    private function fillPreparedBy(DOMXPath $xpath, string $projectLeader): void
    {
        $paragraphs = $xpath->query('/w:document/w:body/w:p');
        $preparedByFound = false;

        foreach ($paragraphs as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $text = trim($this->paragraphText($paragraph));

            if ($text === 'Prepared by:') {
                $preparedByFound = true;

                continue;
            }

            if ($preparedByFound && $text === 'NAME') {
                $this->replaceParagraphText($xpath, $paragraph, $projectLeader);

                return;
            }
        }

        throw new RuntimeException('The Comment-Response Form prepared-by slot is missing.');
    }

    /** @param array{comment_response_head?: string, comment_response_vice_chancellor?: string} $form */
    private function fillReviewSignatories(DOMXPath $xpath, array $form): void
    {
        $table = $xpath->query('/w:document/w:body/w:tbl[.//w:t[contains(., "Research Head/ RDES Head")]]')->item(0);
        if (! $table instanceof DOMElement) {
            throw new RuntimeException('The Comment-Response Form review signatory slots are missing.');
        }

        $cells = $this->elements($xpath, './w:tr[1]/w:tc', $table);
        $defaults = ProposalSignatory::defaultSelections();
        foreach (['comment_response_head', 'comment_response_vice_chancellor'] as $index => $key) {
            $paragraph = $xpath->query('./w:p[1]', $cells[$index])->item(0);
            $this->replaceParagraphText($xpath, $paragraph, filled($form[$key] ?? null) ? $form[$key] : $defaults[$key]['name']);
        }
    }

    private function updateFooterPageFieldCache(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement || ! str_starts_with($this->paragraphText($paragraph), 'Page ')) {
                continue;
            }

            $fieldName = null;
            $isResult = false;

            foreach ($xpath->query('./w:r', $paragraph) as $run) {
                if (! $run instanceof DOMElement) {
                    continue;
                }

                $instruction = $xpath->query('./w:instrText', $run)->item(0)?->textContent;

                if ($instruction !== null) {
                    $fieldName = trim($instruction);
                }

                $fieldCharacter = $xpath->query('./w:fldChar', $run)->item(0);

                if ($fieldCharacter instanceof DOMElement) {
                    $fieldType = $fieldCharacter->getAttributeNS(self::W, 'fldCharType');

                    if ($fieldType === 'separate') {
                        $isResult = true;
                    } elseif ($fieldType === 'end') {
                        $fieldName = null;
                        $isResult = false;
                    }
                }

                if ($isResult && in_array($fieldName, ['PAGE', 'NUMPAGES'], true)) {
                    $result = $xpath->query('./w:t', $run)->item(0);

                    if ($result instanceof DOMElement) {
                        $result->nodeValue = '1';
                    }
                }
            }

            return;
        }
    }

    private function replaceParagraphText(DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $sourceRunProperties = $this->sourceRunProperties($xpath, $paragraph);
        $this->removeRuns($xpath, $paragraph);
        $this->appendRun($paragraph, $text, $sourceRunProperties);
    }

    private function sourceRunProperties(DOMXPath $xpath, DOMElement $paragraph): ?DOMNode
    {
        $runProperties = $xpath->query('./w:r[1]/w:rPr', $paragraph)->item(0)
            ?? $xpath->query('./w:pPr/w:rPr', $paragraph)->item(0);

        return $runProperties?->cloneNode(true);
    }

    private function boldRunProperties(DOMXPath $xpath, DOMElement $paragraph, ?int $size = null): DOMElement
    {
        $properties = $this->sourceRunProperties($xpath, $paragraph)
            ?? $paragraph->ownerDocument->createElementNS(self::W, 'w:rPr');
        foreach ($this->elements($xpath, './w:b | ./w:bCs | ./w:color | ./w:u', $properties) as $property) {
            $properties->removeChild($property);
        }
        $properties->appendChild($paragraph->ownerDocument->createElementNS(self::W, 'w:b'));
        $properties->appendChild($paragraph->ownerDocument->createElementNS(self::W, 'w:bCs'));
        $color = $paragraph->ownerDocument->createElementNS(self::W, 'w:color');
        $color->setAttributeNS(self::W, 'w:val', '000000');
        $properties->appendChild($color);
        if ($size !== null) {
            foreach ($this->elements($xpath, './w:sz | ./w:szCs', $properties) as $property) {
                $properties->removeChild($property);
            }
            foreach (['sz', 'szCs'] as $property) {
                $fontSize = $paragraph->ownerDocument->createElementNS(self::W, 'w:'.$property);
                $fontSize->setAttributeNS(self::W, 'w:val', (string) $size);
                $properties->appendChild($fontSize);
            }
        }

        return $properties;
    }

    private function removeRuns(DOMXPath $xpath, DOMElement $paragraph): void
    {
        $runs = [];

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            $runs[] = $run;
        }

        foreach ($runs as $run) {
            $paragraph->removeChild($run);
        }
    }

    private function appendRun(
        DOMElement $paragraph,
        string $text,
        ?DOMNode $sourceRunProperties,
    ): void {
        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $sourceRunProperties?->cloneNode(true);

        if ($runProperties instanceof DOMNode) {
            $run->appendChild($runProperties);
        }

        $textElement = $document->createElementNS(self::W, 'w:t');
        $textElement->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textElement->appendChild($document->createTextNode($text));
        $run->appendChild($textElement);
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

    /** @return list<string> */
    private function rowText(DOMXPath $xpath, DOMElement $row): array
    {
        return collect($this->elements($xpath, './w:tc', $row))
            ->map(fn (DOMElement $cell): string => trim($this->paragraphText($cell)))
            ->all();
    }

    private function paragraphText(DOMElement $element): string
    {
        $text = '';

        foreach ($element->getElementsByTagNameNS(self::W, 't') as $textNode) {
            $text .= $textNode->textContent;
        }

        return $text;
    }

    private function serialized(DOMDocument $document, string $part): string
    {
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException("The Comment-Response Form {$part} XML could not be serialized.");
        }

        return $xml;
    }
}
