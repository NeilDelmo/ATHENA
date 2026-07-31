<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class WordDocumentPaginationService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    public function addPageNumbers(ZipArchive $archive): void
    {
        foreach ($this->footerPartNames($archive) as $footerPartName) {
            $footerXml = $archive->getFromName($footerPartName);

            if ($footerXml === false) {
                throw new RuntimeException("The Word footer [{$footerPartName}] could not be read.");
            }

            $renderedFooterXml = $this->renderFooterXml($footerXml);

            if (! $archive->addFromString($footerPartName, $renderedFooterXml)) {
                throw new RuntimeException("The Word footer [{$footerPartName}] could not be updated.");
            }
        }

        $settingsXml = $archive->getFromName('word/settings.xml');

        if ($settingsXml === false) {
            throw new RuntimeException('The Word document settings are missing.');
        }

        if (! $archive->addFromString('word/settings.xml', $this->enableFieldUpdates($settingsXml))) {
            throw new RuntimeException('The Word document field settings could not be updated.');
        }
    }

    /** @return list<string> */
    private function footerPartNames(ZipArchive $archive): array
    {
        $footerPartNames = [];

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $partName = $archive->getNameIndex($index);

            if (is_string($partName)
                && preg_match('/^word\/footer\d+\.xml$/', $partName) === 1) {
                $footerPartNames[] = $partName;
            }
        }

        if ($footerPartNames === []) {
            throw new RuntimeException('The Word document does not contain a footer.');
        }

        sort($footerPartNames, SORT_NATURAL);

        return $footerPartNames;
    }

    private function renderFooterXml(string $footerXml): string
    {
        [$document, $xpath] = $this->documentAndXPath($footerXml, 'footer');

        if ($this->hasPageField($xpath)) {
            return $footerXml;
        }

        $footer = $document->documentElement;

        if (! $footer instanceof DOMElement) {
            throw new RuntimeException('The Word footer root is missing.');
        }

        $footer->appendChild($this->pageNumberParagraph($document));

        return $this->serialized($document, 'footer');
    }

    private function hasPageField(DOMXPath $xpath): bool
    {
        foreach ($xpath->query('//w:instrText') as $instruction) {
            if (preg_match('/^PAGE(?:\s|$)/i', trim($instruction->textContent)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function pageNumberParagraph(DOMDocument $document): DOMElement
    {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
        $style = $document->createElementNS(self::W, 'w:pStyle');
        $style->setAttributeNS(self::W, 'w:val', 'Footer');
        $alignment = $document->createElementNS(self::W, 'w:jc');
        $alignment->setAttributeNS(self::W, 'w:val', 'right');
        $spacing = $document->createElementNS(self::W, 'w:spacing');
        $spacing->setAttributeNS(self::W, 'w:before', '0');
        $spacing->setAttributeNS(self::W, 'w:after', '0');
        $paragraphProperties->appendChild($style);
        $paragraphProperties->appendChild($alignment);
        $paragraphProperties->appendChild($spacing);
        $paragraph->appendChild($paragraphProperties);
        $paragraph->appendChild($this->textRun($document, 'Page '));
        $this->appendField($document, $paragraph, 'PAGE');
        $paragraph->appendChild($this->textRun($document, ' of '));
        $this->appendField($document, $paragraph, 'NUMPAGES');

        return $paragraph;
    }

    private function appendField(DOMDocument $document, DOMElement $paragraph, string $instruction): void
    {
        $beginRun = $this->runWithProperties($document);
        $begin = $document->createElementNS(self::W, 'w:fldChar');
        $begin->setAttributeNS(self::W, 'w:fldCharType', 'begin');
        $beginRun->appendChild($begin);
        $paragraph->appendChild($beginRun);

        $instructionRun = $this->runWithProperties($document);
        $instructionText = $document->createElementNS(self::W, 'w:instrText');
        $instructionText->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $instructionText->appendChild($document->createTextNode(" {$instruction} "));
        $instructionRun->appendChild($instructionText);
        $paragraph->appendChild($instructionRun);

        $separatorRun = $this->runWithProperties($document);
        $separator = $document->createElementNS(self::W, 'w:fldChar');
        $separator->setAttributeNS(self::W, 'w:fldCharType', 'separate');
        $separatorRun->appendChild($separator);
        $paragraph->appendChild($separatorRun);
        $paragraph->appendChild($this->textRun($document, '1'));

        $endRun = $this->runWithProperties($document);
        $end = $document->createElementNS(self::W, 'w:fldChar');
        $end->setAttributeNS(self::W, 'w:fldCharType', 'end');
        $endRun->appendChild($end);
        $paragraph->appendChild($endRun);
    }

    private function textRun(DOMDocument $document, string $text): DOMElement
    {
        $run = $this->runWithProperties($document);
        $textElement = $document->createElementNS(self::W, 'w:t');
        $textElement->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textElement->appendChild($document->createTextNode($text));
        $run->appendChild($textElement);

        return $run;
    }

    private function runWithProperties(DOMDocument $document): DOMElement
    {
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $document->createElementNS(self::W, 'w:rPr');
        $fonts = $document->createElementNS(self::W, 'w:rFonts');
        $fonts->setAttributeNS(self::W, 'w:ascii', 'Times New Roman');
        $fonts->setAttributeNS(self::W, 'w:hAnsi', 'Times New Roman');
        $fonts->setAttributeNS(self::W, 'w:cs', 'Times New Roman');
        $fontSize = $document->createElementNS(self::W, 'w:sz');
        $fontSize->setAttributeNS(self::W, 'w:val', '18');
        $complexFontSize = $document->createElementNS(self::W, 'w:szCs');
        $complexFontSize->setAttributeNS(self::W, 'w:val', '18');
        $runProperties->appendChild($fonts);
        $runProperties->appendChild($fontSize);
        $runProperties->appendChild($complexFontSize);
        $run->appendChild($runProperties);

        return $run;
    }

    private function enableFieldUpdates(string $settingsXml): string
    {
        [$document, $xpath] = $this->documentAndXPath($settingsXml, 'settings');
        $updateFields = $xpath->query('/w:settings/w:updateFields')->item(0);

        if ($updateFields instanceof DOMElement
            && in_array($updateFields->getAttributeNS(self::W, 'val'), ['1', 'true'], true)) {
            return $settingsXml;
        }

        if (! $updateFields instanceof DOMElement) {
            $settings = $document->documentElement;

            if (! $settings instanceof DOMElement) {
                throw new RuntimeException('The Word settings root is missing.');
            }

            $updateFields = $document->createElementNS(self::W, 'w:updateFields');
            $settings->appendChild($updateFields);
        }

        $updateFields->setAttributeNS(self::W, 'w:val', 'true');

        return $this->serialized($document, 'settings');
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function documentAndXPath(string $xml, string $part): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException("The Word {$part} XML is invalid.");
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        return [$document, $xpath];
    }

    private function serialized(DOMDocument $document, string $part): string
    {
        return $document->saveXML()
            ?: throw new RuntimeException("The Word {$part} XML could not be serialized.");
    }
}
