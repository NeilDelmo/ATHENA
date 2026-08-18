<section id="project-monitoring" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
    @php
        $projectStatus = $topic->project_status ?: 'ongoing';
        $projectStatusClass = match ($projectStatus) { 'completed' => 'bg-green-50 text-green-700', 'delayed' => 'bg-red-50 text-red-700', default => 'bg-blue-50 text-blue-700' };
        $revisionProgressReport = $topic->progressReports->first(
            fn ($report): bool => $report->review_status === 'revision_requested' && $report->nextVersion === null,
        );
    @endphp
    @if ($topic->hasIssuedNoticeToProceed())
        <article class="border-b border-red-200 bg-red-50 px-6 py-5 dark:border-red-950 dark:bg-slate-950" aria-labelledby="official-notice-to-proceed-heading">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-red-700 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">Official project record</span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-red-800 dark:text-red-300">Notice to Proceed</span>
                    </div>
                    <h3 id="official-notice-to-proceed-heading" class="mt-2 text-lg font-black text-gray-950 dark:text-white">Notice to Proceed issued</h3>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-700 dark:text-slate-300">This is the authorization document for the active project. Keep it with the project record for future monitoring and reporting.</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="{{ route('topics.notice-to-proceed.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl bg-gray-950 px-4 py-2.5 text-xs font-black text-white transition hover:bg-black dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download PDF</a>
                    <a href="{{ route('topics.show', $topic) }}#notice-to-proceed" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-black text-gray-900 transition hover:bg-red-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">View notice details</a>
                </div>
            </div>
            <dl class="mt-4 grid gap-3 border-t border-red-200 pt-4 text-xs dark:border-red-950 sm:grid-cols-3">
                <div>
                    <dt class="font-black uppercase tracking-wider text-red-700 dark:text-red-300">Issued</dt>
                    <dd class="mt-1 font-bold text-gray-950 dark:text-white">{{ $topic->notice_to_proceed_issued_at?->format('M j, Y g:i A') ?: 'Not recorded' }}</dd>
                    @if ($topic->noticeIssuer)<dd class="mt-0.5 text-gray-600 dark:text-slate-300">by {{ $topic->noticeIssuer->name }}</dd>@endif
                </div>
                <div>
                    <dt class="font-black uppercase tracking-wider text-red-700 dark:text-red-300">Approved period</dt>
                    <dd class="mt-1 font-bold text-gray-950 dark:text-white">{{ data_get($topic->notice_to_proceed_data, 'approved_start_date', 'Not recorded') }} to {{ data_get($topic->notice_to_proceed_data, 'approved_end_date', 'Not recorded') }}</dd>
                </div>
                <div>
                    <dt class="font-black uppercase tracking-wider text-red-700 dark:text-red-300">Approved budget</dt>
                    <dd class="mt-1 font-bold text-gray-950 dark:text-white">PHP {{ number_format((float) data_get($topic->notice_to_proceed_data, 'approved_budget', 0), 2) }}</dd>
                </div>
            </dl>
        </article>
    @endif
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
        <div><h3 class="text-sm font-black text-gray-900">Project monitoring</h3><p class="mt-1 text-xs text-gray-500">Official monitoring tools and Research Head feedback.</p></div>
        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $projectStatusClass }}">{{ $projectStatus }}</span>
    </div>
    <div class="space-y-5 p-6">
        @if ($topic->isCompletedProject())
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                This project is complete. Monitoring records are available below in read-only mode.
            </div>
        @elseif (Auth::user()->isUsingWorkspace('research_head'))
            <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" class="flex flex-col gap-2 rounded-xl bg-gray-50 p-4 sm:flex-row sm:items-end">@csrf @method('PATCH')
                <label class="flex-1 text-[11px] font-bold uppercase text-gray-500">Execution status<select name="project_status" class="mt-1 block w-full rounded-xl border-gray-200 text-xs font-bold">@foreach (['ongoing', 'delayed', 'completed'] as $value)<option value="{{ $value }}" @selected($projectStatus === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
                <button class="rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white">Update status</button>
            </form>
        @else
            @if ($topic->isAccessibleTo(Auth::user()) && $projectStatus !== 'completed')
                <section aria-labelledby="monitoring-submissions-heading">
                    <div class="mb-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-red-600">Project monitoring</p>
                        <h3 id="monitoring-submissions-heading" class="mt-1 text-lg font-black text-gray-950 dark:text-white">Monitoring submissions</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Complete one official record at a time. Submitted versions stay below as the project history.</p>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <article class="grid gap-4 border-b border-gray-200 p-5 dark:border-slate-800 sm:grid-cols-[3rem_minmax(0,1fr)_auto] sm:items-center sm:px-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-950 text-xs font-black text-white dark:bg-white dark:text-gray-950" aria-label="Monitoring tool">01</div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black leading-6 text-gray-950 dark:text-white">Monitoring tool</h4>
                                    @if ($revisionProgressReport)
                                        <span class="rounded-full border border-red-200 bg-red-50 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">Revision requested</span>
                                    @else
                                        <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Ready to prepare</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">BatStateU-REC-RES-03 · Revision 03. Record the work plan, accomplishments, budget utilization, and any supporting attachment.</p>
                            </div>
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $revisionProgressReport?->id]) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-900 transition hover:border-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:border-red-600 dark:hover:text-red-300 sm:w-auto">
                                {{ $revisionProgressReport ? 'Revise tool' : 'Open monitoring tool' }}
                            </a>
                        </article>

                        <article class="grid gap-4 p-5 dark:border-slate-800 sm:grid-cols-[3rem_minmax(0,1fr)_auto] sm:items-center sm:px-6">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-950 text-xs font-black text-white dark:bg-white dark:text-gray-950" aria-label="Progress report">02</div>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black leading-6 text-gray-950 dark:text-white">Progress report</h4>
                                    <span class="rounded-full border border-gray-200 bg-white px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Ready to prepare</span>
                                </div>
                                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">BatStateU-REC-RES-02 · Revision 02. Record narrative accomplishments and captioned photo documentation for the reporting period.</p>
                            </div>
                            <a href="{{ route('project-narrative-reports.create', $topic) }}" class="inline-flex w-full shrink-0 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-xs font-bold text-gray-900 transition hover:border-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-white dark:hover:border-red-600 dark:hover:text-red-300 sm:w-auto">Open progress report</a>
                        </article>
                    </div>
                </section>
            @endif
        @endif

        <x-monitoring-quarter-overview :quarter-rows="$monitoringQuarterRows" />

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Monitoring tool versions</p><p class="mt-1 text-xs text-gray-400">Submitted work plan and budget utilization records.</p></div>
            </div>
            @forelse ($topic->progressReports as $report)
                @php
                    $isCurrentVersion = $report->nextVersion === null;
                    $reviewStatusClass = match ($report->review_status) {
                        'reviewed' => 'bg-green-50 text-green-700',
                        'revision_requested' => 'bg-red-50 text-red-700',
                        default => 'bg-amber-50 text-amber-700',
                    };
                @endphp
                <article id="monitoring-tool-{{ $report->id }}" class="scroll-mt-32 rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-black text-gray-900">{{ $report->quarter_label }} Monitoring Tool · {{ $report->version_label }}</p>
                                <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $isCurrentVersion ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-500' }}">{{ $isCurrentVersion ? 'Current submission' : 'Historical version' }}</span>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->reporting_period_label }} · Submitted {{ $report->submitted_at?->format('M d, Y g:i A') ?? $report->created_at->format('M d, Y g:i A') }} by {{ $report->submitter->name }}</p>
                        </div>
                        <div class="flex flex-wrap items-start gap-2">
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ $report->progress_percentage }}% complete</span>
                            <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $reviewStatusClass }}">{{ str_replace('_', ' ', $report->review_status) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-red-600" style="width: {{ $report->progress_percentage }}%"></div></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><p class="text-[10px] font-black uppercase text-gray-400">Accomplishments</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishments }}</p></div><div><p class="text-[10px] font-black uppercase text-gray-400">Issues or delays</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->issues ?: 'None reported.' }}</p></div></div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        @if (is_array($report->work_plan) && is_array($report->budget_utilization))
                            <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download official monitoring tool</a>
                        @endif
                        @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download supporting attachment</a>@endif
                        @if (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex text-xs font-bold text-red-700">Revise this {{ $report->quarter_label }} submission</a>
                        @endif
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks @if ($report->reviewer) · {{ $report->reviewer->name }} @endif</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion)
                        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No monitoring tools yet</p><p class="mt-1 text-xs text-gray-400">The first faculty submission will appear here.</p></div>
            @endforelse
        </div>

        <div class="space-y-4 border-t border-gray-100 pt-5">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Progress report versions</p><p class="mt-1 text-xs text-gray-400">Submitted narrative accomplishment records with captioned photo documentation.</p></div>
            </div>
            @forelse ($topic->narrativeReports as $report)
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-gray-900">Progress report</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->submission_date->format('M d, Y') }} · {{ $report->submitter->name }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ str_replace('_', ' ', $report->review_status) }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Monitoring-period accomplishment</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishment_summary }}</p></div>
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Funding agency</p><p class="mt-1 text-xs leading-5 text-gray-600">{{ $report->funding_agency }}</p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <a href="{{ route('project-narrative-reports.download', $report) }}" class="inline-flex text-xs font-bold text-red-700">Download official progress report</a>
                        @foreach ($report->photos ?? [] as $photoIndex => $photo)
                            <a href="{{ route('project-narrative-reports.photos.download', [$report, $photoIndex]) }}" class="inline-flex text-xs font-bold text-red-700">Photo {{ $photoIndex + 1 }}: {{ $photo['caption'] }}</a>
                        @endforeach
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No progress reports yet</p><p class="mt-1 text-xs text-gray-400">The first narrative progress report will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
