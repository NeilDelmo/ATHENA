<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Attachment B - Line-Item Budget Preview</title>
        @vite('resources/css/line-item-budget-print.css')
    </head>
    <body class="line-budget-preview-page">
        <main class="line-budget-sheet" aria-label="BatStateU Attachment B Line-Item Budget">
            <p class="line-budget-form-code">Attachment B-BatStateU-FO-RES-02</p>
            <h1>LINE-ITEM BUDGET</h1>

            <table class="line-budget-table">
                <colgroup><col><col><col><col><col><col class="amount-column"></colgroup>
                <tbody>
                    <tr><th colspan="3" scope="row">Program Title:</th><td colspan="3"></td></tr>
                    <tr><th colspan="3" scope="row">Project Title:</th><td colspan="3" class="project-title">{{ $lineItemBudget['project_title'] }}</td></tr>
                    <tr class="staff-heading"><th colspan="3"></th><th>Name</th><th>Campus</th><th>College</th></tr>
                    <tr><th colspan="3" scope="row">Project Leader:</th><td>{{ $lineItemBudget['project_leader'] }}</td><td>{{ $lineItemBudget['leader_campus'] }}</td><td>{{ $lineItemBudget['leader_college'] }}</td></tr>
                    @forelse ($lineItemBudget['staff'] as $member)
                        <tr><th colspan="3" scope="row">{{ $loop->first ? 'Project Staff:' : '' }}</th><td>{{ $member['name'] }}</td><td>{{ $member['campus'] }}</td><td>{{ $member['college'] }}</td></tr>
                    @empty
                        <tr><th colspan="3" scope="row">Project Staff:</th><td></td><td></td><td></td></tr>
                    @endforelse
                    <tr><th colspan="3" scope="row">Duration:</th><td colspan="3" class="duration"><em>{{ $lineItemBudget['duration'] }}</em></td></tr>
                    <tr class="budget-heading"><th colspan="5">Particulars</th><th>Amount (Php)</th></tr>
                    <tr class="section-heading"><th colspan="5">I. Maintenance and Other Operating Expenses (MOOE)</th><td></td></tr>
                    @foreach (config('line_item_budget.sections.mooe.items') as $item)
                        <tr><td colspan="5" class="particular level-{{ $item['level'] }}">{{ $item['label'] }}</td><td class="amount">{{ isset($lineItemBudget['amounts'][$item['key']]) ? number_format($lineItemBudget['amounts'][$item['key']], 2) : '' }}</td></tr>
                    @endforeach
                    @foreach ($lineItemBudget['custom_mooe_items'] as $item)
                        <tr><td colspan="5" class="particular level-1">{{ $item['particular'] }}</td><td class="amount">{{ $item['amount'] === null ? '' : number_format($item['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><th colspan="5">Total for Maintenance and Other Operating Expenses (MOOE)</th><td class="amount">{{ number_format($lineItemBudget['mooe_total'], 2) }}</td></tr>
                    <tr class="section-heading"><th colspan="5">II. Capital Outlays (CO)</th><td></td></tr>
                    @foreach (config('line_item_budget.sections.co.items') as $item)
                        <tr><td colspan="5" class="particular level-{{ $item['level'] }}">{{ $item['label'] }}</td><td class="amount">{{ isset($lineItemBudget['amounts'][$item['key']]) ? number_format($lineItemBudget['amounts'][$item['key']], 2) : '' }}</td></tr>
                    @endforeach
                    @foreach ($lineItemBudget['custom_co_items'] as $item)
                        <tr><td colspan="5" class="particular level-1">{{ $item['particular'] }}</td><td class="amount">{{ $item['amount'] === null ? '' : number_format($item['amount'], 2) }}</td></tr>
                    @endforeach
                    <tr class="total-row"><th colspan="5">Total for Capital Outlays (CO)</th><td class="amount">{{ number_format($lineItemBudget['co_total'], 2) }}</td></tr>
                    <tr class="spacer-row"><td colspan="5"></td><td></td></tr>
                    <tr class="project-total"><th colspan="5">TOTAL PROJECT COST</th><td class="amount">{{ number_format($lineItemBudget['project_total'], 2) }}</td></tr>
                    <tr><td colspan="6" class="signature-cell"><p class="signature-heading">Prepared by:</p><p class="signature-line"></p><p class="signature-name">{{ $lineItemBudget['project_leader'] }}</p><p>Project Leader</p><p class="date-signed">Date Signed:</p></td></tr>
                    <tr><td colspan="6" class="research-office-heading"><em>To be accomplished by the Research Office</em></td></tr>
                    <tr><td colspan="6" class="research-office"><strong>Level of Call</strong><br><span>{{ $lineItemBudget['level_of_call'] === 'central_agency' ? '☒' : '☐' }} Central Agency (VPRDES, President)</span><span>{{ $lineItemBudget['level_of_call'] === 'constituent_campus' ? '☒' : '☐' }} Constituent Campus (VCRDES, Chancellor)</span></td></tr>
                    <tr><td colspan="6" class="approval-line">
                        @if ($lineItemBudget['approval_body'] || $lineItemBudget['resolution_number'] || $lineItemBudget['resolution_year'])
                            Approved by the {{ $lineItemBudget['approval_body'] === 'lrec' ? 'Local Research Evaluation Committee as per LREC' : 'Research Council as per Research Council' }} Resolution No. {{ $lineItemBudget['resolution_number'] ?: '_____' }}, S. {{ $lineItemBudget['resolution_year'] ?: '_____' }}
                        @else
                            Approved by the Research Council/Local Research Evaluation Committee as per Research Council Resolution No. _____, S. _____/LREC Resolution No. _____, S. _____
                        @endif
                    </td></tr>
                    <tr><td colspan="6" class="signature-cell certified"><p class="signature-heading">Certified correct:</p><p class="signature-line"></p><p class="signature-name">{{ $lineItemBudget['certified_by'] }}</p><p>{{ $lineItemBudget['certified_role'] }}</p><p class="date-signed">Date Signed:</p></td></tr>
                </tbody>
            </table>

            <div class="line-budget-notes">
                <p><em>Note: Add category or sub-category if needed base in UACS Codes/Chart of Accounts</em></p>
                <p><em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*Contingency shall not exceed in 10% of Total MOOE Cost</em></p>
                <p><em>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;**For LREC approved research projects signatory shall be the Chancellor, LREC Vice Chairperson</em></p>
            </div>

            <footer><span>{{ $lineItemBudget['project_title'] }}</span><span>Page 1 of 1</span></footer>
        </main>
    </body>
</html>
