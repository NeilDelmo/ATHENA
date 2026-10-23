<section id="project-monitoring" class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
    @php
        $storedProjectStatus = $topic->project_status ?: 'ongoing';
        $latestProgressPercentage = $projectHealth ? $projectHealth['progress'] : $topic->progressReports->first()?->progress_percentage;
        $monitoringIssues = $projectHealth['issues'] ?? collect();
        if ($monitoringIssues->whereIn('type', ['quarterly', 'progress_quarterly', 'report_review', 'narrative_review', 'terminal', 'terminal_review'])->isNotEmpty()) {
            $monitoringIssues = $monitoringIssues->reject(fn ($issue) => $issue['type'] === 'completion');
        }
        $reviewIssues = $monitoringIssues->whereIn('type', ['report_review', 'narrative_review', 'terminal_review']);
        $reviewGroups = $reviewIssues->groupBy(fn ($issue) => $issue['review_status'] === 'revision_requested'
            ? 'Corrections needed'
            : (($issue['review_stage'] ?? 'head') === 'secretary' ? 'Office review' : 'Head review'));
        $otherIssues = $monitoringIssues->whereNotIn('type', ['report_review', 'narrative_review', 'terminal_review']);
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $latestTerminalReport = $topic->narrativeReports->where('report_type', 'terminal')->sortByDesc('id')->first();
        $nextPeriod = $monitoringQuarterRows->first(function ($row) use ($progressQuarterRows) {
            $progressReport = $progressQuarterRows->firstWhere('quarter', $row['quarter'])['report'] ?? null;

            $progressRow = $progressQuarterRows->firstWhere('quarter', $row['quarter']);

            return ! ($row['has_submitted_report'] ?? $row['report']?->isSubmitted())
                || $row['report']?->review_status === 'revision_requested'
                || ! ($progressRow['has_submitted_report'] ?? $progressReport?->isSubmitted())
                || $progressReport?->review_status === 'revision_requested';
        });
        $nextProgressRow = $nextPeriod ? $progressQuarterRows->firstWhere('quarter', $nextPeriod['quarter']) : null;
        $nextPeriodHasMissingReport = $nextPeriod && (! ($nextPeriod['has_submitted_report'] ?? $nextPeriod['report']?->isSubmitted())
            || ! ($nextProgressRow['has_submitted_report'] ?? $nextProgressRow['report']?->isSubmitted()));
        $nextSubmission = match (true) {
            $topic->isCompletedProject() => 'Reporting closed',
            $projectHealth && ! $projectHealth['has_schedule'] => 'Schedule not recorded',
            $nextPeriod && ! $nextPeriodHasMissingReport => $nextPeriod['label'].' · Corrections needed',
            $nextPeriod && $nextPeriod['end']->isPast() => $nextPeriod['label'].' · Overdue',
            $nextPeriod !== null => $nextPeriod['label'].' · '.($nextPeriod['reporting_date'] ? 'Open now' : 'Opens '.$nextPeriod['opens_at']->format('M j, Y')),
            $latestTerminalReport?->review_status === 'revision_requested' => 'Terminal · Corrections needed',
            $latestTerminalReport !== null => 'No submissions pending',
            default => $terminalOpen ? 'Terminal Report' : 'Terminal · Opens '.$terminalDate->format('M j, Y'),
        };
        $canReport = Auth::user()->isUsingWorkspace('faculty_researcher') && $topic->isMonitoringAvailable() && $topic->isAccessibleTo(Auth::user());
        $canAssignProjectSecretary = Auth::id() === $topic->user_id
            && ! Auth::user()->isUsingWorkspace('research_head')
            && $topic->isMonitoringAvailable();
        $hasBudgetTasks = ! Auth::user()->isUsingWorkspace('research_head')
            && $topic->canPrepareMonitoringBudget(Auth::user())
            && $topic->preparedProgressReports->isNotEmpty();
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

    <header data-monitoring-overview class="rounded-t-xl border-t-4 border-t-brand bg-brand-wash px-5 py-5 dark:border-t-brand-soft dark:bg-slate-800 sm:px-6">
        <div data-project-monitoring-heading data-monitoring-actions class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-2xl font-bold tracking-tight text-brand dark:text-white">{{ $topic->isCompletedProject() ? 'Project archive' : 'Project monitoring' }}</h3>
                @if ($topic->isCompletedProject())
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Reports are read-only.</p>
                @endif
            </div>
    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
        <div
            x-data="projectStatusManager(@js(old('project_status', $storedProjectStatus)), @js($errors->has('project_status') || $errors->has('completion_confirmed')))"
            x-cloak
            x-on:keydown.escape.window="if (statusManagerOpen) { statusManagerOpen = false; $refs.statusTrigger.focus() }"
            class="contents print:hidden"
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
                class="basis-full rounded-lg border border-slate-300 bg-white p-4 dark:border-slate-600 dark:bg-slate-900"
                role="region"
                aria-labelledby="project-status-manager-heading-{{ $topic->id }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 id="project-status-manager-heading-{{ $topic->id }}" class="text-lg font-semibold text-gray-950 dark:text-white">Manage project status</h4>
                        <p class="mt-1 text-base leading-6 text-gray-500 dark:text-slate-400">Completion requires 100% progress, accepted quarterly reports, a reviewed Terminal Report, and its fully signed PDF.</p>
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
        </div>
        <dl data-project-monitoring-details class="mt-4 grid grid-cols-2 gap-x-5 gap-y-4 border-t border-brand/10 pt-4 dark:border-slate-700 sm:grid-cols-4">
            <div><dt class="text-base text-slate-600 dark:text-slate-400">Monitoring starts</dt><dd class="mt-1 text-base font-semibold text-slate-900 dark:text-white">{{ $window['start']->format('M j, Y') }}</dd></div>
            <div><dt class="text-base text-slate-600 dark:text-slate-400">Project ends</dt><dd class="mt-1 text-base font-semibold text-slate-900 dark:text-white">{{ $projectHealth && ! $projectHealth['has_schedule'] ? 'Not recorded' : $window['end']->format('M j, Y') }}</dd></div>
            <div><dt class="text-base text-slate-600 dark:text-slate-400">Next submission</dt><dd class="mt-1 text-base font-semibold text-slate-900 dark:text-white">{{ $nextSubmission }}</dd></div>
            <div><dt class="text-base text-slate-600 dark:text-slate-400">Latest reported progress</dt><dd class="mt-1 text-base font-semibold tabular-nums text-slate-900 dark:text-white">{{ $latestProgressPercentage !== null ? $latestProgressPercentage.'%' : 'No report yet' }}</dd></div>
        </dl>
        @if ($projectHealth && ! $projectHealth['has_schedule'])
            <p class="mt-4 text-base text-amber-800 dark:text-amber-300">No project schedule recorded. Project deadline checks are unavailable.</p>
        @endif
        @if ($monitoringIssues->isNotEmpty() || count($overdueWorkPlanActivities ?? []) > 0)
            <details data-monitoring-attention class="monitoring-disclosure mt-4 border-t border-brand/10 dark:border-slate-700">
                <summary class="py-2">
                    <span class="flex flex-wrap items-center gap-x-3 gap-y-1"><span class="text-red-700 dark:text-red-300">Needs attention</span><span class="font-normal text-slate-600 dark:text-slate-400">{{ $monitoringIssues->count() + count($overdueWorkPlanActivities ?? []) }} {{ Str::plural('item', $monitoringIssues->count() + count($overdueWorkPlanActivities ?? [])) }}</span></span>
                    <svg class="h-4 w-4 shrink-0 transition-transform [[open]>summary>&]:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="monitoring-attention-items grid min-w-0 gap-x-6 gap-y-2 pb-3 xl:grid-cols-2">
                @foreach ($otherIssues as $issue)
                    @php
                        $taskUrl = match ($issue['type']) {
                            'quarterly', 'progress_quarterly', 'delayed' => '#progress-reports',
                            'terminal', 'completion' => '#terminal-reports',
                            'budget' => '#monitoring-tool-'.$projectHealth['report_id'],
                            default => $issue['url'],
                        };
                    @endphp
                    <a data-monitoring-task-link href="{{ $taskUrl }}" class="flex min-h-11 min-w-0 items-center gap-3 rounded-md py-1 text-base text-red-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700 dark:text-red-200">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v6m0 4h.01"/></svg>
                        <span class="min-w-0"><span class="block font-semibold">{{ $issue['issue'] }}</span><span class="block text-base font-normal text-slate-600 dark:text-slate-300">{{ in_array($issue['type'], ['quarterly', 'progress_quarterly']) ? 'Oldest missing report · ' : '' }}{{ $issue['days'] !== null ? $issue['days'].' '.str('day')->plural($issue['days']).' '.$issue['basis'] : $issue['basis'] }}</span></span>
                    </a>
                @endforeach
                @foreach ($reviewGroups as $label => $issues)
                    <details data-monitoring-review-group="{{ $label }}" class="monitoring-disclosure group min-w-0">
                        <summary>
                            <span class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1"><span>{{ $label }}</span><span class="font-normal text-slate-600 dark:text-slate-300">{{ $issues->count() }} {{ str('report')->plural($issues->count()) }}</span></span>
                            <svg class="h-5 w-5 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <ul class="divide-y divide-red-100 border-t border-red-100 dark:divide-red-900 dark:border-red-900">
                            @foreach ($issues as $issue)
                                <li><a data-monitoring-task-link href="{{ Auth::user()->isUsingWorkspace('research_head') && $issue['review_stage'] === 'secretary' ? '#progress-reports' : $issue['url'] }}" class="flex min-h-11 flex-col justify-center gap-1 rounded-md py-2 text-base hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-700"><span class="font-medium text-red-800 dark:text-red-200">{{ str($issue['issue'])->before(' · ') }}</span><span class="text-slate-600 dark:text-slate-300">{{ $issue['days'] !== null ? $issue['days'].' '.str('day')->plural($issue['days']).' '.$issue['basis'] : $issue['basis'] }}</span></a></li>
                            @endforeach
                        </ul>
                    </details>
                @endforeach
                <x-monitoring-work-plan-alert :activities="$overdueWorkPlanActivities ?? []" />
                </div>
            </details>
        @endif
    </header>

    <x-monitoring-quarter-overview :quarter-rows="$monitoringQuarterRows" :progress-quarter-rows="$progressQuarterRows" :topic="$topic" :can-report="$canReport" />

    <section id="terminal-reports" aria-labelledby="terminal-reports-heading" class="border-t border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/40">
        @if ($latestTerminalReport)
            <x-narrative-report-summary :report="$latestTerminalReport" heading-id="terminal-reports-heading" class="!border-0" />
        @else
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 py-5 sm:px-6">
            <div class="max-w-2xl">
                <h3 id="terminal-reports-heading" class="text-lg font-bold text-slate-950 dark:text-white">Terminal Report</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $topic->isCompletedProject() ? 'Final report and signed document.' : 'Project completion requires 100% progress, accepted quarterly reports, Research Head review of the Terminal Report, and its fully signed PDF.' }}</p>
                @if (! $topic->isCompletedProject())
                    <p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $terminalOpen ? 'Submission is open after all quarterly reports are complete.' : 'Submission opens '.$terminalDate->format('M j, Y').'. You can draft now.' }}</p>
                @endif
            </div>
            @if ($canReport)
                <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'terminal']) }}" aria-label="Open terminal report" class="monitoring-secondary-action">{{ $terminalOpen ? 'Start terminal report' : 'Draft terminal report' }}</a>
            @endif
        </header>
        @endif
        @if ($canReport && $latestTerminalReport?->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVISION_REQUESTED)
            <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => 'terminal']) }}" aria-label="Revise terminal report" class="rh-button !rounded-lg">Revise terminal report</a>
            </div>
        @endif
    </section>

    @if (! Auth::user()->isUsingWorkspace('research_head'))
    <details data-monitoring-budget-section class="monitoring-disclosure border-t border-slate-200 dark:border-slate-700" @if ($errors->has('research_secretary_id') || $hasBudgetTasks) open @endif>
        <summary class="bg-slate-100/80 px-5 py-3 dark:bg-slate-800/60 sm:px-6">
            <span class="flex flex-wrap items-center gap-x-3 gap-y-1"><span id="project-secretary-heading">Monitoring budget</span><span class="text-sm font-normal text-slate-600 dark:text-slate-400">{{ $topic->researchSecretary?->name ?? 'No secretary assigned' }}</span></span>
            <svg class="h-4 w-4 shrink-0 transition-transform [[open]>summary>&]:rotate-180" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
        </summary>
        <div class="px-5 py-5 sm:px-6">
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,auto)]">
            <div class="min-w-0 max-w-xl">
                <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $topic->isCompletedProject() ? 'Budget utilization is recorded in the submitted Monitoring Tools.' : 'The project secretary or confirmed project staff completes and confirms budget utilization before submission.' }}</p>
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
                        <span class="min-w-0"><span class="block text-base font-semibold text-slate-900 dark:text-white">{{ $topic->researchSecretary->name }}</span><span class="block break-all text-sm text-slate-500 dark:text-slate-400">{{ $topic->researchSecretary->email }}</span></span>
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
        @if ($hasBudgetTasks)
            <div data-monitoring-budget-tasks class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                @foreach ($topic->preparedProgressReports->sortBy('reporting_date') as $budgetReport)
                    <a data-monitoring-budget-task="{{ $budgetReport->id }}" href="{{ route(Auth::user()->isUsingWorkspace('research_secretary') ? 'research_secretary.projects.budget.edit' : 'project-budget.edit', [$topic, $budgetReport]) }}" class="monitoring-secondary-action">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 6v12m3-10h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9" /></svg>
                        {{ $budgetReport->quarter_label }} · {{ $budgetReport->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}
                    </a>
                @endforeach
            </div>
        @endif
        </div>
    </details>
    @endif

    @if ($reportHistoryHashes->isNotEmpty())
        <details id="narrative-report-history" class="monitoring-disclosure group border-t border-slate-200 dark:border-slate-700" x-data="{ historyOpen: @js($reportHistoryHashes).includes(window.location.hash) }" x-bind:open="historyOpen" x-on:toggle="historyOpen = $el.open" x-on:hashchange.window="if (@js($reportHistoryHashes).includes(window.location.hash)) { historyOpen = true }">
            <summary class="bg-slate-100/80 px-5 py-3 dark:bg-slate-800/60 sm:px-6">
                <span>Report history <span class="ml-2 font-normal text-slate-600 dark:text-slate-400">{{ $reportHistoryHashes->count() }} previous {{ Str::plural('submission', $reportHistoryHashes->count()) }}</span></span>
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
