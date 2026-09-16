<?php

namespace App\Services;

use App\Models\ProjectNarrativeReport;
use App\Support\ProposalRichText;
use App\Support\TerminalReportRules;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;

class TerminalReportDocumentService extends ProgressReportDocumentService
{
    public function renderXml(string $xml, ProjectNarrativeReport $report, array $figures): string
    {
        [$doc, $xpath] = $this->documentAndXPath($xml, 'terminal report');
        $body = $xpath->query('/w:document/w:body')->item(0);
        $section = $xpath->query('./w:sectPr', $body)->item(0)?->cloneNode(true);
        $originalTable = $xpath->query('./w:tbl[1]', $body)->item(0);
        $header = $originalTable->cloneNode(true);
        foreach (array_slice($this->elements($xpath, './w:tr', $header), 1) as $row) {
            $header->removeChild($row);
        }
        foreach ($xpath->query('.//w:t', $header) as $text) {
            $text->nodeValue = str_replace('BatStateU-REC-RES-02', 'BatStateU-REC-RES-04', $text->textContent);
        }
        while ($body->firstChild) {
            $body->removeChild($body->firstChild);
        }
        $body->appendChild($header);
        $data = $report->terminal_data ?? [];
        $title = $data['project_title'] ?? $report->topic->title;
        $body->appendChild($this->paragraph($doc, 'TERMINAL REPORT', true, 'center', 32));
        $body->appendChild($this->paragraph($doc, 'I. Cover Page', true));
        $body->appendChild($this->paragraph($doc, 'Batangas State University', true, 'center', 28));
        $body->appendChild($this->paragraph($doc, 'The National Engineering University', false, 'center'));
        $coverFigure = collect($figures)->firstWhere('section', 'cover');
        if (is_array($coverFigure)) {
            $coverFigure = $this->scaleCoverFigure($coverFigure);
            $body->appendChild($this->figureParagraph($doc, $coverFigure, 0));
            if (filled($coverFigure['caption'] ?? null)) {
                $body->appendChild($this->captionParagraph($doc, (string) $coverFigure['caption']));
            }
        }
        $body->appendChild($this->paragraph($doc, $title, true, 'center', 30));
        $body->appendChild($this->paragraph($doc, $report->researchers, false, 'center'));
        $body->appendChild($this->paragraph($doc, $this->date($report->implementation_start).' – '.$this->date($report->implementation_end), false, 'center'));
        $body->appendChild($this->paragraph($doc, 'Approved funding: PHP '.number_format((float) $report->budget, 2), false, 'center'));
        $body->appendChild($this->pageBreak($doc));
        $body->appendChild($this->paragraph($doc, 'II. Project Details', true));
        $body->appendChild($this->paragraph($doc, 'Title: '.$title));
        $body->appendChild($this->paragraph($doc, "Author/s:\n".$report->researchers));
        $body->appendChild($this->paragraph($doc, 'Approved duration: '.$this->date($data['approved_start'] ?? null).' – '.$this->date($data['approved_end'] ?? null).' ('.($data['approved_duration_months'] ?? '').' months)'));
        $body->appendChild($this->paragraph($doc, 'Actual duration: '.$this->date($report->implementation_start).' – '.$this->date($report->implementation_end)));
        $utilization = (float) $report->budget > 0 ? number_format((float) ($data['total_expenditure'] ?? 0) / (float) $report->budget * 100, 2).'%' : 'N/A (no approved funding)';
        $body->appendChild($this->paragraph($doc, 'Approved budget: PHP '.number_format((float) $report->budget, 2)."\nTotal expenditure: PHP ".number_format((float) ($data['total_expenditure'] ?? 0), 2)."\nPercent utilization: ".$utilization));
        $body->appendChild($this->paragraph($doc, 'Collaborating agency: '.(($data['collaborating_agency'] ?? '') ?: 'None')));
        $body->appendChild($this->paragraph($doc, 'III. Summary of Accomplishment', true));
        $body->appendChild($this->dataTable($doc, ['Objectives', 'Target Accomplishments', 'Actual Accomplishments'], collect($report->accomplishments ?? [])->map(fn (array $row): array => [$row['objective'], $row['target'], $row['actual']])->all()));
        $figureNumber = 1;
        $tableNumber = 1;
        $this->narrative($body, 'IV. Abstract (200–250 words)', $data['abstract'] ?? '');
        $this->narrative($body, 'V. Introduction (Brief with rationale), Review of Literature and Objectives', $report->introduction);
        $this->narrative($body, 'Rationale', $report->rationale ?? '');
        $this->narrative($body, 'Review of Literature', $data['literature_review'] ?? '');
        $objectives = trim($report->objectives."\n".collect($report->accomplishments ?? [])->pluck('objective')->map(fn (string $text, int $index): string => ($index + 1).'. '.$text)->implode("\n"));
        $this->narrative($body, 'Objectives', $objectives);
        $this->narrative($body, 'VI. Materials and Methods / Methodology', $report->methodology, 'methodology', $figures, $data['tables'] ?? [], $figureNumber, $tableNumber);
        $this->narrative($body, 'VII. Results and Discussion', $report->results_discussion, 'results_discussion', $figures, $data['tables'] ?? [], $figureNumber, $tableNumber);
        $this->narrative($body, 'Conclusions', $data['conclusions'] ?? '');
        $this->narrative($body, 'Recommendations', $data['recommendations'] ?? '');
        $this->narrative($body, 'Bibliography', $data['bibliography'] ?? '');
        $body->appendChild($this->pageBreak($doc));
        $body->appendChild($this->paragraph($doc, 'Prepared by:', true));
        foreach ($data['authors'] ?? [] as $author) {
            $this->signatory($body, $author['name'] ?? '', $author['role'] ?? '', $author['date_signed'] ?? null);
        }
        $lastGroup = '';
        foreach (TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role]) {
            if ($lastGroup !== $group) {
                $body->appendChild($this->paragraph($doc, $group.':', true));
                $lastGroup = $group;
            }
            $this->signatory($body, $data['signatories'][$key]['name'] ?? '', $role, $data['signatories'][$key]['date_signed'] ?? null);
        }
        if ($section) {
            $body->appendChild($section);
        }

        return $this->serialized($doc, 'terminal report');
    }

    private function narrative(DOMElement $body, string $heading, string $value, string $section = '', array $figures = [], array $tables = [], int &$figureNumber = 1, int &$tableNumber = 1): void
    {
        $doc = $body->ownerDocument;
        $body->appendChild($this->paragraph($doc, $heading, true));
        $blocks = (new ProposalRichText)->blocks($value);
        $orderedNumber = 0;
        foreach ($blocks as $index => $block) {
            $paragraph = $this->paragraph($doc, '');
            $prefix = match ($block['type']) {
                'ordered' => (++$orderedNumber).'. ', 'unordered' => '• ', default => '',
            };
            if ($block['type'] !== 'ordered') {
                $orderedNumber = 0;
            }
            if ($prefix !== '') {
                $paragraph->appendChild($this->textRun($doc, $prefix));
            }
            foreach ($block['runs'] as $run) {
                $paragraph->appendChild($this->textRun($doc, $run['text'], $run['bold'], $run['italic'], $run['underline'], $run['break']));
            }
            $body->appendChild($paragraph);
            $this->insertEvidence($body, $section, $index + 1, count($blocks), $figures, $tables, $figureNumber, $tableNumber);
        }
        $this->insertEvidence($body, $section, 0, count($blocks), $figures, $tables, $figureNumber, $tableNumber);
    }

    private function insertEvidence(DOMElement $body, string $section, int $position, int $paragraphCount, array $figures, array $tables, int &$figureNumber, int &$tableNumber): void
    {
        $matches = fn (array $item): bool => ($item['section'] ?? '') === $section && ((int) ($item['after_paragraph'] ?? 0) === $position || ($position === 0 && (int) ($item['after_paragraph'] ?? 0) > $paragraphCount));
        foreach (array_filter($tables, $matches) as $table) {
            $body->appendChild($this->paragraph($body->ownerDocument, 'Table '.$tableNumber++.'. '.$table['caption'], true));
            $body->appendChild($this->dataTable($body->ownerDocument, $table['headers'], $table['rows']));
        }
        foreach (array_filter($figures, $matches) as $figure) {
            $paragraph = $this->figureParagraph($body->ownerDocument, $figure, $figureNumber);
            $paragraph->firstChild->appendChild($body->ownerDocument->createElementNS(self::W, 'w:keepNext'));
            $body->appendChild($paragraph);
            $body->appendChild($this->captionParagraph($body->ownerDocument, 'Figure '.$figureNumber++.'. '.$figure['caption']));
        }
    }

    private function paragraph(DOMDocument $doc, string $text, bool $bold = false, string $align = 'left', int $size = 22): DOMElement
    {
        $p = $doc->createElementNS(self::W, 'w:p');
        $properties = $doc->createElementNS(self::W, 'w:pPr');
        $spacing = $doc->createElementNS(self::W, 'w:spacing');
        $spacing->setAttributeNS(self::W, 'w:after', '140');
        $properties->appendChild($spacing);
        if ($bold) {
            $properties->appendChild($doc->createElementNS(self::W, 'w:keepNext'));
        }
        $jc = $doc->createElementNS(self::W, 'w:jc');
        $jc->setAttributeNS(self::W, 'w:val', $align);
        $properties->appendChild($jc);
        $p->appendChild($properties);
        $run = $this->textRun($doc, $text, $bold);
        $fontSize = $doc->createElementNS(self::W, 'w:sz');
        $fontSize->setAttributeNS(self::W, 'w:val', (string) $size);
        $run->firstChild->appendChild($fontSize);
        $p->appendChild($run);

        return $p;
    }

    private function textRun(DOMDocument $doc, string $text, bool $bold = false, bool $italic = false, bool $underline = false, bool $break = false): DOMElement
    {
        $run = $doc->createElementNS(self::W, 'w:r');
        $properties = $doc->createElementNS(self::W, 'w:rPr');
        $fonts = $doc->createElementNS(self::W, 'w:rFonts');
        $fonts->setAttributeNS(self::W, 'w:ascii', 'Times New Roman');
        $fonts->setAttributeNS(self::W, 'w:hAnsi', 'Times New Roman');
        $properties->appendChild($fonts);
        foreach (['b' => $bold, 'i' => $italic, 'u' => $underline] as $tag => $enabled) {
            if ($enabled) {
                $element = $doc->createElementNS(self::W, 'w:'.$tag);
                if ($tag === 'u') {
                    $element->setAttributeNS(self::W, 'w:val', 'single');
                }
                $properties->appendChild($element);
            }
        }
        $run->appendChild($properties);
        foreach (preg_split('/\R/u', $text) ?: [''] as $index => $line) {
            if ($index > 0 || $break) {
                $run->appendChild($doc->createElementNS(self::W, 'w:br'));
            }
            $node = $doc->createElementNS(self::W, 'w:t');
            $node->setAttributeNS(self::XML, 'xml:space', 'preserve');
            $node->appendChild($doc->createTextNode(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $line) ?? ''));
            $run->appendChild($node);
        }

        return $run;
    }

    private function dataTable(DOMDocument $doc, array $headers, array $rows): DOMElement
    {
        $table = $doc->createElementNS(self::W, 'w:tbl');
        $properties = $doc->createElementNS(self::W, 'w:tblPr');
        $width = $doc->createElementNS(self::W, 'w:tblW');
        $width->setAttributeNS(self::W, 'w:w', '5000');
        $width->setAttributeNS(self::W, 'w:type', 'pct');
        $properties->appendChild($width);
        $borders = $doc->createElementNS(self::W, 'w:tblBorders');
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $side) {
            $border = $doc->createElementNS(self::W, 'w:'.$side);
            $border->setAttributeNS(self::W, 'w:val', 'single');
            $border->setAttributeNS(self::W, 'w:sz', '4');
            $border->setAttributeNS(self::W, 'w:color', '999999');
            $borders->appendChild($border);
        }
        $properties->appendChild($borders);
        $table->appendChild($properties);
        foreach ([$headers, ...$rows] as $index => $values) {
            $row = $doc->createElementNS(self::W, 'w:tr');
            if ($index === 0) {
                $rowProperties = $doc->createElementNS(self::W, 'w:trPr');
                $rowProperties->appendChild($doc->createElementNS(self::W, 'w:tblHeader'));
                $row->appendChild($rowProperties);
            }
            foreach (array_keys($headers) as $column) {
                $cell = $doc->createElementNS(self::W, 'w:tc');
                $cell->appendChild($this->paragraph($doc, (string) ($values[$column] ?? ''), $index === 0));
                $row->appendChild($cell);
            }
            $table->appendChild($row);
        }

        return $table;
    }

    private function signatory(DOMElement $body, string $name, string $role, ?string $date): void
    {
        $body->appendChild($this->paragraph($body->ownerDocument, "______________________________\n".$name."\n".$role."\nDate signed: ".$this->date($date)));
    }

    /**
     * @param  array<string, mixed>  $figure
     * @return array<string, mixed>
     */
    private function scaleCoverFigure(array $figure): array
    {
        $width = max(1, (int) ($figure['width'] ?? 1));
        $height = max(1, (int) ($figure['height'] ?? 1));
        $scale = min(1, 5029200 / $width, 3657600 / $height);

        return [
            ...$figure,
            'width' => (int) round($width * $scale),
            'height' => (int) round($height * $scale),
        ];
    }

    private function pageBreak(DOMDocument $doc): DOMElement
    {
        $p = $this->paragraph($doc, '');
        $break = $doc->createElementNS(self::W, 'w:br');
        $break->setAttributeNS(self::W, 'w:type', 'page');
        $p->lastChild->appendChild($break);

        return $p;
    }

    private function date(mixed $date): string
    {
        return filled($date) ? Carbon::parse($date)->format('F j, Y') : '';
    }
}
