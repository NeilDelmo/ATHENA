@php
    $workPlan = collect($report->work_plan ?? []);
    $budget = collect($report->budget_utilization ?? [])->keyBy('type');
    $budgetTypes = ['Purchase Request', 'Cash Advance', 'Request of Payment'];
    $projectCost = (float) ($report->topic->estimated_budget ?? 0);
    $requestedTotal = $budget->sum(fn (array $entry): float => (float) ($entry['amount_requested'] ?? 0));
    $actualTotal = $budget->sum(fn (array $entry): float => (float) ($entry['actual_amount'] ?? 0));
    $money = fn (float $amount): string => 'PHP '.number_format($amount, 2);
    $percentage = fn (float $value): string => number_format($value, 2, '.', '').'%';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Monitoring Tool Preview</title>
        @vite('resources/css/monitoring-tool-print.css')
    </head>
    <body class="monitoring-preview-page">
        <main class="monitoring-sheet" aria-label="BatStateU monitoring tool preview">
            <header class="monitoring-header">
                <p>BatStateU-REC-RES-03</p>
                <div>
                    <h1>MONITORING TOOL</h1>
                    <p>Research Project Progress Report</p>
                </div>
                <p>Revision 03</p>
            </header>

            <table class="monitoring-table monitoring-metadata-table">
                <tbody>
                    <tr>
                        <th scope="row">Reporting Date</th>
                        <td>{{ $report->reporting_date?->format('F j, Y') }}</td>
                        <th scope="row">Tracking No.</th>
                        <td>{{ $report->tracking_number ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Research Project Title</th>
                        <td colspan="3" class="monitoring-project-title">{{ $report->topic->title }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Project Leader</th>
                        <td>{{ $report->topic->user->name }}</td>
                        <th scope="row">Project Duration</th>
                        <td>{{ $report->topic->estimated_duration_months }} months</td>
                    </tr>
                    <tr>
                        <th scope="row">Approved Project Cost</th>
                        <td>{{ $money($projectCost) }}</td>
                        <th scope="row">Overall Progress</th>
                        <td class="monitoring-emphasis">{{ $report->progress_percentage }}%</td>
                    </tr>
                </tbody>
            </table>

            <section class="monitoring-section">
                <h2>A. WORK PLAN</h2>
                <table class="monitoring-table monitoring-work-plan-table">
                    <colgroup>
                        <col class="activity-column">
                        <col class="weight-column">
                        <col class="target-column">
                        <col class="date-column">
                        <col class="accomplishment-column">
                        <col class="progress-column">
                        <col class="findings-column">
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Activity</th>
                            <th scope="col">Percent Weight</th>
                            <th scope="col">Physical Target<br>(Quantifiable)</th>
                            <th scope="col">Target Completion Date</th>
                            <th scope="col">Actual Accomplishment</th>
                            <th scope="col">Percentage of Accomplished Tasks</th>
                            <th scope="col">Notable Findings / Challenges</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workPlan as $entry)
                            <tr>
                                <td>{{ $entry['activity'] }}</td>
                                <td class="monitoring-number">{{ $percentage((float) $entry['percent_weight']) }}</td>
                                <td>{{ $entry['physical_target'] }}</td>
                                <td class="monitoring-date">{{ \Illuminate\Support\Carbon::parse($entry['target_completion_date'])->format('M j, Y') }}</td>
                                <td>{{ $entry['actual_accomplishment'] }}</td>
                                <td class="monitoring-number">{{ $percentage((float) $entry['accomplished_percentage']) }}</td>
                                <td>{{ ($entry['findings'] ?? '') ?: '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="monitoring-total-row">
                            <th scope="row">TOTAL</th>
                            <td class="monitoring-number">{{ $percentage($workPlan->sum(fn (array $entry): float => (float) $entry['percent_weight'])) }}</td>
                            <td colspan="3"></td>
                            <td class="monitoring-number">{{ $report->progress_percentage }}%</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="monitoring-section">
                <h2>B. BUDGET UTILIZATION</h2>
                <table class="monitoring-table monitoring-budget-table">
                    <thead>
                        <tr>
                            <th scope="col">Type of Request</th>
                            <th scope="col">Details of Request</th>
                            <th scope="col">Amount Requested</th>
                            <th scope="col">Actual Amount Disbursed</th>
                            <th scope="col">Utilization</th>
                            <th scope="col">Remarks / Challenges</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budgetTypes as $type)
                            @php
                                $entry = $budget->get($type, []);
                                $amountRequested = (float) ($entry['amount_requested'] ?? 0);
                                $actualAmount = (float) ($entry['actual_amount'] ?? 0);
                                $utilization = $amountRequested > 0 ? ($actualAmount / $amountRequested) * 100 : 0;
                            @endphp
                            <tr>
                                <th scope="row">{{ $type }}</th>
                                <td>{{ ($entry['details'] ?? '') ?: '—' }}</td>
                                <td class="monitoring-money">{{ $money($amountRequested) }}</td>
                                <td class="monitoring-money">{{ $money($actualAmount) }}</td>
                                <td class="monitoring-number">{{ $percentage($utilization) }}</td>
                                <td>{{ ($entry['remarks'] ?? '') ?: '—' }}</td>
                            </tr>
                        @endforeach
                        <tr class="monitoring-total-row">
                            <th scope="row" colspan="2">TOTAL</th>
                            <td class="monitoring-money">{{ $money($requestedTotal) }}</td>
                            <td class="monitoring-money">{{ $money($actualTotal) }}</td>
                            <td class="monitoring-number">{{ $percentage($requestedTotal > 0 ? ($actualTotal / $requestedTotal) * 100 : 0) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <footer class="monitoring-signature">
                <p>Prepared by:</p>
                <div class="monitoring-signature-line">{{ strtoupper($report->submitter->name) }}</div>
                <p>Project Leader</p>
                <p>Date Signed: {{ $report->prepared_by_date_signed?->format('F j, Y') ?: '____________________' }}</p>
            </footer>
        </main>
    </body>
</html>
