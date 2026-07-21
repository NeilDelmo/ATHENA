<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class ExpenseBreakdownDocumentService
{
    /** @param array<string, mixed> $expenseBreakdown */
    public function generate(array $expenseBreakdown): string
    {
        $templatePath = (string) config('expense_breakdown.template_path');

        if (! is_file($templatePath)) {
            throw new RuntimeException('The official Estimated Expense Breakdown workbook is unavailable.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'athena-expense-breakdown-');

        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('A temporary Estimated Expense Breakdown workbook could not be created.');
        }

        $archive = new ZipArchive;
        $archiveIsOpen = false;

        try {
            if ($archive->open($temporaryPath) !== true) {
                throw new RuntimeException('The official Estimated Expense Breakdown workbook could not be opened.');
            }

            $archiveIsOpen = true;
            $worksheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
            $workbookXml = $archive->getFromName('xl/workbook.xml');
            $workbookRelationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
            $contentTypesXml = $archive->getFromName('[Content_Types].xml');

            if (
                $worksheetXml === false
                || $workbookXml === false
                || $workbookRelationshipsXml === false
                || $contentTypesXml === false
            ) {
                throw new RuntimeException('The Estimated Expense Breakdown workbook structure is incomplete.');
            }

            $layout = $this->renderLayout($expenseBreakdown);

            $this->writeEntry(
                $archive,
                'xl/worksheets/sheet1.xml',
                $this->renderWorksheetXml($worksheetXml, $layout),
            );
            $this->writeEntry(
                $archive,
                'xl/workbook.xml',
                $this->renderWorkbookXml($workbookXml, $layout['last_row']),
            );
            $this->writeEntry(
                $archive,
                'xl/_rels/workbook.xml.rels',
                $this->removeCalcChainRelationship($workbookRelationshipsXml),
            );
            $this->writeEntry(
                $archive,
                '[Content_Types].xml',
                $this->removeCalcChainContentType($contentTypesXml),
            );
            $archive->deleteName('xl/calcChain.xml');
            $archive->close();
            $archiveIsOpen = false;
            $contents = file_get_contents($temporaryPath);

            if ($contents === false) {
                throw new RuntimeException('The generated Estimated Expense Breakdown workbook could not be read.');
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
     * @param  array<string, mixed>  $expenseBreakdown
     * @return array{sheet_data: string, merges: list<string>, last_row: int}
     */
    private function renderLayout(array $expenseBreakdown): array
    {
        $rows = [];
        $merges = [];

        $rows[] = $this->row(1, [
            $this->stringCell('A', 1, 59, 'Estimated Breakdown and Details of Expenses'),
            ...$this->emptyCells(1, range('B', 'I'), 58),
        ], 14.25);
        $merges[] = 'A1:I1';
        $rows[] = $this->blankRow(2, 58);
        $rows[] = $this->row(3, [
            $this->stringCell('A', 3, 60, 'Project Title: '.$expenseBreakdown['project_title']),
            ...$this->emptyCells(3, range('B', 'I'), 58),
        ], 14.25);
        $merges[] = 'A3:I3';
        $rows[] = $this->blankRow(4, 58);

        $headers = [
            'A' => 'Account',
            'B' => 'Sub-account',
            'C' => 'Particular/s',
            'D' => 'Descriptions/ Specifications/ Details',
            'E' => 'Purpose in the project',
            'F' => 'Unit',
            'G' => 'Qty.',
            'H' => 'Unit Cost (Php)',
            'I' => 'Total Unit Cost (Php)',
        ];
        $headerCells = [];

        foreach ($headers as $column => $header) {
            $headerCells[] = $this->stringCell($column, 5, in_array($column, ['H', 'I'], true) ? 62 : 61, $header);
            $merges[] = $column.'5:'.$column.'6';
        }

        $rows[] = $this->row(5, $headerCells, 14.25);
        $rows[] = $this->row(6, $this->emptyCells(6, range('A', 'I'), 47), 14.25);

        $rowNumber = 7;
        $sectionTotalRows = [];

        foreach ($expenseBreakdown['sections'] as $section) {
            $isMooe = $section['key'] === 'mooe';
            $rows[] = $this->sectionHeaderRow($rowNumber, $section['label'], $isMooe);
            $merges[] = 'A'.$rowNumber.':I'.$rowNumber;
            $rowNumber++;
            $accountSubtotalRows = [];

            foreach ($section['accounts'] as $account) {
                $accountStartRow = $rowNumber;
                $isFirstAccountRow = true;
                $subAccountTotalRows = [];

                foreach ($account['sub_accounts'] as $subAccount) {
                    $subAccountStartRow = $rowNumber;
                    $isFirstSubAccountRow = true;
                    $itemStartRow = $rowNumber;

                    foreach ($subAccount['items'] as $item) {
                        $rows[] = $this->itemRow(
                            $rowNumber,
                            $isFirstAccountRow ? $account['label'] : null,
                            $isFirstSubAccountRow ? $subAccount['label'] : null,
                            $item,
                            $isMooe,
                        );
                        $rowNumber++;
                        $isFirstAccountRow = false;
                        $isFirstSubAccountRow = false;
                    }

                    $itemEndRow = $rowNumber - 1;
                    $rows[] = $this->subAccountTotalRow(
                        $rowNumber,
                        $subAccount['total_label'],
                        $itemStartRow,
                        $itemEndRow,
                        $subAccount['total'],
                        $isMooe,
                    );
                    $merges[] = 'B'.$subAccountStartRow.':B'.$rowNumber;
                    $merges[] = 'C'.$rowNumber.':H'.$rowNumber;
                    $subAccountTotalRows[] = $rowNumber;
                    $rowNumber++;
                }

                $merges[] = 'A'.$accountStartRow.':A'.($rowNumber - 1);
                $rows[] = $this->subtotalRow(
                    $rowNumber,
                    $subAccountTotalRows,
                    $account['total'],
                    $isMooe,
                );
                $merges[] = 'A'.$rowNumber.':H'.$rowNumber;
                $accountSubtotalRows[] = $rowNumber;
                $rowNumber++;
            }

            $rows[] = $this->sectionTotalRow(
                $rowNumber,
                $isMooe ? 'TOTAL MOOE:' : 'TOTAL CAPITAL OUTLAY:',
                $accountSubtotalRows,
                $section['total'],
            );
            $merges[] = 'A'.$rowNumber.':H'.$rowNumber;
            $sectionTotalRows[] = $rowNumber;
            $rowNumber++;
        }

        $rows[] = $this->grandTotalRow($rowNumber, $sectionTotalRows, $expenseBreakdown['grand_total']);
        $merges[] = 'A'.$rowNumber.':H'.$rowNumber;
        $rowNumber++;
        $rows[] = $this->footerBlankRow($rowNumber);
        $rowNumber++;
        $rows[] = $this->noteRow($rowNumber);
        $merges[] = 'A'.$rowNumber.':E'.$rowNumber;
        $rowNumber++;
        $rows[] = $this->footerBlankRow($rowNumber);

        return [
            'sheet_data' => '<sheetData>'.implode('', $rows).'</sheetData>',
            'merges' => $merges,
            'last_row' => $rowNumber,
        ];
    }

    /** @param array{sheet_data: string, merges: list<string>, last_row: int} $layout */
    private function renderWorksheetXml(string $xml, array $layout): string
    {
        $xml = $this->replaceOnce(
            '/<dimension\b[^>]*\/>/',
            '<dimension ref="A1:I'.$layout['last_row'].'"/>',
            $xml,
            'worksheet dimension',
        );
        $xml = $this->replaceOnce(
            '/<sheetData>.*?<\/sheetData>/s',
            $layout['sheet_data'],
            $xml,
            'worksheet rows',
        );
        $mergeCells = '<mergeCells count="'.count($layout['merges']).'">'
            .collect($layout['merges'])
                ->map(fn (string $range): string => '<mergeCell ref="'.$range.'"/>')
                ->implode('')
            .'</mergeCells>';

        return $this->replaceOnce(
            '/<mergeCells\b[^>]*>.*?<\/mergeCells>/s',
            $mergeCells,
            $xml,
            'merged cells',
        );
    }

    private function renderWorkbookXml(string $xml, int $lastRow): string
    {
        $xml = $this->replaceOnce(
            '/<definedName\b[^>]*name="_xlnm\.Print_Area"[^>]*>.*?<\/definedName>/s',
            '<definedName name="_xlnm.Print_Area" localSheetId="0">Breakdown!$A$1:$I$'.$lastRow.'</definedName>',
            $xml,
            'print area',
        );

        return $this->replaceOnce(
            '/<calcPr\b[^>]*\/>/',
            '<calcPr calcMode="auto" fullCalcOnLoad="1" forceFullCalc="1"/>',
            $xml,
            'calculation settings',
        );
    }

    private function removeCalcChainRelationship(string $xml): string
    {
        return preg_replace('/<Relationship\b[^>]*calcChain[^>]*\/>/', '', $xml)
            ?? throw new RuntimeException('The workbook relationships could not be updated.');
    }

    private function removeCalcChainContentType(string $xml): string
    {
        return preg_replace('/<Override\b[^>]*calcChain[^>]*\/>/', '', $xml)
            ?? throw new RuntimeException('The workbook content types could not be updated.');
    }

    private function replaceOnce(string $pattern, string $replacement, string $subject, string $part): string
    {
        $count = 0;
        $result = preg_replace_callback($pattern, static fn (): string => $replacement, $subject, 1, $count);

        if ($result === null || $count !== 1) {
            throw new RuntimeException('The official Estimated Expense Breakdown '.$part.' does not match the expected workbook.');
        }

        return $result;
    }

    private function writeEntry(ZipArchive $archive, string $name, string $contents): void
    {
        if (! $archive->addFromString($name, $contents)) {
            throw new RuntimeException('The generated Estimated Expense Breakdown workbook could not be written.');
        }
    }

    private function sectionHeaderRow(int $row, string $label, bool $isMooe): string
    {
        return $this->row($row, [
            $this->stringCell('A', $row, $isMooe ? 63 : 33, $label),
            $this->emptyCell('B', $row, $isMooe ? 27 : 34),
            ...$this->emptyCells($row, range('C', 'H'), 27),
            $this->emptyCell('I', $row, 28),
        ], 15);
    }

    /** @param array<string, mixed> $item */
    private function itemRow(
        int $row,
        ?string $account,
        ?string $subAccount,
        array $item,
        bool $isMooe,
    ): string {
        $accountStyle = $isMooe ? 35 : 43;
        $cells = [
            $account === null
                ? $this->emptyCell('A', $row, $accountStyle)
                : $this->stringCell('A', $row, $accountStyle, $account),
            $subAccount === null
                ? $this->emptyCell('B', $row, $accountStyle)
                : $this->stringCell('B', $row, $accountStyle, $subAccount),
        ];

        if ($item['is_contingency']) {
            array_push(
                $cells,
                $this->stringCell('C', $row, 19, 'N/A'),
                $this->stringCell('D', $row, 19, 'N/A'),
                $this->stringCell('E', $row, 19, $item['purpose']),
                $this->emptyCell('F', $row, 20),
                $this->emptyCell('G', $row, 19),
                $this->numberCell('H', $row, 16, $item['unit_cost']),
                $this->formulaCell('I', $row, 16, 'H'.$row, $item['total_cost']),
            );
        } else {
            array_push(
                $cells,
                $this->stringCell('C', $row, $isMooe ? 15 : 19, $item['particulars']),
                $this->stringCell('D', $row, 15, $item['details']),
                $this->stringCell('E', $row, 15, $item['purpose']),
                $this->stringCell('F', $row, 15, $item['unit']),
                $this->numberCell('G', $row, 15, $item['quantity']),
                $this->numberCell('H', $row, 16, $item['unit_cost']),
                $this->formulaCell('I', $row, 16, 'G'.$row.'*H'.$row, $item['total_cost']),
            );
        }

        return $this->row($row, $cells, 15);
    }

    private function subAccountTotalRow(
        int $row,
        string $label,
        int $itemStartRow,
        int $itemEndRow,
        float|int $total,
        bool $isMooe,
    ): string {
        return $this->row($row, [
            $this->emptyCell('A', $row, $isMooe ? 50 : 43),
            $this->emptyCell('B', $row, $isMooe ? 47 : 43),
            $this->stringCell('C', $row, $isMooe ? 29 : 44, $label),
            ...$this->emptyCells($row, range('D', 'G'), 30),
            $this->emptyCell('H', $row, 31),
            $this->formulaCell('I', $row, 18, 'SUM(I'.$itemStartRow.':I'.$itemEndRow.')', $total),
        ], 15);
    }

    /** @param list<int> $subAccountTotalRows */
    private function subtotalRow(int $row, array $subAccountTotalRows, float|int $total, bool $isMooe): string
    {
        return $this->row($row, [
            $this->stringCell('A', $row, $isMooe ? 26 : 45, 'Subtotal:'),
            $this->emptyCell('B', $row, $isMooe ? 27 : 30),
            ...$this->emptyCells($row, range('C', 'G'), 27),
            $this->emptyCell('H', $row, 28),
            $this->formulaCell('I', $row, 17, $this->sumFormula($subAccountTotalRows), $total),
        ], 15);
    }

    /** @param list<int> $accountSubtotalRows */
    private function sectionTotalRow(int $row, string $label, array $accountSubtotalRows, float|int $total): string
    {
        return $this->row($row, [
            $this->stringCell('A', $row, 32, $label),
            ...$this->emptyCells($row, range('B', 'G'), 27),
            $this->emptyCell('H', $row, 28),
            $this->formulaCell('I', $row, 21, $this->sumFormula($accountSubtotalRows), $total),
        ], 15);
    }

    /** @param list<int> $sectionTotalRows */
    private function grandTotalRow(int $row, array $sectionTotalRows, float|int $total): string
    {
        return $this->row($row, [
            $this->stringCell('A', $row, 37, 'TOTAL MOOE and CAPITAL OUTLAY:'),
            ...$this->emptyCells($row, range('B', 'G'), 38),
            $this->emptyCell('H', $row, 39),
            $this->formulaCell('I', $row, 22, $this->sumFormula($sectionTotalRows), $total),
        ], 15);
    }

    private function noteRow(int $row): string
    {
        return $this->row($row, [
            $this->stringCell('A', $row, 41, '*please remove the blank rows to simplify the form'),
            ...$this->emptyCells($row, range('B', 'E'), 42),
            ...$this->emptyCells($row, range('F', 'G'), 14),
            ...$this->emptyCells($row, range('H', 'I'), 6),
        ], 20.25);
    }

    private function blankRow(int $row, int $style): string
    {
        return $this->row($row, $this->emptyCells($row, range('A', 'I'), $style), 14.25);
    }

    private function footerBlankRow(int $row): string
    {
        return $this->row($row, [
            ...$this->emptyCells($row, range('A', 'E'), 40),
            ...$this->emptyCells($row, range('F', 'G'), 14),
            ...$this->emptyCells($row, range('H', 'I'), 6),
        ], 15);
    }

    /** @param list<string> $cells */
    private function row(int $row, array $cells, float $height): string
    {
        return '<row r="'.$row.'" spans="1:9" ht="'.$this->number($height).'" customHeight="1">'
            .implode('', $cells)
            .'</row>';
    }

    private function stringCell(string $column, int $row, int $style, string $value): string
    {
        return '<c r="'.$column.$row.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .$this->escape($value)
            .'</t></is></c>';
    }

    private function numberCell(string $column, int $row, int $style, float|int $value): string
    {
        return '<c r="'.$column.$row.'" s="'.$style.'"><v>'.$this->number($value).'</v></c>';
    }

    private function formulaCell(
        string $column,
        int $row,
        int $style,
        string $formula,
        float|int $value,
    ): string {
        return '<c r="'.$column.$row.'" s="'.$style.'"><f>'.$this->escape($formula).'</f><v>'
            .$this->number($value)
            .'</v></c>';
    }

    private function emptyCell(string $column, int $row, int $style): string
    {
        return '<c r="'.$column.$row.'" s="'.$style.'"/>';
    }

    /** @param list<string> $columns @return list<string> */
    private function emptyCells(int $row, array $columns, int $style): array
    {
        return collect($columns)
            ->map(fn (string $column): string => $this->emptyCell($column, $row, $style))
            ->all();
    }

    /** @param list<int> $rows */
    private function sumFormula(array $rows): string
    {
        if ($rows === []) {
            return 'SUM(0)';
        }

        return 'SUM('.collect($rows)->map(fn (int $row): string => 'I'.$row)->implode(',').')';
    }

    private function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
