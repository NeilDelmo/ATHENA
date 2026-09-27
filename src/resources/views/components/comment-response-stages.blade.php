@props(['stages' => []])

<section {{ $attributes->class(['rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950/50']) }} data-comment-response-stages aria-label="Comment Response feedback stages">
    <p class="mb-3 text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">Feedback stage</p>
    <ol class="grid gap-2 sm:grid-cols-4">
        @foreach (\App\Services\CommentResponseFeedback::STAGE_LABELS as $stage => $label)
            @php($active = in_array($stage, $stages, true))
            <li data-feedback-stage="{{ $stage }}" data-stage-active="{{ $active ? 'true' : 'false' }}" class="flex items-center gap-2 rounded-lg border px-3 py-2.5 text-sm {{ $active ? 'border-red-700 bg-red-100 font-bold text-red-900 dark:border-red-400 dark:bg-red-950 dark:text-red-100' : 'border-gray-200 bg-white text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400' }}">
                <span class="shrink-0" aria-hidden="true">{{ $active ? '✓' : $loop->iteration }}</span>
                <span>{{ $label }}@if ($active)<span class="sr-only"> — Feedback included</span>@endif</span>
            </li>
        @endforeach
    </ol>
    <p class="mt-3 text-xs leading-5 text-gray-600 dark:text-gray-400">Shaded stages identify the comments in this form. Research Head, GAD and Co-Evaluator feedback are grouped under Initial Screening on the official paper.</p>
</section>
