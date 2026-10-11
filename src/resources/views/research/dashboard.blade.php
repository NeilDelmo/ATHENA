<x-app-layout>
    @php
        $totalProjects = $activeProjects->count() + $awaitingProjects->count() + $completedProjects->count();
        $unknownSchedules = $projectHealth->where('has_schedule', false)->count();
    @endphp
    <x-slot name="header">
        <x-page-header variant="hero" data-faculty-researcher-dashboard eyebrow="Faculty Researcher" title="Dashboard" subtitle="Your projects and reports.">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <a wire:navigate href="{{ route('faculty.calendar') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">Calendar</a>
                    <a wire:navigate href="{{ route('research.index') }}" class="dashboard-action">My projects</a>
                </div>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="space-y-5" data-dashboard-layout="research-overview" data-dashboard-palette="red-black-white">
        <section aria-labelledby="portfolio-summary-heading">
            <h2 id="portfolio-summary-heading" class="sr-only">Portfolio summary</h2>
            <dl class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <x-dashboard-stat label="Needs attention" :value="$attentionProjects->count()" description="Projects with required actions" href="#needs-attention" :priority="$attentionProjects->isNotEmpty()" icon="M12 9v4m0 3h.01M12 3 2 20h20L12 3Z" />
                <x-dashboard-stat label="Active projects" :value="$activeProjects->count()" description="In implementation" :href="route('research.index', ['status' => 'active'])" icon="M3 12h4l2-6 4 12 2-6h6" />
                <x-dashboard-stat label="Awaiting release" :value="$awaitingProjects->count()" description="Notice to Proceed pending" :href="route('research.index', ['status' => 'waiting'])" icon="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                <x-dashboard-stat label="Average completion" :value="$averageProgress === null ? '—' : $averageProgress.'%'" :description="$reportedProjects->count().' of '.$activeProjects->count().' projects reported'" :href="route('research.index', ['status' => 'active'])" icon="M4 19 9 14l4 3 7-10m0 0h-5m5 0v5" />
            </dl>
        </section>

        <div data-dashboard-columns class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section id="needs-attention" class="dashboard-panel scroll-mt-32" aria-labelledby="focus-heading" x-data="{ showAll: false }">
                <div class="dashboard-panel-heading">
                    <h2 id="focus-heading" class="text-base font-semibold text-slate-950 dark:text-white">Needs attention</h2>
                    <span class="text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $attentionProjects->count() }} {{ str('project')->plural($attentionProjects->count()) }}</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800" id="faculty-project-actions">
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
                        <article data-project-attention="{{ $topic->id }}" class="min-w-0 p-5" @if ($loop->index >= 4) x-show="showAll" x-cloak @endif>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <h3 class="min-w-0 break-words text-base font-semibold leading-6 text-slate-950 dark:text-white"><a wire:navigate href="{{ route('research.show', $topic) }}#project-monitoring" class="hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:text-rose-300">{{ $topic->title }}</a></h3>
                                <span class="shrink-0 text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $health['progress'] === null ? 'Progress unreported' : $health['progress'].'% reported' }}</span>
                            </div>
                            <ul class="mt-3 space-y-2 text-sm text-brand dark:text-rose-300">
                                @foreach ($health['actions'] as $issue)
                                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"><a wire:navigate href="{{ $issue['url'] }}" class="min-w-0 break-words hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand">{{ $issue['issue'] }}</a>@if ($issue['days'] !== null)<span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $issue['days'] }} {{ str('day')->plural($issue['days']) }} {{ $issue['basis'] === 'overdue' ? 'overdue' : 'waiting' }}</span>@endif</li>
                                @endforeach
                            </ul>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $topic->latestProgressReport ? 'Monitoring dated '.$topic->latestProgressReport->reporting_date->format('M j, Y') : 'No submitted Monitoring Tool' }}</p>
                                <a wire:navigate href="{{ $primaryIssue['url'] }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-brand hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:text-rose-300">{{ $action }}</a>
                            </div>
                        </article>
                    @empty
                        <div class="px-5 py-10">
                            <p class="text-base font-semibold text-slate-900 dark:text-slate-100">No actions currently flagged</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Check your projects for reporting periods and review updates.</p>
                            <a wire:navigate href="{{ route('research.index') }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-brand dark:text-rose-300">View projects</a>
                        </div>
                    @endforelse
                </div>
                @if ($attentionProjects->count() > 4)
                    <div class="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
                        <button type="button" @click="showAll = !showAll" :aria-expanded="showAll" aria-controls="faculty-project-actions" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300" x-text="showAll ? 'Show fewer projects' : 'Show {{ $attentionProjects->count() - 4 }} more projects'">Show {{ $attentionProjects->count() - 4 }} more projects</button>
                    </div>
                @endif
            </section>

            <aside class="min-w-0" aria-label="Project portfolio">
                <section class="dashboard-panel" aria-labelledby="project-distribution-heading">
                    <div class="dashboard-panel-heading"><h2 id="project-distribution-heading" class="text-base font-semibold text-slate-900 dark:text-white">Project distribution</h2><span class="text-sm tabular-nums text-slate-500 dark:text-slate-400">{{ $totalProjects }} total</span></div>
                    <div class="space-y-5 p-5">
                        @foreach ([
                            ['Active', $activeProjects->count(), 'active', 'bg-brand dark:bg-rose-400'],
                            ['Awaiting release', $awaitingProjects->count(), 'waiting', 'bg-rose-300 dark:bg-rose-700'],
                            ['Completed', $completedProjects->count(), 'completed', 'bg-slate-400 dark:bg-slate-500'],
                        ] as [$label, $count, $status, $tone])
                            <a wire:navigate href="{{ route('research.index', ['status' => $status]) }}" class="block rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                                <span class="flex items-center justify-between gap-3 text-sm"><span class="text-slate-600 dark:text-slate-300">{{ $label }}</span><strong class="tabular-nums text-slate-900 dark:text-white">{{ $count }}</strong></span>
                                <span class="mt-2 block h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="block h-full rounded-full {{ $tone }}" style="width: {{ $totalProjects === 0 ? 0 : round($count / $totalProjects * 100) }}%"></span></span>
                            </a>
                        @endforeach
                        @if ($totalProjects === 0)<p class="text-sm text-slate-500 dark:text-slate-400">Approved projects will appear here.</p>@endif
                    </div>
                    @if ($unknownSchedules)
                        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $unknownSchedules }} active {{ str('project')->plural($unknownSchedules) }} without a recorded schedule. Reporting dates cannot be checked.</p>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
