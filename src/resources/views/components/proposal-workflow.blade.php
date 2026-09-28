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

<section id="proposal-routing-docket" data-proposal-routing-docket class="proposal-docket mt-4 overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm dark:border-red-950/70 dark:bg-slate-950" aria-labelledby="proposal-routing-heading">
    <header class="grid gap-3 border-b border-red-100 bg-red-50 px-4 py-3 text-[#7A0019] dark:border-red-950 dark:bg-red-950/25 dark:text-red-100 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end sm:px-5">
        <div>
            <p class="text-[0.65rem] font-black tracking-[0.18em] text-[#7A0019]/75 dark:text-red-300">PROPOSAL ROUTING DOCKET</p>
            <h3 id="proposal-routing-heading" class="mt-1 text-base font-black tracking-tight sm:text-lg">Required review sequence</h3>
        </div>
        <dl class="grid grid-cols-2 gap-x-4 text-[11px] sm:text-right">
            <div><dt class="text-[#7A0019]/65 dark:text-red-200/70">Proposal</dt><dd class="font-black tabular-nums">#{{ $topic->id }}</dd></div>
            <div><dt class="text-[#7A0019]/65 dark:text-red-200/70">Version</dt><dd class="font-black tabular-nums">{{ $version?->version_number ?? '—' }}</dd></div>
        </dl>
    </header>

    <div class="overflow-x-auto">
        <ol class="grid min-w-[66rem] grid-cols-6 list-none divide-x divide-red-100 dark:divide-red-950" aria-label="Proposal review route">
            @foreach ($stages as $number => $stage)
            @php
                $isCurrent = $number === $currentStep && ! $released;
                $isComplete = $number < $currentStep || ($number === 6 && $released);
                $isClosed = $topic->status === 'rejected' && $isCurrent;
                $state = $isComplete ? 'Cleared' : ($isClosed ? 'Closed' : ($isCurrent ? 'In progress' : 'Locked'));
            @endphp
            <li @if ($isCurrent) aria-current="step" @endif @class([
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
                    </div>
                </div>
            </li>
            @endforeach
        </ol>
    </div>

    <footer class="grid gap-1 border-t border-red-100 bg-red-50/60 px-4 py-3 dark:border-red-950 dark:bg-red-950/15 sm:grid-cols-[6.5rem_minmax(0,1fr)] sm:items-start sm:px-5">
        <p class="text-[10px] font-black tracking-[0.12em] text-[#7A0019]/70 dark:text-red-300">NEXT ROUTING</p>
        <p class="text-xs font-semibold leading-5 text-slate-700 dark:text-slate-200">{{ $nextAction }}</p>
    </footer>
</section>
