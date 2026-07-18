<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class CommentResponseFormDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    /**
     * @param  array{
     *     project_title: string,
     *     project_leader: string,
     *     leader_campus: string,
     *     leader_college: string,
     *     leader_department: string,
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
            $footerXml = $archive->getFromName('word/footer1.xml');

            if ($documentXml === false || $footerXml === false) {
                throw new RuntimeException('The Comment-Response Form body or footer is missing.');
            }

            if (! $archive->addFromString(
                'word/document.xml',
                $this->renderDocumentXml($documentXml, $commentResponseForm),
            ) || ! $archive->addFromString(
                'word/footer1.xml',
                $this->renderFooterXml($footerXml, $commentResponseForm['project_title']),
            )) {
                throw new RuntimeException('The generated Comment-Response Form could not be written.');
            }

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
     *     staff: list<array{name: string, campus: string, college: string, department: string}>
     * }  $commentResponseForm
     */
    private function renderDocumentXml(string $documentXml, array $commentResponseForm): string
    {
        [$document, $xpath] = $this->documentAndXPath($documentXml, 'document');
        $this->fillProjectTitle($xpath, $commentResponseForm['project_title']);
        $this->fillResearchers($xpath, $commentResponseForm);
        $this->fillPreparedBy($xpath, $commentResponseForm['project_leader']);

        return $this->serialized($document, 'document');
    }

    private function renderFooterXml(string $footerXml, string $projectTitle): string
    {
        [$document, $xpath] = $this->documentAndXPath($footerXml, 'footer');
        $this->fillFooterTitle($xpath, $projectTitle);
        $this->updateFooterPageFieldCache($xpath);

        return $this->serialized($document, 'footer');
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

            $remainingLineLength = max(0, 90 - Str::length($projectTitle));
            $this->replaceParagraphWithTitleLine(
                $xpath,
                $titleParagraph,
                $projectTitle,
                str_repeat('_', $remainingLineLength),
            );

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

            $this->fillResearcherRow($xpath, $rows[1], [
                'name' => $commentResponseForm['project_leader'],
                'campus' => $commentResponseForm['leader_campus'],
                'college' => $commentResponseForm['leader_college'],
                'department' => $commentResponseForm['leader_department'],
            ]);

            foreach ($commentResponseForm['staff'] as $index => $member) {
                if ($index >= 2) {
                    break;
                }

                $this->fillResearcherRow($xpath, $rows[$index + 2], $member);
            }

            return;
        }

        throw new RuntimeException('The Comment-Response Form researcher table is missing.');
    }

    /** @param array{name: string, campus: string, college: string, department: string} $researcher */
    private function fillResearcherRow(DOMXPath $xpath, DOMElement $row, array $researcher): void
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== 5) {
            throw new RuntimeException('A Comment-Response Form researcher row is malformed.');
        }

        foreach (['name', 'campus', 'college', 'department'] as $offset => $key) {
            if ($researcher[$key] === '') {
                continue;
            }

            $paragraph = $xpath->query('./w:p[1]', $cells[$offset + 1])->item(0);

            if (! $paragraph instanceof DOMElement) {
                throw new RuntimeException('A Comment-Response Form researcher cell is missing.');
            }

            $this->replaceParagraphText($xpath, $paragraph, $researcher[$key]);
        }
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

    private function fillFooterTitle(DOMXPath $xpath, string $projectTitle): void
    {
        foreach ($xpath->query('//w:p') as $paragraph) {
            if (! $paragraph instanceof DOMElement
                || ! str_starts_with($this->paragraphText($paragraph), 'Comment-Response Form |')) {
                continue;
            }

            $textNodes = $xpath->query('.//w:t', $paragraph);

            if ($textNodes->length < 2) {
                break;
            }

            $titleNode = $textNodes->item(1);

            if (! $titleNode instanceof DOMElement) {
                break;
            }

            $titleNode->setAttributeNS(self::XML, 'xml:space', 'preserve');
            $titleNode->nodeValue = ' '.$projectTitle;

            for ($index = 2; $index < $textNodes->length; $index++) {
                $textNodes->item($index)->nodeValue = '';
            }

            return;
        }

        throw new RuntimeException('The Comment-Response Form footer title slot is missing.');
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

    private function replaceParagraphWithTitleLine(
        DOMXPath $xpath,
        DOMElement $paragraph,
        string $projectTitle,
        string $remainingLine,
    ): void {
        $sourceRunProperties = $this->sourceRunProperties($xpath, $paragraph);
        $this->removeRuns($xpath, $paragraph);
        $this->appendRun($paragraph, $projectTitle, $sourceRunProperties, true);

        if ($remainingLine !== '') {
            $this->appendRun($paragraph, $remainingLine, $sourceRunProperties);
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
        bool $underlined = false,
    ): void {
        $document = $paragraph->ownerDocument;
        $run = $document->createElementNS(self::W, 'w:r');
        $runProperties = $sourceRunProperties?->cloneNode(true);

        if ($underlined) {
            if (! $runProperties instanceof DOMElement) {
                $runProperties = $document->createElementNS(self::W, 'w:rPr');
            }

            $underline = $document->createElementNS(self::W, 'w:u');
            $underline->setAttributeNS(self::W, 'w:val', 'single');
            $runProperties->appendChild($underline);
        }

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
