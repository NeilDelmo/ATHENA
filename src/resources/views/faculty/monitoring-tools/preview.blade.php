@php
    $workPlan = collect($report->work_plan ?? []);
    $budget = collect($report->budget_utilization ?? [])->keyBy('type');
    $budgetTypes = ['Purchase Request', 'Cash Advance', 'Request of Payment'];
    $projectCost = (float) ($report->topic->estimated_budget ?? 0);
    $requestedTotal = $budget->sum(fn (array $entry): float => (float) ($entry['amount_requested'] ?? 0));
    $actualTotal = $budget->sum(fn (array $entry): float => (float) ($entry['actual_amount'] ?? 0));
    $money = fn (float $amount): string => 'PHP '.number_format($amount, 2);
    $percentage = fn (float $value): string => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%';
    $leader = $report->submitter?->name ?? $report->topic->user?->name ?? '';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <x-favicon />
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Monitoring Tool Preview</title>
        @vite('resources/css/monitoring-tool-print.css')
    </head>
    <body class="monitoring-preview-page">
        <div class="monitoring-paper-footer">Tracking No. __________________________</div>
        <main class="monitoring-sheet" aria-label="BatStateU monitoring tool preview">
            <table class="monitoring-table monitoring-header">
                <colgroup><col style="width: 8.548%"><col style="width: 35.116%"><col style="width: 40.365%"><col style="width: 15.971%"></colgroup>
                <tbody>
                    <tr>
                        <td class="monitoring-logo"><img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal"></td>
                        <td><strong>Reference No.:</strong> BatStateU-REC-RES-03</td>
                        <td><strong>Effectivity Date:</strong> May 18, 2022</td>
                        <td><strong>Revision No.:</strong> 03</td>
                    </tr>
                    <tr><td colspan="4"><h1>MONITORING TOOL FOR ONGOING RESEARCH PROJECT</h1></td></tr>
                </tbody>
            </table>

            <table class="monitoring-table monitoring-metadata-table">
                <colgroup><col style="width: 46.613%"><col style="width: 53.387%"></colgroup>
                <tbody>
                    <tr><td colspan="2">Date: {{ $report->reporting_date?->format('F j, Y') }}</td></tr>
                    <tr>
                        <td>Research Project Title: {{ $report->topic->title }}</td>
                        <td>Project Leader: {{ \Illuminate\Support\Str::upper($leader) }}</td>
                    </tr>
                    <tr>
                        <td>Total Project Cost: {{ $money($projectCost) }}</td>
                        <td>Duration: {{ (int) $report->topic->estimated_duration_months }} {{ (int) $report->topic->estimated_duration_months === 1 ? 'month' : 'months' }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="monitoring-section">
                <h2 class="monitoring-section-heading">Work Plan</h2>
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
                            <th scope="col">Activities</th>
                            <th scope="col">Percent Weight of the Activity</th>
                            <th scope="col">Physical Targets<br><span class="monitoring-regular">(Quantifiable)</span></th>
                            <th scope="col">Target Date of Completion based on the Approved Work Plan</th>
                            <th scope="col">Actual Accomplishment</th>
                            <th scope="col">*Percentage of the Accomplished Tasks</th>
                            <th scope="col">Notable findings or challenges encountered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($workPlan as $entry)
                            <tr>
                                <td class="monitoring-activity">
                                    @if (filled($entry['objective'] ?? null))
                                        Objective: {{ $entry['objective'] }}<br>
                                        Activity:
                                    @endif
                                    {{ $entry['activity'] }}
                                </td>
                                <td class="monitoring-number">{{ $percentage((float) $entry['percent_weight']) }}</td>
                                <td class="monitoring-pre-line monitoring-number">{{ $entry['physical_target'] }}</td>
                                <td class="monitoring-date">{{ \Illuminate\Support\Carbon::parse($entry['target_completion_date'])->format('M j, Y') }}</td>
                                <td class="monitoring-pre-line monitoring-number">{{ $entry['actual_accomplishment'] }}</td>
                                <td class="monitoring-number">{{ $percentage((float) $entry['accomplished_percentage']) }}</td>
                                <td class="monitoring-pre-line monitoring-number">{{ $entry['findings'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        @for ($index = $workPlan->count(); $index < 11; $index++)
                            <tr class="monitoring-empty-row">@for ($column = 0; $column < 7; $column++)<td></td>@endfor</tr>
                        @endfor
                    </tbody>
                </table>
                <table class="monitoring-table monitoring-total-table">
                    <colgroup><col style="width: 73.753%"><col style="width: 26.247%"></colgroup>
                    <tbody>
                        <tr><td>Total accomplishment as of {{ $report->reporting_date?->format('F j, Y') }}:</td><td class="monitoring-number">{{ $percentage((float) $report->progress_percentage) }}</td></tr>
                        <tr><td colspan="2">* = (actual accomplishment ÷ physical target) x percent weight of the activity</td></tr>
                    </tbody>
                </table>
            </section>

            <section class="monitoring-section">
                <h2 class="monitoring-section-heading">Budget Utilization</h2>
                <table class="monitoring-table monitoring-budget-table">
                    <colgroup><col style="width: 16.477%"><col style="width: 29.553%"><col style="width: 10.752%"><col style="width: 11.205%"><col style="width: 10.765%"><col style="width: 21.248%"></colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Type of Request</th>
                            <th scope="col">Details of Request</th>
                            <th scope="col">Amount Requested</th>
                            <th scope="col">Actual Amount<br><span class="monitoring-regular">(Amount disbursed)</span></th>
                            <th scope="col">Budget Utilized (%)</th>
                            <th scope="col">Remarks / Challenges Encountered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budgetTypes as $type)
                            @php
                                $entry = $budget->get($type, []);
                                $amountRequested = (float) ($entry['amount_requested'] ?? 0);
                                $actualAmount = (float) ($entry['actual_amount'] ?? 0);
                                $utilization = $projectCost > 0 ? ($actualAmount / $projectCost) * 100 : 0;
                            @endphp
                            <tr>
                                <th scope="row">{{ $type }}</th>
                                <td class="monitoring-request-details">{{ $entry['details'] ?? '' }}</td>
                                <td class="monitoring-money">{{ $money($amountRequested) }}</td>
                                <td class="monitoring-money">{{ $money($actualAmount) }}</td>
                                <td class="monitoring-number">{{ $percentage($utilization) }}</td>
                                <td>{{ $entry['remarks'] ?? '' }}</td>
                            </tr>
                        @endforeach
                        <tr class="monitoring-total-row">
                            <th scope="row" colspan="2">Total</th>
                            <td class="monitoring-money">{{ $money($requestedTotal) }}</td>
                            <td class="monitoring-money">{{ $money($actualTotal) }}</td>
                            <td class="monitoring-number">{{ $percentage($projectCost > 0 ? ($actualTotal / $projectCost) * 100 : 0) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <footer class="monitoring-signatures">
                <section class="monitoring-signature">
                    <p>Prepared by:</p>
                    <div class="monitoring-signature-line">_____________________________________</div>
                    <p>{{ \Illuminate\Support\Str::upper($leader) }}</p>
                    <p>Project Leader</p>
                    <p class="monitoring-date-signed">Date Signed: {{ $report->prepared_by_date_signed?->format('F j, Y') }}</p>
                </section>
                <section class="monitoring-signature">
                    <p>Monitored by:</p>
                    <div class="monitoring-signature-line">_____________________________________</div>
                    <p>{{ config('work_plan.verifier.name') }}</p>
                    <p>Head, Research</p>
                    <p class="monitoring-date-signed">Date Signed:</p>
                </section>
                <section class="monitoring-signature">
                    <p>Reviewed &amp; Evaluated by:</p>
                    <div class="monitoring-signature-line">___________________________________</div>
                    <p>{{ config('notice_to_proceed.issuing_officer.name') }}</p>
                    <p>Vice Chancellor for Research, Development and Extension Services</p>
                    <p class="monitoring-date-signed">Date Signed:</p>
                </section>
            </footer>
        </main>
    </body>
</html>
