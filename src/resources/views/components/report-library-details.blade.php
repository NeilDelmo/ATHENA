@props(['item'])

<dl class="report-library-details">
    <div><dt>Status</dt><dd @class(['font-medium', 'text-emerald-700 dark:text-emerald-300' => $item['status'] === 'Submitted', 'text-amber-800 dark:text-amber-300' => in_array($item['status'], ['Correction needed', 'Returned for correction'])])>{{ $item['status'] }}</dd></div>
    <div><dt>Reports</dt><dd class="tabular-nums">{{ $item['report_count'] }}</dd></div>
    <div><dt>Last submitted</dt><dd>@if ($item['submitted_at'])<time datetime="{{ $item['submitted_at'] }}">{{ $item['submitted_label'] }}</time>@else<span aria-label="No submission">—</span>@endif</dd></div>
    <div><dt>Submitted by</dt><dd>{{ $item['submitted_by'] ?? '—' }}</dd></div>
</dl>
