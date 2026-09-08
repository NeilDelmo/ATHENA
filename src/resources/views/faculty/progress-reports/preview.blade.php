@php
    $figures = collect($report->photos ?? []);
    $figureNumber = 1;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Progress Report Preview</title>
        @vite('resources/css/progress-report-print.css')
    </head>
    <body class="progress-report-preview-page">
        <main class="progress-report-sheet" aria-label="BatStateU progress report preview">
            <header class="progress-report-header">
                <p>BatStateU-REC-RES-02</p>
                <div>
                    <h1>{{ strtoupper($report->report_label) }}</h1>
                    <p>Research Project {{ $report->report_label }}</p>
                </div>
                <p>Revision 02</p>
            </header>

            <table class="progress-report-table progress-report-metadata-table">
                <tbody>
                    <tr>
                        <th scope="row">I. Submission Date</th>
                        <td>{{ $report->submission_date?->format('F j, Y') }}</td>
                    </tr>
                    <tr>
                        <th scope="row">Research Project Title</th>
                        <td class="progress-report-emphasis">{{ $report->topic->title }}</td>
                    </tr>
                    <tr>
                        <th scope="row">II. Researchers</th>
                        <td class="progress-report-pre-line">{{ $report->researchers }}</td>
                    </tr>
                    <tr>
                        <th scope="row">III. Project Duration</th>
                        <td>{{ $report->topic->estimated_duration_months }} months ({{ $report->implementation_start?->format('F j, Y') }} - {{ $report->implementation_end?->format('F j, Y') }})</td>
                    </tr>
                    <tr>
                        <th scope="row">IV. Project Cost</th>
                        <td>P {{ number_format((float) $report->budget, 2) }}</td>
                    </tr>
                    <tr>
                        <th scope="row">V. Funding Agency</th>
                        <td>{{ $report->funding_agency }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="progress-report-section">
                <h2>VI. SUMMARY OF ACCOMPLISHMENT FOR THE MONITORING PERIOD</h2>
                <table class="progress-report-table progress-report-accomplishments-table">
                    <thead>
                        <tr>
                            <th scope="col">Objectives</th>
                            <th scope="col">Target Accomplishment</th>
                            <th scope="col">Actual Accomplishment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report->accomplishments ?? [] as $accomplishment)
                            <tr>
                                <td>{{ $accomplishment['objective'] }}</td>
                                <td>{{ $accomplishment['target'] }}</td>
                                <td>{{ $accomplishment['actual'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            <section class="progress-report-section">
                <h2>VII. INTRODUCTION</h2>
                <p class="progress-report-narrative">{{ $report->introduction }}</p>
            </section>

            <section class="progress-report-section">
                <h2>VIII. RATIONALE</h2>
                <p class="progress-report-narrative">{{ $report->rationale }}</p>
            </section>

            <section class="progress-report-section">
                <h2>VIII. OBJECTIVES</h2>
                <p class="progress-report-narrative">{{ $report->objectives }}</p>
            </section>

            <section class="progress-report-section">
                <h2>IX. METHODOLOGY</h2>
                <p class="progress-report-narrative">{{ $report->methodology }}</p>
                @foreach ($figures->where('section', 'methodology') as $photo)
                    <figure hidden>
                        <img data-preview-file-input="{{ $photo['preview_file_input'] }}" alt="{{ $photo['caption'] }}">
                        <figcaption>Figure {{ $figureNumber++ }}. {{ $photo['caption'] }}</figcaption>
                    </figure>
                @endforeach
            </section>

            <section class="progress-report-section">
                <h2>X. RESULTS AND DISCUSSION</h2>
                <p class="progress-report-narrative">{{ $report->results_discussion }}</p>
                @foreach ($figures->where('section', 'results_discussion') as $photo)
                    <figure hidden>
                        <img data-preview-file-input="{{ $photo['preview_file_input'] }}" alt="{{ $photo['caption'] }}">
                        <figcaption>Figure {{ $figureNumber++ }}. {{ $photo['caption'] }}</figcaption>
                    </figure>
                @endforeach
            </section>

            <footer class="progress-report-signature">
                <p>Prepared by:</p>
                <div class="progress-report-signature-line">{{ strtoupper($report->submitter->name) }}</div>
                <p>Project Leader</p>
                <p>Date Signed: {{ $report->prepared_by_date_signed?->format('F j, Y') ?: '____________________' }}</p>
                <p>Tracking No. {{ $report->tracking_number ?: '__________________________' }}</p>
            </footer>
        </main>
    </body>
</html>
