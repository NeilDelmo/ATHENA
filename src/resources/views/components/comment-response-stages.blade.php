@props(['stages' => []])

@php
    $knownStages = array_intersect(array_keys(\App\Services\CommentResponseFeedback::STAGE_LABELS), $stages);
    $levels = [
        ['label' => 'Initial Screening', 'active' => count(array_diff($knownStages, ['lrec'])) > 0],
        ['label' => 'Local Research Evaluation', 'active' => in_array('lrec', $knownStages, true)],
    ];
@endphp

<section {{ $attributes->class(['rounded-xl border border-gray-200 bg-white p-4 text-black dark:border-gray-700 dark:bg-gray-950 dark:text-white']) }} data-comment-response-stages aria-label="Level of evaluation done">
    <p class="mb-3 text-xs font-bold uppercase tracking-wide">LEVEL OF EVALUATION DONE:</p>
    <ul class="space-y-1">
        @foreach ($levels as $level)
            <li data-evaluation-level="{{ $loop->index }}" data-stage-active="{{ $level['active'] ? 'true' : 'false' }}" class="flex items-center gap-3 text-sm">
                <span @class(['inline-flex h-4 w-4 shrink-0 border border-black', 'bg-black' => $level['active'], 'bg-white' => ! $level['active']]) aria-hidden="true"></span>
                <span>{{ $level['label'] }}<span class="sr-only">{{ $level['active'] ? ' — Selected' : ' — Not selected' }}</span></span>
            </li>
        @endforeach
    </ul>
</section>
