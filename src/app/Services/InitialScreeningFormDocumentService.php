<?php

namespace App\Services;

use App\Support\InitialScreeningSubmissionOrder;
use App\Support\ResearchHeadScreeningData;
use App\Support\WordCheckbox;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class InitialScreeningFormDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    public function __construct(
        private readonly WordDocumentPaginationService $paginationService,
    ) {}

    /** @param array{project_title: string, project_leader: string, order_of_submission?: string, level_of_call?: string|null} $screeningForm */
    public function generate(array $screeningForm): string
    {
        $templatePath = (string) config('initial_screening_form.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Initial Screening Form template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-initial-screening-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Initial Screening Form document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Initial Screening Form template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The Initial Screening Form document body is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $screeningForm))) {
                throw new RuntimeException('The generated Initial Screening Form could not be written.');
            }

            $this->paginationService->addPageNumbers($archive);
            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Initial Screening Form could not be read.');
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

    /** @param array{project_title: string, project_leader: string, order_of_submission?: string, level_of_call?: string|null} $screeningForm */
    private function renderDocumentXml(string $documentXml, array $screeningForm): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The Initial Screening Form template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $signatureKeys = ['screening_head', 'screening_center', 'screening_verifier'];
        $signatureIndex = 0;
        foreach ($xpath->query('//w:p') as $paragraph) {
            if (trim($paragraph->textContent) !== 'NAME') {
                continue;
            }
            $key = $signatureKeys[$signatureIndex++] ?? null;

            if ($key !== null) {
                $name = filled($screeningForm[$key] ?? null)
                    ? mb_strtoupper((string) $screeningForm[$key])
                    : 'NAME';
                $this->replaceSignatureName($xpath, $paragraph, $name);
            }
        }
        $this->checkOrderOfSubmission(
            $xpath,
            $screeningForm['order_of_submission'] ?? InitialScreeningSubmissionOrder::FIRST_SUBMISSION,
        );
        $this->fillLabeledValue($xpath, 'Research Project Title:', $screeningForm['project_title']);
        $levelCheckboxes = iterator_to_array($xpath->query('//w:body//w:checkBox'));
        foreach ([3 => 'central_agency', 4 => 'constituent_campus'] as $index => $level) {
            $checkbox = $levelCheckboxes[$index] ?? null;
            if (! $checkbox instanceof DOMElement) {
                throw new RuntimeException('The Initial Screening Form call level checkbox is missing.');
            }
            WordCheckbox::setLegacy($xpath, $checkbox, ($screeningForm['level_of_call'] ?? null) === $level);
        }
        foreach ([5 => InitialScreeningSubmissionOrder::FOR_ENDORSEMENT, 6 => InitialScreeningSubmissionOrder::MINOR_REVISION, 7 => InitialScreeningSubmissionOrder::MAJOR_REVISION] as $index => $recommendation) {
            $checkbox = $levelCheckboxes[$index] ?? null;
            if ($checkbox instanceof DOMElement) {
                WordCheckbox::setLegacy($xpath, $checkbox, ($screeningForm['recommended_action'] ?? null) === $recommendation);
            }
        }
        $this->fillLabeledValue($xpath, 'Project Leader:', $screeningForm['project_leader']);
        $this->fillScreeningResults($xpath, $screeningForm);
        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Initial Screening Form XML could not be serialized.');
        }

        return $renderedXml;
    }

    private function replaceSignatureName(DOMXPath $xpath, DOMElement $paragraph, string $name): void
    {
        $signatureLine = $xpath->query('preceding-sibling::w:p[1]', $paragraph)->item(0);

        if (! $signatureLine instanceof DOMElement
            || preg_match('/^_{10,}$/', trim($signatureLine->textContent)) !== 1) {
            throw new RuntimeException('An Initial Screening Form handwritten signature line is missing.');
        }

        $this->clearParagraphContent($signatureLine);
        $this->clearParagraphContent($paragraph);

        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $document->createElementNS(self::W, 'w:rPr');
        $runProperties->appendChild($document->createElementNS(self::W, 'w:b'));
        $underline = $document->createElementNS(self::W, 'w:u');
        $underline->setAttributeNS(self::W, 'w:val', 'single');
        $runProperties->appendChild($underline);
        $run->appendChild($runProperties);
        $text = $document->createElementNS(self::W, 'w:t');
        $text->appendChild($document->createTextNode($name));
        $run->appendChild($text);
        $paragraph->appendChild($run);
    }

    /** @param array<string, mixed> $screeningForm */
    private function fillScreeningResults(DOMXPath $xpath, array $screeningForm): void
    {
        foreach (['requested_budget' => 'Requested Budget:', 'duration_months' => 'Duration (months):', 'researcher_count' => 'Number of Researchers Involved:', 'department' => 'Department:', 'college' => 'College:', 'campus' => 'Campus:'] as $key => $label) {
            if (filled($screeningForm[$key] ?? null)) {
                $value = $key === 'requested_budget' ? number_format((float) $screeningForm[$key], 2) : (string) $screeningForm[$key];
                $this->fillLabeledValue($xpath, $label, $value);
            }
        }

        $checklist = $xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Particulars"]]')->item(0);
        if ($checklist instanceof DOMElement) {
            foreach ($xpath->query('./w:tr', $checklist) as $row) {
                $cells = $xpath->query('./w:tc', $row);
                $type = array_search(trim($cells->item(0)->textContent), ResearchHeadScreeningData::DOCUMENTS, true);
                if ($type !== false && isset($screeningForm['documents'][$type])) {
                    $entry = $screeningForm['documents'][$type];
                    $this->fillCell($xpath, $cells->item(1), ! empty($entry['attached']) ? '×' : '');
                    $this->fillCell($xpath, $cells->item(2), (string) ($entry['pages'] ?? ''));
                }
            }
        }

        $rubrics = $xpath->query('//w:tbl[w:tr[1]/w:tc[1]//w:t[text()="Criteria"]]')->item(0);
        if ($rubrics instanceof DOMElement && ($screeningForm['scores'] ?? []) !== []) {
            foreach (['documents' => 2, 'alignment' => 4, 'content' => 6] as $key => $row) {
                $cell = $xpath->query('./w:tr['.$row.']/w:tc[last()]', $rubrics)->item(0);
                $this->fillCell($xpath, $cell, (string) ($screeningForm['scores'][$key] ?? ''));
            }
            $this->fillCell($xpath, $xpath->query('./w:tr[last()]/w:tc[last()]', $rubrics)->item(0), (string) array_sum($screeningForm['scores']));
        }

        if (filled($screeningForm['narrative_evaluation'] ?? null)) {
            $heading = $xpath->query('//w:p[w:r/w:t[text()="Narrative Evaluation:"]]')->item(0);
            if (! $heading instanceof DOMElement) {
                throw new RuntimeException('The Initial Screening Form narrative slot is missing.');
            }
            $cell = $heading->parentNode;
            foreach (iterator_to_array($xpath->query('./w:p[not(.//w:t[normalize-space(.) != ""]) and not(.//w:drawing)]', $cell)) as $blankParagraph) {
                $cell->removeChild($blankParagraph);
            }
            foreach (preg_split('/\R/u', $screeningForm['narrative_evaluation']) as $line) {
                $paragraph = $heading->cloneNode(true);
                $this->clearParagraphContent($paragraph);
                $this->appendSourceStyledRun($xpath, $paragraph, $line);
                $cell->appendChild($paragraph);
            }
            foreach ($xpath->query('ancestor::w:tr[1]/w:trPr/w:trHeight', $cell) as $height) {
                $height->setAttributeNS(self::W, 'w:hRule', 'atLeast');
            }
            foreach (iterator_to_array($xpath->query('ancestor::w:tr[1]/w:trPr/w:cantSplit | .//w:pPr/w:keepNext | .//w:pPr/w:keepLines', $cell)) as $constraint) {
                $constraint->parentNode->removeChild($constraint);
            }
            foreach ($xpath->query('//w:tr[w:tc/w:p/w:r/w:t[text()="Prepared by:" or text()="Checked and Verified by:"]]') as $signatureRow) {
                $properties = $xpath->query('./w:trPr', $signatureRow)->item(0);
                if (! $properties instanceof DOMElement) {
                    $properties = $signatureRow->ownerDocument->createElementNS(self::W, 'w:trPr');
                    $signatureRow->insertBefore($properties, $signatureRow->firstChild);
                }
                if ($xpath->query('./w:cantSplit', $properties)->length === 0) {
                    $properties->appendChild($signatureRow->ownerDocument->createElementNS(self::W, 'w:cantSplit'));
                }
            }
            foreach ($xpath->query('/w:document/w:body/w:p[not(.//w:t[normalize-space(.) != ""])]') as $trailingParagraph) {
                foreach ($xpath->query('./w:pPr/w:rPr/w:sz | ./w:pPr/w:rPr/w:szCs', $trailingParagraph) as $size) {
                    $size->setAttributeNS(self::W, 'w:val', '2');
                }
                foreach ($xpath->query('./w:pPr/w:spacing', $trailingParagraph) as $spacing) {
                    $spacing->setAttributeNS(self::W, 'w:before', '0');
                    $spacing->setAttributeNS(self::W, 'w:after', '0');
                    $spacing->setAttributeNS(self::W, 'w:line', '20');
                    $spacing->setAttributeNS(self::W, 'w:lineRule', 'exact');
                }
            }
        }
    }

    private function fillCell(DOMXPath $xpath, ?DOMElement $cell, string $value): void
    {
        if (! $cell instanceof DOMElement) {
            throw new RuntimeException('An Initial Screening Form result cell is missing.');
        }
        $paragraph = $xpath->query('./w:p[1]', $cell)->item(0);
        if (! $paragraph instanceof DOMElement) {
            throw new RuntimeException('An Initial Screening Form result paragraph is missing.');
        }
        $this->clearParagraphContent($paragraph);
        $this->appendSourceStyledRun($xpath, $paragraph, $value);
    }

    private function clearParagraphContent(DOMElement $paragraph): void
    {
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'pPr') {
                $paragraph->removeChild($child);
            }
        }
    }

    private function checkOrderOfSubmission(DOMXPath $xpath, string $order): void
    {
        $labels = [
            'First Submission' => InitialScreeningSubmissionOrder::FIRST_SUBMISSION,
            'Revised with Minor Changes' => InitialScreeningSubmissionOrder::REVISED_WITH_MINOR_CHANGES,
            'Revised with Major Changes' => InitialScreeningSubmissionOrder::REVISED_WITH_MAJOR_CHANGES,
        ];

        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $paragraphOrder = $labels[trim($this->paragraphText($paragraph))] ?? null;

            if ($paragraphOrder === null) {
                continue;
            }

            $checkBox = $xpath->query('.//w:ffData/w:checkBox', $paragraph)->item(0);

            if (! $checkBox instanceof DOMElement) {
                throw new RuntimeException("The Initial Screening Form checkbox [{$paragraphOrder}] is missing.");
            }

            WordCheckbox::setLegacy($xpath, $checkBox, $paragraphOrder === $order);
        }
    }

    private function fillLabeledValue(DOMXPath $xpath, string $label, string $value): void
    {
        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement
                || ! str_starts_with(trim($this->paragraphText($paragraph)), $label)) {
                continue;
            }

            $separator = str_ends_with($this->paragraphText($paragraph), ' ') ? '' : ' ';
            $this->appendSourceStyledRun($xpath, $paragraph, $separator.$value);

            return;
        }

        throw new RuntimeException("The Initial Screening Form slot [{$label}] is missing.");
    }

    private function appendSourceStyledRun(DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $sourceRunProperties = $xpath->query('./w:r[last()]/w:rPr', $paragraph)->item(0);

        if ($sourceRunProperties instanceof DOMElement) {
            $run->appendChild($sourceRunProperties->cloneNode(true));
        }

        $textElement = $document->createElementNS(self::W, 'w:t');
        $textElement->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textElement->appendChild($document->createTextNode($text));
        $run->appendChild($textElement);
        $paragraph->appendChild($run);
    }

    private function paragraphText(DOMElement $paragraph): string
    {
        $text = '';

        foreach ($paragraph->getElementsByTagNameNS(self::W, 't') as $textNode) {
            $text .= $textNode->textContent;
        }

        return $text;
    }
}
