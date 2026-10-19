@php
    $figures = collect($report->photos ?? []);
    $figureNumber = 1;
    $trackingContent = '"'.str_replace(['\\', '"', "\n", "\r", "\f"], ['\\\\', '\\"', '\\a ', '\\d ', '\\c '], 'Tracking No.'.($report->tracking_number ?: '____________________')).'"';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" style="--report-tracking: {{ $trackingContent }}">
    <head>
        <x-favicon />
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Progress Report Preview</title>
        @vite('resources/css/progress-report-print.css')
    </head>
    <body class="progress-report-preview-page">
        <div class="progress-report-paper-footer">Tracking No.{{ $report->tracking_number ?: '____________________' }}</div>
        <main class="progress-report-sheet" aria-label="BatStateU progress report preview">
            <table class="progress-report-table progress-report-header">
                <colgroup><col style="width: 10.113%"><col style="width: 37.118%"><col style="width: 34.355%"><col style="width: 18.414%"></colgroup>
                <tbody>
                    <tr>
                        <td class="progress-report-logo"><img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal"></td>
                        <td><strong>Reference No.:</strong> BatStateU-REC-RES-02</td>
                        <td><strong>Effectivity Date:</strong> May 18, 2022</td>
                        <td><strong>Revision No.:</strong> 02</td>
                    </tr>
                    <tr><td colspan="4"><h1>PROGRESS REPORT ON RESEARCH PROJECT</h1></td></tr>
                </tbody>
            </table>

            <table class="progress-report-table progress-report-content">
                <tbody>
                    <tr><td>Submission Date: {{ $report->submission_date?->format('F j, Y') }}@if ($report->reporting_period_label)<br>Reporting period: {{ $report->reporting_period_label }}@endif</td></tr>
                    <tr><td><h2>Research Project Title</h2></td></tr>
                    <tr><td>{{ $report->topic->title }}</td></tr>
                    <tr><td><h2>Researchers</h2></td></tr>
                    <tr><td class="progress-report-pre-line">{{ $report->researchers }}</td></tr>
                    <tr><td><h2>Duration (Approved Start &amp; End of Implementation)</h2></td></tr>
                    <tr><td>{{ $report->topic->estimated_duration_months ? $report->topic->estimated_duration_months.' months ' : '' }}({{ $report->implementation_start?->format('F j, Y') }} - {{ $report->implementation_end?->format('F j, Y') }})</td></tr>
                    <tr><td><h2>Budget</h2></td></tr>
                    <tr><td>P {{ number_format((float) $report->budget, 2) }}</td></tr>
                    <tr><td><h2>Funding Agency</h2></td></tr>
                    <tr><td>{{ $report->funding_agency }}</td></tr>
                    <tr><td><h2>Summary of Accomplishment for the Monitoring Period</h2></td></tr>
                    <tr>
                        <td class="progress-report-summary-cell">
                            <table class="progress-report-table progress-report-accomplishments-table">
                                <colgroup><col style="width: 34.862%"><col style="width: 33.303%"><col style="width: 31.835%"></colgroup>
                                <thead><tr><th scope="col">Objectives</th><th scope="col">Target Accomplishments</th><th scope="col">Actual Accomplishments</th></tr></thead>
                                <tbody>
                                    @foreach ($report->accomplishments ?? [] as $accomplishment)
                                        <tr>
                                            <td class="progress-report-pre-line">{{ $accomplishment['objective'] }}</td>
                                            <td class="progress-report-pre-line">{{ $accomplishment['target'] }}</td>
                                            <td class="progress-report-pre-line">{{ $accomplishment['actual'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h2>Introduction</h2>
                            <p class="progress-report-narrative">{{ $report->introduction }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h2 class="progress-report-rationale">VIII. Rationale: <span>(include available statistics related to the problem)</span></h2>
                            <p class="progress-report-narrative">{{ $report->rationale }}</p>
                        </td>
                    </tr>
                    <tr><td><h2>Objectives</h2></td></tr>
                    <tr><td class="progress-report-pre-line">{{ $report->objectives }}</td></tr>
                    @foreach (['methodology' => 'Methodology', 'results_discussion' => 'Results and Discussion'] as $field => $heading)
                        @php
                            $paragraphs = preg_split('/\n\s*\n/u', str_replace(["\r\n", "\r"], "\n", $report->$field ?? '')) ?: [''];
                        @endphp
                        <tr><td><h2>{{ $heading }}</h2></td></tr>
                        <tr>
                            <td class="progress-report-section">
                                @foreach ($paragraphs as $paragraphIndex => $paragraph)
                                    <p class="progress-report-narrative">{{ $paragraph }}</p>
                                    @foreach ($figures->where('section', $field) as $photo)
                                        @php
                                            $position = (int) ($photo['after_paragraph'] ?? 0);
                                            $position = $position === 0 ? count($paragraphs) : min($position, count($paragraphs));
                                        @endphp
                                        @if ($position === $paragraphIndex + 1)
                                            <figure @if(isset($photo['preview_file_input'])) hidden @endif>
                                                <picture>@if(isset($photo['original_url']))<source media="print" srcset="{{ $photo['original_url'] }}">@endif<img @if(isset($photo['preview_file_input'])) data-preview-file-input="{{ $photo['preview_file_input'] }}" @else src="{{ $photo['preview_url'] ?? '' }}" @endif alt="{{ $photo['caption'] }}" decoding="async"></picture>
                                                <figcaption>Figure {{ $figureNumber++ }}. {{ $photo['caption'] }}</figcaption>
                                            </figure>
                                        @endif
                                    @endforeach
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="progress-report-table progress-report-sign-off">
                <colgroup><col style="width: 46.427%"><col style="width: 53.573%"></colgroup>
                <tbody>
                    <tr><td colspan="2"><strong>Note: Please attach high resolution pictures with captions.</strong></td></tr>
                    <tr>
                        <td colspan="2" class="progress-report-signature progress-report-prepared-by">
                            <p>Prepared by:</p>
                            <div class="progress-report-signature-line">_________________________</div>
                            <p><strong>{{ \Illuminate\Support\Str::upper($report->topic->user->name) }}</strong></p>
                            <p>Project Leader</p>
                            <p>Date Signed: {{ $report->prepared_by_date_signed?->format('F j, Y') }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td class="progress-report-signature">
                            <p>Monitored by:</p>
                            <div class="progress-report-signature-line">_________________________</div>
                            <p>{{ config('work_plan.verifier.name') }}</p>
                            <p>Head, Research and Extension/ Head, Research/<br>Center Head/ Assistant Director, Research</p>
                            <p>Date Signed:</p>
                        </td>
                        <td class="progress-report-signature">
                            <p>Reviewed and Verified by:</p>
                            <div class="progress-report-signature-line">__________________________</div>
                            <p>{{ config('notice_to_proceed.issuing_officer.name') }}</p>
                            <p>Vice Chancellor for Research, Development and Extension Services/ Director, Research</p>
                            <p>Date Signed:</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </main>
    </body>
</html>
