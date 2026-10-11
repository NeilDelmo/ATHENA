@props(['status' => 'unsubmitted', 'label', 'description' => null, 'compact' => false])

@php
    $displayLabel = $compact ? match ($label) {
        'Corrections requested', 'Report corrections requested' => 'Corrections',
        'Awaiting review', 'Pending review', 'Submitted', 'Submitted – awaiting review', 'Resubmitted – awaiting review' => 'Pending',
        'No tool on file', 'No report on file' => 'No file',
        'PDF prepared' => 'Prepared',
        'Not submitted' => 'Missing',
        default => $label,
    } : $label;
@endphp

<span {{ $attributes->class(['report-review-status']) }} data-status="{{ $status }}" title="{{ $description ?? $label }}">
    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
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
