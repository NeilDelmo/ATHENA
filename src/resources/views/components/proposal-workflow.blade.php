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

<section id="proposal-routing-docket" data-proposal-routing-docket data-proposal-route data-current-route-stage="{{ str($stages[$currentStep]['label'])->slug() }}" class="proposal-docket mt-4 overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm dark:border-red-950/70 dark:bg-slate-950" aria-labelledby="proposal-routing-heading">
    <header class="grid gap-3 border-b border-red-100 bg-red-50 px-4 py-3 text-[#7A0019] dark:border-red-950 dark:bg-red-950/25 dark:text-red-100 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:px-5">
        <div>
            <p class="text-[0.65rem] font-black tracking-[0.18em] text-[#7A0019]/75 dark:text-red-300">PROPOSAL REVIEW ROUTE</p>
            <h3 id="proposal-routing-heading" class="mt-1 text-base font-black tracking-tight sm:text-lg">Review progress</h3>
        </div>
        <dl class="grid grid-cols-2 gap-x-4 text-[11px] sm:text-right">
            <div><dt class="text-[#7A0019]/65 dark:text-red-200/70">Proposal</dt><dd class="font-black tabular-nums">#{{ $topic->id }}</dd></div>
            <div><dt class="text-[#7A0019]/65 dark:text-red-200/70">Version</dt><dd class="font-black tabular-nums">{{ $version?->version_number ?? '—' }}</dd></div>
        </dl>
    </header>

    <div class="overflow-x-auto" data-horizontal-stepper>
        <ol class="grid min-w-[55rem] grid-cols-5 list-none divide-x divide-red-100 dark:divide-red-950" aria-label="Proposal review route">
            @foreach ($stages as $number => $stage)
                @php
                    $isCurrent = $number === $currentStep && ! $released;
                    $isComplete = $number < $currentStep || ($number === 5 && $released);
                    $isClosed = $topic->status === 'rejected' && $isCurrent;
                    $state = $isComplete
                        ? 'Cleared'
                        : ($isClosed ? 'Closed' : ($isCurrent ? ($revisionRequested ? 'Revision requested' : 'In progress') : 'Locked'));
                @endphp
                <li @if ($isCurrent) aria-current="step" @endif data-route-step data-route-state="{{ str($state)->slug() }}" @class([
                    'relative min-w-0 px-4 py-4 sm:px-5',
                    'bg-white dark:bg-slate-950' => ! $isCurrent,
                    'bg-red-50/70 dark:bg-red-950/20' => $isCurrent,
                ])>
                    @if ($isCurrent)
                        <span class="absolute inset-x-0 top-0 h-0.5 bg-[#7A0019] dark:bg-red-400" aria-hidden="true"></span>
                    @endif
                    <div class="flex items-start gap-2.5">
                        <span @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-black tabular-nums',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300' => $isComplete,
                            'bg-[#7A0019] text-white shadow-sm dark:bg-red-700' => $isCurrent,
                            'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $isComplete && ! $isCurrent,
                        ])>
                            @if ($isComplete)
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                            @else
                                {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                <h4 class="text-sm font-black leading-5 text-slate-950 dark:text-white">{{ $stage['label'] }}</h4>
                                <span @class([
                                    'text-[0.625rem] font-black tracking-wider',
                                    'text-emerald-700 dark:text-emerald-400' => $isComplete,
                                    'text-[#7A0019] dark:text-red-300' => $isCurrent,
                                    'text-slate-400 dark:text-slate-500' => ! $isComplete && ! $isCurrent,
                                ])>{{ strtoupper($state) }}</span>
                            </div>
                            <p class="mt-1.5 text-xs font-bold text-slate-600 dark:text-slate-300">{{ $stage['owner'] }}</p>
                            <span class="sr-only">{{ $stage['detail'] }}</span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    <footer class="grid gap-1 border-t border-red-100 bg-red-50/60 px-4 py-3 dark:border-red-950 dark:bg-red-950/15 sm:grid-cols-[6.5rem_minmax(0,1fr)] sm:items-start sm:px-5">
        <p class="text-[10px] font-black tracking-[0.12em] text-[#7A0019]/70 dark:text-red-300">NEXT ROUTING</p>
        <p class="text-xs font-semibold leading-5 text-slate-700 dark:text-slate-200">{{ $nextAction }} <span class="text-slate-500 dark:text-slate-400">Revision requests return to the review stage that issued them.</span></p>
    </footer>
</section>
