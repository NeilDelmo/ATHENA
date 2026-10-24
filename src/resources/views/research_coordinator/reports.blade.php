@php
    $submitted = $report?->submitted_at !== null;
    $latestSubmission = $report?->submissions->first();
    $returned = ! $submitted && $latestSubmission?->returned_at !== null;
    $activities = collect(['presentations', 'publications', 'seminars', 'manual_research', 'ip_assets'])->mapWithKeys(fn (string $section): array => [
        $section => collect(old($section, $report?->{$section} ?? []))->filter(fn (mixed $row): bool => is_array($row))->map(fn (array $row): array => [
            'id' => (string) Illuminate\Support\Str::uuid(), ...($section === 'ip_assets' ? ['title' => '', 'type' => '', 'inventors' => '', 'date' => '', 'remarks' => '', 'evidence_url' => ''] : ['research_title' => '', 'project_id' => '']), ...$row,
        ])->values()->all(),
    ])->all();
    $rowProofs = collect($report?->evidence ?? [])->map(fn (array $image, int $index): array => [
        'section' => $image['section'] ?? null, 'row_id' => $image['row_id'] ?? null,
        'token' => hash('sha256', $image['path']), 'filename' => $image['original_filename'] ?? basename($image['path']),
        'caption' => old('saved_evidence_captions.'.hash('sha256', $image['path']), $image['caption'] ?? ''),
        'url' => route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index]),
        'preview_url' => route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index, 'preview' => 1]),
    ])->values()->all();
    $nextQuarter = $period['end']->addDay()->startOfDay();
    $inputClass = 'w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-950 dark:text-white';
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" eyebrow="Research Coordinator" title="Quarterly Accomplishment Reports"
            :subtitle="($coordinator->college ?: 'Your college').' · One general report each calendar quarter.'">
            <x-slot name="actions">
                <a href="{{ route('research_coordinator.reports.template') }}" class="dashboard-action">Download blank template</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="space-y-6" data-quarterly-reports>
        @if (session('status'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
        @endif
        @if (session('report_download_url'))
            <p class="text-sm text-slate-600 dark:text-slate-300">Your download should start automatically. <a href="{{ session('report_download_url') }}" x-init="window.location.assign($el.href)" class="font-semibold text-brand underline dark:text-brand-soft">Download Word again</a></p>
        @endif

        @if ($returned)
            <section role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <h2 class="font-semibold">Returned for correction</h2>
                <p class="mt-1 whitespace-pre-line">{{ $latestSubmission->return_reason }}</p>
                <p class="mt-2">Update this draft and resubmit it. The previous submission remains available in the history.</p>
            </section>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-semibold">Check the report before saving.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section aria-label="Reporting quarters" class="space-y-4">
            <form method="GET" action="{{ route('research_coordinator.reports.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <x-input-label for="report-year" value="Reporting year" />
                    <input id="report-year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" required class="mt-1 w-32 rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <input type="hidden" name="quarter" value="{{ $quarter }}">
                <button type="submit" class="dashboard-action">View year</button>
                @if ($nextQuarter->year <= 2100)
                    <a href="{{ route('research_coordinator.reports.index', ['year' => $nextQuarter->year, 'quarter' => $nextQuarter->quarter]) }}" class="dashboard-action">Open next quarter (Q{{ $nextQuarter->quarter }} {{ $nextQuarter->year }})</a>
                @endif
            </form>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($quarters as $quarterRow)
                    <a wire:navigate href="{{ route('research_coordinator.reports.index', ['year' => $year, 'quarter' => $quarterRow['quarter']]) }}"
                        @if ($quarterRow['quarter'] === $quarter) aria-current="page" @endif
                        @class(['dashboard-panel block border p-4 transition hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand', 'border-brand ring-1 ring-brand' => $quarterRow['quarter'] === $quarter])>
                        <span class="flex items-center justify-between gap-2"><span class="font-bold text-gray-950 dark:text-white">Q{{ $quarterRow['quarter'] }}</span><span class="text-xs font-semibold text-brand dark:text-rose-300">{{ $quarterRow['status'] }}</span></span>
                        <span class="mt-2 block text-sm text-slate-500 dark:text-slate-400">{{ $quarterRow['start']->format('M') }} – {{ $quarterRow['end']->format('M Y') }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="dashboard-panel flex flex-wrap items-start justify-between gap-4 p-5" aria-labelledby="quarter-report-title">
            <div>
                <h2 id="quarter-report-title" class="text-xl font-bold text-gray-950 dark:text-white">Accomplishment Report in Research</h2>
                <p class="mt-1 font-medium text-gray-700 dark:text-slate-300">{{ $period['start']->format('F') }} to {{ $period['end']->format('F Y') }} · Q{{ $quarter }}</p>
                <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-slate-400">{{ $submitted ? 'Submitted '.$report->submitted_at->format('M j, Y').'. This report preserves the records included at submission.' : 'Section II fills from your college’s projects with an issued Notice to Proceed. Add unmonitored or externally funded research manually below. Enter participation activities manually and attach proof to every row in a, b, and c.' }}</p>
                @unless ($submitted)
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Faculty research refreshes from project records whenever you open a draft. Your manual entries are saved for the selected quarter.</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Final submission opens {{ $period['end']->addDay()->startOfDay()->format('F j, Y') }}. Faculty project approvals continue through their existing workflow.</p>
                @endunless
            </div>
            @if ($submitted)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('research_coordinator.reports.print', ['year' => $year, 'quarter' => $quarter]) }}" target="_blank" rel="noopener" class="dashboard-action">Print preview</a>
                <a href="{{ route('research_coordinator.reports.download', ['year' => $year, 'quarter' => $quarter]) }}" class="dashboard-action">Download Word</a>
            </div>
            @endif
        </section>

        @if ($hasSourceChanges)
            <div role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <p class="font-semibold">Automatic records changed since this draft was saved.</p>
                <p class="mt-1">Section II includes the latest faculty research. Review the updates, then save your draft.</p>
            </div>
        @endif

        <form id="quarterly-report-form" method="POST" action="{{ route('research_coordinator.reports.store') }}" enctype="multipart/form-data" class="space-y-6"
            data-proposal-paper-workspace data-document-writing-workspace
            data-assistant-report-type="college-quarterly" data-assistant-report-key="{{ $report ? 'college-quarterly:'.$report->id : '' }}" data-assistant-report-save-mode="manual"
            x-data="quarterlyReportForm({{ Illuminate\Support\Js::from([...$activities, 'rowProofs' => $rowProofs, 'removedProofs' => old('remove_evidence', [])]) }})"
            @focusin="focusWritingField($event)" @formdata="serialize($event)">
            @csrf
            <input type="hidden" name="reporting_year" value="{{ $year }}">
            <input type="hidden" name="reporting_quarter" value="{{ $quarter }}">
            <input type="hidden" name="draft_token" value="{{ session()->hasOldInput() ? old('draft_token', '') : ($report ?? new \App\Models\CollegeQuarterlyReport)->draftToken() }}">

            <x-assistant-report-editor :context="['report_type' => 'college-quarterly', 'report_id' => $report?->id]" :editable="! $submitted" />

            @unless ($submitted)
                <x-quarterly-report-writing-toolbar :period="$period" :returned="$returned" />
            @endunless

            <h2 class="text-lg font-bold text-gray-950 dark:text-white">I. Participation in Research Activities</h2>
            @if ($submitted)
                <x-college-quarterly-report-table title="a. Presentation" :columns="['Event/Activity', 'Research Title', 'Date', 'Place', 'Personnel(s) involved']" :rows="$reportData['presentations'] ?? []" empty-message="No presentations reported for this quarter." />
                <x-college-quarterly-report-table title="b. Publication" :columns="['Journal Name/Conference', 'Research Title', 'Date', 'Personnel(s) involved']" :rows="$reportData['publications'] ?? []" empty-message="No publications reported for this quarter." />
                <x-college-quarterly-report-table title="c. Seminar/Training" :columns="['Event/Activity', 'Date', 'Place', 'Personnel(s) involved']" :rows="$report?->activityRows('seminars') ?? []" empty-message="No seminars or training reported for this quarter." />
            @else
                @foreach (['presentations' => 'a. Presentation', 'publications' => 'b. Publication', 'seminars' => 'c. Seminar/Training'] as $section => $title)
                    <x-college-quarterly-report-activities :section="$section" :title="$title" :period="$period" :projects="$evidenceProjects" />
                @endforeach
            @endif

            <div class="space-y-4">
                <x-college-quarterly-report-table title="II. Research of Faculty Members" :columns="['Title', 'Duration', 'Personnel(s) involved', 'Status']" :rows="$submitted ? $reportData['research'] : $automaticResearch" empty-message="No faculty research projects with an issued Notice to Proceed active during this quarter." />
                @unless ($submitted)
                    <x-college-quarterly-report-research-entries />
                @endunless
            </div>

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="other-accomplishments-heading">
                <h2 id="other-accomplishments-heading" class="text-lg font-bold text-gray-950 dark:text-white">III. Other Accomplishments</h2>
                @if ($submitted)
                    <div class="text-sm text-gray-700 dark:text-slate-300 [&_ol]:list-decimal [&_ul]:list-disc [&_ol]:pl-6 [&_ul]:pl-6 [&_p]:mb-3">{!! app(\App\Support\ProposalRichText::class)->sanitize($report->other_accomplishments ?: 'No other accomplishments reported.') !!}</div>
                @else
                    <label for="other-accomplishments" class="block text-sm text-slate-500 dark:text-slate-400">Describe other accomplishments from this quarter, if any.</label>
                    <textarea id="other-accomplishments" name="other_accomplishments" rows="6" maxlength="100000" data-semantic-editor data-semantic-editor-size="large" class="{{ $inputClass }}">{{ old('other_accomplishments', $report?->other_accomplishments) }}</textarea>
                @endif
            </section>

            @if ($submitted)
                @if (! empty($reportData['ip_assets']))
                    <x-college-quarterly-report-table title="Intellectual Property Assets" :columns="['Title', 'Type', 'Inventors / authors', 'Date Submitted', 'Remarks / evidence link']" :rows="$report->activityRows('ip_assets')" />
                @endif
            @else
                <x-college-quarterly-report-ip-assets :period="$period" />
            @endif

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="report-signatories-heading">
                <h2 id="report-signatories-heading" class="text-lg font-bold text-gray-950 dark:text-white">Report signatories</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="space-y-2 text-sm">
                        <p class="font-semibold text-gray-700 dark:text-slate-300">Prepared and submitted by</p>
                        <p class="font-bold text-gray-950 dark:text-white">{{ $reportData['prepared_by_name'] ?? $report?->preparedBy?->name ?? $coordinator->name }}</p>
                        <p class="text-slate-500 dark:text-slate-400">Research Coordinator · {{ $coordinator->college }}</p>
                    </div>
                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-gray-700 dark:text-slate-300">Checked and reviewed by</p>
                        @if ($submitted)
                            <p class="text-sm text-gray-700 dark:text-slate-300">{{ $report->reviewed_by ?: '________________________' }}<br>{{ $report->reviewed_by_title }}</p>
                        @else
                            <div><x-input-label for="reviewed-by" value="Reviewer name (optional)" /><input id="reviewed-by" name="reviewed_by" maxlength="255" value="{{ old('reviewed_by', $report?->reviewed_by) }}" class="{{ $inputClass }} mt-1"></div>
                            <div><x-input-label for="reviewed-by-title" value="Reviewer title (optional)" /><input id="reviewed-by-title" name="reviewed_by_title" maxlength="255" value="{{ old('reviewed_by_title', $report?->reviewed_by_title) }}" class="{{ $inputClass }} mt-1"></div>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Signatures and dates signed remain blank in the Word report for signing.</p>
            </section>

            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="supporting-documentation-heading">
                <h2 id="supporting-documentation-heading" class="text-lg font-bold text-gray-950 dark:text-white">Supporting Documentation</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Submitted faculty evidence for this quarter appears here and in the accomplishment report’s print preview and Word download. Faculty drafts remain private.</p>
                @foreach ($reportEvidence as $index => $evidence)
                    @continue(! $submitted && isset($evidence['section']))
                    @php
                        $evidenceUrl = $evidence['url'] ?? route('research_coordinator.reports.evidence', ['report' => $report, 'index' => $index]);
                        $evidenceLabel = $report?->evidenceLabel($evidence) ?: ($evidence['caption'] ?? 'Supporting documentation '.($index + 1));
                        $isImage = in_array($evidence['mime_type'] ?? 'image/png', ['image/jpeg', 'image/png'], true);
                        $previewUrl = $evidenceUrl.(str_contains($evidenceUrl, '?') ? '&' : '?').'preview=1';
                    @endphp
                    <figure class="space-y-2">
                        @if ($isImage)
                            <a href="{{ $evidence['download_url'] ?? $evidenceUrl }}" target="_blank" rel="noopener" class="inline-block rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand"><img src="{{ $previewUrl }}" alt="{{ $evidenceLabel }}" class="max-h-64 rounded-lg object-contain" loading="lazy" decoding="async"></a>
                        @else
                            <a href="{{ $evidenceUrl }}" class="inline-block break-words rounded font-semibold text-brand underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $evidence['original_filename'] }}</a>
                        @endif
                        <figcaption class="text-sm text-gray-600 dark:text-slate-400">{{ $evidenceLabel }}</figcaption>
                        @if (filled($evidence['folder'] ?? null))
                            <p class="text-xs text-slate-500 dark:text-slate-400">Evidence folder: {{ $evidence['folder'] }} / {{ $year }} / Q{{ $quarter }}</p>
                        @endif
                        @if (! $submitted && ($evidence['source'] ?? null) !== 'faculty_report')
                            @php($evidenceCaptionToken = hash('sha256', $evidence['path']))
                            <div>
                                <label for="saved-evidence-caption-{{ $evidenceCaptionToken }}" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Caption</label>
                                <textarea id="saved-evidence-caption-{{ $evidenceCaptionToken }}" name="saved_evidence_captions[{{ $evidenceCaptionToken }}]" rows="2" maxlength="1000" class="{{ $inputClass }} mt-1">{{ old('saved_evidence_captions.'.$evidenceCaptionToken, $evidence['caption'] ?? '') }}</textarea>
                                <x-input-error :messages="$errors->get('saved_evidence_captions.'.$evidenceCaptionToken)" class="mt-1" />
                            </div>
                            <label class="inline-flex items-center gap-2 text-sm text-red-700 dark:text-red-300"><input type="checkbox" name="remove_evidence[]" value="{{ hash('sha256', $evidence['path']) }}" class="rounded border-gray-300 text-brand focus:ring-brand"> Remove photo when saving</label>
                        @endif
                    </figure>
                @endforeach
                @unless ($submitted)
                    <p id="evidence-help" class="text-sm text-slate-500 dark:text-slate-400">Additional appendix photos are optional and do not replace row proof. JPEG or PNG, up to 5 MB each; up to 10 additional photos. Attach proof directly to each activity above.</p>
                    <label for="evidence-images" class="block text-sm font-medium text-gray-700 dark:text-slate-300">Add photos</label>
                    <input id="evidence-images" type="file" name="evidence_images[]" accept="image/jpeg,image/png" multiple aria-describedby="evidence-help" @change="attachments = Array.from($event.target.files)" class="block max-w-full text-sm text-gray-700 dark:text-slate-300">
                    <template x-for="(attachment, index) in attachments" :key="index">
                        <div>
                            <label :for="`evidence-caption-${index}`" class="block text-sm font-medium text-gray-700 dark:text-slate-300" x-text="`Caption for ${attachment.name}`"></label>
                            <input :id="`evidence-caption-${index}`" :name="`evidence_captions[${index}]`" maxlength="1000" class="{{ $inputClass }} mt-1">
                        </div>
                    </template>
                @elseif (empty($reportEvidence))
                    <p class="text-sm text-slate-500 dark:text-slate-400">No supporting evidence attached.</p>
                @endunless
            </section>

            @unless ($submitted)
                <p class="text-sm text-slate-500 dark:text-slate-400">Use the report toolbar to save, preview, download, or submit. Each row in a, b, and c needs proof before final submission, which preserves this quarter’s entries and faculty research records.</p>
            @endunless
            <input type="hidden" name="form_complete" value="1">
        </form>

        @if ($report?->submissions->isNotEmpty())
            <section class="dashboard-panel space-y-4 p-5" aria-labelledby="submission-history-heading">
                <h2 id="submission-history-heading" class="text-lg font-bold text-gray-950 dark:text-white">Submission history</h2>
                <ul class="space-y-3">
                    @foreach ($report->submissions as $submission)
                        <li class="flex flex-wrap items-center justify-between gap-3 text-sm">
                            <span class="text-gray-700 dark:text-slate-300">Version {{ $submission->version_number }} · {{ $submission->submitted_at->format('M j, Y g:i A') }}{{ $submission->returned_at ? ' · Correction requested' : '' }}</span>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('college-quarterly-submissions.print', $submission) }}" target="_blank" rel="noopener" class="dashboard-action">Preview version {{ $submission->version_number }}</a>
                                <a href="{{ route('college-quarterly-submissions.download', $submission) }}" class="dashboard-action">Download Word</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-app-layout>
