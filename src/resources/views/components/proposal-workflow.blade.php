@props(['topic', 'version' => null])

@php
    $versionFiles = $version?->files ?? collect();
    $gadAssessment = $versionFiles
        ->filter(fn ($file) => $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
        ->sortByDesc('id')
        ->first();
    $coEvaluatorReview = $versionFiles
        ->filter(fn ($file) => $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
            && filled($file->source_data['narrative_evaluation'] ?? null))
        ->sortByDesc('id')
        ->first();
    $gadPassed = $version?->hasPassingGadAssessment() ?? false;
    $gadNeedsRevision = $gadAssessment && ! $gadPassed;
    $researchHeadCleared = $topic->review_stage === 'gad'
        || $topic->review_stage === 'lrec'
        || in_array($topic->status, [
            \App\Models\TopicProposal::STATUS_GAD_REVIEW,
            \App\Models\TopicProposal::STATUS_LREC_QUEUED,
            \App\Models\TopicProposal::STATUS_LREC_REVIEW,
            \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'approved',
        ], true);
    $lrecReached = $topic->review_stage === 'lrec'
        || in_array($topic->status, [
            \App\Models\TopicProposal::STATUS_LREC_QUEUED,
            \App\Models\TopicProposal::STATUS_LREC_REVIEW,
            \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE,
            'approved',
        ], true);
    $signingReached = in_array($topic->status, [\App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE, 'approved'], true);
    $released = $topic->hasIssuedNoticeToProceed();
    $revisionRequested = $topic->status === 'revision_requested';

    $currentStep = match (true) {
        $released => 5,
        $signingReached => 5,
        $revisionRequested && $topic->review_stage === 'lrec' => 4,
        $revisionRequested && $topic->review_stage === 'gad' && $gadPassed => 3,
        $revisionRequested && $topic->review_stage === 'gad' => 2,
        $lrecReached => 4,
        $researchHeadCleared && $gadPassed => 3,
        $researchHeadCleared => 2,
        default => 1,
    };

    $stages = [
        1 => ['label' => 'Research Head review', 'owner' => 'Research Head', 'detail' => 'Review submitted papers and clear them for GAD'],
        2 => ['label' => 'GAD Office review', 'owner' => 'GAD Office', 'detail' => 'Passing assessment required to advance'],
        3 => ['label' => 'Co-evaluator review', 'owner' => 'Co-evaluator', 'detail' => 'Narrative Evaluation recorded'],
        4 => ['label' => 'LREC review', 'owner' => 'LREC', 'detail' => 'Presentation, comments, and clearance'],
        5 => ['label' => 'Signing and release', 'owner' => 'Research Office', 'detail' => 'Signed papers and Notice to Proceed'],
    ];

    $nextAction = match (true) {
        $topic->status === 'rejected' => 'This proposal is closed and cannot advance to another review office.',
        $released => 'The signed papers and Notice to Proceed are available. Project monitoring is open.',
        $revisionRequested => 'Researcher: address the recorded feedback and resubmit the corrected package to the review stage that requested it.',
        $researchHeadCleared && $gadNeedsRevision => 'Request a faculty revision to address the GAD result.',
        $topic->status === \App\Models\TopicProposal::STATUS_LREC_QUEUED => 'Research office: schedule the proposal for its LREC presentation.',
        $topic->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW => 'Research office: record the LREC outcome, comments, or clearance for signing.',
        $signingReached => 'Research office: collect the signed papers and release them with the signed Notice to Proceed.',
        $researchHeadCleared && $gadPassed && $coEvaluatorReview => in_array($coEvaluatorReview->source_data['recommended_action'] ?? null, [\App\Support\InitialScreeningSubmissionOrder::MINOR_REVISION, \App\Support\InitialScreeningSubmissionOrder::MAJOR_REVISION], true)
            ? 'Request a faculty revision to address the co-evaluator’s feedback.'
            : 'Review the co-evaluator’s outcome and clear this package for LREC.',
        $researchHeadCleared && $gadPassed => 'Record the co-evaluator’s completed Initial Screening Form.',
        $topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW => 'GAD Office: evaluate the Research Head-cleared version before it can be sent to a co-evaluator.',
        $topic->status === 'resubmitted' => 'Research Head: review the corrected package again, request another revision if needed, or explicitly clear it for GAD assessment.',
        default => 'Review the submitted papers, request changes if needed, or clear the package for GAD.',
    };
@endphp

<section class="mt-4 border-y border-slate-200 bg-white py-4 dark:border-slate-800 dark:bg-slate-950" aria-labelledby="proposal-routing-heading" data-proposal-route data-current-route-stage="{{ str($stages[$currentStep]['label'])->slug() }}">
    <div class="flex flex-wrap items-center justify-between gap-2 px-1">
        <h3 id="proposal-routing-heading" class="truncate text-sm font-bold text-slate-950 dark:text-white">Review progress</h3>
        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
            Current: <span class="font-black text-red-700 dark:text-red-300">{{ $stages[$currentStep]['label'] }}</span>
        </p>
    </div>

    <div class="mt-3 overflow-x-auto pb-1" data-horizontal-stepper>
        <ol class="flex min-w-[36rem] list-none items-start p-0 sm:min-w-0" aria-label="Proposal review route">
            @foreach ($stages as $number => $stage)
                @php
                    $isCurrent = $number === $currentStep && ! $released;
                    $isComplete = $number < $currentStep || ($number === 5 && $released);
                    $isClosed = $topic->status === 'rejected' && $isCurrent;
                    $state = $isComplete
                        ? 'Cleared'
                        : ($isClosed ? 'Closed' : ($isCurrent ? ($revisionRequested ? 'Revision requested' : 'In progress') : 'Locked'));
                @endphp
                <li @if ($isCurrent) aria-current="step" @endif class="min-w-0 flex-1" data-route-step data-route-state="{{ str($state)->slug() }}">
                    <div class="flex items-center">
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-[0.68rem] font-black tabular-nums',
                            'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' => $isComplete,
                            'border-red-700 bg-red-700 text-white ring-4 ring-red-50 dark:ring-red-950/40' => $isCurrent,
                            'border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500' => ! $isComplete && ! $isCurrent,
                        ])>
                            @if ($isComplete)
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                            @else
                                {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
                            @endif
                        </span>
                        @if (! $loop->last)
                            <span @class([
                                'h-0.5 min-w-4 flex-1',
                                'bg-slate-950 dark:bg-white' => $number < $currentStep || $released,
                                'bg-slate-200 dark:bg-slate-800' => $number >= $currentStep && ! $released,
                            ]) aria-hidden="true"></span>
                        @endif
                    </div>
                    <div class="min-w-0 pr-3 pt-2">
                        <p @class([
                            'text-[0.7rem] font-bold leading-4',
                            'text-slate-950 dark:text-white' => $isComplete,
                            'text-red-800 dark:text-red-300' => $isCurrent,
                            'text-slate-400 dark:text-slate-500' => ! $isComplete && ! $isCurrent,
                        ])>{{ $stage['label'] }}</p>
                        <p @class([
                            'mt-0.5 text-[0.6rem] font-semibold',
                            'text-red-700 dark:text-red-300' => $isCurrent,
                            'text-slate-500 dark:text-slate-400' => ! $isCurrent,
                        ])>{{ $state }}</p>
                        <span class="sr-only">{{ $stage['owner'] }}. {{ $stage['detail'] }}. {{ $state }}.</span>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <footer class="mt-3 flex items-start gap-2 border-t border-slate-100 px-1 pt-3 dark:border-slate-900">
        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-700" aria-hidden="true"></span>
        <p class="text-xs leading-5 text-slate-600 dark:text-slate-300">
            <span class="font-black text-slate-900 dark:text-white">Next:</span> {{ $nextAction }}
            <span class="text-slate-500 dark:text-slate-400">Revision requests return to the review stage that issued them.</span>
        </p>
    </footer>
</section>
