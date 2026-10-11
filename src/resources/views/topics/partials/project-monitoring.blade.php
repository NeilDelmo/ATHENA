<section id="project-monitoring" class="space-y-5">
    @php
        $storedProjectStatus = $topic->project_status ?: 'ongoing';
        $latestProgressPercentage = $projectHealth ? $projectHealth['progress'] : $topic->progressReports->first()?->progress_percentage;
        $projectStatus = $projectHealth['status'] ?? $topic->monitoringStatusForProgress($latestProgressPercentage);
        $projectStatusLabel = match ($projectStatus) {
            'delayed' => 'Delayed / overdue',
            'awaiting' => 'Awaiting required report / review',
            'completed' => 'Completed',
            'ongoing' => 'Ongoing',
            default => $topic->monitoringStatusLabelForProgress($latestProgressPercentage),
        };
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $nextPeriod = $monitoringQuarterRows->first(function ($row) use ($progressQuarterRows) {
            $progressReport = $progressQuarterRows->firstWhere('quarter', $row['quarter'])['report'] ?? null;

            return $row['report'] === null
                || $row['report']->review_status === 'revision_requested'
                || $progressReport === null
                || $progressReport->isPrepared()
                || $progressReport->review_status === 'revision_requested';
        });
        $nextSubmission = match (true) {
            $topic->isCompletedProject() => 'Reporting closed',
            $projectHealth && ! $projectHealth['has_schedule'] => 'Schedule not recorded',
            $nextPeriod !== null => $nextPeriod['label'].' · '.($nextPeriod['reporting_date'] ? 'Open now' : $nextPeriod['opens_at']->format('M j, Y')),
            default => 'Terminal Report',
        };
        $canReport = ! Auth::user()->isUsingWorkspace('research_head') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(Auth::user());
        $canAssignProjectSecretary = Auth::id() === $topic->user_id
            && ! Auth::user()->isUsingWorkspace('research_head')
            && $topic->isMonitoringAvailable();
        $projectSecretaryCandidates = $topic->collaborators
            ->filter(fn ($collaborator) => $collaborator->accepted_at !== null && $collaborator->user !== null)
            ->pluck('user')
            ->unique('id')
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'college' => $user->college,
            ])
            ->values();
        $latestTerminalReport = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
        $currentProgressReportIds = $progressQuarterRows->pluck('report')->filter()->pluck('id');
        $currentMonitoringReportIds = $monitoringQuarterRows->map(function ($row) use ($topic, $canReport) {
            $preparedReport = $canReport ? $topic->preparedProgressReports->first(fn ($report) => $report->reporting_date->betweenIncluded($row['start'], $row['end'])) : null;

            return ($preparedReport ?? $row['report'])?->id;
        })->filter();
        $monitoringReportHistory = $topic->progressReports->reject(fn ($report) => $currentMonitoringReportIds->contains($report->id));
        $narrativeReportHistory = $topic->narrativeReports->reject(fn ($report) =>
            $report->id === $latestTerminalReport?->id
            || $currentProgressReportIds->contains($report->id)
        );
        $reportHistoryHashes = $monitoringReportHistory->toBase()->map(fn ($report) => '#monitoring-tool-'.$report->id)
            ->merge($narrativeReportHistory->map(fn ($report) => '#narrative-report-'.$report->id))->values();
    @endphp

    <header data-monitoring-overview class="rounded-xl border border-slate-300 bg-slate-100 px-5 py-5 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
        <div data-project-monitoring-heading class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $topic->isCompletedProject() ? 'Project archive' : 'Project monitoring' }}</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $topic->isCompletedProject() ? 'Completed project archive. Reports are read-only.' : 'Track quarterly submissions and prepare the final project report.' }}</p>
            </div>
            <span class="rounded-lg px-3 py-1.5 text-sm font-semibold border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ $projectStatusLabel }}</span>
        </div>
        <dl data-project-monitoring-details class="mt-4 grid grid-cols-2 gap-x-5 gap-y-4 border-t border-slate-100 pt-4 dark:border-slate-800 sm:grid-cols-4">
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Monitoring starts</dt><dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $window['start']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Project ends</dt><dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $projectHealth && ! $projectHealth['has_schedule'] ? 'Not recorded' : $window['end']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Next submission</dt><dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $nextSubmission }}</dd></div>
            <div><dt class="text-xs text-slate-500 dark:text-slate-400">Overall project progress</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $latestProgressPercentage !== null ? $latestProgressPercentage.'%' : 'No report yet' }}</dd></div>
        </dl>
        @if ($projectHealth && ! $projectHealth['has_schedule'])
            <p class="mt-4 text-sm text-amber-800 dark:text-amber-300">No project schedule recorded. Project deadline checks are unavailable.</p>
        @endif
        @if ($projectHealth && $projectHealth['issues']->isNotEmpty())
            <ul class="mt-4 space-y-2 text-sm text-slate-700 dark:text-slate-300" aria-label="Project monitoring issues">
                @foreach ($projectHealth['issues'] as $issue)
                    <li><a href="{{ $issue['url'] }}" class="font-semibold hover:text-brand">{{ $issue['issue'] }}</a> <span class="text-slate-500 dark:text-slate-400">· {{ $issue['days'] !== null ? $issue['days'].' '.str('day')->plural($issue['days']).' '.$issue['basis'] : $issue['basis'] }}</span></li>
                @endforeach
            </ul>
        @endif
        @if ($topic->hasIssuedNoticeToProceed())
            <a data-monitoring-notice-link href="{{ route('topics.show', $topic) }}#notice-to-proceed" class="monitoring-secondary-action mt-4">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 15l2 2 5-5" /></svg>
                Notice to Proceed &amp; signed papers
            </a>
        @endif
    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
        <div
            x-data="projectStatusManager(@js(old('project_status', $storedProjectStatus)), @js($errors->has('project_status') || $errors->has('completion_confirmed')))"
            x-cloak
            x-on:keydown.escape.window="if (statusManagerOpen) { statusManagerOpen = false; $refs.statusTrigger.focus() }"
            class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700 print:hidden"
            data-project-status-manager
        >
            <div class="flex items-center">
                <button
                    type="button"
                    x-ref="statusTrigger"
                    x-on:click="statusManagerOpen = ! statusManagerOpen"
                    x-bind:aria-expanded="statusManagerOpen"
                    aria-controls="project-status-manager-{{ $topic->id }}"
                    class="monitoring-secondary-action"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M4 7h16M4 17h16" stroke-linecap="round"/><circle cx="9" cy="7" r="3" fill="currentColor"/><circle cx="15" cy="17" r="3" fill="currentColor"/></svg>
                    <span class="min-w-0 break-words">Manage status</span>
                </button>
            </div>
            <section
                id="project-status-manager-{{ $topic->id }}"
                x-cloak
                x-show="statusManagerOpen"
                x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                x-transition:enter-start="translate-y-3 opacity-0"
                x-transition:enter-end="translate-y-0 opacity-100"
                x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                x-transition:leave-start="translate-y-0 opacity-100"
                x-transition:leave-end="translate-y-3 opacity-0"
                class="mt-3 rounded-lg border border-slate-300 bg-white p-4 dark:border-slate-600 dark:bg-slate-900"
                role="region"
                aria-labelledby="project-status-manager-heading-{{ $topic->id }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 id="project-status-manager-heading-{{ $topic->id }}" class="text-lg font-semibold text-gray-950 dark:text-white">Manage project status</h4>
                        <p class="mt-1 text-base leading-6 text-gray-500 dark:text-slate-400">Completion requires 100% progress, a reviewed Terminal Report, and its fully signed PDF.</p>
                    </div>
                    <button type="button" x-on:click="statusManagerOpen = false; $refs.statusTrigger.focus()" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close status manager">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                @error('project_status')<p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 dark:bg-red-950/50 dark:text-red-200">{{ $message }}</p>@enderror

                <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" @submit.prevent="submitStatus($event)" class="mt-4 space-y-3" data-project-status-form>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="completion_confirmed" value="0">
                    <label for="project-status-{{ $topic->id }}" class="block text-base font-semibold text-gray-700 dark:text-slate-200">Status</label>
                    <div class="flex flex-col gap-2">
                        <select id="project-status-{{ $topic->id }}" name="project_status" x-model="selectedStatus" class="h-11 w-full min-w-0 rounded-xl border-gray-300 bg-white text-base font-semibold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            @foreach (['ongoing', 'delayed', 'completed'] as $value)
                                <option value="{{ $value }}" @selected($storedProjectStatus === $value)>{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                        <button :disabled="submitting" class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-base font-semibold text-white transition hover:bg-red-800 disabled:opacity-50">Save status</button>
                    </div>
                    <p x-show="selectedStatus === 'completed'" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-3 text-base leading-6 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">Completion is final. The status cannot be changed back, and monitoring reports become read-only.</p>
                    @error('completion_confirmed')<p role="alert" class="text-sm text-red-700 dark:text-red-200">{{ $message }}</p>@enderror
                </form>
            </section>

        </div>
    @endif

    </header>

    <x-monitoring-work-plan-alert :activities="$overdueWorkPlanActivities ?? []" />

    @if (! Auth::user()->isUsingWorkspace('research_head'))
    <section data-monitoring-budget-section aria-labelledby="project-secretary-heading" class="rounded-xl border border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,auto)]">
            <div class="min-w-0 max-w-xl">
                <h4 id="project-secretary-heading" class="text-base font-semibold text-slate-950 dark:text-white">Monitoring budget</h4>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $topic->isCompletedProject() ? 'Budget utilization is recorded in the submitted Monitoring Tools.' : 'The project secretary completes and confirms budget utilization before the Monitoring Tool is submitted. Confirmed project staff can also complete it.' }}</p>
            </div>
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                @if ($topic->researchSecretary)
                    <div data-project-secretary-assignment class="flex min-w-0 items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            @if ($topic->researchSecretary->avatar)
                                <img src="{{ $topic->researchSecretary->avatar }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ collect(explode(' ', $topic->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            @endif
                        </span>
                        <span class="min-w-0"><span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $topic->researchSecretary->name }}</span><span class="block break-all text-xs text-slate-500 dark:text-slate-400">{{ $topic->researchSecretary->email }}</span></span>
                    </div>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-300">No project secretary assigned.</p>
                @endif
                @if ($canAssignProjectSecretary)
                    <div x-data="researchSecretaryPicker({ candidates: @js($projectSecretaryCandidates), selectedId: @js($topic->research_secretary_id) })" class="relative w-full sm:w-auto" @keydown.escape.stop="open = false; $refs.secretaryTrigger.focus()" data-project-secretary-picker>
                        <form x-ref="form" method="POST" action="{{ route('project-secretary.assign', $topic) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="research_secretary_id" :value="selectedId || ''">
                        </form>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" x-ref="secretaryTrigger" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="monitoring-secondary-action">{{ $topic->researchSecretary ? 'Change secretary' : 'Select project staff' }}</button>
                            @if ($topic->researchSecretary)
                                <button type="button" @click="clearSelection" class="monitoring-secondary-action">Remove</button>
                            @endif
                        </div>
                        @error('research_secretary_id')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror

                        <div x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" class="absolute left-0 z-30 mt-2 w-[min(20rem,calc(100vw-4rem))] rounded-xl border border-gray-200 bg-white p-3 shadow-2xl shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900 sm:left-auto sm:right-0">
                            <label class="sr-only" for="project-secretary-search-{{ $topic->id }}">Search confirmed project staff</label>
                            <input x-ref="search" id="project-secretary-search-{{ $topic->id }}" x-model="query" type="search" autocomplete="off" placeholder="Search confirmed project staff" class="block w-full rounded-xl border-gray-200 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox">
                                <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                    <button type="button" role="option" @click="select(candidate.id)" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-slate-50 focus:bg-slate-50 focus:outline-none dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-xs font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600"><img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover"><span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-gray-900 dark:text-white" x-text="candidate.name"></span><span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block truncate text-[10px] font-bold uppercase tracking-wide text-gray-400" x-text="candidate.college"></span></span>
                                    </button>
                                </template>
                                <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-xs font-semibold text-gray-500">No confirmed project staff matches this search.</p>
                            </div>
                        </div>
                    </div>
                @elseif (! $topic->isCompletedProject() && ! Auth::user()->isUsingWorkspace('research_head') && Auth::id() !== $topic->research_secretary_id)
                    <p class="mt-2 text-sm text-gray-600 dark:text-slate-300">Only the project leader can change this assignment.</p>
                @endif
            </div>
        </div>
        @if (! Auth::user()->isUsingWorkspace('research_head') && $topic->canPrepareMonitoringBudget(Auth::user()) && $topic->preparedProgressReports->isNotEmpty())
            <div data-monitoring-budget-tasks class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                @foreach ($topic->preparedProgressReports->sortBy('reporting_date') as $budgetReport)
                    <a data-monitoring-budget-task="{{ $budgetReport->id }}" href="{{ route(Auth::user()->isUsingWorkspace('research_secretary') ? 'research_secretary.projects.budget.edit' : 'project-budget.edit', [$topic, $budgetReport]) }}" class="monitoring-secondary-action">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 6v12m3-10h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" /></svg>
                        {{ $budgetReport->quarter_label }} · {{ $budgetReport->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}
                    </a>
                @endforeach
            </div>
        @endif
    </section>
    @endif

    <x-monitoring-quarter-overview :quarter-rows="$monitoringQuarterRows" :progress-quarter-rows="$progressQuarterRows" :topic="$topic" :can-report="$canReport" />

    <section id="terminal-reports" aria-labelledby="terminal-reports-heading" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        @if ($latestTerminalReport)
            <x-narrative-report-summary :report="$latestTerminalReport" heading-id="terminal-reports-heading" class="!border-0" />
        @else
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 py-5 sm:px-6">
            <div class="max-w-2xl">
                <h3 id="terminal-reports-heading" class="text-lg font-bold text-slate-950 dark:text-white">Terminal Report</h3>
                <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $topic->isCompletedProject() ? 'Final report and signed document for the completed project.' : 'Close the project with a final report, Research Head review, and signed PDF.' }}</p>
                @if (! $topic->isCompletedProject())
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $terminalOpen ? 'Submission is open. Complete all quarterly reports before submitting.' : 'Draft now. Submission opens '.$terminalDate->format('M j, Y').'.' }}</p>
                @endif
            </div>
            @if ($canReport)
                <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'terminal']) }}" aria-label="Open terminal report" class="rh-button !rounded-lg">{{ $terminalOpen ? 'Start terminal report' : 'Draft terminal report' }}</a>
            @endif
        </header>
        @endif
        @if ($canReport && $latestTerminalReport?->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVISION_REQUESTED)
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'terminal']) }}" aria-label="Revise terminal report" class="rh-button !rounded-lg">Revise terminal report</a>
            </div>
        @endif
    </section>

    @if ($reportHistoryHashes->isNotEmpty())
        <details id="narrative-report-history" class="monitoring-disclosure group rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900" x-data="{ historyOpen: @js($reportHistoryHashes).includes(window.location.hash) }" x-bind:open="historyOpen" x-on:toggle="historyOpen = $el.open" x-on:hashchange.window="if (@js($reportHistoryHashes).includes(window.location.hash)) { historyOpen = true }">
            <summary class="px-5 py-3 sm:px-6">
                <span>Report history <span class="ml-2 font-normal text-slate-500 dark:text-slate-400">{{ $reportHistoryHashes->count() }} previous {{ Str::plural('submission', $reportHistoryHashes->count()) }}</span></span>
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14" /><path class="group-open:hidden" stroke-linecap="round" d="M12 5v14" /></svg>
            </summary>
            <div class="space-y-3 border-t border-slate-100 p-5 dark:border-slate-800 sm:p-6">
                @foreach ($monitoringReportHistory->sortBy('reporting_date') as $report)
                    <article id="monitoring-tool-{{ $report->id }}" class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <x-monitoring-tool-report-details :topic="$topic" :report="$report" />
                    </article>
                @endforeach
                @foreach ($narrativeReportHistory->sortBy('submission_date') as $report)
                    <x-narrative-report-summary :report="$report" />
                @endforeach
            </div>
        </details>
    @endif
</section>
