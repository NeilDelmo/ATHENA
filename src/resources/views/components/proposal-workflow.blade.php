@props(['topic'])
@php
    $step = $topic->hasIssuedNoticeToProceed() ? 4 : (in_array($topic->status, ['ready_for_signature', 'approved'], true) ? 3 : ($topic->review_stage === 'lrec' ? 2 : 1));
    $nextAction = match (true) {
        $topic->status === 'rejected' => 'This proposal is closed.',
        $topic->hasIssuedNoticeToProceed() => 'Faculty can download the signed papers and Notice to Proceed. Project monitoring is open.',
        $topic->status === 'revision_requested' => 'Faculty: address the comments, complete the responses, and resubmit for the same review stage.',
        $topic->status === 'lrec_queued' => 'Await the LREC presentation schedule from the research office.',
        $step === 3 => 'Research office: collect signed papers and the signed Notice to Proceed, then release them together.',
        $step === 2 => 'Research office: record the LREC outcome and request revisions or clear the proposal for signing.',
        default => 'Research Head: complete the review with the Co-evaluator, then send the cleared proposal to LREC.',
    };
@endphp
<div class="mt-4 border-t border-gray-200 pt-4 dark:border-slate-700">
    <ol class="m-0 grid list-none p-0 sm:grid-cols-4" aria-label="Proposal progress">
        @foreach ([1 => 'Initial review', 2 => 'LREC', 3 => 'Signing and notice', 4 => 'Released to faculty'] as $number => $label)
            @php
                $isCurrent = $step === $number;
                $isComplete = $number < $step || ($number === 4 && $topic->hasIssuedNoticeToProceed());
                $stageState = $isComplete ? 'Completed' : ($isCurrent ? ($topic->status === 'rejected' ? 'Closed' : 'Current stage') : ($topic->status === 'rejected' ? 'Not reached' : 'Upcoming'));
            @endphp
            <li class="relative flex gap-3 pb-5 last:pb-0 sm:flex-col sm:gap-2 sm:pb-0 sm:pr-4" @if ($isCurrent) aria-current="step" @endif>
                @if (! $loop->last)
                    <span aria-hidden="true" @class(['absolute left-4 top-8 h-full w-px sm:left-8 sm:top-4 sm:h-px sm:w-[calc(100%-2rem)]', 'bg-emerald-500' => $number < $step, 'bg-gray-200 dark:bg-slate-700' => $number >= $step])></span>
                @endif
                <span aria-hidden="true" @class(['relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold', 'bg-emerald-600 text-white' => $isComplete, 'bg-red-700 text-white ring-4 ring-red-50 dark:ring-red-950' => $isCurrent && ! $isComplete, 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-400' => ! $isCurrent && ! $isComplete])>
                    @if ($isComplete)
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                    @else
                        {{ $number }}
                    @endif
                </span>
                <div class="relative">
                    <p @class(['text-sm font-semibold', 'text-gray-900 dark:text-white' => $isCurrent || $isComplete, 'text-gray-500 dark:text-slate-400' => ! $isCurrent && ! $isComplete])>{{ $label }}</p>
                    <p @class(['mt-0.5 text-xs', 'text-emerald-700 dark:text-emerald-400' => $isComplete, 'text-red-700 dark:text-red-300' => $isCurrent && ! $isComplete, 'text-gray-400 dark:text-slate-500' => ! $isCurrent && ! $isComplete])>{{ $stageState }}</p>
                </div>
            </li>
        @endforeach
    </ol>
    <p class="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-slate-800 dark:text-slate-300">{{ $nextAction }}</p>
</div>
