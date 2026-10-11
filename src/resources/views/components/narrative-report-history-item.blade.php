@props(['report', 'canReview' => false, 'canRecordSignedCopy' => false])

@php
    $isTerminalReport = $report->report_type === 'terminal';
    $signedCopy = $report->signedCopy();
    $photos = collect($report->photos ?? []);
    $statusLabel = $report->review_status_label;
    $sections = ['introduction' => 'Introduction', 'rationale' => 'Rationale', 'objectives' => 'Objectives', 'methodology' => 'Methodology', 'results_discussion' => 'Results and Discussion'];
    $reportId = 'narrative-report-'.$report->id;
    $figureNumber = 1;
    $isDemo = str_starts_with($report->accomplishment_summary ?? '', 'UI DEMONSTRATION');
@endphp

<article id="{{ $reportId }}" {{ $attributes->class(['report-reader scroll-mt-36 rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900']) }} data-narrative-report-card>
    <header data-report-reader-heading class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 dark:border-slate-700 sm:px-8 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-slate-950 dark:text-white">{{ $report->report_label }}@if ($report->reporting_quarter)<span class="ml-2 font-normal text-slate-500 dark:text-slate-400">Q{{ $report->reporting_quarter }}</span>@endif</h2>
            @if ($report->period_start && $report->period_end)<p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $report->period_start->format('M j, Y') }} – {{ $report->period_end->format('M j, Y') }} <span class="ml-2 text-slate-400">v{{ $report->version_number }}</span></p>@endif
            <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $report->submitter->name }} · {{ $report->submission_date->format('M j, Y') }}</p>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm leading-6 text-slate-500 dark:text-slate-400" data-narrative-report-status>
                <p class="inline-flex items-center gap-2"><span aria-hidden="true" class="h-1.5 w-1.5 rounded-full {{ $report->review_status === 'reviewed' ? 'bg-emerald-500' : ($report->review_status === 'revision_requested' ? 'bg-red-500' : 'bg-amber-400') }}"></span>{{ $statusLabel }}</p>
                @if ($isTerminalReport && ($signedCopy || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED))
                    <p>{{ $signedCopy ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed' }}</p>
                @endif
            </div>
        </div>
        <div data-report-reader-actions class="flex shrink-0 flex-wrap items-center gap-2">
            <button type="button" @click="$dispatch('open-modal', 'narrative-pdf-{{ $report->id }}')" aria-label="Preview PDF" title="Preview PDF" aria-haspopup="dialog" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                <span class="sr-only sm:not-sr-only">Preview PDF</span>
            </button>
            <a href="{{ route('project-narrative-reports.download', $report) }}" aria-label="Download {{ strtolower($report->report_label) }} PDF" title="Download PDF" class="monitoring-report-control">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/></svg>
                <span class="sr-only">Download PDF</span>
            </a>
            @if ($signedCopy)
                <a href="{{ route('project-narrative-reports.signed-copy.download', $report) }}" aria-label="Download signed Terminal Report" class="inline-flex min-h-11 items-center rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Signed PDF</a>
            @endif
        </div>
    </header>
    @if ($isDemo)
        <p class="border-b border-slate-100 px-5 py-2 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400 sm:px-8">Sample report · Illustrative figures and findings.</p>
    @endif
    <div class="report-reader-layout">
        <nav data-report-reader-navigation aria-label="{{ $report->report_label }} sections" class="report-reader-navigation">
            <a href="#{{ $reportId }}-accomplishments">Accomplishments</a>
            @foreach ($sections as $field => $label)
                @if (filled($report->$field) || $photos->where('section', $field)->isNotEmpty())
                    <a href="#{{ $reportId }}-{{ $field }}">{{ $label }}</a>
                @endif
            @endforeach
            @if ($canReview || $report->research_head_remarks)<a href="#{{ $reportId }}-review">{{ $canReview ? 'Review' : 'Remarks' }}</a>@endif
        </nav>
        <div class="min-w-0" data-narrative-report-content>
            <div class="px-5 py-5 sm:px-8">
                <dl class="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Project duration</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $report->implementation_start?->format('M j, Y') }} – {{ $report->implementation_end?->format('M j, Y') }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Funding agency</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $report->funding_agency }}</dd></div>
                </dl>
            </div>
            <div class="report-reader-document divide-y divide-slate-100 px-5 dark:divide-slate-800 sm:px-8">
                <section id="{{ $reportId }}-accomplishments" class="scroll-mt-36 space-y-5 py-7" aria-labelledby="{{ $reportId }}-accomplishments-heading">
                    <h3 id="{{ $reportId }}-accomplishments-heading" class="text-base font-semibold text-slate-950 dark:text-white">Accomplishments</h3>
                    @if ($report->accomplishments)
                        <table class="block w-full table-fixed text-left text-sm leading-7 sm:table" data-report-accomplishments>
                            <thead class="hidden border-b border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400 sm:table-header-group"><tr><th scope="col" class="w-1/4 pb-3 pr-4 font-medium">Objective</th><th scope="col" class="w-1/4 pb-3 pr-4 font-medium">Target</th><th scope="col" class="w-1/2 pb-3 font-medium">Accomplished</th></tr></thead>
                            <tbody class="block divide-y divide-slate-100 dark:divide-slate-800 sm:table-row-group">
                                @foreach ($report->accomplishments as $row)
                                    <tr class="block space-y-4 py-5 sm:table-row sm:space-y-0 sm:py-0">
                                        @foreach (['objective' => 'Objective', 'target' => 'Target accomplishment', 'actual' => 'Actual accomplishment'] as $key => $label)
                                            <td class="block break-words align-top sm:table-cell sm:py-4 sm:pr-4"><span class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400 sm:hidden">{{ $key === 'actual' ? 'Accomplished' : ($key === 'target' ? 'Target' : $label) }}</span><span class="whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $row[$key] ?? '' }}</span></td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="max-w-[75ch] whitespace-pre-line text-base leading-8 text-gray-700 dark:text-slate-200">{{ $report->accomplishment_summary ?: 'See the submitted PDF for this report’s accomplishments.' }}</p>
                    @endif
                </section>
                <x-progress-report-evidence :report="$report" class="py-7" />
                @foreach ($sections as $field => $label)
                    @php
                        $paragraphs = preg_split('/\n\s*\n/u', app(\App\Support\ProgressReportData::class)->plain($report->$field ?? '')) ?: [''];
                        $sectionFigures = $photos->filter(fn ($photo) => ($photo['section'] ?? 'results_discussion') === $field);
                    @endphp
                    @if (filled($report->$field) || $sectionFigures->isNotEmpty())
                        <section id="{{ $reportId }}-{{ $field }}" class="scroll-mt-36 space-y-5 py-7" aria-labelledby="{{ $reportId }}-{{ $field }}-heading" data-report-section="{{ $field }}">
                            <h3 id="{{ $reportId }}-{{ $field }}-heading" class="text-base font-semibold text-slate-950 dark:text-white">{{ $label }}</h3>
                            @foreach ($paragraphs as $paragraphIndex => $paragraph)
                                @if (filled($paragraph))<p class="max-w-3xl whitespace-pre-line break-words text-base leading-8 text-gray-700 dark:text-slate-200">{{ $paragraph }}</p>@endif
                                @foreach ($sectionFigures as $photoIndex => $photo)
                                    @php
                                        $position = (int) ($photo['after_paragraph'] ?? 0);
                                        $position = $position <= 0 ? count($paragraphs) : min($position, count($paragraphs));
                                    @endphp
                                    @if ($position === $paragraphIndex + 1)
                                        @php($currentFigureNumber = $figureNumber++)
                                        <x-narrative-report-figure :report="$report" :photo="$photo" :photo-index="$photoIndex" :figure-number="$currentFigureNumber" />
                                    @endif
                                @endforeach
                            @endforeach
                        </section>
                    @endif
                @endforeach
                @if ($photos->where('section', 'cover')->isNotEmpty())
                    <section class="space-y-5 py-7" aria-label="Project illustration">
                        @foreach ($photos->where('section', 'cover') as $photoIndex => $photo)
                            @php($currentFigureNumber = $figureNumber++)
                                        <x-narrative-report-figure :report="$report" :photo="$photo" :photo-index="$photoIndex" :figure-number="$currentFigureNumber" />
                        @endforeach
                    </section>
                @endif
            </div>
        @if ($canReview || $report->research_head_remarks)
            <section id="{{ $reportId }}-review" class="scroll-mt-36 border-t border-slate-100 px-5 py-7 dark:border-slate-800 sm:px-8" aria-label="Research Head review">
                <div class="space-y-5">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $canReview ? 'Research Head review' : 'Research Head remarks' }}</h3>
                    @if ($canReview)
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="space-y-5" data-narrative-report-review>
                            @csrf
                            @method('PATCH')
                            <fieldset class="flex flex-wrap gap-x-6 gap-y-2">
                                <legend class="mb-2 text-sm font-semibold text-gray-700 dark:text-slate-200">Decision</legend>
                                @foreach (['reviewed' => 'Mark reviewed', 'revision_requested' => 'Request corrections'] as $value => $label)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                        <input type="radio" name="review_status" value="{{ $value }}" required @checked($report->review_status === $value || ($report->review_status === 'pending' && $value === 'reviewed')) class="h-4 w-4 shrink-0 border-red-300 text-brand focus:ring-brand dark:border-red-800 dark:bg-slate-950">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            <div class="space-y-2">
                                <label for="narrative-review-notes-{{ $report->id }}" class="block text-sm font-semibold text-gray-700 dark:text-slate-200">Feedback</label>
                                <textarea id="narrative-review-notes-{{ $report->id }}" name="research_head_remarks" rows="4" maxlength="5000" placeholder="Write the feedback or corrections needed." class="block w-full rounded-lg border-slate-200 bg-white text-base leading-7 text-slate-900 placeholder:text-slate-400 focus:border-brand focus:ring-brand dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('research_head_remarks', $report->research_head_remarks) }}</textarea>
                                @error('research_head_remarks')<p class="text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-soft focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Save review</button>
                        </form>
                    @else
                        <p class="whitespace-pre-line break-words text-base leading-7 text-gray-900 dark:text-slate-100">{{ $report->research_head_remarks }}</p>
                    @endif
                </div>
            </section>
        @endif
    @if ($canRecordSignedCopy)
        <form method="POST" action="{{ route('research_head.narrative-progress-reports.signed-copy.store', $report) }}" enctype="multipart/form-data" class="space-y-4 border-t border-slate-100 px-5 py-7 dark:border-slate-800 sm:px-8">
            @csrf
            <div class="space-y-1">
                <h5 class="text-lg font-semibold text-brand dark:text-red-200">Signed terminal report</h5>
                <p class="text-sm leading-6 text-gray-600 dark:text-slate-300">Upload the PDF with all required signatures.</p>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <div class="min-w-0 flex-1 space-y-2">
                    <label for="narrative-signed-report-{{ $report->id }}" class="block text-sm font-medium text-brand dark:text-red-200">{{ $signedCopy ? 'Replace signed PDF' : 'Signed PDF' }}</label>
                    <input id="narrative-signed-report-{{ $report->id }}" name="signed_report" type="file" accept=".pdf,application/pdf" required class="block w-full rounded-lg border border-red-300 bg-white p-3 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-red-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand dark:border-red-800 dark:bg-slate-950 dark:text-slate-200 dark:file:bg-red-950 dark:file:text-red-200">
                </div>
                <button type="submit" class="inline-flex min-h-12 shrink-0 items-center justify-center rounded-lg bg-brand px-5 py-3 text-sm font-semibold text-white hover:bg-brand-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">{{ $signedCopy ? 'Replace signed PDF' : 'Record signed PDF' }}</button>
            </div>
            @error('signed_report')<p class="text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
        </form>
    @endif
        </div>
    </div>
</article>

<x-submitted-report-preview name="narrative-pdf-{{ $report->id }}" :title="$report->report_label.' preview'" :description="$report->reporting_period_label" :preview-url="route('project-narrative-reports.view', $report)" :download-url="route('project-narrative-reports.download', $report)" data-narrative-pdf-preview-modal />
