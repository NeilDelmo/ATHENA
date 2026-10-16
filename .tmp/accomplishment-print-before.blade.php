<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Quarterly Accomplishment Report {{ $year }} Q{{ $quarter }}</title>
        <style>
            @page { size: 8.5in 13in; margin: 1in; }
            * { box-sizing: border-box; }
            body { margin: 0; color: #111; background: #e8e8e8; font: 11pt Arial, sans-serif; line-height: 1.4; }
            main { width: 8.5in; min-height: 13in; margin: 24px auto; padding: 1in; background: #fff; }
            header { position: relative; min-height: 110px; padding-left: 95px; text-align: center; font-size: 9pt; }
            header img { position: absolute; left: 0; top: 8px; width: 78px; }
            header p { margin: 0; }
            header strong { font-size: 12pt; }
            h1 { margin: 28px 0 4px; font-size: 14pt; text-align: center; }
            h2 { margin: 24px 0 10px; font-size: 12pt; }
            h3 { margin: 14px 0 8px; font-size: 11pt; }
            .period, .college { text-align: center; }
            .college { margin-top: 12px; font-weight: bold; }
            .period { margin: 0 0 20px; }
            table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10pt; }
            th, td { padding: 7px; border: 1px solid #444; vertical-align: top; text-align: left; white-space: pre-line; overflow-wrap: anywhere; }
            th { font-weight: bold; }
            thead { display: table-header-group; }
            tr, figure { break-inside: avoid; }
            .signatories { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; break-inside: avoid; }
            .signature-name { min-height: 40px; padding-top: 28px; font-weight: bold; }
            .signatories p { margin: 5px 0; }
            .narrative p { margin: 0 0 10px; }
            .narrative ol { list-style: decimal outside; padding-left: 24px; }
            .narrative ul { list-style: disc outside; padding-left: 24px; }
            figure { margin: 20px 0; text-align: center; }
            figure img { max-width: 100%; max-height: 7in; object-fit: contain; }
            figcaption { margin-top: 8px; font-size: 10pt; }
            footer { margin-top: 30px; text-align: center; font-size: 9pt; }
            .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 16px; padding: 16px; font: 14px Arial, sans-serif; }
            .toolbar button, .toolbar a { padding: 10px 14px; border: 1px solid #777; border-radius: 6px; background: #fff; color: #111; text-decoration: none; cursor: pointer; }
            .toolbar button { background: #800000; border-color: #800000; color: #fff; }
            @media print { body { background: white; } main { width: auto; min-height: auto; margin: 0; padding: 0; } .toolbar { display: none; } }
            @media screen and (max-width: 850px) { main { width: 100%; margin: 0; padding: 24px; } .signatories { gap: 16px; } }
        </style>
    </head>
    <body>
        <nav class="toolbar" aria-label="Report preview actions">
            <a href="{{ $backUrl ?? route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarter]) }}">Back to report</a>
            <button type="button" onclick="window.print()">Print or save PDF</button>
            <span>{{ $report?->submitted_at ? 'Submitted report' : 'Draft preview · Save your draft to include recent changes' }}</span>
        </nav>
        <main>
            <header>
                <img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University logo">
                <p>Republic of the Philippines</p>
                <p><strong>BATANGAS STATE UNIVERSITY</strong></p>
                <p>The National Engineering University</p>
                <p>ARASOF-Nasugbu Campus</p>
                <p>R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231</p>
                <p class="college">{{ $report?->college ?? $coordinator->college }}</p>
            </header>
            <h1>ACCOMPLISHMENT REPORT IN RESEARCH</h1>
            <p class="period">{{ $period['start']->format('F') }} to {{ $period['end']->format('F Y') }} (Q{{ $quarter }})</p>
            <h2>I. Participation in Research Activities</h2>
            <x-college-quarterly-report-table title="a. Presentation" :columns="['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved']" :rows="$reportData['presentations']" empty-message="None reported." />
            <x-college-quarterly-report-table title="b. Publication" :columns="['Journal Name/Conference', 'Research Title', 'Date', 'Personnel(s) involved']" :rows="$reportData['publications']" empty-message="None reported." />
            <x-college-quarterly-report-table title="c. Seminar/Training" :columns="['Event/Activity', 'Date', 'Place', 'Personnel(s) involved']" :rows="$report?->activityRows('seminars') ?? []" empty-message="None reported." />
            <x-college-quarterly-report-table title="II. Research of Faculty Members" :columns="['Title', 'Duration', 'Personnel(s) involved', 'Status']" :rows="$reportData['research']" empty-message="None reported." />
            <h2>III. Other Accomplishments</h2>
            <div class="narrative">{!! app(\App\Support\ProposalRichText::class)->sanitize($report?->other_accomplishments ?: 'None reported.') !!}</div>
            @if ($report?->activityRows('ip_assets'))
                <x-college-quarterly-report-table title="Intellectual Property Assets" :columns="['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link']" :rows="$report->activityRows('ip_assets')" />
            @endif
            <div class="signatories">
                <section>
                    <p>Prepared and submitted by:</p>
                    <p class="signature-name">{{ $reportData['prepared_by_name'] ?? $report?->preparedBy?->name ?? $coordinator->name }}</p>
                    <p>Research Coordinator</p>
                    <p>{{ $report?->college ?? $coordinator->college }}</p>
                    <p>Date Signed: __________________</p>
                </section>
                <section>
                    <p>Checked and reviewed by:</p>
                    <p class="signature-name">{{ $report?->reviewed_by ?: '________________________' }}</p>
                    <p>{{ $report?->reviewed_by_title ?: '________________________' }}</p>
                    <p>Date Signed: __________________</p>
                </section>
            </div>
            @if ($reportEvidence ?? $report?->evidence)
                <h2>Supporting Documentation</h2>
                @foreach ($reportEvidence ?? $report->evidence as $index => $evidence)
                    @php
                        $evidenceUrl = $evidence['url'] ?? (isset($submission)
                            ? route('college-quarterly-submissions.evidence', ['submission' => $submission, 'index' => $index])
                            : route('college-quarterly-reports.evidence', ['report' => $report, 'index' => $index]));
                        $evidenceLabel = $report?->evidenceLabel($evidence) ?: ($evidence['caption'] ?? 'Supporting documentation '.($index + 1));
                    @endphp
                    <figure>
                        @if (in_array($evidence['mime_type'] ?? 'image/png', ['image/jpeg', 'image/png'], true))
                            <img src="{{ $evidenceUrl }}" alt="{{ $evidenceLabel }}">
                        @else
                            <a href="{{ $evidenceUrl }}">{{ $evidence['original_filename'] }}</a>
                        @endif
                        <figcaption>{{ $evidenceLabel }}</figcaption>
                    </figure>
                @endforeach
            @endif
            <footer>Leading Innovations, Transforming Lives, Building the Nation</footer>
        </main>
    </body>
</html>
