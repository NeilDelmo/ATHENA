@props(['report', 'projectTitle' => '', 'canReview' => false, 'canRecordSignedCopy' => false])

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

<article id="{{ $reportId }}" {{ $attributes->class(['scroll-mt-36 rounded-xl border border-red-200 bg-white dark:border-red-900 dark:bg-slate-900']) }} data-narrative-report-card>
    <header class="flex flex-col gap-5 rounded-t-xl bg-brand px-5 py-6 text-white sm:px-7 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <h4 class="text-2xl font-bold">{{ $report->report_label }}</h4>
            <p class="mt-2 text-base leading-6 text-red-100">Submitted {{ $report->submission_date->format('M j, Y') }} by {{ $report->submitter->name }}</p>
            <div class="mt-3 space-y-1 text-base leading-6" data-narrative-report-status>
                <p class="font-semibold text-white">{{ $statusLabel }}</p>
                @if ($isTerminalReport && ($signedCopy || $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED))
                    <p class="text-red-100">{{ $signedCopy ? 'Signed terminal PDF uploaded' : 'Signed terminal PDF needed' }}</p>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-3">
            <button type="button" @click="$dispatch('open-modal', 'narrative-pdf-{{ $report->id }}')" aria-haspopup="dialog" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg bg-white px-4 py-3 text-base font-semibold text-brand hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-brand">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-7.5 9.75-7.5 9.75 7.5 9.75 7.5-3.5 7.5-9.75 7.5S2.25 12 2.25 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview PDF
            </button>
            <a href="{{ route('project-narrative-reports.download', $report) }}" aria-label="Download {{ strtolower($report->report_label) }} PDF" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-lg border border-white/60 px-4 py-3 text-base font-semibold hover:bg-white hover:text-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/></svg>
                Download PDF
            </a>
            @if ($signedCopy)
                <a href="{{ route('project-narrative-reports.signed-copy.download', $report) }}" aria-label="Download signed Terminal Report" class="inline-flex min-h-12 items-center gap-2 rounded-lg border border-white/60 px-4 py-3 text-base font-semibold hover:bg-white hover:text-brand focus-visible:ring-2 focus-visible:ring-white"><svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m-4-4 4 4 4-4M4 16v4h16v-4"/></svg>Download signed PDF</a>
            @endif
        </div>
    </header>
    @if ($isDemo)
        <p class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:px-7">Demonstration report. The figures and findings illustrate the report format.</p>
    @endif
    <div class="grid {{ ($canReview || $report->research_head_remarks) ? 'xl:grid-cols-[minmax(0,1fr)_300px]' : '' }}">
        <div class="min-w-0" data-narrative-report-content>
            <div class="space-y-5 px-5 py-6 sm:px-7">
                @if ($projectTitle)<p class="max-w-3xl text-xl font-bold leading-8 text-gray-950 dark:text-white">{{ $projectTitle }}</p>@endif
                <dl class="grid gap-x-8 gap-y-4 text-base sm:grid-cols-2">
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Project duration</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $report->implementation_start?->format('M j, Y') }} – {{ $report->implementation_end?->format('M j, Y') }}</dd></div>
                    <div><dt class="text-sm text-gray-500 dark:text-slate-400">Funding agency</dt><dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $report->funding_agency }}</dd></div>
                </dl>
            </div>
            <nav aria-label="{{ $report->report_label }} sections" class="flex flex-wrap gap-x-4 gap-y-1 border-y border-red-100 bg-red-50/60 px-5 py-2 dark:border-red-900 dark:bg-red-950/20 sm:px-7">
                <a href="#{{ $reportId }}-accomplishments" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand underline-offset-4 hover:underline focus-visible:outline-brand dark:text-red-200">Accomplishments</a>
                @foreach ($sections as $field => $label)
                    @if (filled($report->$field) || $photos->where('section', $field)->isNotEmpty())
                        <a href="#{{ $reportId }}-{{ $field }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand underline-offset-4 hover:underline focus-visible:outline-brand dark:text-red-200">{{ $label }}</a>
                    @endif
                @endforeach
                <span class="inline-flex min-h-11 items-center text-sm text-gray-600 dark:text-slate-300">{{ $photos->count() }} {{ $photos->count() === 1 ? 'figure' : 'figures' }}</span>
            </nav>
            <div class="divide-y divide-gray-200 px-5 dark:divide-slate-700 sm:px-7">
                <section id="{{ $reportId }}-accomplishments" class="scroll-mt-36 space-y-5 py-7" aria-labelledby="{{ $reportId }}-accomplishments-heading">
                    <h5 id="{{ $reportId }}-accomplishments-heading" class="text-xl font-bold text-brand dark:text-red-200">Monitoring-period accomplishments</h5>
                    @if ($report->accomplishments)
                        <table class="block w-full text-left text-base leading-7 sm:table" data-report-accomplishments>
                            <thead class="hidden bg-red-50 text-brand dark:bg-red-950/40 dark:text-red-200 sm:table-header-group"><tr><th scope="col" class="p-3">Objective</th><th scope="col" class="p-3">Target accomplishment</th><th scope="col" class="p-3">Actual accomplishment</th></tr></thead>
                            <tbody class="block divide-y divide-red-100 dark:divide-red-900 sm:table-row-group">
                                @foreach ($report->accomplishments as $row)
                                    <tr class="block space-y-4 py-5 sm:table-row sm:space-y-0 sm:py-0">
                                        @foreach (['objective' => 'Objective', 'target' => 'Target accomplishment', 'actual' => 'Actual accomplishment'] as $key => $label)
                                            <td class="block break-words align-top sm:table-cell sm:w-1/3 sm:p-3"><span class="mb-1 block text-sm font-semibold text-brand dark:text-red-200 sm:hidden">{{ $label }}</span><span class="whitespace-pre-line text-gray-800 dark:text-slate-200">{{ $row[$key] ?? '' }}</span></td>
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
                            <h5 id="{{ $reportId }}-{{ $field }}-heading" class="text-xl font-bold text-brand dark:text-red-200">{{ $label }}</h5>
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
        </div>
        @if ($canReview || $report->research_head_remarks)
            <aside class="min-w-0 border-t border-red-200 bg-red-50/50 p-5 dark:border-red-900 dark:bg-red-950/10 xl:sticky xl:top-36 xl:self-start xl:border-b xl:border-l xl:border-t-0" aria-label="Research Head review">
                <div class="space-y-5">
                    <h5 class="text-lg font-bold text-brand dark:text-red-200">{{ $canReview ? 'Research Head review' : 'Research Head remarks' }}</h5>
                    @if ($canReview)
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="space-y-5" data-narrative-report-review>
                            @csrf
                            @method('PATCH')
                            <fieldset class="space-y-2">
                                <legend class="mb-2 text-sm font-semibold text-gray-700 dark:text-slate-200">Decision</legend>
                                @foreach (['reviewed' => 'Mark reviewed', 'revision_requested' => 'Request corrections'] as $value => $label)
                                    <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-red-200 bg-white px-3 py-3 text-base text-gray-900 has-[:checked]:border-brand has-[:checked]:bg-red-50 dark:border-red-900 dark:bg-slate-900 dark:text-white dark:has-[:checked]:bg-red-950/40">
                                        <input type="radio" name="review_status" value="{{ $value }}" required @checked($report->review_status === $value || ($report->review_status === 'pending' && $value === 'reviewed')) class="h-4 w-4 shrink-0 border-red-300 text-brand focus:ring-brand dark:border-red-800 dark:bg-slate-950">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            <div class="space-y-2">
                                <label for="narrative-review-notes-{{ $report->id }}" class="block text-sm font-semibold text-gray-700 dark:text-slate-200">Feedback</label>
                                <textarea id="narrative-review-notes-{{ $report->id }}" name="research_head_remarks" rows="6" maxlength="5000" placeholder="Write the feedback or corrections needed." class="block w-full rounded-lg border-red-200 bg-white text-base leading-7 text-gray-900 placeholder:text-gray-500 focus:border-brand focus:ring-brand dark:border-red-900 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-400">{{ $report->research_head_remarks }}</textarea>
                                @error('research_head_remarks')<p class="text-sm font-medium text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-brand px-5 py-3 text-base font-semibold text-white hover:bg-brand-soft focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Save review</button>
                        </form>
                    @else
                        <p class="whitespace-pre-line break-words text-base leading-7 text-gray-900 dark:text-slate-100">{{ $report->research_head_remarks }}</p>
                    @endif
                </div>
            </aside>
        @endif
    </div>
    @if ($canRecordSignedCopy)
        <form method="POST" action="{{ route('research_head.narrative-progress-reports.signed-copy.store', $report) }}" enctype="multipart/form-data" class="space-y-4 border-t border-red-200 p-5 dark:border-red-900 sm:p-6">
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
</article>

<x-modal name="narrative-pdf-{{ $report->id }}" maxWidth="6xl" focusable class="!z-[140]" data-narrative-pdf-preview-modal>
    <template x-if="show">
        <section role="dialog" aria-modal="true" aria-labelledby="narrative-pdf-heading-{{ $report->id }}">
            <header class="flex items-center justify-between gap-4 border-b border-red-200 bg-red-50 px-5 py-4 dark:border-red-900 dark:bg-red-950/30">
                <h3 id="narrative-pdf-heading-{{ $report->id }}" class="text-xl font-bold text-brand dark:text-red-200">{{ $report->report_label }} preview</h3>
                <button type="button" @click="$dispatch('close')" aria-label="Close PDF preview" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-red-200 bg-white text-brand hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand dark:border-red-800 dark:bg-slate-900 dark:text-red-200">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </header>
            <x-proposal-revision-pdf :configuration="['pdfUrl' => route('project-narrative-reports.view', $report), 'annotations' => [], 'canAnnotate' => false]" loading-label="Loading submitted report…" viewer-label="Submitted report PDF" class="!h-[75dvh]" />
        </section>
    </template>
</x-modal>
