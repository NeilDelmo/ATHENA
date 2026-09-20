@props(['topic', 'version' => null])

@php
    $versionFiles = $version?->files ?? collect();
    $gadAssessment = $versionFiles
        ->filter(fn ($file) => $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT)
        ->sortByDesc('id')
        ->first();
    $centralEvaluation = $versionFiles
        ->filter(fn ($file) => $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD
            && ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION
            && filled($file->source_data['narrative_evaluation'] ?? null))
        ->sortByDesc('id')
        ->first();
    $gadPassed = $version?->hasPassingGadAssessment() ?? false;
    $gadNeedsRevision = $gadAssessment && ! $gadPassed;
    $hasFacultyRevision = $version && ($version->version_number > 1 || $version->submission_type === 'revision');
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

    $currentStep = match (true) {
        $released => 6,
        $signingReached => 6,
        $lrecReached || ($gadPassed && $centralEvaluation) => 5,
        $gadPassed => 4,
        $researchHeadCleared => 3,
        $topic->status === 'revision_requested' => 2,
        default => 1,
    };

    $stages = [
        1 => ['label' => 'Research Office screening', 'owner' => 'Research Head', 'detail' => 'Initial review and first set of comments'],
        2 => ['label' => 'Faculty revision', 'owner' => 'Researcher', 'detail' => 'Corrected proposal package and response'],
        3 => ['label' => 'GAD Office review', 'owner' => 'GAD Office', 'detail' => 'Passing assessment required to advance'],
        4 => ['label' => 'Central evaluation', 'owner' => 'Central evaluator', 'detail' => 'Narrative Evaluation recorded'],
        5 => ['label' => 'LREC review', 'owner' => 'LREC', 'detail' => 'Presentation, comments, and clearance'],
        6 => ['label' => 'Signing and release', 'owner' => 'Research Office', 'detail' => 'Signed papers and Notice to Proceed'],
    ];

    $nextAction = match (true) {
        $topic->status === 'rejected' => 'This proposal is closed and cannot advance to another review office.',
        $released => 'The signed papers and Notice to Proceed are available. Project monitoring is open.',
        $topic->status === 'revision_requested' => 'Researcher: address the recorded feedback and submit the corrected proposal package.',
        $gadNeedsRevision => 'Research office: return the proposal to the researcher. Central evaluation remains locked until GAD clearance.',
        $topic->status === \App\Models\TopicProposal::STATUS_LREC_QUEUED => 'Research office: schedule the proposal for its LREC presentation.',
        $topic->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW => 'Research office: record the LREC outcome, comments, or clearance for signing.',
        $signingReached => 'Research office: collect the signed papers and release them with the signed Notice to Proceed.',
        $gadPassed && $centralEvaluation => 'Research office: the ordered prerequisites are complete; route this version to LREC.',
        $gadPassed => 'Research office: send the GAD-cleared version to the central evaluator and record the Narrative Evaluation.',
        $topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW => 'GAD Office: evaluate the Research Head-cleared version before it can be sent to a central evaluator.',
        $topic->status === 'resubmitted' => 'Research Head: review the corrected package again, request another revision if needed, or explicitly clear it for GAD review.',
        default => 'Research Head: complete the initial screening and return comments for the faculty revision.',
    };
@endphp

<section class="proposal-docket mt-5 overflow-hidden border border-slate-300 bg-white shadow-[0_22px_55px_-42px_rgba(15,23,42,0.8)] dark:border-slate-700 dark:bg-slate-950" aria-labelledby="proposal-routing-heading">
    <header class="relative grid gap-4 border-b border-slate-300 bg-slate-950 px-5 py-5 text-white dark:border-slate-700 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:px-6">
        <span class="absolute inset-y-0 left-0 w-1.5 bg-red-700" aria-hidden="true"></span>
        <div>
            <p class="text-[0.68rem] font-bold tracking-[0.18em] text-red-300">PROPOSAL ROUTING DOCKET</p>
            <h3 id="proposal-routing-heading" class="mt-1 text-xl font-bold tracking-tight sm:text-2xl">Required review sequence</h3>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs sm:text-right">
            <div><dt class="text-slate-400">Proposal</dt><dd class="font-bold tabular-nums">#{{ $topic->id }}</dd></div>
            <div><dt class="text-slate-400">Version</dt><dd class="font-bold tabular-nums">{{ $version?->version_number ?? '—' }}</dd></div>
        </dl>
    </header>

    <ol class="grid list-none divide-y divide-slate-200 p-0 dark:divide-slate-800 md:grid-cols-2 md:divide-y-0 xl:grid-cols-3" aria-label="Proposal review route">
        @foreach ($stages as $number => $stage)
            @php
                $isCurrent = $number === $currentStep && ! $released;
                $isComplete = $number < $currentStep || ($number === 6 && $released);
                $isClosed = $topic->status === 'rejected' && $isCurrent;
                $state = $isComplete ? 'Cleared' : ($isClosed ? 'Closed' : ($isCurrent ? 'In progress' : 'Locked'));
            @endphp
            <li @if ($isCurrent) aria-current="step" @endif @class([
                'relative min-h-36 border-slate-200 px-5 py-5 dark:border-slate-800 sm:px-6',
                'md:border-r md:[&:nth-child(2n)]:border-r-0 xl:[&:nth-child(2n)]:border-r xl:[&:nth-child(3n)]:border-r-0',
                'bg-white/90 dark:bg-slate-950/90' => ! $isCurrent,
                'bg-red-50/90 dark:bg-red-950/20' => $isCurrent,
            ])>
                @if ($isCurrent)
                    <span class="absolute inset-y-0 left-0 w-1 bg-red-700" aria-hidden="true"></span>
                @endif
                <div class="flex items-start gap-4">
                    <span @class([
                        'flex h-9 w-9 shrink-0 items-center justify-center border text-sm font-black tabular-nums',
                        'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' => $isComplete,
                        'border-red-700 bg-red-700 text-white shadow-[3px_3px_0_0_rgb(15_23_42)] dark:shadow-[3px_3px_0_0_rgb(248_250_252)]' => $isCurrent,
                        'border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-500' => ! $isComplete && ! $isCurrent,
                    ])>
                        @if ($isComplete)
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                        @else
                            {{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <h4 class="text-base font-bold leading-5 text-slate-950 dark:text-white">{{ $stage['label'] }}</h4>
                            <span @class([
                                'text-[0.65rem] font-bold tracking-wider',
                                'text-emerald-700 dark:text-emerald-400' => $isComplete,
                                'text-red-800 dark:text-red-300' => $isCurrent,
                                'text-slate-400 dark:text-slate-500' => ! $isComplete && ! $isCurrent,
                            ])>{{ strtoupper($state) }}</span>
                        </div>
                        <p class="mt-2 text-sm font-bold text-slate-700 dark:text-slate-200">{{ $stage['owner'] }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $stage['detail'] }}</p>
                    </div>
                </div>
            </li>
        @endforeach
    </ol>

    <footer class="grid gap-2 border-t border-slate-300 bg-stone-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-start sm:px-6">
        <p class="text-xs font-black tracking-[0.12em] text-slate-500 dark:text-slate-400">NEXT ROUTING</p>
        <p class="text-sm font-semibold leading-6 text-slate-800 dark:text-slate-100">{{ $nextAction }}</p>
    </footer>
</section>
