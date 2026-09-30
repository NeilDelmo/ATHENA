<x-app-layout>
    @php
        $totalProjects = $activeProjects->count() + $awaitingProjects->count() + $completedProjects->count();
        $monitoredProjects = $activeProjects->filter(fn ($topic) => $topic->latestProgressReport !== null)->count();
        $focusProjects = $attentionProjects->take(4);
    @endphp

    <x-slot name="header">
        <x-page-header variant="hero"
            data-faculty-researcher-dashboard
            eyebrow="Faculty Researcher Dashboard"
            title="Your research at a glance"
            subtitle="Track your projects, review what needs attention, and plan your next steps."
        >
            <x-slot:actions>
                <a href="{{ route('research.index') }}" class="dashboard-action">
                    Open My Projects
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                </a>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-dashboard-layout="research-overview" data-dashboard-palette="red-black-white">
        <section aria-labelledby="portfolio-summary-heading">
            <h2 id="portfolio-summary-heading" class="sr-only">Portfolio summary</h2>
            <dl class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <x-dashboard-stat label="Active projects" :value="$activeProjects->count()" :description="$monitoredProjects.' with submitted monitoring'" icon="M3 12h4l2-6 4 12 2-6h6" />
                <x-dashboard-stat label="Average completion" :value="$averageProgress.'%'" description="Across your active projects" icon="M4 19 9 14l4 3 7-10m0 0h-5m5 0v5" />
                <x-dashboard-stat label="Needs attention" :value="$attentionProjects->count()" description="Delayed, unreported, or ready to close" icon="M12 9v4m0 3h.01M12 3 2 20h20L12 3Z" />
                <x-dashboard-stat label="Awaiting release" :value="$awaitingProjects->count()" description="Waiting for Notice to Proceed" icon="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </dl>
        </section>

        <div data-dashboard-columns class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0 space-y-5">
                <section class="dashboard-panel" aria-labelledby="project-distribution-heading">
                    <div class="dashboard-panel-heading">
                        <h2 id="project-distribution-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Project distribution</h2>
                        <span class="text-[11px] text-slate-400">Approved research portfolio</span>
                    </div>
                    <div class="grid gap-6 p-5 sm:grid-cols-[minmax(0,0.7fr)_minmax(0,1fr)] sm:p-6">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Total projects</p>
                            <p class="mt-3 text-5xl font-bold tracking-tight tabular-nums text-slate-950 dark:text-white">{{ $totalProjects }}</p>
                            <p class="mt-3 max-w-xs text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $totalProjects === 0 ? 'Your approved projects will appear here as they move into execution.' : 'A view of your research from approval through completion.' }}</p>
                        </div>
                        <div class="space-y-5">
                            @foreach ([
                                ['Active', $activeProjects->count(), 'bg-[#7A0019] dark:bg-red-400'],
                                ['Awaiting release', $awaitingProjects->count(), 'bg-[#7A0019]/40 dark:bg-red-400/40'],
                                ['Completed', $completedProjects->count(), 'bg-slate-300 dark:bg-slate-500'],
                            ] as [$label, $count, $tone])
                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                                        <span class="text-slate-600 dark:text-slate-300">{{ $label }}</span>
                                        <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $count }}</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                                        <div class="h-full rounded-full {{ $tone }}" style="width: {{ $totalProjects === 0 ? 0 : round($count / $totalProjects * 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 dark:border-slate-800 sm:px-6">
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $monitoredProjects }} of {{ $activeProjects->count() }} active projects have monitoring reports.</p>
                        <a href="{{ route('research.index') }}" class="text-xs font-semibold text-[#7A0019] hover:underline dark:text-red-300">View projects &rarr;</a>
                    </div>
                </section>
            <section class="dashboard-panel" aria-labelledby="focus-heading">
                    <div class="dashboard-panel-heading">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#7A0019] dark:text-red-300">Priority queue</p>
                            <h2 id="focus-heading" class="mt-1 text-base font-semibold text-slate-950 dark:text-white">What needs your attention</h2>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 dark:bg-slate-800 dark:text-slate-300 px-3 py-1 text-[10px] font-semibold text-slate-600">{{ $attentionProjects->count() }} open</span>
                    </div>

                    @if ($focusProjects->isEmpty())
                        <div class="px-6 py-12 text-center">
                            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-red-50 text-[#7A0019] dark:bg-red-950/30 dark:text-red-300">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5L19.5 6.75" /></svg>
                            </span>
                            <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">No urgent project actions</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Your active projects are currently on track.</p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($focusProjects as $topic)
                                @php
                                    $progress = min(100, max(0, (int) ($topic->latestProgressReport?->progress_percentage ?? 0)));
                                    $monitoringStatus = $topic->monitoringStatusForProgress($topic->latestProgressReport?->progress_percentage);

                                    [$label, $description, $action, $badgeClass] = match ($monitoringStatus) {
                                        \App\Models\TopicProposal::PROJECT_STATUS_COMPLETION_PENDING => ['Ready to close', 'Monitoring reached 100%. Complete and submit the signed terminal report.', 'Complete terminal report', 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900'],
                                        \App\Models\TopicProposal::PROJECT_STATUS_DELAYED => ['Delayed', 'Record the current accomplishment and explain what caused the delay.', 'Update monitoring', 'bg-red-50 text-[#7A0019] ring-red-200 dark:bg-red-950/30 dark:text-red-300 dark:ring-red-900'],
                                        default => ['Monitoring needed', 'No submitted monitoring record is available for this project yet.', 'Start monitoring', 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'],
                                    };
                                @endphp

                                <article class="grid gap-4 px-5 py-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-semibold ring-1 ring-inset {{ $badgeClass }}">{{ $label }}</span>
                                            <span class="whitespace-nowrap text-[10px] font-bold tabular-nums text-slate-500 dark:text-slate-400">{{ $progress }}% complete</span>
                                        </div>
                                        <h3 class="mt-2 truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $topic->title }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</p>
                                    </div>
                                    <a href="{{ route('research.show', $topic) }}#project-monitoring" class="dark:border-slate-700 dark:text-slate-300 dark:hover:bg-red-950/30 dark:hover:text-red-200 inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 px-3 text-[11px] font-semibold text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-[#7A0019]">
                                        {{ $action }}
                                        <span class="ml-1" aria-hidden="true">&rarr;</span>
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @endif
            </section>


            </div>
            <aside class="min-w-0 space-y-5" aria-label="Research schedule">
                <section class="dashboard-panel p-5" aria-label="Research planning">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Plan your research</h2>
                    <p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-400">Open your calendar for deadlines, activities, and reminders.</p>
                    <a wire:navigate href="{{ route('faculty.calendar') }}" class="dashboard-action mt-4">Open calendar</a>
                </section>
                <section class="dashboard-panel p-5" aria-labelledby="research-workflow-heading">
                    <h2 id="research-workflow-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Keep your research moving</h2>
                    <p class="mt-2 text-xs leading-6 text-slate-500 dark:text-slate-400">Update your monitoring reports as work progresses. When a project reaches 100%, submit its signed terminal report to complete the process.</p>
                    <a href="{{ route('research.index') }}" class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-[#7A0019] hover:underline dark:text-red-300">Go to My Projects <span aria-hidden="true">&rarr;</span></a>
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
