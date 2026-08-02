<?php

namespace App\Services;

use App\Models\ProjectProgressReport;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class MonitoringToolDocumentService
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const XML = 'http://www.w3.org/XML/1998/namespace';

    public function generate(ProjectProgressReport $report): string
    {
        $report->loadMissing(['topic.user', 'submitter', 'reviewer']);
        $templatePath = (string) config('monitoring_tool.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Monitoring Tool template is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-monitoring-tool-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Monitoring Tool document could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Monitoring Tool template could not be opened.');
            }

            $archiveIsOpen = true;
            $documentXml = $archive->getFromName('word/document.xml');
            $footerXml = $archive->getFromName('word/footer1.xml');

            if ($documentXml === false || $footerXml === false) {
                throw new RuntimeException('The Monitoring Tool body or footer is missing.');
            }

            if (! $archive->addFromString('word/document.xml', $this->renderDocumentXml($documentXml, $report))
                || ! $archive->addFromString('word/footer1.xml', $this->renderFooterXml($footerXml, $report))) {
                throw new RuntimeException('The generated Monitoring Tool could not be written.');
            }

            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Monitoring Tool could not be read.');
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

    private function renderDocumentXml(string $xml, ProjectProgressReport $report): string
    {
        [$document, $xpath] = $this->documentAndXPath($xml, 'document');
        $table = $xpath->query('/w:document/w:body/w:tbl[1]')->item(0);

        if (! $table instanceof DOMElement) {
            throw new RuntimeException('The Monitoring Tool table is missing.');
        }

        $rows = $this->elements($xpath, './w:tr', $table);

        if (count($rows) !== 27) {
            throw new RuntimeException('The Monitoring Tool table structure has changed.');
        }

        $this->fillMetadata($xpath, $rows, $report);
        $this->fillWorkPlan($xpath, $rows, $report);
        $this->fillBudgetUtilization($xpath, $rows, $report);
        $this->fillPreparedBy($xpath, $rows[26], $report);

        return $this->serialized($document, 'document');
    }

    /** @param list<DOMElement> $rows */
    private function fillMetadata(DOMXPath $xpath, array $rows, ProjectProgressReport $report): void
    {
        $topic = $report->topic;
        $leader = $report->submitter?->name ?? $topic->user?->name ?? '';
        $this->replaceCellText($xpath, $this->cells($xpath, $rows[2], 1)[0], 'Date: '.$report->reporting_date->format('F j, Y'));

        $projectCells = $this->cells($xpath, $rows[3], 2);
        $this->replaceCellText($xpath, $projectCells[0], 'Research Project Title: '.$topic->title);
        $this->replaceCellText($xpath, $projectCells[1], 'Project Leader: '.$leader);

        $costCells = $this->cells($xpath, $rows[4], 2);
        $this->replaceCellText($xpath, $costCells[0], 'Total Project Cost: '.$this->money((float) $topic->estimated_budget));
        $this->replaceCellText(
            $xpath,
            $costCells[1],
            'Duration: '.(int) $topic->estimated_duration_months.' month'.((int) $topic->estimated_duration_months === 1 ? '' : 's'),
        );
    }

    /** @param list<DOMElement> $rows */
    private function fillWorkPlan(DOMXPath $xpath, array $rows, ProjectProgressReport $report): void
    {
        $entries = array_values($report->work_plan ?? []);

        for ($index = 0; $index < 11; $index++) {
            $cells = $this->cells($xpath, $rows[$index + 7], 7);
            $entry = $entries[$index] ?? [];
            $values = $entry === [] ? array_fill(0, 7, '') : [
                (string) ($entry['activity'] ?? ''),
                $this->percentage((float) ($entry['percent_weight'] ?? 0)),
                (string) ($entry['physical_target'] ?? ''),
                $this->date((string) ($entry['target_completion_date'] ?? '')),
                (string) ($entry['actual_accomplishment'] ?? ''),
                $this->percentage((float) ($entry['accomplished_percentage'] ?? 0)),
                (string) ($entry['findings'] ?? ''),
            ];

            foreach ($cells as $cellIndex => $cell) {
                $this->replaceCellText($xpath, $cell, $values[$cellIndex]);
            }
        }

        $totalCells = $this->cells($xpath, $rows[18], 2);
        $this->replaceCellText(
            $xpath,
            $totalCells[0],
            'Total accomplishment as of '.$report->reporting_date->format('F j, Y').':',
        );
        $this->replaceCellText($xpath, $totalCells[1], $this->percentage((float) $report->progress_percentage));
    }

    /** @param list<DOMElement> $rows */
    private function fillBudgetUtilization(DOMXPath $xpath, array $rows, ProjectProgressReport $report): void
    {
        $budget = collect($report->budget_utilization ?? [])->keyBy('type');
        $types = ['Purchase Request', 'Cash Advance', 'Request of Payment'];
        $projectCost = (float) $report->topic->estimated_budget;
        $requestedTotal = 0.0;
        $actualTotal = 0.0;

        foreach ($types as $index => $type) {
            $entry = $budget->get($type, []);
            $requested = (float) ($entry['amount_requested'] ?? 0);
            $actual = (float) ($entry['actual_amount'] ?? 0);
            $requestedTotal += $requested;
            $actualTotal += $actual;
            $cells = $this->cells($xpath, $rows[$index + 22], 6);

            $this->replaceCellText($xpath, $cells[0], $type);
            $this->replaceCellText($xpath, $cells[1], (string) ($entry['details'] ?? ''));
            $this->replaceCellText($xpath, $cells[2], $this->money($requested));
            $this->replaceCellText($xpath, $cells[3], $this->money($actual));
            $this->replaceCellText($xpath, $cells[4], $this->percentage($projectCost > 0 ? ($actual / $projectCost) * 100 : 0));
            $this->replaceCellText($xpath, $cells[5], (string) ($entry['remarks'] ?? ''));
        }

        $totalCells = $this->cells($xpath, $rows[25], 5);
        $this->replaceCellText($xpath, $totalCells[0], 'Total');
        $this->replaceCellText($xpath, $totalCells[1], $this->money($requestedTotal));
        $this->replaceCellText($xpath, $totalCells[2], $this->money($actualTotal));
        $this->replaceCellText($xpath, $totalCells[3], $this->percentage($projectCost > 0 ? ($actualTotal / $projectCost) * 100 : 0));
        $this->replaceCellText($xpath, $totalCells[4], '');
    }

    private function fillPreparedBy(DOMXPath $xpath, DOMElement $row, ProjectProgressReport $report): void
    {
        $cells = $this->cells($xpath, $row, 3);
        $paragraphs = $this->elements($xpath, './w:p', $cells[0]);

        if (count($paragraphs) < 8) {
            throw new RuntimeException('The Monitoring Tool prepared-by block is incomplete.');
        }

        $leader = $report->submitter?->name ?? $report->topic->user?->name ?? '';
        $dateSigned = $report->prepared_by_date_signed?->format('F j, Y') ?? '';
        $this->replaceParagraphText($xpath, $paragraphs[0], 'Prepared by:');
        $this->replaceParagraphText($xpath, $paragraphs[3], '_____________________________________');
        $this->replaceParagraphText($xpath, $paragraphs[4], Str::upper($leader));
        $this->replaceParagraphText($xpath, $paragraphs[5], 'Project Leader');
        $this->replaceParagraphText($xpath, $paragraphs[7], 'Date Signed: '.$dateSigned);
    }

    private function renderFooterXml(string $xml, ProjectProgressReport $report): string
    {
        [$document, $xpath] = $this->documentAndXPath($xml, 'footer');
        $replacement = 'Tracking No. '.($report->tracking_number ?: '__________________________').' ';

        foreach ($xpath->query('//w:t') as $text) {
            if ($text instanceof DOMElement && str_contains($text->textContent, 'Tracking No.')) {
                $text->setAttributeNS(self::XML, 'xml:space', 'preserve');
                $text->nodeValue = $replacement;

                return $this->serialized($document, 'footer');
            }
        }

        throw new RuntimeException('The Monitoring Tool tracking number slot is missing.');
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function documentAndXPath(string $xml, string $part): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = true;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException("The Monitoring Tool template contains invalid {$part} XML.");
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::W);

        return [$document, $xpath];
    }

    /** @return list<DOMElement> */
    private function cells(DOMXPath $xpath, DOMElement $row, int $expected): array
    {
        $cells = $this->elements($xpath, './w:tc', $row);

        if (count($cells) !== $expected) {
            throw new RuntimeException('A Monitoring Tool table row is malformed.');
        }

        return $cells;
    }

    private function replaceCellText(DOMXPath $xpath, DOMElement $cell, string $text): void
    {
        $paragraphs = $this->elements($xpath, './w:p', $cell);

        if ($paragraphs === []) {
            throw new RuntimeException('A Monitoring Tool table cell has no paragraph.');
        }

        $this->replaceParagraphText($xpath, $paragraphs[0], $text);

        foreach (array_slice($paragraphs, 1) as $paragraph) {
            $this->replaceParagraphText($xpath, $paragraph, '');
        }
    }

    private function replaceParagraphText(DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $runProperties = $xpath->query('./w:r[1]/w:rPr', $paragraph)->item(0)
            ?? $xpath->query('./w:pPr/w:rPr', $paragraph)->item(0);
        $runProperties = $runProperties?->cloneNode(true);
        $runs = [];

        foreach ($xpath->query('./w:r', $paragraph) as $run) {
            $runs[] = $run;
        }

        foreach ($runs as $run) {
            $paragraph->removeChild($run);
        }

        if ($text === '') {
            return;
        }

        $run = $paragraph->ownerDocument->createElementNS(self::W, 'w:r');

        if ($runProperties instanceof DOMNode) {
            $run->appendChild($runProperties);
        }

        $textElement = $paragraph->ownerDocument->createElementNS(self::W, 'w:t');
        $textElement->setAttributeNS(self::XML, 'xml:space', 'preserve');
        $textElement->appendChild($paragraph->ownerDocument->createTextNode($text));
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

    private function money(float $amount): string
    {
        return 'PHP '.number_format($amount, 2);
    }

    private function percentage(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    }

    private function date(string $value): string
    {
        return $value === '' ? '' : Carbon::parse($value)->format('M j, Y');
    }

    private function serialized(DOMDocument $document, string $part): string
    {
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException("The Monitoring Tool {$part} XML could not be serialized.");
        }

        return $xml;
    }
}
