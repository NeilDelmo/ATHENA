<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Attachment A - Work Plan Preview</title>
        @vite('resources/css/work-plan-print.css')
    </head>
    <body class="work-plan-preview-page">
        <main class="work-plan-sheet" aria-label="BatStateU Attachment A Work Plan">
            <p class="work-plan-form-code">Attachment A-BatStateU-FO-RES-02</p>
            <h1>MAJOR ACTIVITIES/WORK PLAN</h1>

            <table class="work-plan-table">
                <colgroup>
                    <col style="width: 22.7691%">
                    <col style="width: 18.3508%">
                    <col style="width: 8.7903%">
                    <col style="width: 10.0110%">
                    @for ($month = 1; $month <= 8; $month++)
                        <col style="width: 3.3992%">
                    @endfor
                    <col style="width: 2.9996%">
                    @for ($month = 10; $month <= 12; $month++)
                        <col style="width: 3.2949%">
                    @endfor
                </colgroup>
                <tbody>
                    <tr class="work-plan-title-row">
                        <th scope="row">Title:</th>
                        <td colspan="15" class="work-plan-value">{{ $workPlan['title'] }}</td>
                    </tr>
                    <tr class="work-plan-project-row">
                        <th scope="row">Project Title:</th>
                        <td colspan="15" class="work-plan-value">{{ $workPlan['project_title'] }}</td>
                    </tr>
                    <tr class="work-plan-duration-row">
                        <th scope="row">
                            <span>Total Duration (in months):</span>
                            <strong>{{ $workPlan['total_duration_label'] }}</strong>
                        </th>
                        <td colspan="3" class="work-plan-date-cell">
                            <span>Planned Start:</span>
                            <strong>{{ $workPlan['planned_start'] }}</strong>
                        </td>
                        <td colspan="12" class="work-plan-date-cell">
                            <span>Planned End:</span>
                            <strong>{{ $workPlan['planned_end'] }}</strong>
                        </td>
                    </tr>
                    <tr class="work-plan-heading-row">
                        <th rowspan="2" scope="col">Objectives</th>
                        <th rowspan="2" scope="col">Expected Output</th>
                        <th rowspan="2" colspan="2" scope="col">Activities or Workplan</th>
                        <th colspan="12" scope="colgroup">Y1</th>
                    </tr>
                    <tr class="work-plan-month-heading-row">
                        @for ($month = 1; $month <= 12; $month++)
                            <th scope="col">M{{ $month }}</th>
                        @endfor
                    </tr>
                    @foreach ($workPlan['entries'] as $entry)
                        <tr class="work-plan-entry-row" data-work-plan-entry-row>
                            <td>{{ $entry['objective'] }}</td>
                            <td>{{ $entry['expected_output'] }}</td>
                            <td colspan="2">{{ $entry['activity'] }}</td>
                            @for ($month = 1; $month <= 12; $month++)
                                <td
                                    class="work-plan-month-mark {{ in_array($month, $entry['months'], true) ? 'is-active' : '' }}"
                                    @if (in_array($month, $entry['months'], true)) data-scheduled-month="{{ $month }}" @endif
                                ></td>
                            @endfor
                        </tr>
                    @endforeach
                    <tr class="work-plan-signature-row">
                        <td colspan="3">
                            <section class="work-plan-signature-block">
                                <p class="work-plan-signature-heading">Prepared by:</p>
                                <p class="work-plan-signature-line" data-signature-line aria-hidden="true"></p>
                                <p class="work-plan-signature-name" data-signature-name>{{ $workPlan['prepared_by'] }}</p>
                                <p class="work-plan-signature-role">Project Leader</p>
                                <p class="work-plan-signature-date">Date Signed: <span>{{ $workPlan['prepared_date'] }}</span></p>
                            </section>
                        </td>
                        <td colspan="13">
                            <section class="work-plan-signature-block">
                                <p class="work-plan-signature-heading">Checked &amp; Verified by:</p>
                                <p class="work-plan-signature-line" data-signature-line aria-hidden="true"></p>
                                <p class="work-plan-signature-name" data-signature-name>{{ $workPlan['verified_by'] }}</p>
                                <p class="work-plan-signature-role">{{ $workPlan['verified_role'] }}</p>
                                <p class="work-plan-signature-date">Date Signed: <span>{{ $workPlan['verified_date'] }}</span></p>
                            </section>
                        </td>
                    </tr>
                </tbody>
            </table>
        </main>
    </body>
</html>
