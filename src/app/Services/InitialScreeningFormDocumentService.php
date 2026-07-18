<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class InitialScreeningFormDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** @param array{project_title: string, project_leader: string} $screeningForm */
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

    /** @param array{project_title: string, project_leader: string} $screeningForm */
    private function renderDocumentXml(string $documentXml, array $screeningForm): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The Initial Screening Form template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $this->fillLabeledValue($xpath, 'Research Project Title:', $screeningForm['project_title']);
        $this->fillLabeledValue($xpath, 'Project Leader:', $screeningForm['project_leader']);
        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Initial Screening Form XML could not be serialized.');
        }

        return $renderedXml;
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
