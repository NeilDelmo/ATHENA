<section id="project-monitoring" class="space-y-5">
    @php
        $storedProjectStatus = $topic->project_status ?: 'ongoing';
        $latestProgressPercentage = $topic->progressReports->first()?->progress_percentage;
        $projectStatus = $topic->monitoringStatusForProgress($latestProgressPercentage);
        $projectStatusLabel = $topic->monitoringStatusLabelForProgress($latestProgressPercentage);
        $schedule = app(\App\Services\MonitoringQuarterService::class);
        $window = $schedule->reportingWindow($topic);
        $terminalDate = $schedule->terminalOpensAt($topic);
        $terminalOpen = $schedule->canSubmitTerminal($topic);
        $openPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] !== null);
        $nextPeriod = $monitoringQuarterRows->first(fn ($row) => $row['reporting_date'] === null);
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
        $latestTerminalReportId = $topic->narrativeReports
            ->where('report_type', 'terminal')
            ->sortByDesc('id')
            ->first()?->id;
    @endphp
    <header class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-bold text-gray-950 dark:text-white">Project monitoring</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Report every three months. Submit the terminal report after the project ends.</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $projectStatus === 'completion_pending' ? 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-200' : 'bg-gray-100 text-gray-700 dark:bg-slate-800 dark:text-slate-200' }}">{{ $projectStatusLabel }}</span>
        </div>
        <dl class="mt-5 grid gap-4 border-t border-gray-100 pt-4 dark:border-slate-800 sm:grid-cols-3">
            <div><dt class="text-xs text-gray-500">Monitoring starts</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $window['start']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Project ends</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $window['end']->format('M j, Y') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Next period opens</dt><dd class="mt-1 text-sm font-semibold dark:text-white">{{ $nextPeriod ? $nextPeriod['opens_at']->format('M j, Y') : 'All periods have ended' }}</dd></div>
        </dl>
        @if ($topic->hasIssuedNoticeToProceed())
            <a href="{{ route('topics.show', $topic) }}#notice-to-proceed" class="mt-4 inline-block text-xs font-semibold text-red-700 dark:text-red-300">View Notice to Proceed and signed papers</a>
        @endif
        @if ($topic->isCompletedProject())
            <p class="mt-4 text-sm text-gray-600 dark:text-slate-300">Project completed. Reports remain available as read-only records. Conference activity and publication tracking continue in Conferences &amp; Publications.</p>
        @endif
    </header>

    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900 sm:p-6" aria-labelledby="project-secretary-heading">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-xl">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-700 dark:text-red-300">Project team role</p>
                <h4 id="project-secretary-heading" class="mt-1 text-base font-black text-gray-950 dark:text-white">Project Secretary</h4>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">This role stays with the project team from proposal preparation onward. The secretary receives priority budget reminders, but the project leader or any accepted team member can complete the section when needed.</p>
            </div>

            <div class="w-full lg:max-w-md">
                @if ($topic->researchSecretary)
                    <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-xs font-black text-emerald-800 ring-1 ring-emerald-200 dark:bg-slate-900">
                            @if ($topic->researchSecretary->avatar)
                                <img src="{{ $topic->researchSecretary->avatar }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ collect(explode(' ', $topic->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                            @endif
                        </span>
                        <span class="min-w-0"><span class="block truncate text-sm font-black text-gray-950 dark:text-white">{{ $topic->researchSecretary->name }}</span><span class="block truncate text-xs text-gray-500 dark:text-slate-400">{{ $topic->researchSecretary->email }}</span></span>
                    </div>
                @else
                    <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">No project secretary has been selected yet.</div>
                @endif

                @if ($canAssignProjectSecretary)
                    <div x-data="researchSecretaryPicker({ candidates: @js($projectSecretaryCandidates), selectedId: @js($topic->research_secretary_id) })" class="relative mt-3" data-project-secretary-picker>
                        <form x-ref="form" method="POST" action="{{ route('project-secretary.assign', $topic) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="research_secretary_id" :value="selectedId || ''">
                        </form>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-950 px-4 py-2 text-xs font-black text-white hover:bg-gray-800 dark:bg-white dark:text-slate-950">{{ $topic->researchSecretary ? 'Change secretary' : 'Select team member' }}</button>
                            @if ($topic->researchSecretary)
                                <button type="button" @click="clearSelection" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Remove</button>
                            @endif
                        </div>
                        @error('research_secretary_id')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror

                        <div x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-full min-w-72 rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900">
                            <label class="sr-only" for="project-secretary-search-{{ $topic->id }}">Search accepted team members</label>
                            <input x-ref="search" id="project-secretary-search-{{ $topic->id }}" x-model="query" type="search" autocomplete="off" placeholder="Search accepted team members" class="block w-full rounded-xl border-gray-200 text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                            <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox">
                                <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                    <button type="button" role="option" @click="select(candidate.id)" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-red-50 focus:bg-red-50 focus:outline-none dark:hover:bg-slate-800 dark:focus:bg-slate-800">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200"><img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover"><span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-gray-900 dark:text-white" x-text="candidate.name"></span><span class="block truncate text-xs text-gray-500 dark:text-slate-400" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block truncate text-[10px] font-bold uppercase tracking-wide text-gray-400" x-text="candidate.college"></span></span>
                                    </button>
                                </template>
                                <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-xs font-semibold text-gray-500">No accepted team member matches this search.</p>
                            </div>
                        </div>
                    </div>
                @elseif (! Auth::user()->isUsingWorkspace('research_head') && Auth::id() !== $topic->research_secretary_id)
                    <p class="mt-2 text-xs text-gray-500 dark:text-slate-400">Only the project leader can change this assignment.</p>
                @endif
            </div>
        </div>

        @if ($canReport && $topic->preparedProgressReports->isNotEmpty())
            @php
                $isPriorityProjectSecretary = Auth::id() === $topic->research_secretary_id;
            @endphp
            <div class="mt-5 border-t border-gray-100 pt-4 dark:border-slate-800">
                <p class="text-xs font-black uppercase tracking-wide text-gray-500 dark:text-slate-400">{{ $isPriorityProjectSecretary ? 'Priority budget queue' : 'Budget utilization available to the project team' }}</p>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($topic->preparedProgressReports as $preparedReport)
                        <a href="{{ route('project-budget.edit', [$topic, $preparedReport]) }}" class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-sm font-bold {{ $isPriorityProjectSecretary ? 'border-amber-200 bg-amber-50 text-amber-950 hover:border-amber-300 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100' : 'border-gray-200 bg-gray-50 text-gray-900 hover:border-red-200 hover:bg-red-50 dark:border-slate-700 dark:bg-slate-950 dark:text-white' }}">
                            <span>{{ $preparedReport->quarter_label }} · {{ $preparedReport->version_label }}</span>
                            <span class="text-xs">{{ $preparedReport->hasPreparedBudget() ? 'Review budget' : 'Complete budget' }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <x-monitoring-quarter-overview :quarter-rows="$monitoringQuarterRows" :topic="$topic" />

    <section class="divide-y divide-gray-200 rounded-xl border border-gray-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900" aria-label="Narrative reports">
        @foreach (['progress' => 'Progress report', 'terminal' => 'Terminal report'] as $reportType => $reportLabel)
            @php
                $available = $reportType === 'terminal' ? $terminalOpen : $openPeriod !== null;
            @endphp
            <article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 class="text-sm font-bold text-gray-950 dark:text-white">{{ $reportLabel }}</h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $reportType === 'terminal' ? 'Summarize the full project, final results, and accomplishments.' : 'Add narrative accomplishments and photo documentation for an ended reporting period.' }}</p>
                    @if (! $available)
                        <p class="mt-2 text-xs font-medium text-gray-600 dark:text-slate-300">Opens {{ ($reportType === 'terminal' ? $terminalDate : ($nextPeriod['opens_at'] ?? $terminalDate))->format('M j, Y') }}</p>
                    @endif
                </div>
                @if ($canReport && $available)
                    <a href="{{ route('project-narrative-reports.create', ['topic' => $topic, 'report_type' => $reportType]) }}" class="inline-flex shrink-0 justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-slate-600 dark:text-red-300">Open {{ strtolower($reportLabel) }}</a>
                @elseif ($canReport)
                    <button type="button" disabled class="shrink-0 rounded-lg bg-gray-100 px-4 py-2 text-sm text-gray-400 dark:bg-slate-800">Not open yet</button>
                @endif
            </article>
        @endforeach
    </section>

    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
        @php
            $floatingStatusClasses = match ($projectStatus) {
                'delayed' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200',
                'completion_pending' => 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-200',
                default => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200',
            };
        @endphp
        <div
            x-data="{ statusManagerOpen: @js($errors->has('project_status')) }"
            x-on:keydown.escape.window="statusManagerOpen = false"
            class="fixed bottom-5 right-4 z-40 w-[calc(100%-2rem)] max-w-[22.5rem] sm:right-5 sm:w-auto sm:max-w-none"
            data-project-status-manager
        >
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
                x-on:click.outside="statusManagerOpen = false"
                class="absolute bottom-full right-0 mb-3 w-full rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900"
                role="dialog"
                aria-labelledby="project-status-manager-heading-{{ $topic->id }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h4 id="project-status-manager-heading-{{ $topic->id }}" class="text-sm font-black text-gray-950 dark:text-white">Manage project status</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">Completion requires 100% progress, a reviewed Terminal Report, and its fully signed PDF.</p>
                    </div>
                    <button type="button" x-on:click="statusManagerOpen = false" class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close status manager">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                @error('project_status')<p class="mt-3 rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 dark:bg-red-950/50 dark:text-red-200">{{ $message }}</p>@enderror

                <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" class="mt-4 space-y-3">
                    @csrf
                    @method('PATCH')
                    <label for="project-status-{{ $topic->id }}" class="block text-xs font-bold text-gray-700 dark:text-slate-200">Status</label>
                    <div class="flex gap-2">
                        <select id="project-status-{{ $topic->id }}" name="project_status" class="h-11 min-w-0 flex-1 rounded-xl border-gray-300 bg-white text-sm font-semibold text-gray-900 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                            @foreach (['ongoing', 'delayed', 'completed'] as $value)
                                <option value="{{ $value }}" @selected($storedProjectStatus === $value)>{{ ucfirst($value) }}</option>
                            @endforeach
                        </select>
                        <button class="inline-flex h-11 shrink-0 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-black text-white transition hover:bg-red-800">Save status</button>
                    </div>
                </form>
            </section>

            <div class="flex items-center justify-between gap-3 rounded-full border border-gray-200 bg-white p-1.5 pl-3.5 shadow-lg shadow-gray-900/15 dark:border-slate-700 dark:bg-slate-900">
                <span class="inline-flex min-w-0 items-center gap-2 text-sm font-black {{ $floatingStatusClasses }} rounded-full px-3 py-2">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-current"></span>
                    <span class="truncate">{{ $projectStatusLabel }}</span>
                </span>
                <button
                    type="button"
                    x-on:click="statusManagerOpen = ! statusManagerOpen"
                    x-bind:aria-expanded="statusManagerOpen"
                    aria-controls="project-status-manager-{{ $topic->id }}"
                    class="inline-flex min-h-12 shrink-0 items-center gap-1.5 rounded-full bg-gray-900 px-4 py-2.5 text-sm font-black text-white transition hover:bg-gray-800 dark:bg-white dark:text-slate-950"
                >
                    Manage status
                    <span class="text-[9px] leading-none" aria-hidden="true" x-text="statusManagerOpen ? '▼' : '▲'"></span>
                </button>
            </div>
        </div>
    @endif
    <div class="space-y-5">

        <div class="hidden" aria-hidden="true">
            @forelse ($topic->progressReports as $report)
                @php
                    $isCurrentVersion = $report->nextVersion === null;
                    $reviewStatusClass = match ($report->review_status) {
                        'reviewed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200',
                        'revision_requested' => 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200',
                        default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-200',
                    };
                    $canManageCurrentReport = Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isCurrentVersion;
                @endphp
                <article
                    id="monitoring-tool-{{ $report->id }}"
                    x-data="{ open: window.location.hash === '#monitoring-tool-{{ $report->id }}' || @js($errors->has('research_head_remarks')), remarksExpanded: false, remarksText: @js(old('research_head_remarks', $report->research_head_remarks)) }"
                    x-init="if (open) { $dispatch('monitoring-tool-toggled', { reportId: {{ $report->id }}, open: true }) }"
                    x-on:open-monitoring-tool.window="if ($event.detail.reportId === {{ $report->id }}) { open = ! open; $dispatch('monitoring-tool-toggled', { reportId: {{ $report->id }}, open }); if (open) { $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' })) } }"
                    x-cloak
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                    x-transition:enter-start="-translate-y-3 opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="-translate-y-3 opacity-0"
                    class="scroll-mt-32 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-base font-black text-gray-950 dark:text-white">{{ $report->quarter_label }} Monitoring Tool · {{ $report->version_label }}</p>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $isCurrentVersion ? 'bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-200' : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-300' }}">{{ $isCurrentVersion ? 'Current submission' : 'Historical version' }}</span>
                            </div>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">{{ $report->reporting_period_label }} · Submitted {{ $report->submitted_at?->format('M d, Y g:i A') ?? $report->created_at->format('M d, Y g:i A') }} by {{ $report->submitter->name }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1.5 text-[10px] font-black uppercase text-gray-700 dark:bg-slate-800 dark:text-slate-200">{{ $report->progress_percentage }}% complete</span>
                            <span class="rounded-full px-2.5 py-1.5 text-[10px] font-black uppercase {{ $reviewStatusClass }}">{{ $report->review_status_label }}</span>
                            @if (is_array($report->work_plan) && is_array($report->budget_utilization))
                                <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900" aria-label="Download monitoring tool" title="Download monitoring tool">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M4.5 15.75v2.625A1.125 1.125 0 0 0 5.625 19.5h12.75a1.125 1.125 0 0 0 1.125-1.125V15.75" /></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                    </div>
                    <div
                        id="monitoring-tool-details-{{ $report->id }}"
                        x-cloak
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
                        x-transition:enter-start="-translate-y-2 opacity-0"
                        x-transition:enter-end="translate-y-0 opacity-100"
                        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
                        x-transition:leave-start="translate-y-0 opacity-100"
                        x-transition:leave-end="-translate-y-2 opacity-0"
                        class="border-t border-gray-100 p-5 dark:border-slate-800 sm:p-6"
                    >
                    <div class="overflow-hidden rounded-full bg-red-100 dark:bg-red-950/50" aria-label="{{ $report->progress_percentage }} percent complete"><div class="h-2.5 rounded-full bg-gradient-to-r from-red-300 via-red-500 to-red-700 dark:from-red-800 dark:via-red-600 dark:to-red-400" style="width: {{ $report->progress_percentage }}%"></div></div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Accomplishments</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->accomplishments }}</p>
                        </section>
                        <section class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Issues or delays</p>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->issues ?: 'None reported.' }}</p>
                        </section>
                    </div>
                    @if ($report->attachment_path || (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested'))
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-slate-800">
                        @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900">Download attachment</a>@endif
                        @if (! Auth::user()->isUsingWorkspace('research_head') && $isCurrentVersion && $report->review_status === 'revision_requested')
                            <a href="{{ route('project-progress.create', ['topic' => $topic, 'revise_monitoring_report' => $report->id]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-900">Correct {{ $report->quarter_label }} submission</a>
                        @endif
                    </div>
                    @endif
                    @if ($report->research_head_remarks && ! $canManageCurrentReport)<div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-slate-700 dark:bg-slate-800/60"><p class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks @if ($report->reviewer) · {{ $report->reviewer->name }} @endif</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-slate-300">{{ $report->research_head_remarks }}</p></div>@endif
                    @if ($canManageCurrentReport)
                        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" class="mt-4">
                            @csrf
                            @method('PATCH')
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <label for="research-head-remarks-{{ $report->id }}" class="block min-w-0 flex-1">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-700 dark:text-slate-200">Research Head remarks</span>
                                    <textarea id="research-head-remarks-{{ $report->id }}" name="research_head_remarks" x-model="remarksText" x-bind:rows="remarksExpanded ? Math.max(3, Math.ceil(remarksText.length / 75)) : 1" maxlength="5000" class="mt-2 block min-h-11 w-full resize-none rounded-xl border-gray-200 bg-white py-2.5 text-sm leading-6 text-gray-700 placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" placeholder="Add review notes or correction instructions">{{ old('research_head_remarks', $report->research_head_remarks) }}</textarea>
                                    <button type="button" x-show="remarksText.length > 120" x-on:click="remarksExpanded = ! remarksExpanded" class="mt-1 text-xs font-bold text-red-700 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200" x-text="remarksExpanded ? 'Show less' : 'See more…'"></button>
                                </label>
                                <div class="flex shrink-0 gap-2 sm:w-auto">
                                    <label class="sr-only" for="review-status-{{ $report->id }}">Review status</label>
                                    <button class="inline-flex h-11 flex-1 items-center justify-center rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 sm:flex-none">Save review</button>
                                    <select id="review-status-{{ $report->id }}" name="review_status" class="h-11 min-w-0 flex-1 rounded-xl border-gray-200 bg-white text-xs font-black text-gray-700 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 sm:w-44 sm:flex-none"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request corrections</option></select>
                                </div>
                            </div>
                            @error('research_head_remarks')<p class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300">{{ $message }}</p>@enderror
                        </form>
                    @endif
                    </div>
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No monitoring tools yet</p><p class="mt-1 text-xs text-gray-400">The first faculty submission will appear here.</p></div>
            @endforelse
        </div>

        <div class="space-y-4 border-t border-gray-100 pt-5">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Progress and terminal report history</p><p class="mt-1 text-xs text-gray-400">Submitted narratives, final results, and photo documentation.</p></div>
            </div>
            @forelse ($topic->narrativeReports as $report)
                @php
                    $isTerminalReport = $report->report_type === 'terminal';
                    $isLatestTerminalReport = $isTerminalReport && $report->id === $latestTerminalReportId;
                    $signedCopy = $report->signedCopy();
                @endphp
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-gray-900">{{ $report->report_label }}@if ($report->report_type === 'terminal' && isset($report->terminal_data['version_number'])) · Version {{ $report->terminal_data['version_number'] }}@endif</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->submission_date->format('M d, Y') }} · {{ $report->submitter->name }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ $report->review_status_label }}</span>
                            @if ($isTerminalReport)
                                <span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $signedCopy ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $signedCopy ? 'Signed copy recorded' : 'Signed copy required' }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Monitoring-period accomplishment</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishment_summary }}</p></div>
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Funding agency</p><p class="mt-1 text-xs leading-5 text-gray-600">{{ $report->funding_agency }}</p></div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4 dark:border-slate-800">
                        <a href="{{ route('project-narrative-reports.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900">Download {{ strtolower($report->report_label) }}</a>
                        @if ($signedCopy)
                            <a href="{{ route('project-narrative-reports.signed-copy.download', $report) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-emerald-300 bg-white px-3 py-2 text-xs font-bold text-emerald-800 transition hover:bg-emerald-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:border-emerald-900 dark:bg-slate-900 dark:text-emerald-300 dark:hover:bg-emerald-950/30 dark:focus-visible:ring-offset-slate-900">Download signed Terminal Report</a>
                        @endif
                        @foreach ($report->photos ?? [] as $photoIndex => $photo)
                            <a href="{{ route('project-narrative-reports.photos.download', [$report, $photoIndex]) }}" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300 dark:focus-visible:ring-offset-slate-900">Photo {{ $photoIndex + 1 }}: {{ $photo['caption'] }}</a>
                        @endforeach
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject())
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request corrections</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Describe the corrections needed"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                    @if (Auth::user()->isUsingWorkspace('research_head') && ! $topic->isCompletedProject() && $isLatestTerminalReport && $report->review_status === \App\Models\ProjectNarrativeReport::STATUS_REVIEWED)
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.signed-copy.store', $report) }}" enctype="multipart/form-data" class="mt-4 border-l-4 border-emerald-700 bg-emerald-50/70 p-4">
                            @csrf
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <label class="block min-w-0 flex-1 text-sm font-bold text-emerald-950">
                                    {{ $signedCopy ? 'Replace signed Terminal Report' : 'Fully signed Terminal Report' }}
                                    <span class="mt-1 block text-xs font-medium text-emerald-800">Upload the final PDF bearing the required signatures before project completion.</span>
                                    <input name="signed_report" type="file" accept=".pdf,application/pdf" required class="mt-2 block w-full rounded-lg border border-emerald-300 bg-white p-2 text-xs text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-emerald-900">
                                </label>
                                <button class="inline-flex min-h-10 shrink-0 items-center justify-center rounded-lg bg-emerald-800 px-4 py-2 text-xs font-black text-white hover:bg-emerald-900">{{ $signedCopy ? 'Replace signed PDF' : 'Record signed PDF' }}</button>
                            </div>
                            @error('signed_report')<p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p>@enderror
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No progress reports yet</p><p class="mt-1 text-xs text-gray-400">The first narrative progress report will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
