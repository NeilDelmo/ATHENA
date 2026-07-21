<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Estimated Expense Breakdown Preview</title>
        @vite('resources/css/expense-breakdown-print.css')
    </head>
    <body class="expense-breakdown-preview-page">
        <main class="expense-breakdown-sheet" aria-label="Estimated Breakdown and Details of Expenses">
            <h1>Estimated Breakdown and Details of Expenses</h1>
            <p class="expense-breakdown-project-title"><strong>Project Title:</strong> {{ $expenseBreakdown['project_title'] }}</p>

            <table class="expense-breakdown-table">
                <thead>
                    <tr>
                        <th scope="col">Account</th>
                        <th scope="col">Sub-account</th>
                        <th scope="col">Particular/s</th>
                        <th scope="col">Descriptions/Specifications/Details</th>
                        <th scope="col">Purpose in the project</th>
                        <th scope="col">Unit</th>
                        <th scope="col">Qty.</th>
                        <th scope="col">Unit Cost (Php)</th>
                        <th scope="col">Total Unit Cost (Php)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($expenseBreakdown['sections'] as $section)
                        <tr class="expense-breakdown-section-row">
                            <th colspan="9" scope="rowgroup">{{ $section['label'] }}</th>
                        </tr>
                        @foreach ($section['accounts'] as $account)
                            @php($accountRowspan = collect($account['sub_accounts'])->sum(fn (array $subAccount): int => count($subAccount['items']) + 1))
                            @foreach ($account['sub_accounts'] as $subAccountIndex => $subAccount)
                                @foreach ($subAccount['items'] as $itemIndex => $item)
                                    <tr class="expense-breakdown-item-row">
                                        @if ($subAccountIndex === 0 && $itemIndex === 0)
                                            <td rowspan="{{ $accountRowspan }}">{{ $account['label'] }}</td>
                                        @endif
                                        @if ($itemIndex === 0)
                                            <td rowspan="{{ count($subAccount['items']) }}">{{ $subAccount['label'] }}</td>
                                        @endif
                                        <td>{{ $item['particulars'] }}</td>
                                        <td>{{ $item['details'] }}</td>
                                        <td>{{ $item['purpose'] }}</td>
                                        <td>{{ $item['unit'] }}</td>
                                        <td class="expense-breakdown-number">{{ $item['is_contingency'] ? '' : (fmod($item['quantity'], 1.0) === 0.0 ? number_format($item['quantity']) : number_format($item['quantity'], 2)) }}</td>
                                        <td class="expense-breakdown-money">{{ number_format($item['unit_cost'], 2) }}</td>
                                        <td class="expense-breakdown-money">{{ number_format($item['total_cost'], 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="expense-breakdown-sub-account-total">
                                    <th colspan="7" scope="row">{{ $subAccount['total_label'] }}</th>
                                    <td class="expense-breakdown-money">{{ number_format($subAccount['total'], 2) }}</td>
                                </tr>
                            @endforeach
                            <tr class="expense-breakdown-account-total">
                                <th colspan="8" scope="row">Subtotal:</th>
                                <td class="expense-breakdown-money">{{ number_format($account['total'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="expense-breakdown-section-total">
                            <th colspan="8" scope="row">{{ $section['key'] === 'mooe' ? 'TOTAL MOOE:' : 'TOTAL CAPITAL OUTLAY:' }}</th>
                            <td class="expense-breakdown-money">{{ number_format($section['total'], 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="expense-breakdown-grand-total">
                        <th colspan="8" scope="row">TOTAL MOOE and CAPITAL OUTLAY:</th>
                        <td class="expense-breakdown-money">{{ number_format($expenseBreakdown['grand_total'], 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </main>
    </body>
</html>
