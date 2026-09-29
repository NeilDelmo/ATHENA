@props(['topic', 'workspace', 'returnToReview' => false])

@php
    $latestVersion = $workspace['latestVersion'];
    $facultySubmittedFiles = $workspace['facultySubmittedFiles'];
    $headUploadedFiles = $workspace['headUploadedFiles'];
    $headUploadsBySource = $workspace['headUploadsBySource'];
    $availableFileIds = $workspace['availableFileIds'];
    $viewableFileIds = $workspace['viewableFileIds'];
    $requiredSignatureFiles = $workspace['requiredSignatureFiles'];
    $signedSourceFileIds = $workspace['signedSourceFileIds'];
    $missingSignatureFiles = $workspace['missingSignatureFiles'];
    $signaturesComplete = $workspace['signaturesComplete'];
    $gadAssessment = $workspace['gadAssessment'] ?? null;
    $gadPassed = $workspace['gadPassed'] ?? false;
    $coEvaluatorEvaluation = $workspace['coEvaluatorEvaluation'] ?? null;
    $isSigningStage = $topic->status === \App\Models\TopicProposal::STATUS_READY_FOR_SIGNATURE;
    $canUploadEvaluation = $topic->status === \App\Models\TopicProposal::STATUS_GAD_REVIEW;
    $gadChecklistFile = $facultySubmittedFiles->firstWhere('document_type', \App\Models\ProposalVersionFile::TYPE_GAD_CHECKLIST);
    $initialScreeningFile = $facultySubmittedFiles->firstWhere('document_type', \App\Models\ProposalVersionFile::TYPE_INITIAL_SCREENING_FORM);
    $gadAssessmentAvailable = $gadAssessment && $availableFileIds->contains($gadAssessment->id);
    $gadAssessmentViewable = $gadAssessment && $viewableFileIds->contains($gadAssessment->id);
    $coEvaluatorEvaluationAvailable = $coEvaluatorEvaluation && $availableFileIds->contains($coEvaluatorEvaluation->id);
    $coEvaluatorEvaluationViewable = $coEvaluatorEvaluation && $viewableFileIds->contains($coEvaluatorEvaluation->id);
    $coEvaluatorRecommendedAction = $coEvaluatorEvaluation?->source_data['recommended_action'] ?? null;
    $coEvaluatorRecommendedActionLabel = \App\Support\InitialScreeningSubmissionOrder::recommendationLabel($coEvaluatorRecommendedAction);
    $coEvaluatorReviewComplete = $coEvaluatorEvaluation && in_array($coEvaluatorRecommendedAction, [
        \App\Support\InitialScreeningSubmissionOrder::FOR_ENDORSEMENT,
        \App\Support\InitialScreeningSubmissionOrder::MINOR_REVISION,
        \App\Support\InitialScreeningSubmissionOrder::MAJOR_REVISION,
    ], true);
    $expandGadReview = ! $gadPassed || ($errors->headUpload->any() && old('purpose') === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT);
    $expandCoEvaluatorReview = ! $coEvaluatorReviewComplete || ($errors->headUpload->any() && old('purpose') === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION);
    $gadScore = $gadAssessment?->source_data['gad_score'] ?? null;
    $gadScoreEntryMethod = $gadAssessment?->source_data['gad_score_entry_method'] ?? null;
    $gadRating = $gadAssessment?->source_data['gad_rating'] ?? null;
    $gadInterpretation = $gadAssessment?->source_data['gad_interpretation'] ?? null;
    $gadOutcome = $gadAssessment?->source_data['gad_outcome'] ?? null;
    $gadSignatureDetected = ($gadAssessment?->source_data['gad_signature_detected'] ?? false) === true;
    $gadSignatureConfirmed = ($gadAssessment?->source_data['gad_signature_confirmed'] ?? false) === true;
    $gadOutcomeLabel = match ($gadOutcome) {
        'returned' => 'Return proposal',
        'conditional_pass' => 'Conditional pass',
        'passed' => 'Pass',
        'commended' => 'Commended',
        default => $gadOutcome ? str($gadOutcome)->replace('_', ' ')->title()->toString() : null,
    };
    $gadOutcomeClass = match ($gadOutcome) {
        'returned' => 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-200',
        'conditional_pass' => 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-100',
        default => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
    };
    $gadScorePassed = in_array($gadOutcome, ['passed', 'commended'], true)
        || ($gadOutcome === null && is_numeric($gadScore) && (float) $gadScore >= 8);
    $gadNeedsRevision = $gadAssessment && ! $gadScorePassed;
    $gadNeedsSignatureConfirmation = $gadAssessment && $gadScorePassed && ! $gadSignatureConfirmed;
    $activeSignedCopiesBySource = $headUploadedFiles
        ->filter(fn ($file) => ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED && ! $file->isSuperseded())
        ->groupBy('source_version_file_id');
    $supersededSignedCopiesBySource = $headUploadedFiles
        ->filter(fn ($file) => ($file->source_data['purpose'] ?? null) === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED && $file->isSuperseded())
        ->groupBy('source_version_file_id');
@endphp

<div data-research-head-file-workspace {{ $attributes->merge(['class' => 'space-y-5']) }}>

    @if ($errors->headUpload->any())
        <div role="alert" class="rounded-2xl border border-red-300 bg-red-50 p-5 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
            <p class="font-black">The file could not be uploaded.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->headUpload->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($latestVersion && $canUploadEvaluation)
        <div id="initial-review-workflow" data-current-review-controls="{{ $gadPassed ? 'co-evaluator' : 'gad' }}" class="space-y-5 scroll-mt-6">
            <section id="gad-office-review" data-gad-review-card data-initially-expanded="{{ $expandGadReview ? 'true' : 'false' }}" x-data="{ expanded: @js($expandGadReview) }" aria-labelledby="gad-review-heading" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950 sm:p-6">
                <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded" aria-controls="gad-review-content" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">
                    <span class="flex flex-wrap items-center gap-3">
                        <span id="gad-review-heading" class="text-lg font-bold text-gray-950 dark:text-white">GAD Office review</span>
                        @if ($gadPassed)
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"><svg class="h-4 w-4 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>Completed · {{ $gadScore !== null ? number_format((float) $gadScore, 2) : '—' }}/20</span>
                        @endif
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div id="gad-review-content" x-show="expanded" @if (! $expandGadReview) x-cloak @endif>
                @if ($gadNeedsSignatureConfirmation || $gadNeedsRevision)
                    <p class="mt-2 text-sm font-semibold text-amber-800 dark:text-amber-200">{{ $gadNeedsRevision ? 'Return for revision' : 'Signature check required' }}</p>
                @endif
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $gadPassed ? 'The signed checklist and passing score are recorded. Continue with co-evaluator review below.' : 'Upload the completed, signed GAD Checklist to read its score.' }}</p>
            @if ($gadChecklistFile)
                <div x-data="{ replacing: @js(! $gadAssessment) }" class="mt-4">
                    @if ($gadAssessment)
                        <div data-gad-score-summary role="status" class="grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60 md:grid-cols-[9rem_minmax(0,1fr)]">
                            <div>
                                <p class="text-sm font-bold text-gray-600 dark:text-gray-300">Detected total</p>
                                <p class="mt-1 text-3xl font-bold text-gray-950 dark:text-white">
                                    {{ $gadScore !== null ? number_format((float) $gadScore, 2) : '—' }}
                                    <span class="text-base font-semibold text-gray-500 dark:text-gray-400">/ 20</span>
                                </p>
                                @if ($gadScoreEntryMethod === 'manual')
                                    <p class="mt-1 text-xs font-bold text-amber-700 dark:text-amber-300">Confirmed from scanned copy</p>
                                @endif
                            </div>
                            <div class="min-w-0 sm:border-l sm:border-gray-200 sm:pl-4 dark:sm:border-gray-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-base font-black text-gray-950 dark:text-white">{{ $gadRating ?: 'GAD assessment recorded' }}</p>
                                    @if ($gadOutcomeLabel)
                                        <span class="rounded-full px-2.5 py-1 text-sm font-bold {{ $gadOutcomeClass }}">{{ $gadOutcomeLabel }}</span>
                                    @endif
                                </div>
                                @if ($gadInterpretation)
                                    <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $gadInterpretation }}</p>
                                @endif
                                <div class="mt-3 flex items-start gap-2 rounded-lg border {{ $gadSignatureConfirmed ? 'border-gray-200 bg-white text-gray-800 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200' : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100' }} px-3 py-2">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $gadSignatureConfirmed ? 'text-red-700 dark:text-red-300' : '' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4 4L19 7" /></svg>
                                    <div>
                                        <p class="text-sm font-black">GAD verifier signature</p>
                                        <p class="text-sm leading-5">
                                            @if ($gadSignatureConfirmed && $gadSignatureDetected)
                                                Signature evidence detected in the file and confirmed after preview.
                                            @elseif ($gadSignatureConfirmed)
                                                Confirmed by the Research Head after preview.
                                            @elseif ($gadSignatureDetected)
                                                Signature evidence detected, but Research Head confirmation is still required.
                                            @else
                                                Signature confirmation is missing.
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $gadAssessment->original_filename }}</p>
                            </div>
                            <div class="flex flex-wrap justify-end gap-2 md:col-span-2">
                                @if ($gadAssessmentViewable)
                                    <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $gadAssessment]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">View</a>
                                @endif
                                @if ($gadAssessmentAvailable)
                                    <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $gadAssessment]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">Download</a>
                                @endif
                                @if ($canUploadEvaluation)
                                    <button type="button" @click="replacing = ! replacing" :aria-expanded="replacing" aria-controls="gad-assessment-replacement-{{ $topic->id }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900" x-text="replacing ? 'Cancel' : 'Replace'">Replace</button>
                                @endif
                            </div>
                        </div>
                        @if ($gadNeedsRevision)
                            <div data-gad-revision-required role="alert" class="mt-3 border-l-4 border-amber-600 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                                <p class="font-black">This result cannot proceed to co-evaluator review.</p>
                                <p class="mt-1">Request revisions from the researcher. Upload the corrected version’s GAD assessment before routing it to the co-evaluator.</p>
                            </div>
                        @elseif ($gadNeedsSignatureConfirmation)
                            <div data-gad-signature-required role="alert" class="mt-3 border-l-4 border-amber-600 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100">
                                <p class="font-black">A passing score is not enough to unlock co-evaluator review.</p>
                            <p class="mt-1">Confirm the GAD verifier’s signature before co-evaluator review.</p>
                            </div>
                        @endif
                    @endif

                    @if ($canUploadEvaluation)
                        <form id="gad-assessment-replacement-{{ $topic->id }}" x-show="replacing" @if ($gadAssessment) x-cloak x-transition.opacity @endif action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="{{ $gadAssessment ? 'mt-3' : '' }} grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                            @csrf
                            @if ($returnToReview)<input type="hidden" name="return_to_review" value="1">@endif
                            <input type="hidden" name="source_file_id" value="{{ $gadChecklistFile->id }}">
                            <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT }}">
                            <div class="min-w-0 space-y-3">
                            <div data-gad-checklist-dropzone x-data="fileDropzone({ accept: '.pdf,.docx', maxBytes: 26214400, multiple: false })" @paste="paste($event)" class="min-w-0">
                                <label for="gad_assessment_{{ $topic->id }}" class="sr-only">Completed GAD assessment</label>
                                <label for="gad_assessment_{{ $topic->id }}" data-file-dropzone tabindex="0" @dragenter.prevent="dragEnter()" @dragover.prevent="dragging = true" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)" @keydown.enter.prevent="browse()" @keydown.space.prevent="browse()" :class="dragging ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950'" class="flex min-h-24 cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed px-4 py-3 text-left transition hover:border-red-400 hover:bg-red-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:hover:bg-red-950/20 dark:focus-visible:ring-offset-gray-950">
                                    <input id="gad_assessment_{{ $topic->id }}" x-ref="input" name="review_file" type="file" accept=".pdf,.docx" required @change="syncFiles(true)" class="sr-only">
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M5.25 15.75v2.25A2.25 2.25 0 0 0 7.5 20.25h9a2.25 2.25 0 0 0 2.25-2.25v-2.25" /></svg>
                                    </span>
                                    <span class="min-w-0">
                                        <span x-show="files.length === 0" class="block text-base font-black text-gray-900 dark:text-white">Drop completed GAD checklist here</span>
                                        <span x-show="files.length > 0" x-cloak class="block truncate text-base font-black text-red-700 dark:text-red-300" x-text="files[0]?.name"></span>
                                        <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400" x-text="files.length ? formatSize(files[0].size) + ' · ready to upload' : 'PDF (including phone scans) or DOCX · up to 25 MB'"></span>
                                    </span>
                                </label>
                                <p x-show="message" x-cloak role="alert" class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300" x-text="message"></p>
                            </div>
                            <label for="gad_score_{{ $topic->id }}" class="block rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm leading-6 text-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200">
                                <strong class="block text-gray-950 dark:text-white">Enter score only if automatic reading fails</strong>
                                <span class="block">Leave this blank first. If ATHENA cannot read the score, enter the final score printed on the completed checklist and upload it again.</span>
                                <span class="mt-2 flex items-center gap-2">
                                    <input id="gad_score_{{ $topic->id }}" name="gad_score" type="number" min="0" max="20" step="0.01" inputmode="decimal" value="{{ old('gad_score') }}" class="block min-h-11 w-32 rounded-xl border-gray-300 text-base focus:border-red-700 focus:ring-red-700 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <span class="font-bold text-gray-500 dark:text-gray-400">/ 20</span>
                                </span>
                            </label>
                            <label for="gad_signature_confirmed_{{ $topic->id }}" class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm leading-6 text-gray-700 transition hover:border-red-300 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-red-900">
                                <input id="gad_signature_confirmed_{{ $topic->id }}" name="gad_signature_confirmed" type="checkbox" value="1" required @checked(old('gad_signature_confirmed')) class="mt-1 h-4 w-4 rounded border-gray-300 text-red-700 focus:ring-red-700 dark:border-gray-600 dark:bg-gray-900">
                                <span><strong class="block text-gray-950 dark:text-white">Confirm the verifier’s signature</strong>I previewed the completed GAD Checklist and confirm that a signature is present in the “Checked and verified by” section.</span>
                            </label>
                            </div>
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950 lg:w-auto">Upload &amp; read score</button>
                        </form>
                    @endif
                </div>
            @else
                <p role="alert" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">The submitted package does not include a GAD checklist.</p>
            @endif
                </div>
            </section>
            @if ($gadPassed)
                <section id="co-evaluator-review" data-co-evaluator-review-card data-initially-expanded="{{ $expandCoEvaluatorReview ? 'true' : 'false' }}" x-data="{ expanded: @js($expandCoEvaluatorReview) }" aria-labelledby="co-evaluator-review-heading" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950 sm:p-6">
                <button type="button" @click="expanded = ! expanded" :aria-expanded="expanded" aria-controls="co-evaluator-review-content" class="flex min-h-11 w-full items-center justify-between gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">
                    <span class="flex flex-wrap items-center gap-3">
                        <span id="co-evaluator-review-heading" class="text-lg font-bold text-gray-950 dark:text-white">Co-evaluator review</span>
                        @if ($coEvaluatorReviewComplete)
                            <span class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">Recorded · {{ $coEvaluatorRecommendedActionLabel }}</span>
                        @endif
                    </span>
                    <svg class="h-5 w-5 shrink-0 text-gray-500 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </button>
                <div id="co-evaluator-review-content" x-show="expanded" @if (! $expandCoEvaluatorReview) x-cloak @endif>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $coEvaluatorReviewComplete ? 'The completed Initial Screening Form and recommendation are recorded.' : 'Enter the evaluator’s name and recommendation, then attach their completed Initial Screening Form. Review outcome choices appear once this step is recorded.' }}</p>
                @if ($initialScreeningFile)
                    <div x-data="{ replacing: @js(! $coEvaluatorReviewComplete) }" class="mt-4">
                        @if ($coEvaluatorEvaluation)
                            <div data-co-evaluator-evaluation-summary role="status" class="grid gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-900/60 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-base font-black text-gray-950 dark:text-white">Narrative Evaluation extracted</p>
                                        <span class="rounded-full bg-white px-2.5 py-1 text-sm font-bold text-gray-700 shadow-sm dark:bg-gray-950 dark:text-gray-200">{{ $coEvaluatorEvaluation->source_data['co_evaluator_name'] ?? 'Co-evaluator' }}</span>
                                        @if ($coEvaluatorRecommendedActionLabel)
                                            <span class="rounded-full border border-red-200 bg-white px-2.5 py-1 text-sm font-bold text-red-800 dark:border-red-900 dark:bg-gray-950 dark:text-red-300">{{ $coEvaluatorRecommendedActionLabel }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 truncate text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $coEvaluatorEvaluation->original_filename }}</p>
                                    @if ($coEvaluatorEvaluation->source_data['narrative_evaluation'] ?? null)
                                        <p class="mt-2 max-h-24 overflow-y-auto whitespace-pre-line text-sm leading-6 text-gray-800 dark:text-gray-200">{{ $coEvaluatorEvaluation->source_data['narrative_evaluation'] }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($coEvaluatorEvaluationViewable)
                                        <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $coEvaluatorEvaluation]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">View</a>
                                    @endif
                                    @if ($coEvaluatorEvaluationAvailable)
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $coEvaluatorEvaluation]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900">Download</a>
                                    @endif
                                    @if ($canUploadEvaluation)
                                        <button type="button" @click="replacing = ! replacing" :aria-expanded="replacing" aria-controls="co-evaluator-upload-{{ $topic->id }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 sm:flex-none dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-900" x-text="replacing ? 'Cancel' : 'Upload another'">Upload another</button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($canUploadEvaluation)
                            <form id="co-evaluator-upload-{{ $topic->id }}" x-show="replacing" @if ($coEvaluatorEvaluation) x-cloak x-transition.opacity @endif action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" data-co-evaluator-screening-panel="true" class="{{ $coEvaluatorEvaluation ? 'mt-5' : '' }} space-y-5">
                                @csrf
                                @if ($returnToReview)<input type="hidden" name="return_to_review" value="1">@endif
                                <input type="hidden" name="source_file_id" value="{{ $initialScreeningFile->id }}">
                                <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION }}">
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)] lg:items-start" data-co-evaluator-details>
                                    <label for="co_evaluator_name_{{ $topic->id }}" class="block text-sm font-bold text-gray-800 dark:text-gray-100">
                                        Co-evaluator name
                                        <input id="co_evaluator_name_{{ $topic->id }}" name="co_evaluator_name" type="text" maxlength="160" autocomplete="off" required value="{{ old('co_evaluator_name') }}" placeholder="Full name" class="mt-2 block min-h-12 w-full rounded-xl border-gray-300 text-base focus:border-red-700 focus:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                    </label>
                                    <fieldset class="min-w-0" aria-describedby="recommended-action-help-{{ $topic->id }}">
                                        <legend class="text-sm font-bold text-gray-800 dark:text-gray-100">Recommendation on the completed form</legend>
                                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 transition focus-within:ring-2 focus-within:ring-red-700 has-[:checked]:border-red-700 has-[:checked]:bg-red-50 dark:border-gray-700 dark:bg-gray-950 dark:has-[:checked]:border-red-400 dark:has-[:checked]:bg-red-950/30">
                                                <input type="radio" name="recommended_action" value="{{ \App\Support\InitialScreeningSubmissionOrder::FOR_ENDORSEMENT }}" required @checked(old('recommended_action') === \App\Support\InitialScreeningSubmissionOrder::FOR_ENDORSEMENT) class="mt-0.5 shrink-0 border-gray-400 text-red-700 focus:ring-red-700 dark:border-gray-600">
                                                <span><span class="block text-sm font-bold text-gray-950 dark:text-white">For Endorsement</span><span class="block text-xs text-gray-600 dark:text-gray-300">Ready for LREC</span></span>
                                            </label>
                                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 transition focus-within:ring-2 focus-within:ring-red-700 has-[:checked]:border-red-700 has-[:checked]:bg-red-50 dark:border-gray-700 dark:bg-gray-950 dark:has-[:checked]:border-red-400 dark:has-[:checked]:bg-red-950/30">
                                                <input type="radio" name="recommended_action" value="{{ \App\Support\InitialScreeningSubmissionOrder::MINOR_REVISION }}" required @checked(old('recommended_action') === \App\Support\InitialScreeningSubmissionOrder::MINOR_REVISION) class="mt-0.5 shrink-0 border-gray-400 text-red-700 focus:ring-red-700 dark:border-gray-600">
                                                <span><span class="block text-sm font-bold text-gray-950 dark:text-white">Minor Revision</span><span class="block text-xs text-gray-600 dark:text-gray-300">Limited corrections</span></span>
                                            </label>
                                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2.5 transition focus-within:ring-2 focus-within:ring-red-700 has-[:checked]:border-red-700 has-[:checked]:bg-red-50 dark:border-gray-700 dark:bg-gray-950 dark:has-[:checked]:border-red-400 dark:has-[:checked]:bg-red-950/30">
                                                <input type="radio" name="recommended_action" value="{{ \App\Support\InitialScreeningSubmissionOrder::MAJOR_REVISION }}" required @checked(old('recommended_action') === \App\Support\InitialScreeningSubmissionOrder::MAJOR_REVISION) class="mt-0.5 shrink-0 border-gray-400 text-red-700 focus:ring-red-700 dark:border-gray-600">
                                                <span><span class="block text-sm font-bold text-gray-950 dark:text-white">Major Revision</span><span class="block text-xs text-gray-600 dark:text-gray-300">Substantial changes</span></span>
                                            </label>
                                        </div>
                                        <p id="recommended-action-help-{{ $topic->id }}" class="mt-2 text-xs leading-5 text-gray-600 dark:text-gray-300">Match the co-evaluator’s completed form. The Research Head sends revision requests separately.</p>
                                    </fieldset>
                                </div>
                                <div data-co-evaluator-dropzone x-data="fileDropzone({ accept: '.pdf,.docx', maxBytes: 26214400, multiple: false })" @paste="paste($event)" class="min-w-0">
                                    <label for="co_evaluator_file_{{ $topic->id }}" class="sr-only">Completed Initial Screening Form</label>
                                    <label for="co_evaluator_file_{{ $topic->id }}" data-file-dropzone tabindex="0" @dragenter.prevent="dragEnter()" @dragover.prevent="dragging = true" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)" @keydown.enter.prevent="browse()" @keydown.space.prevent="browse()" :class="dragging ? 'border-red-500 bg-red-50 dark:bg-red-950/30' : 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-950'" class="flex min-h-20 cursor-pointer items-center gap-3 rounded-xl border-2 border-dashed px-4 py-3 text-left transition hover:border-red-400 hover:bg-red-50/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:hover:bg-red-950/20 dark:focus-visible:ring-offset-gray-950">
                                        <input id="co_evaluator_file_{{ $topic->id }}" x-ref="input" name="review_file" type="file" accept=".pdf,.docx" required @change="syncFiles(true)" class="sr-only">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M5.25 15.75v2.25A2.25 2.25 0 0 0 7.5 20.25h9a2.25 2.25 0 0 0 2.25-2.25v-2.25" /></svg>
                                        </span>
                                        <span class="min-w-0">
                                            <span x-show="files.length === 0" class="block text-base font-black text-gray-900 dark:text-white">Drop Initial Screening Form here</span>
                                            <span x-show="files.length > 0" x-cloak class="block truncate text-base font-black text-red-700 dark:text-red-300" x-text="files[0]?.name"></span>
                                            <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400" x-text="files.length ? formatSize(files[0].size) + ' · ready to upload' : 'PDF or DOCX · up to 25 MB · or click to browse'"></span>
                                        </span>
                                    </label>
                                    <p x-show="message" x-cloak role="alert" class="mt-2 text-sm font-semibold text-red-700 dark:text-red-300" x-text="message"></p>
                                </div>
                                <div class="flex justify-end border-t border-gray-200 pt-4 dark:border-gray-800">
                                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-base font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950 sm:w-auto">Record evaluation</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @else
                    <p role="alert" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">The submitted package does not include an Initial Screening Form.</p>
                @endif
                </div>
                </section>
            @endif
        </div>
    @endif

    @if ($requiredSignatureFiles->isNotEmpty() && ($isSigningStage || $topic->status === 'approved'))
        <section aria-labelledby="signature-progress-heading" class="rounded-2xl border border-red-300 bg-white p-5 shadow-sm dark:border-red-900 dark:bg-gray-950 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-bold text-red-700 dark:text-red-300">Final signing</p>
                    <h3 id="signature-progress-heading" class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                        {{ $topic->status === 'approved' ? 'Released signed copies' : 'Upload the required signed PDFs' }}
                    </h3>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-gray-700 dark:text-gray-200">Signed PDFs are required for all five listed proposal papers. Attachment C and Estimated Expense Breakdown stay in the package without signatures.</p>
                </div>
                <span class="inline-flex w-fit rounded-full {{ $missingSignatureFiles->isEmpty() ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'border border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }} px-3 py-1.5 text-sm font-black">
                    {{ $requiredSignatureFiles->count() - $missingSignatureFiles->count() }}/{{ $requiredSignatureFiles->count() }} uploaded
                </span>
            </div>

            <div class="mt-5 divide-y divide-gray-200 overflow-hidden rounded-2xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($requiredSignatureFiles as $requiredSignatureFile)
                    @php
                        $hasSignedCopy = $signedSourceFileIds->contains($requiredSignatureFile->id);
                        $activeSignedCopy = $activeSignedCopiesBySource->get($requiredSignatureFile->id, collect())->first();
                        $supersededSignedCopies = $supersededSignedCopiesBySource->get($requiredSignatureFile->id, collect());
                    @endphp
                    <article x-data="{ previewOpen: false }" class="grid gap-4 bg-white p-4 dark:bg-gray-950 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.9fr)] lg:items-start">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-lg font-bold text-gray-950 dark:text-white">{{ $requiredSignatureFile->label() }}</h4>
                                <span class="rounded-full {{ $hasSignedCopy ? 'bg-emerald-700 text-white dark:bg-emerald-500 dark:text-emerald-950' : 'border border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200' }} px-2.5 py-1 text-sm font-bold">
                                    {{ $hasSignedCopy ? 'Signed PDF uploaded' : 'Waiting for signed PDF' }}
                                </span>
                            </div>
                            <p class="mt-2 text-sm font-semibold text-gray-500 dark:text-gray-400">Required faculty paper</p>
                            <p class="mt-2 break-all text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $requiredSignatureFile->original_filename }}</p>
                        </div>

                        <div class="space-y-3">
                            @if ($activeSignedCopy)
                                <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/70 dark:bg-emerald-950/25" aria-label="Uploaded signed PDF">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm dark:bg-emerald-500 dark:text-emerald-950" aria-hidden="true">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4.25 4.25L19 6.5" /></svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-black text-emerald-950 dark:text-emerald-100">Uploaded signed copy</p>
                                            <p class="mt-1 break-all text-sm font-semibold text-emerald-900 dark:text-emerald-200">{{ $activeSignedCopy->original_filename }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                        <div class="rounded-xl bg-white/80 px-3 py-2.5 dark:bg-gray-950/70">
                                            <p class="font-bold text-emerald-800 dark:text-emerald-300">File size</p>
                                            <p class="mt-1 font-black text-emerald-950 dark:text-emerald-100">{{ $activeSignedCopy->file_size ? \Illuminate\Support\Number::fileSize($activeSignedCopy->file_size) : 'Size unavailable' }}</p>
                                        </div>
                                        <div class="rounded-xl bg-white/80 px-3 py-2.5 dark:bg-gray-950/70">
                                            <p class="font-bold text-emerald-800 dark:text-emerald-300">Uploaded</p>
                                            <p class="mt-1 font-black text-emerald-950 dark:text-emerald-100">{{ $activeSignedCopy->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button type="button" @click="previewOpen = ! previewOpen" :aria-expanded="previewOpen" class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-300 bg-white px-3 py-2.5 text-sm font-black text-emerald-900 transition hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 dark:border-emerald-800 dark:bg-gray-950 dark:text-emerald-200 dark:hover:bg-emerald-950 dark:focus:ring-emerald-400 dark:focus:ring-offset-gray-950">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                                            <span x-text="previewOpen ? 'Close preview' : 'Preview signed PDF'">Preview signed PDF</span>
                                        </button>
                                        <a href="{{ route('topics.versions.files.download', [$topic, $latestVersion, $activeSignedCopy]) }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-3 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-700 focus:ring-offset-2 dark:bg-emerald-500 dark:text-emerald-950 dark:hover:bg-emerald-400 dark:focus:ring-emerald-400 dark:focus:ring-offset-gray-950">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4m-4 6.75v1.5A2.25 2.25 0 0 0 6.25 21h11.5A2.25 2.25 0 0 0 20 18.75v-1.5" /></svg>
                                            Download
                                        </a>
                                    </div>
                                </section>
                            @endif

                            @if ($isSigningStage)
                                <form action="{{ route('topics.head-uploads.store', $topic) }}" method="POST" enctype="multipart/form-data" class="rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50">
                                    @csrf
                                    <input type="hidden" name="source_file_id" value="{{ $requiredSignatureFile->id }}">
                                    <input type="hidden" name="purpose" value="{{ \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_SIGNED }}">
                                    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                        <label class="block text-sm font-bold text-gray-800 dark:text-gray-200">
                                            {{ $hasSignedCopy ? 'Replace signed final PDF' : 'Signed final PDF' }}
                                            <span class="mt-1 block text-sm font-medium leading-6 text-gray-500 dark:text-gray-400">{{ $hasSignedCopy ? 'Use this only if the uploaded copy needs to be replaced.' : 'PDF only. The uploaded copy will appear here for preview.' }}</span>
                                            <input name="review_file" type="file" accept=".pdf" required class="mt-2 block w-full rounded-xl border border-gray-300 bg-white p-2.5 text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-gray-800 hover:file:bg-gray-200 focus:border-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:file:bg-gray-800 dark:file:text-white dark:focus:border-red-400 dark:focus:ring-red-400">
                                        </label>
                                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 sm:w-auto dark:focus:ring-offset-gray-950">
                                            {{ $hasSignedCopy ? 'Replace PDF' : 'Upload PDF' }}
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>

                        @if ($activeSignedCopy)
                            <section x-show="previewOpen" x-cloak x-transition.opacity class="overflow-hidden rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-900/50 lg:col-span-2" aria-label="Signed PDF preview">
                                <div class="flex flex-wrap items-center justify-between gap-3 pb-3">
                                    <div>
                                        <p class="text-sm font-black text-gray-950 dark:text-white">Signed PDF preview</p>
                                        <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">Check the uploaded copy here before the final release.</p>
                                    </div>
                                    <a href="{{ route('topics.versions.files.view', [$topic, $latestVersion, $activeSignedCopy]) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-800 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-700 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:hover:bg-gray-800 dark:focus:ring-gray-400 dark:focus:ring-offset-gray-950">Open in new tab</a>
                                </div>
                                <iframe data-signed-copy-preview src="{{ route('topics.versions.files.view', [$topic, $latestVersion, $activeSignedCopy]) }}" title="Preview of {{ $activeSignedCopy->original_filename }}" class="h-[34rem] w-full rounded-xl border border-gray-300 bg-white shadow-inner dark:border-gray-700"></iframe>
                            </section>
                        @endif
                    </article>
                    @if ($supersededSignedCopies->isNotEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                            {{ $supersededSignedCopies->count() }} superseded signed {{ \Illuminate\Support\Str::plural('copy', $supersededSignedCopies->count()) }} retained in the Research Head audit record.
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($isSigningStage)
                <form action="{{ route('research_head.topics.finalizeApproval', $topic) }}" method="POST" class="mt-5 flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    @csrf
                    @method('PATCH')
                    <p class="text-sm font-semibold leading-6 text-gray-700 dark:text-gray-300">
                        {{ $signaturesComplete ? 'Signed papers are ready. Prepare the signed Notice to Proceed to release the complete package.' : 'Final release stays locked until all five required papers have signed PDFs.' }}
                    </p>
                    <button type="submit" @disabled(! $signaturesComplete) class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-700 px-5 py-3 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600 dark:disabled:bg-gray-800 dark:disabled:text-gray-500">
                        Continue to Notice to Proceed
                    </button>
                </form>
            @endif
        </section>
    @endif


    @if (! $isSigningStage && $headUploadsBySource->get(0, collect())->isNotEmpty())
        <section class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/30 sm:p-6">
            <h3 class="text-base font-black text-red-900 dark:text-red-100">Earlier unlinked Research Head uploads</h3>
            <p class="mt-1 text-sm leading-6 text-red-800 dark:text-red-200">These older files remain in the proposal record but are not linked to a specific faculty paper.</p>
            <div class="mt-3 grid gap-2">
                @foreach ($headUploadsBySource->get(0) as $unlinkedCopy)
                    <p class="break-all rounded-xl bg-white px-3 py-2 text-sm font-semibold text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ $unlinkedCopy->original_filename }}</p>
                @endforeach
            </div>
        </section>
    @endif
</div>
