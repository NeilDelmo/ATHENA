@php
    $data = $report->terminal_data ?? [];
    $rich = app(\App\Support\ProposalRichText::class);
    $date = fn ($value) => filled($value) ? \Carbon\Carbon::parse($value)->format('F j, Y') : '';
    $figureNumber = 1;
    $tableNumber = 1;
    $title = $data['project_title'] ?? $report->topic->title;
    $coverImage = collect($report->photos ?? [])->first(fn (array $photo): bool => ($photo['section'] ?? null) === 'cover');
    $sections = [
        ['IV. Abstract (200–250 words)', $data['abstract'] ?? '', 'abstract'],
        ['V. Introduction (Brief with rationale), Review of Literature and Objectives', $report->introduction, 'introduction'],
        ['Rationale', $report->rationale ?? '', 'rationale'],
        ['Review of Literature', $data['literature_review'] ?? '', 'literature_review'],
        ['Objectives', trim($report->objectives."\n".collect($report->accomplishments)->pluck('objective')->map(fn ($text, $i) => ($i + 1).'. '.$text)->implode("\n")), 'objectives'],
        ['VI. Materials and Methods / Methodology', $report->methodology, 'methodology'],
        ['VII. Results and Discussion', $report->results_discussion, 'results_discussion'],
        ['Conclusions', $data['conclusions'] ?? '', 'conclusions'],
        ['Recommendations', $data['recommendations'] ?? '', 'recommendations'],
        ['Bibliography', $data['bibliography'] ?? '', 'bibliography'],
    ];
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Terminal Report Preview</title>
<style>
body{margin:0;background:#e5e7eb;color:#111;font:11pt/1.45 'Times New Roman',serif}.sheet{box-sizing:border-box;background:white;max-width:210mm;margin:20px auto;padding:18mm}h1{font-size:20pt}h2{font-size:12pt;margin-top:22px}h3{font-size:11pt}.cover{text-align:center;min-height:230mm;break-after:page}.cover-poster{margin:18px auto;max-width:155mm}.cover-poster img{max-height:105mm}.cover-poster figcaption{font-size:9.5pt}.reference{display:flex;justify-content:space-between;gap:12px;border:1px solid #333;padding:8px;font-size:9pt}table{width:100%;border-collapse:collapse;margin:12px 0;table-layout:fixed}th,td{border:1px solid #888;padding:7px;overflow-wrap:anywhere;vertical-align:top}th{background:#f2f2f2}thead{display:table-header-group}p{white-space:pre-wrap}figure{margin:18px 0;text-align:center;break-inside:avoid}figure img{max-width:100%;max-height:650px;object-fit:contain}figcaption{font-style:italic}.signatures{break-before:page}.signature{break-inside:avoid;margin:20px 0}.signature p{margin:3px 0}.placeholder{min-height:100px;border:1px dashed #aaa} @media print{body{background:white}.sheet{margin:0;padding:0;max-width:none}@page{size:A4;margin:18mm}}
</style></head><body><main class="sheet">
<div class="reference"><span>Reference No.: BatStateU-REC-RES-04</span><span>Effectivity Date: May 18, 2022</span><span>Revision No.: 02</span></div>
<section class="cover"><h1>TERMINAL REPORT</h1><h2>I. Cover Page</h2><h2>Batangas State University</h2><p>The National Engineering University</p>
@if ($coverImage)
    <figure class="cover-poster"><img @if(isset($coverImage['preview_file_input'])) data-preview-file-input="{{ $coverImage['preview_file_input'] }}" @else src="{{ $coverImage['preview_url'] ?? '' }}" @endif alt="{{ $coverImage['caption'] ?? 'Project poster' }}">@if(filled($coverImage['caption'] ?? null))<figcaption>{{ $coverImage['caption'] }}</figcaption>@endif</figure>
@endif
<h1>{{ $title }}</h1><p>{{ $report->researchers }}</p><p>{{ $date($report->implementation_start) }} – {{ $date($report->implementation_end) }}</p><p>Approved funding: PHP {{ number_format((float) $report->budget, 2) }}</p></section>
<section><h2>II. Project Details</h2><p><strong>Title:</strong> {{ $title }}</p><p><strong>Author/s:</strong><br>{{ $report->researchers }}</p><p>Approved duration: {{ $date($data['approved_start'] ?? null) }} – {{ $date($data['approved_end'] ?? null) }} ({{ $data['approved_duration_months'] ?? '' }} months)</p><p>Actual duration: {{ $date($report->implementation_start) }} – {{ $date($report->implementation_end) }}</p><p>Approved budget: PHP {{ number_format((float) $report->budget, 2) }}<br>Total expenditure: PHP {{ number_format((float) ($data['total_expenditure'] ?? 0), 2) }}<br>Percent utilization: {{ (float) $report->budget > 0 ? number_format((float) ($data['total_expenditure'] ?? 0) / (float) $report->budget * 100, 2).'%' : 'N/A (no approved funding)' }}</p><p>Collaborating agency: {{ ($data['collaborating_agency'] ?? '') ?: 'None' }}</p></section>
<section><h2>III. Summary of Accomplishment</h2><table><thead><tr><th>Objectives</th><th>Target Accomplishments</th><th>Actual Accomplishments</th></tr></thead><tbody>@foreach ($report->accomplishments as $row)<tr><td>{{ $row['objective'] }}</td><td>{{ $row['target'] }}</td><td>{{ $row['actual'] }}</td></tr>@endforeach</tbody></table></section>
@foreach ($sections as [$heading, $text, $section])
    @php
        $blocks = $rich->blocks($text);
        $ordered = 0;
        $positions = [...range(1, max(1, count($blocks))), 0];
    @endphp
    <section><h2>{{ $heading }}</h2>
        @foreach ($positions as $position)
            @if ($position > 0 && isset($blocks[$position - 1]))
                @php($block = $blocks[$position - 1])
                <p>@if ($block['type'] === 'ordered'){{ ++$ordered }}. @elseif ($block['type'] === 'unordered')• @else @php($ordered = 0) @endif @foreach ($block['runs'] as $run)@if ($run['break'])<br>@endif<span style="{{ $run['bold'] ? 'font-weight:bold;' : '' }}{{ $run['italic'] ? 'font-style:italic;' : '' }}{{ $run['underline'] ? 'text-decoration:underline;' : '' }}">{{ $run['text'] }}</span>@endforeach</p>
            @endif
            @php($matches = fn ($item) => ($item['section'] ?? '') === $section && ((int) ($item['after_paragraph'] ?? 0) === $position || ($position === 0 && (int) ($item['after_paragraph'] ?? 0) > count($blocks))))
            @foreach (array_filter($data['tables'] ?? [], $matches) as $table)
                <h3>Table {{ $tableNumber++ }}. {{ $table['caption'] }}</h3><table><thead><tr>@foreach ($table['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@foreach ($table['rows'] as $row)<tr>@foreach ($table['headers'] as $ci => $header)<td>{{ $row[$ci] ?? '' }}</td>@endforeach</tr>@endforeach</tbody></table>
            @endforeach
            @foreach (array_filter($report->photos ?? [], $matches) as $photo)
                <figure><img @if(isset($photo['preview_file_input'])) data-preview-file-input="{{ $photo['preview_file_input'] }}" @else src="{{ $photo['preview_url'] ?? '' }}" @endif alt="{{ $photo['caption'] }}"><figcaption>Figure {{ $figureNumber++ }}. {{ $photo['caption'] }}</figcaption></figure>
            @endforeach
        @endforeach
    </section>
@endforeach
<section class="signatures"><h2>Prepared by:</h2>
    @foreach ($data['authors'] ?? [] as $author)<div class="signature"><p>______________________________</p><p>{{ $author['name'] }}</p><p>{{ $author['role'] }}</p><p>Date signed: {{ $date($author['date_signed'] ?? null) }}</p></div>@endforeach
    @php($lastGroup = '')
    @foreach (\App\Support\TerminalReportRules::SIGNATORY_ROLES as $key => [$group, $role])
        @if ($lastGroup !== $group)<h2>{{ $group }}:</h2>@php($lastGroup = $group)@endif
        <div class="signature"><p>______________________________</p><p>{{ $data['signatories'][$key]['name'] ?? '' }}</p><p>{{ $role }}</p><p>Date signed: {{ $date($data['signatories'][$key]['date_signed'] ?? null) }}</p></div>
    @endforeach
</section></main></body></html>
