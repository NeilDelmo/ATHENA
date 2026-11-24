<x-app-layout>
    @php
        $unknownSchedules = $projectHealth->where('has_schedule', false)->count();
    @endphp
    <x-slot name="header">
        <x-page-header data-faculty-researcher-dashboard data-dashboard-header variant="hero" eyebrow="Faculty Researcher" title="Dashboard" subtitle="Track approved projects, reporting deadlines, and follow-up actions.">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <a wire:navigate href="{{ route('faculty.calendar') }}" class="secondary-action"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" /></svg>Calendar</a>
                    <a wire:navigate href="{{ route('research.index') }}" class="dashboard-action"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" /></svg>Projects</a>
                </div>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="role-dashboard" data-dashboard-layout="research-overview" data-dashboard-palette="maroon-slate-white">
        <section aria-labelledby="portfolio-summary-heading">
            <h2 id="portfolio-summary-heading" class="sr-only">Project summary</h2>
            <dl class="dashboard-metrics" data-project-summary data-dashboard-metrics>
                <x-dashboard-stat label="Needs attention" :value="$attentionProjects->count()" description="Projects with actions to complete"
                    icon="history" href="#needs-attention" action="View actions" priority />
                <x-dashboard-stat label="Active projects" :value="$activeProjects->count()" description="Issued projects in progress"
                    icon="folder-open" :href="route('research.index', ['status' => 'active'])" action="View active" wire:navigate />
                <x-dashboard-stat label="Completed projects" :value="$completedProjects->count()" description="Projects with completed research"
                    icon="publication" :href="route('research.index', ['status' => 'completed'])" action="View completed" wire:navigate />
                <x-dashboard-stat label="Average completion" :value="$averageProgress === null ? '–' : $averageProgress.'%'"
                    :description="$reportedProjects->count().' of '.$activeProjects->count().' projects reported'" icon="presentation"
                    :href="route('research.index', ['status' => 'active'])" action="View progress" wire:navigate />
            </dl>
        </section>

        <div data-dashboard-columns>
            <section id="needs-attention" class="scroll-mt-32 space-y-4" aria-labelledby="focus-heading" x-data="{ showAll: false }">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6M9 3h6v4H9V3Zm3 9v3m0 3h.01" /></svg></span>
                    <h2 id="focus-heading" class="dashboard-section-title">Needs attention</h2>
                    <span class="rh-badge">{{ $attentionProjects->count() }} {{ str('project')->plural($attentionProjects->count()) }}</span>
                </div>
                <div class="grid gap-4 xl:grid-cols-2" id="faculty-project-actions">
                    @forelse ($attentionProjects as $topic)
                        @php
                            $health = $projectHealth[$topic->id];
                            $primaryIssue = $health['actions']->first();
                            $action = match ($primaryIssue['type']) {
                                'quarterly' => 'Complete Monitoring Tool',
                                'progress_quarterly' => 'Complete Progress Report',
                                'report_review' => 'Correct Monitoring Tool',
                                'narrative_review' => 'Correct Progress Report',
                                'terminal_review' => 'Correct Terminal Report',
                                'terminal' => 'Prepare Terminal Report',
                                'budget' => 'Review budget',
                                default => 'Update monitoring',
                            };
                        @endphp
                        <article data-project-attention="{{ $topic->id }}" class="flex min-w-0 flex-col gap-4 dashboard-panel p-5" @if ($loop->index >= 4) x-show="showAll" x-cloak @endif>
                            <div class="space-y-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center text-brand dark:text-red-300" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8M8 17h5" /></svg></span>
                                    <h3 class="min-w-0 break-words text-base font-semibold">{{ $topic->title }}</h3>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-sm leading-5 text-slate-600 dark:text-slate-400">
                                    <p>{{ $topic->latestProgressReport ? 'Monitoring Tool · '.$topic->latestProgressReport->reporting_date->format('M j, Y') : 'No submitted Monitoring Tool' }}</p>
                                    <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ $health['progress'] === null ? 'Progress unreported' : $health['progress'].'% reported' }}</span>
                                </div>
                            </div>
                            <ul data-project-issues class="divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-300 bg-slate-50 dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
                                @foreach ($health['actions'] as $issue)
                                    <li class="flex flex-col items-start gap-2 p-3 sm:flex-row sm:justify-between sm:gap-3">
                                        <a wire:navigate href="{{ $issue['url'] }}" class="min-w-0 gap-2 break-words leading-6 secondary-action"><svg class="mt-1 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8h.01M11 11h1v5h1m8-4a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg><span class="min-w-0 break-words">{{ $issue['issue'] }}</span></a>
                                        @if ($issue['days'] !== null)
                                            <span class="shrink-0 rounded-md bg-white px-2 py-1 text-sm leading-5 tabular-nums text-slate-600 dark:bg-slate-900 dark:text-slate-300">{{ $issue['days'] }} {{ str('day')->plural($issue['days']) }} {{ $issue['basis'] === 'overdue' ? 'overdue' : 'waiting' }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-auto flex flex-wrap justify-end gap-2">
                                <a wire:navigate href="{{ route('research.show', $topic) }}#project-monitoring" class="secondary-action">Open project</a>
                                <a wire:navigate data-project-action href="{{ $primaryIssue['url'] }}" class="dashboard-action max-w-full text-center">{{ $action }}<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg></a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-slate-300 bg-white px-5 py-8 xl:col-span-2 dark:border-slate-700 dark:bg-slate-900">
                            <p class="text-base font-semibold text-slate-950 dark:text-white">No actions currently flagged</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Check your projects for reporting periods and review updates.</p>
                            <a wire:navigate href="{{ route('research.index') }}" class="mt-3 secondary-action">View projects</a>
                        </div>
                    @endforelse
                </div>
                @if ($attentionProjects->count() > 4)
                    <div class="flex justify-center">
                        <button type="button" @click="showAll = !showAll" :aria-expanded="showAll" aria-controls="faculty-project-actions" class="secondary-action" x-text="showAll ? 'Show fewer projects' : 'Show {{ $attentionProjects->count() - 4 }} more projects'">Show {{ $attentionProjects->count() - 4 }} more projects</button>
                    </div>
                @endif
            </section>
        </div>

        @if ($unknownSchedules)
            <p class="rounded-lg border border-slate-300 bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">{{ $unknownSchedules }} active {{ str('project')->plural($unknownSchedules) }} without a recorded schedule. Reporting dates cannot be checked.</p>
        @endif
    </div>
</x-app-layout>
