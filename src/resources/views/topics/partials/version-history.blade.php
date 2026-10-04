@php
    $expanded = $expanded ?? false;
@endphp

<section class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950" data-submitted-version-history>
    <details @if ($expanded) open @endif>
        <summary class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-red-700 sm:px-6">
            <div class="min-w-0">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Submitted proposal versions</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Packages sent for review. Working edits appear after submission.</p>
            </div>
            <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                {{ $topic->versions->count() }} {{ Str::plural('version', $topic->versions->count()) }}
            </span>
        </summary>

        <div class="divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-800 dark:border-gray-800">
            @forelse ($topic->versions->sortByDesc('version_number') as $version)
                @php
                    $versionFiles = $version->files->whereNotIn('document_type', [
                        \App\Models\ProposalVersionFile::TYPE_COMMENT_RESPONSE,
                        \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD,
                    ]);
                    $proposalPapers = $versionFiles->reject(fn (\App\Models\ProposalVersionFile $file): bool => $file->isGeneratedAssessmentForm());
                    $assessmentForms = $versionFiles->filter(fn (\App\Models\ProposalVersionFile $file): bool => $file->isGeneratedAssessmentForm());
                    $assessmentUploads = $version->files->filter(fn (\App\Models\ProposalVersionFile $file): bool => $file->document_type === \App\Models\ProposalVersionFile::TYPE_HEAD_UPLOAD
                        && ! $file->isSuperseded()
                        && in_array($file->source_data['purpose'] ?? null, [\App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT, \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_EVALUATION], true))
                        ->sortByDesc('id')->unique(fn ($file) => $file->source_version_file_id.':'.$file->source_data['purpose']);
                @endphp
                <article data-submitted-version="{{ $version->version_number }}">
                    <details class="group">
                        <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-4 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-red-700 dark:hover:bg-gray-900 sm:px-6">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-bold text-red-700 dark:bg-red-950/40 dark:text-red-300" aria-hidden="true">{{ $version->version_number }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="truncate text-sm font-bold text-gray-950 dark:text-white">Version {{ $version->version_number }} · {{ $version->title }}</span>
                                    @if ($loop->first)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">Latest</span>
                                    @endif
                                </span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">
                                    {{ match ($version->submission_type) { 'initial' => 'Initial submission', 'update' => 'Package update before review', default => 'Revision submission' } }} · {{ $version->created_at->format('M j, Y · g:i A') }} · {{ max(1, $versionFiles->count()) }} {{ Str::plural('file', max(1, $versionFiles->count())) }}
                                </span>
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-gray-500 transition-transform group-open:rotate-180 dark:text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                        </summary>

                        <div class="border-t border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-800 dark:bg-gray-900/40 sm:px-6">
                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                                <span>Submitted by <strong class="font-semibold text-gray-900 dark:text-white">{{ $version->submitter?->name ?? 'Former user' }}</strong></span>
                                <span>PHP {{ number_format((float) $version->estimated_budget, 2) }}</span>
                                <span>{{ $version->estimated_duration_months }} months</span>
                            </div>

                            @if ($version->change_summary)
                                <p class="mt-3 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm leading-6 text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100"><span class="font-semibold">Revision summary:</span> {{ $version->change_summary }}</p>
                            @endif

                            @if (Auth::user()?->isUsingWorkspace('research_head') && $version->research_head_screening !== null)
                                <a data-version-screening-form href="{{ route('research_head.topics.initial-screening-form.edit', [$topic, $version]) }}" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">Saved Initial Screening Form · Version {{ $version->version_number }}</a>
                            @endif

                            @if ($versionFiles->isNotEmpty())
                                @foreach (['Proposal papers' => $proposalPapers, 'Assessment forms' => $assessmentForms] as $groupLabel => $groupFiles)
                                    @if ($groupFiles->isNotEmpty())
                                        <div class="mt-4" data-version-file-group="{{ $groupLabel }}">
                                            <h5 class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $groupLabel }}</h5>
                                            <ul class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-950">
                                                @foreach ($groupFiles as $file)
                                                    <li class="flex flex-col gap-2 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                                        <div class="min-w-0">
                                                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                                                <span>{{ $file->label() }}</span>
                                                                <span class="text-xs font-medium {{ $file->is_carried_forward ? 'text-gray-500 dark:text-gray-400' : ($version->version_number > 1 ? 'text-amber-700 dark:text-amber-300' : 'text-emerald-700 dark:text-emerald-300') }}">{{ $file->is_carried_forward ? 'Unchanged' : ($version->version_number > 1 ? 'Changed' : 'Submitted') }}</span>
                                                            </p>
                                                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400" title="{{ $file->original_filename }}">{{ $file->original_filename }} @if ($file->file_size) · {{ number_format($file->file_size / 1024, 1) }} KB @endif</p>
                                                        </div>
                                                        <a href="{{ route('topics.versions.files.download', [$topic, $version, $file]) }}" class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" aria-label="Download {{ $file->label() }} from version {{ $version->version_number }}">Download</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                <div class="mt-4 flex flex-col gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-800 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Detailed Proposal</p>
                                        <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $version->original_filename }} @if ($version->file_size) · {{ number_format($version->file_size / 1024, 1) }} KB @endif</p>
                                    </div>
                                    <a href="{{ route('topics.versions.download', [$topic, $version]) }}" class="inline-flex min-h-9 shrink-0 items-center justify-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Download</a>
                                </div>
                            @endif
                            @if ($assessmentUploads->isNotEmpty())
                                <section data-version-assessment-records class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-700" aria-label="Assessment records for version {{ $version->version_number }}">
                                    <h4 class="text-sm font-bold text-gray-950 dark:text-white">Assessment records</h4>
                                    <ul class="mt-3 space-y-3">
                                        @foreach ($assessmentUploads as $assessmentUpload)
                                            @php
                                                $isGadRecord = $assessmentUpload->source_data['purpose'] === \App\Models\ProposalVersionFile::HEAD_UPLOAD_PURPOSE_GAD_ASSESSMENT;
                                                $recordScore = $assessmentUpload->source_data['gad_score'] ?? null;
                                                $recordRecommendation = \App\Support\InitialScreeningSubmissionOrder::recommendationLabel($assessmentUpload->source_data['recommended_action'] ?? null);
                                            @endphp
                                            <li data-assessment-record="{{ $assessmentUpload->id }}" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $isGadRecord ? 'GAD Office assessment' : 'Co-evaluator review' }}</p>
                                                        <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">{{ $isGadRecord ? ($recordScore !== null ? number_format((float) $recordScore, 2).'/20' : 'Score not recorded') : $recordRecommendation }}</p>
                                                        <p class="mt-1 break-all text-xs text-gray-500 dark:text-gray-400">{{ $assessmentUpload->original_filename }}</p>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('topics.versions.files.view', [$topic, $version, $assessmentUpload]) }}" target="_blank" rel="noopener" class="inline-flex min-h-9 items-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">View</a>
                                                        <a href="{{ route('topics.versions.files.download', [$topic, $version, $assessmentUpload]) }}" class="inline-flex min-h-9 items-center rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Download</a>
                                                    </div>
                                                </div>
                                                @if (! $isGadRecord && filled($assessmentUpload->source_data['narrative_evaluation'] ?? null))
                                                    <p class="mt-3 whitespace-pre-line break-words border-t border-gray-100 pt-3 text-sm leading-6 text-gray-800 dark:border-gray-800 dark:text-gray-200">{{ $assessmentUpload->source_data['narrative_evaluation'] }}</p>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endif
                        </div>
                    </details>
                </article>
            @empty
                <div class="px-6 py-8 text-center">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">No submitted versions yet</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Version history begins with the first submission.</p>
                </div>
            @endforelse
        </div>
    </details>
</section>
