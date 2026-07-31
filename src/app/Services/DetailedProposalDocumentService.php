<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class DetailedProposalDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const W14 = 'http://schemas.microsoft.com/office/word/2010/wordml';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const PIC = 'http://schemas.openxmlformats.org/drawingml/2006/picture';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    private const SIGNATORY_NOTE_TAB_COUNTS = [
        'Notes: The Signatories funded by:' => 3,
        'Approval through Research Council' => 4,
        'Director, Research; Vice President for RDES: & University President' => 5,
        'Approval through Local Research Evaluation Committee' => 4,
        'Head, Research/Head Research & Extension; Vice Chancellor for RDES; & Vice President for RDES' => 5,
    ];

    public function __construct(
        private readonly DetailedProposalMethodologyImageService $methodologyImageService,
        private readonly WordDocumentPaginationService $paginationService,
    ) {}

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

            $methodologyImages = $this->embedMethodologyImages($archive, $proposal);

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $proposal, $methodologyImages))) {
                throw new RuntimeException('The generated Detailed Research Proposal could not be written.');
            }

            $settingsXml = $archive->getFromName('word/settings.xml');

            if ($settingsXml !== false
                && ! $archive->addFromString('word/settings.xml', $this->renderSettingsXml($settingsXml))) {
                throw new RuntimeException('The Detailed Research Proposal field settings could not be written.');
            }

            $this->paginationService->addPageNumbers($archive);
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

    /**
     * @param  array<string, mixed>  $proposal
     * @param  list<array<string, mixed>>  $methodologyImages
     */
    private function renderDocumentXml(string $documentXml, array $proposal, array $methodologyImages): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($documentXml, LIBXML_NONET)) {
            throw new RuntimeException('The Detailed Research Proposal template contains invalid document XML.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);
        $xpath->registerNamespace('w14', self::W14);
        $xpath->registerNamespace('wp', self::WP);
        $xpath->registerNamespace('a', self::A);
        $table = $this->firstElement($xpath, '//w:body/w:tbl[1]');
        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 40) {
            throw new RuntimeException('The official Detailed Research Proposal table structure has changed.');
        }

        $this->formatHeaderRow($xpath, $rows[0]);
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
        $this->fillIntroductionAndLiterature(
            $xpath,
            $rows[21],
            $proposal['introduction'],
            $proposal['related_literature'],
        );
        $nextImageDocumentPropertyId = $this->nextImageDocumentPropertyId($xpath);
        $this->fillMethodology($xpath, $rows[22], $proposal['methodology'], $methodologyImages, $nextImageDocumentPropertyId);
        $this->fillResponsibilities($xpath, $rows[23], $proposal['responsibilities']);
        $this->fillBudget($xpath, $rows[26], (float) $proposal['mooe_total']);
        $this->fillBudget($xpath, $rows[27], (float) $proposal['co_total']);
        $this->fillNarrativeRow($xpath, $rows[28], $proposal['references']);
        $this->fillPreparedBy($xpath, $rows, $proposal);
        $this->fillApprovalSignatories($xpath, $rows, $proposal);
        $this->formatSignatoryNotes($xpath);

        $renderedXml = $document->saveXML();

        if ($renderedXml === false) {
            throw new RuntimeException('The Detailed Research Proposal XML could not be serialized.');
        }

        return $renderedXml;
    }

    private function formatHeaderRow(DOMXPath $xpath, DOMElement $row): void
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== 4) {
            throw new RuntimeException('The Detailed Research Proposal header is incomplete.');
        }

        $labels = [
            1 => 'Reference No.: BatStateU-FO-RES-02',
            2 => 'Effectivity Date: August 22, 2023',
            3 => 'Revision No.: 04',
        ];

        foreach ($labels as $index => $label) {
            $paragraph = $this->paragraphs($xpath, $cells[$index])[0] ?? null;

            if (! $paragraph instanceof DOMElement) {
                throw new RuntimeException('A Detailed Research Proposal header label is missing.');
            }

            $this->replaceParagraphText($paragraph, $label, alignment: 'center', fontSizeHalfPoints: 18);
            $cellProperties = $this->firstElement($xpath, './w:tcPr', $cells[$index]);

            if (! $xpath->query('./w:noWrap', $cellProperties)->item(0) instanceof DOMElement) {
                $cellProperties->appendChild($cellProperties->ownerDocument->createElementNS(self::W, 'w:noWrap'));
            }
        }

        $logoExtent = $xpath->query('.//wp:extent', $cells[0])->item(0);
        $logoTransformExtent = $xpath->query('.//a:xfrm/a:ext', $cells[0])->item(0);

        if ($logoExtent instanceof DOMElement && $logoTransformExtent instanceof DOMElement) {
            $originalWidth = max(1, (int) $logoExtent->getAttribute('cx'));
            $originalHeight = max(1, (int) $logoExtent->getAttribute('cy'));
            $width = 411480;
            $height = (int) round($width * ($originalHeight / $originalWidth));

            foreach ([$logoExtent, $logoTransformExtent] as $extent) {
                $extent->setAttribute('cx', (string) $width);
                $extent->setAttribute('cy', (string) $height);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return list<array<string, mixed>>
     */
    private function embedMethodologyImages(ZipArchive $archive, array $proposal): array
    {
        $images = $proposal['methodology_images'] ?? [];

        if (! is_array($images) || $images === []) {
            return [];
        }

        $relationshipsXml = $archive->getFromName('word/_rels/document.xml.rels');
        $contentTypesXml = $archive->getFromName('[Content_Types].xml');

        if ($relationshipsXml === false || $contentTypesXml === false) {
            throw new RuntimeException('The Detailed Research Proposal image package structure is incomplete.');
        }

        $relationships = new DOMDocument('1.0', 'UTF-8');
        $contentTypes = new DOMDocument('1.0', 'UTF-8');

        if (! $relationships->loadXML($relationshipsXml, LIBXML_NONET)
            || ! $contentTypes->loadXML($contentTypesXml, LIBXML_NONET)
            || ! $relationships->documentElement instanceof DOMElement
            || ! $contentTypes->documentElement instanceof DOMElement) {
            throw new RuntimeException('The Detailed Research Proposal image package could not be read.');
        }

        $relationshipRoot = $relationships->documentElement;
        $contentTypeRoot = $contentTypes->documentElement;
        $relationshipNumber = $this->nextRelationshipNumber($relationshipRoot);
        $embeddedImages = [];
        $imageExtensions = [];

        foreach ($images as $index => $image) {
            if (! is_array($image)) {
                continue;
            }

            $contents = $this->methodologyImageService->contents($image);
            $mimeType = $this->methodologyImageService->mimeType($image);
            $extension = $this->imageExtension($mimeType);
            $dimensions = $contents === null ? false : getimagesizefromstring($contents);

            if ($contents === null || $extension === null || $dimensions === false) {
                continue;
            }

            $imageId = preg_replace('/[^A-Za-z0-9-]/', '', (string) ($image['id'] ?? '')) ?: ($index + 1);
            $mediaFilename = 'methodology-'.$imageId.'-'.($index + 1).'.'.$extension;
            $mediaPath = 'word/media/'.$mediaFilename;
            $relationshipId = 'rId'.$relationshipNumber++;

            if (! $archive->addFromString($mediaPath, $contents)) {
                throw new RuntimeException('A methodology image could not be added to the Word document.');
            }

            $relationship = $relationships->createElementNS(self::RELATIONSHIPS, 'Relationship');
            $relationship->setAttribute('Id', $relationshipId);
            $relationship->setAttribute('Type', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
            $relationship->setAttribute('Target', 'media/'.$mediaFilename);
            $relationshipRoot->appendChild($relationship);
            $imageExtensions[] = $extension;
            $embeddedImages[] = [
                ...$image,
                'relationship_id' => $relationshipId,
                'pixel_width' => (int) $dimensions[0],
                'pixel_height' => (int) $dimensions[1],
            ];
        }

        if ($embeddedImages === []) {
            return [];
        }

        $this->ensureImageContentTypes($contentTypeRoot, $imageExtensions);
        $renderedRelationships = $relationships->saveXML();
        $renderedContentTypes = $contentTypes->saveXML();

        if ($renderedRelationships === false || $renderedContentTypes === false
            || ! $archive->addFromString('word/_rels/document.xml.rels', $renderedRelationships)
            || ! $archive->addFromString('[Content_Types].xml', $renderedContentTypes)) {
            throw new RuntimeException('The Detailed Research Proposal image references could not be written.');
        }

        return $embeddedImages;
    }

    /** @param list<string> $extensions */
    private function ensureImageContentTypes(DOMElement $contentTypeRoot, array $extensions): void
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
        ];
        $existingExtensions = [];

        foreach ($contentTypeRoot->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'Default') {
                $existingExtensions[] = strtolower($child->getAttribute('Extension'));
            }
        }

        foreach (array_unique($extensions) as $extension) {
            if (in_array($extension, $existingExtensions, true)) {
                continue;
            }

            $contentType = $contentTypeRoot->ownerDocument->createElementNS($contentTypeRoot->namespaceURI, 'Default');
            $contentType->setAttribute('Extension', $extension);
            $contentType->setAttribute('ContentType', $mimeTypes[$extension]);
            $contentTypeRoot->appendChild($contentType);
        }
    }

    private function nextRelationshipNumber(DOMElement $relationshipRoot): int
    {
        $numbers = [];

        foreach ($relationshipRoot->childNodes as $relationship) {
            if (! $relationship instanceof DOMElement
                || preg_match('/^rId(\d+)$/', $relationship->getAttribute('Id'), $matches) !== 1) {
                continue;
            }

            $numbers[] = (int) $matches[1];
        }

        return ($numbers === [] ? 0 : max($numbers)) + 1;
    }

    private function nextImageDocumentPropertyId(DOMXPath $xpath): int
    {
        $ids = [];

        foreach ($xpath->query('//wp:docPr') as $documentProperties) {
            if ($documentProperties instanceof DOMElement && ctype_digit($documentProperties->getAttribute('id'))) {
                $ids[] = (int) $documentProperties->getAttribute('id');
            }
        }

        return ($ids === [] ? 0 : max($ids)) + 1;
    }

    private function imageExtension(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
            default => null,
        };
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
        $cell->appendChild($this->labeledParagraph($templates[1], 'IV. Project Leader:', $proposal['project_leader_display']));
        $cell->appendChild($this->labeledParagraph($templates[2], '       Email Address:', $proposal['leader_email'], false));
        $cell->appendChild($this->labeledParagraph($templates[3], '       Contact Number:', $proposal['leader_contact'], false));
        $cell->appendChild($templates[4]->cloneNode(true));

        foreach ($proposal['staff'] as $member) {
            $cell->appendChild($this->labeledParagraph($templates[1], '       Project Staff (s):', $member['display_name']));
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

    private function fillIntroductionAndLiterature(
        DOMXPath $xpath,
        DOMElement $row,
        string $introduction,
        string $relatedLiterature,
    ): void {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);
        $heading = $paragraphs[0] ?? null;

        if (! $heading instanceof DOMElement) {
            throw new RuntimeException('The introduction and literature section is incomplete.');
        }

        $this->removeParagraphs($cell, array_slice($paragraphs, 1));
        $this->replaceParagraphText($heading, 'XI. Introduction:', true);

        foreach ($this->textBlocks($introduction) as $block) {
            $cell->appendChild($this->bodyParagraph($cell->ownerDocument, $block));
        }

        $literatureHeading = $this->simpleParagraph($cell->ownerDocument, '');
        $this->appendRun($literatureHeading, 'Related Studies and Literature:', true);
        $this->appendRun($literatureHeading, ' (minimum of ten literature/studies reviewed)', italic: true);
        $cell->appendChild($literatureHeading);

        foreach ($this->textBlocks($relatedLiterature) as $block) {
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

    /**
     * @param  array<string, string>  $methodology
     * @param  list<array<string, mixed>>  $images
     */
    private function fillMethodology(
        DOMXPath $xpath,
        DOMElement $row,
        array $methodology,
        array $images,
        int &$nextImageDocumentPropertyId,
    ): void {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);

        if (count($paragraphs) !== 4) {
            throw new RuntimeException('The methodology section is incomplete.');
        }

        $sectionParagraph = $paragraphs[0];
        $headingTemplate = $paragraphs[1];
        $this->removeParagraphs($cell, array_slice($paragraphs, 1));
        $this->setParagraphKeepNext($sectionParagraph);
        $figureNumber = 1;

        foreach (config('detailed_proposal.methodology') as $key => $label) {
            $value = (string) ($methodology[$key] ?? '');
            $sectionImages = $key === 'research_design'
                ? collect($images)
                    ->filter(fn (array $image): bool => ($image['section'] ?? null) === 'research_design')
                    ->values()
                    ->all()
                : [];

            if (blank($value) && $sectionImages === []) {
                continue;
            }

            $heading = $headingTemplate->cloneNode(true);

            if (! $heading instanceof DOMElement) {
                throw new RuntimeException('A methodology heading could not be created.');
            }

            $this->replaceParagraphText($heading, $label);
            $this->setParagraphKeepNext($heading);
            $cell->appendChild($heading);

            foreach ($sectionImages as $image) {
                $cell->appendChild($this->methodologyImageParagraph(
                    $sectionParagraph->ownerDocument,
                    $image,
                    $nextImageDocumentPropertyId++,
                ));

                $figureTitle = 'Figure '.$figureNumber++.'.';

                if (filled($image['caption'] ?? null)) {
                    $figureTitle .= ' '.$image['caption'];
                }

                $cell->appendChild($this->simpleParagraph(
                    $sectionParagraph->ownerDocument,
                    $figureTitle,
                    false,
                    $image['alignment'],
                ));
            }

            foreach ($this->textBlocks($value) as $block) {
                $cell->appendChild($this->bodyParagraph($sectionParagraph->ownerDocument, $block));
            }
        }
    }

    /** @param array<string, mixed> $image */
    private function methodologyImageParagraph(DOMDocument $document, array $image, int $documentPropertyId): DOMElement
    {
        $paragraph = $document->createElementNS(self::W, 'w:p');
        $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
        $justification = $document->createElementNS(self::W, 'w:jc');
        $justification->setAttributeNS(self::W, 'w:val', $image['alignment']);
        $paragraphProperties->appendChild($justification);
        $paragraph->appendChild($paragraphProperties);

        [$width, $height] = $this->imageDimensions($image);
        $run = $document->createElementNS(self::W, 'w:r');
        $drawing = $document->createElementNS(self::W, 'w:drawing');
        $inline = $document->createElementNS(self::WP, 'wp:inline');
        $inline->setAttribute('distT', '0');
        $inline->setAttribute('distB', '0');
        $inline->setAttribute('distL', '0');
        $inline->setAttribute('distR', '0');

        $extent = $document->createElementNS(self::WP, 'wp:extent');
        $extent->setAttribute('cx', (string) $width);
        $extent->setAttribute('cy', (string) $height);
        $inline->appendChild($extent);

        $effectExtent = $document->createElementNS(self::WP, 'wp:effectExtent');
        $effectExtent->setAttribute('l', '0');
        $effectExtent->setAttribute('t', '0');
        $effectExtent->setAttribute('r', '0');
        $effectExtent->setAttribute('b', '0');
        $inline->appendChild($effectExtent);

        $documentProperties = $document->createElementNS(self::WP, 'wp:docPr');
        $documentProperties->setAttribute('id', (string) $documentPropertyId);
        $documentProperties->setAttribute('name', 'Methodology image '.$documentPropertyId);
        $documentProperties->setAttribute('descr', (string) ($image['caption'] ?? 'Methodology visual'));
        $inline->appendChild($documentProperties);

        $nonVisualFrameProperties = $document->createElementNS(self::WP, 'wp:cNvGraphicFramePr');
        $graphicFrameLocks = $document->createElementNS(self::A, 'a:graphicFrameLocks');
        $graphicFrameLocks->setAttribute('noChangeAspect', '1');
        $nonVisualFrameProperties->appendChild($graphicFrameLocks);
        $inline->appendChild($nonVisualFrameProperties);

        $graphic = $document->createElementNS(self::A, 'a:graphic');
        $graphicData = $document->createElementNS(self::A, 'a:graphicData');
        $graphicData->setAttribute('uri', self::PIC);
        $picture = $document->createElementNS(self::PIC, 'pic:pic');
        $nonVisualPictureProperties = $document->createElementNS(self::PIC, 'pic:nvPicPr');
        $nonVisualPicture = $document->createElementNS(self::PIC, 'pic:cNvPr');
        $nonVisualPicture->setAttribute('id', '0');
        $nonVisualPicture->setAttribute('name', (string) ($image['original_filename'] ?? 'Methodology visual'));
        $nonVisualPictureProperties->appendChild($nonVisualPicture);
        $nonVisualPictureProperties->appendChild($document->createElementNS(self::PIC, 'pic:cNvPicPr'));
        $picture->appendChild($nonVisualPictureProperties);

        $blipFill = $document->createElementNS(self::PIC, 'pic:blipFill');
        $blip = $document->createElementNS(self::A, 'a:blip');
        $blip->setAttributeNS(self::R, 'r:embed', $image['relationship_id']);
        $blipFill->appendChild($blip);
        $stretch = $document->createElementNS(self::A, 'a:stretch');
        $stretch->appendChild($document->createElementNS(self::A, 'a:fillRect'));
        $blipFill->appendChild($stretch);
        $picture->appendChild($blipFill);

        $shapeProperties = $document->createElementNS(self::PIC, 'pic:spPr');
        $transform = $document->createElementNS(self::A, 'a:xfrm');
        $offset = $document->createElementNS(self::A, 'a:off');
        $offset->setAttribute('x', '0');
        $offset->setAttribute('y', '0');
        $transform->appendChild($offset);
        $transformExtent = $document->createElementNS(self::A, 'a:ext');
        $transformExtent->setAttribute('cx', (string) $width);
        $transformExtent->setAttribute('cy', (string) $height);
        $transform->appendChild($transformExtent);
        $shapeProperties->appendChild($transform);
        $presetGeometry = $document->createElementNS(self::A, 'a:prstGeom');
        $presetGeometry->setAttribute('prst', 'rect');
        $presetGeometry->appendChild($document->createElementNS(self::A, 'a:avLst'));
        $shapeProperties->appendChild($presetGeometry);
        $outline = $document->createElementNS(self::A, 'a:ln');
        $outline->setAttribute('w', '12700');
        $solidFill = $document->createElementNS(self::A, 'a:solidFill');
        $solidFill->appendChild($document->createElementNS(self::A, 'a:srgbClr'))->setAttribute('val', '000000');
        $outline->appendChild($solidFill);
        $shapeProperties->appendChild($outline);
        $picture->appendChild($shapeProperties);
        $graphicData->appendChild($picture);
        $graphic->appendChild($graphicData);
        $inline->appendChild($graphic);
        $drawing->appendChild($inline);
        $run->appendChild($drawing);
        $paragraph->appendChild($run);

        return $paragraph;
    }

    /** @param array<string, mixed> $image @return array{0: int, 1: int} */
    private function imageDimensions(array $image): array
    {
        $maximumWidthInches = match ($image['size'] ?? 'medium') {
            'small' => 2.5,
            'large' => 6.0,
            default => 4.25,
        };
        $pixelWidth = max(1, (int) ($image['pixel_width'] ?? 1));
        $pixelHeight = max(1, (int) ($image['pixel_height'] ?? 1));
        $heightInches = $maximumWidthInches * ($pixelHeight / $pixelWidth);

        if ($heightInches > 5.5) {
            $maximumWidthInches *= 5.5 / $heightInches;
            $heightInches = 5.5;
        }

        return [
            (int) round($maximumWidthInches * 914400),
            (int) round($heightInches * 914400),
        ];
    }

    /** @param list<array{name: string, percentage: string, duties: string}> $responsibilities */
    private function fillResponsibilities(DOMXPath $xpath, DOMElement $row, array $responsibilities): void
    {
        $cell = $this->onlyCell($xpath, $row);
        $paragraphs = $this->paragraphs($xpath, $cell);
        $this->removeParagraphs($cell, array_slice($paragraphs, 1));

        foreach ($responsibilities as $index => $responsibility) {
            $role = $index === 0 ? 'Project Leader' : 'Project Staff (s)';
            $percentage = filled($responsibility['percentage'] ?? null)
                ? ' ('.$responsibility['percentage'].'%)'
                : '';
            $memberHeading = $this->simpleParagraph($cell->ownerDocument, '');
            $this->appendRun($memberHeading, $role.': ', italic: true);
            $this->appendRun(
                $memberHeading,
                Str::upper($responsibility['name']).$percentage,
                bold: true,
                italic: true,
            );
            $cell->appendChild($memberHeading);

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

        $this->replaceParagraphText($signatureParagraphs[4], Str::upper($proposal['project_leader']), true, 'center');
        $this->replaceWithLabelValue($this->paragraphs($xpath, $preparedCells[1])[0], 'Department:', $proposal['proponent_department'], false);
        $this->replaceWithLabelValue($this->paragraphs($xpath, $collegeCells[1])[0], 'College:', $proposal['proponent_college'], false);
        $this->replaceWithLabelValue($this->paragraphs($xpath, $campusCells[1])[0], 'Campus:', $proposal['proponent_campus'], false);
    }

    /**
     * @param  array<int, DOMElement>  $rows
     * @param  array<string, mixed>  $proposal
     */
    private function fillApprovalSignatories(DOMXPath $xpath, array $rows, array $proposal): void
    {
        $approvalCells = $this->elements($xpath, './w:tc', $rows[38]);
        $finalApprovalCell = $this->onlyCell($xpath, $rows[39]);

        if (count($approvalCells) !== 2) {
            throw new RuntimeException('The Detailed Research Proposal approval section is incomplete.');
        }

        $checkedParagraphs = $this->paragraphs($xpath, $approvalCells[0]);
        $recommendingParagraphs = $this->paragraphs($xpath, $approvalCells[1]);
        $finalApprovalParagraphs = $this->paragraphs($xpath, $finalApprovalCell);
        $this->preventRowSplit($xpath, $rows[39]);

        if (! isset(
            $checkedParagraphs[4],
            $checkedParagraphs[5],
            $checkedParagraphs[6],
            $checkedParagraphs[2],
            $recommendingParagraphs[2],
            $recommendingParagraphs[4],
            $recommendingParagraphs[5],
            $finalApprovalParagraphs[5],
        )) {
            throw new RuntimeException('A Detailed Research Proposal signatory slot is missing.');
        }

        $checkedName = filled($proposal['checked_verified_by_name'])
            ? Str::upper((string) $proposal['checked_verified_by_name'])
            : 'NAME';
        $recommendingName = filled($proposal['recommending_approval_name'])
            ? Str::upper((string) $proposal['recommending_approval_name'])
            : 'NAME';
        $approvedName = filled($proposal['approved_by_name'])
            ? Str::upper((string) $proposal['approved_by_name'])
            : 'NAME';

        $this->replaceParagraphText($checkedParagraphs[4], $checkedName, true, 'center');
        $this->replaceParagraphText($checkedParagraphs[5], 'Head, Research Office', alignment: 'center');
        $approvalCells[0]->removeChild($checkedParagraphs[2]);
        $approvalCells[0]->removeChild($checkedParagraphs[6]);
        $this->replaceParagraphText($recommendingParagraphs[4], $recommendingName, true, 'center');
        $this->replaceParagraphText(
            $recommendingParagraphs[5],
            'Vice Chancellor for Research Development and Extension Services',
            alignment: 'center',
        );
        $approvalCells[1]->removeChild($recommendingParagraphs[2]);
        $this->replaceParagraphText($finalApprovalParagraphs[5], $approvedName, true, 'center');
    }

    private function preventRowSplit(DOMXPath $xpath, DOMElement $row): void
    {
        $rowProperties = $this->elements($xpath, './w:trPr', $row)[0] ?? null;

        if (! $rowProperties instanceof DOMElement) {
            $rowProperties = $row->ownerDocument->createElementNS(self::W, 'w:trPr');
            $row->insertBefore($rowProperties, $row->firstChild);
        }

        if ($xpath->query('./w:cantSplit', $rowProperties)?->length === 0) {
            $rowProperties->appendChild($row->ownerDocument->createElementNS(self::W, 'w:cantSplit'));
        }
    }

    private function formatSignatoryNotes(DOMXPath $xpath): void
    {
        foreach (self::SIGNATORY_NOTE_TAB_COUNTS as $text => $tabCount) {
            $paragraph = $this->firstElement(
                $xpath,
                '//w:body/w:p[normalize-space(.) = "'.$text.'"]',
            );
            $this->removeParagraphIndentCharacters($xpath, $paragraph);
            $this->setParagraphLeftIndent($paragraph, 0);
            $this->setParagraphAlignment($paragraph, 'left');
            $this->setParagraphLeadingTabs($xpath, $paragraph, $tabCount);
        }
    }

    private function removeParagraphIndentCharacters(DOMXPath $xpath, DOMElement $paragraph): void
    {
        foreach ($this->elements($xpath, './/w:tab', $paragraph) as $tab) {
            $tab->parentNode?->removeChild($tab);
        }

        foreach ($this->elements($xpath, './w:r', $paragraph) as $run) {
            if (Str::of((string) $xpath->evaluate('string(.)', $run))->trim()->isEmpty()) {
                $paragraph->removeChild($run);
            }
        }

        $textNodes = $this->elements($xpath, './/w:t', $paragraph);
        $firstText = $textNodes[0] ?? null;
        $lastText = $textNodes[array_key_last($textNodes)] ?? null;

        if ($firstText instanceof DOMElement) {
            $firstText->textContent = Str::of($firstText->textContent)->ltrim()->toString();
        }

        if ($lastText instanceof DOMElement) {
            $lastText->textContent = Str::of($lastText->textContent)->rtrim()->toString();
        }
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
        int $fontSizeHalfPoints = 22,
    ): void {
        $this->clearParagraphContent($paragraph);

        if ($alignment !== null) {
            $this->setParagraphAlignment($paragraph, $alignment);
        }

        $this->appendRun($paragraph, $text, $bold, fontSizeHalfPoints: $fontSizeHalfPoints);
    }

    private function appendRun(
        DOMElement $paragraph,
        string $text,
        bool $bold = false,
        bool $italic = false,
        int $fontSizeHalfPoints = 22,
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
        $size->setAttributeNS(self::W, 'w:val', (string) $fontSizeHalfPoints);
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

    private function setParagraphLeftIndent(DOMElement $paragraph, int $leftIndent): void
    {
        $document = $paragraph->ownerDocument;
        $paragraphProperties = null;
        $indentation = null;

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

        foreach ($paragraphProperties->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'ind') {
                $indentation = $child;
                break;
            }
        }

        if (! $indentation instanceof DOMElement) {
            $indentation = $document->createElementNS(self::W, 'w:ind');
            $justification = null;

            foreach ($paragraphProperties->childNodes as $child) {
                if ($child instanceof DOMElement && $child->localName === 'jc') {
                    $justification = $child;
                    break;
                }
            }

            $paragraphProperties->insertBefore($indentation, $justification);
        }

        $indentation->setAttributeNS(self::W, 'w:left', (string) $leftIndent);
        $indentation->setAttributeNS(self::W, 'w:start', (string) $leftIndent);

        foreach (['leftChars', 'hanging', 'hangingChars', 'firstLine', 'firstLineChars'] as $attribute) {
            $indentation->removeAttributeNS(self::W, $attribute);
        }
    }

    private function setParagraphLeadingTabs(DOMXPath $xpath, DOMElement $paragraph, int $tabCount): void
    {
        $document = $paragraph->ownerDocument;
        $paragraphProperties = $this->elements($xpath, './w:pPr', $paragraph)[0] ?? null;

        if (! $paragraphProperties instanceof DOMElement) {
            $paragraphProperties = $document->createElementNS(self::W, 'w:pPr');
            $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
        }

        foreach ($this->elements($xpath, './w:tabs', $paragraphProperties) as $tabs) {
            $paragraphProperties->removeChild($tabs);
        }

        $firstRun = $this->elements($xpath, './w:r', $paragraph)[0] ?? null;

        if (! $firstRun instanceof DOMElement) {
            $firstRun = $document->createElementNS(self::W, 'w:r');
            $paragraph->appendChild($firstRun);
        }

        $runProperties = $this->elements($xpath, './w:rPr', $firstRun)[0] ?? null;
        $runContent = $runProperties?->nextSibling ?? $firstRun->firstChild;

        for ($index = 0; $index < $tabCount; $index++) {
            $firstRun->insertBefore($document->createElementNS(self::W, 'w:tab'), $runContent);
        }
    }

    private function setParagraphKeepNext(DOMElement $paragraph): void
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

        foreach ($paragraphProperties->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'keepNext') {
                return;
            }
        }

        $paragraphProperties->appendChild($document->createElementNS(self::W, 'w:keepNext'));
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
