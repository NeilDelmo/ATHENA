@props(['status' => 'unsubmitted', 'label', 'description' => null, 'compact' => false])

@php
    $displayLabel = $compact ? match ($label) {
        'Reviewed' => 'Accepted',
        'Awaiting Research Head review' => 'Head review',
        'Awaiting Research Office Secretary review' => 'Office review',
        'Overdue' => 'Missing',
        'Targets completed' => 'Completed',
        'Targets still incomplete' => 'Incomplete',
        'Corrections requested', 'Report corrections requested' => 'Corrections',
        'Awaiting review', 'Pending review', 'Submitted', 'Submitted – awaiting review', 'Resubmitted – awaiting review' => 'Pending',
        'No tool on file', 'No report on file' => 'No file',
        'PDF prepared', 'PDF prepared · Overdue' => 'Prepared',
        'Not submitted' => 'Missing',
        default => str_starts_with($label, 'Due ') ? 'Missing' : $label,
    } : $label;
@endphp

<span {{ $attributes->class(['report-review-status']) }} data-status="{{ $status }}" data-overdue="{{ str_contains($label, 'Overdue') ? 'true' : 'false' }}" title="{{ $description ?? $label }}">
    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        @if ($status === 'reviewed')
            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
        @elseif ($status === 'revision_requested')
            <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v6m0 4h.01" />
        @elseif (in_array($status, ['pending', 'submitted']))
            <circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" />
        @else
            <path stroke-linecap="round" d="M6 12h12" />
        @endif
    </svg>
    @if ($displayLabel !== $label)
        <span class="sr-only">{{ $label }}</span><span aria-hidden="true">{{ $displayLabel }}</span>
    @else
        <span>{{ $label }}</span>
    @endif
</span>
