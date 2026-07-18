<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class GADChecklistDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /** @param array{project_title: string, project_leader: string} $checklist */
    public function generate(array $checklist): string
    {
        $templatePath = (string) config('gad_checklist.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official GAD Generic Checklist template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-gad-checklist-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary GAD Generic Checklist document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official GAD Generic Checklist template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');

            if ($documentXml === false) {
                throw new RuntimeException('The GAD Generic Checklist document body is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $checklist))) {
                throw new RuntimeException('The generated GAD Generic Checklist could not be written.');
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated GAD Generic Checklist could not be read.');
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

    /** @param array{project_title: string, project_leader: string} $checklist */
    private function renderDocumentXml(string $documentXml, array $checklist): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The GAD Generic Checklist template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        $this->fillProjectTitle($xpath, $checklist['project_title']);
        $this->fillProjectLeader($xpath, $checklist['project_leader']);

        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The GAD Generic Checklist XML could not be serialized.');
        }

        return $renderedXml;
    }

    private function fillProjectTitle(DOMXPath $xpath, string $title): void
    {
        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            if (str_contains($this->paragraphText($paragraph), 'Research Project Title:')) {
                $this->appendRun($paragraph, ' '.$title, true);

                return;
            }
        }

        throw new RuntimeException('The GAD Generic Checklist project title slot is missing.');
    }

    private function fillProjectLeader(DOMXPath $xpath, string $leader): void
    {
        $preparedByParagraph = null;

        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement) {
                continue;
            }

            $text = $this->paragraphText($paragraph);

            if ($preparedByParagraph instanceof DOMElement) {
                if (trim($text) === 'NAME') {
                    $this->replaceParagraphText($paragraph, $leader, true);

                    return;
                }

                continue;
            }

            if (str_contains($text, 'Prepared by:')) {
                $preparedByParagraph = $paragraph;
            }
        }

        throw new RuntimeException('The GAD Generic Checklist project leader slot is missing.');
    }

    private function appendRun(DOMElement $paragraph, string $text, bool $bold = false): void
    {
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

    private function replaceParagraphText(DOMElement $paragraph, string $text, bool $bold = false): void
    {
        foreach (iterator_to_array($paragraph->childNodes) as $child) {
            if (! $child instanceof DOMElement || $child->localName !== 'pPr') {
                $paragraph->removeChild($child);
            }
        }

        $this->appendRun($paragraph, $text, $bold);
    }

    private function paragraphText(DOMElement $paragraph): string
    {
        $text = '';

        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'r') {
                $text .= $child->textContent;
            }
        }

        return $text;
    }
}
