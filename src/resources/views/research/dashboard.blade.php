<x-app-layout>
    @php
        $totalProjects = $activeProjects->count() + $awaitingProjects->count() + $completedProjects->count();
        $monitoredProjects = $activeProjects->filter(fn ($topic) => $topic->latestProgressReport !== null)->count();
        $focusProjects = $attentionProjects->take(4);
    @endphp

    <x-slot name="header">
        <div data-faculty-researcher-dashboard class="mx-auto max-w-7xl">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-6 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:px-8">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] font-black uppercase tracking-[0.2em] text-[#7A0019]">
                            <span class="h-2 w-2 rounded-full bg-[#7A0019]" aria-hidden="true"></span>
                            Faculty Researcher Dashboard
                        </div>
                        <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Your research at a glance</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">
                            Start with work that needs attention, check portfolio progress, and review upcoming research dates.
                        </p>
                    </div>

                    <a href="{{ route('research.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-[#7A0019] px-5 text-sm font-black text-white shadow-sm transition hover:bg-[#650015] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2">
                        Open My Projects
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
                <div class="h-1 bg-gradient-to-r from-[#7A0019] via-rose-500 to-amber-400"></div>
            </div>
        </div>
    </x-slot>

    <div class="-mx-4 -my-6 min-h-[calc(100vh-12rem)] bg-[#F8FAFC] px-4 py-8 text-slate-900 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" data-dashboard-layout="research-overview">
        <div class="mx-auto max-w-7xl space-y-6">
            <section aria-labelledby="portfolio-summary-heading">
                <div class="mb-3 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#7A0019]">Portfolio summary</p>
                        <h2 id="portfolio-summary-heading" class="mt-1 text-lg font-black text-slate-950">Current research position</h2>
                    </div>
                    <p class="hidden text-xs font-semibold text-slate-500 sm:block">{{ $totalProjects }} approved {{ str('project')->plural($totalProjects) }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold text-slate-500">Active projects</p>
                                <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $activeProjects->count() }}</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h3l2.25-6 4.5 12 2.25-6h4.5" /></svg>
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">{{ $monitoredProjects }} with submitted monitoring</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold text-slate-500">Average completion</p>
                                <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $averageProgress }}%</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 text-[#7A0019]">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5 9 15l3 3 7.5-9m0 0h-5.25m5.25 0v5.25" /></svg>
                            </span>
                        </div>
                        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Average project completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $averageProgress }}">
                            <div class="h-full rounded-full bg-[#7A0019]" style="width: {{ $averageProgress }}%"></div>
                        </div>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold text-slate-500">Needs attention</p>
                                <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $attentionProjects->count() }}</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5Zm8.25 2.25H3.75L12 3l8.25 15.75Z" /></svg>
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Delayed, unreported, or ready to close</p>
                    </article>

                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold text-slate-500">Awaiting release</p>
                                <p class="mt-2 text-3xl font-black tabular-nums text-slate-950">{{ $awaitingProjects->count() }}</p>
                            </div>
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4.5 2.25m4.5-2.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-slate-500">Waiting for Notice to Proceed</p>
                    </article>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="focus-heading">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#7A0019]">Priority queue</p>
                            <h2 id="focus-heading" class="mt-1 text-base font-black text-slate-950">What needs your attention</h2>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-3 py-1 text-[10px] font-black text-slate-600">{{ $attentionProjects->count() }} open</span>
                    </div>

                    @if ($focusProjects->isEmpty())
                        <div class="px-6 py-12 text-center">
                            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 4.5 4.5L19.5 6.75" /></svg>
                            </span>
                            <p class="mt-3 text-sm font-black text-slate-800">No urgent project actions</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Your active projects are currently on track.</p>
                        </div>
                    @else
                        <div class="divide-y divide-slate-100">
                            @foreach ($focusProjects as $topic)
                                @php
                                    $progress = min(100, max(0, (int) ($topic->latestProgressReport?->progress_percentage ?? 0)));
                                    $monitoringStatus = $topic->monitoringStatusForProgress($topic->latestProgressReport?->progress_percentage);

                                    [$label, $description, $action, $badgeClass] = match ($monitoringStatus) {
                                        \App\Models\TopicProposal::PROJECT_STATUS_COMPLETION_PENDING => ['Ready to close', 'Monitoring reached 100%. Complete and submit the signed terminal report.', 'Complete terminal report', 'bg-rose-50 text-[#7A0019] ring-rose-200'],
                                        \App\Models\TopicProposal::PROJECT_STATUS_DELAYED => ['Delayed', 'Record the current accomplishment and explain what caused the delay.', 'Update monitoring', 'bg-amber-50 text-amber-800 ring-amber-200'],
                                        default => ['Monitoring needed', 'No submitted monitoring record is available for this project yet.', 'Start monitoring', 'bg-sky-50 text-sky-800 ring-sky-200'],
                                    };
                                @endphp

                                <article class="grid gap-4 px-5 py-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $badgeClass }}">{{ $label }}</span>
                                            <span class="whitespace-nowrap text-[10px] font-bold tabular-nums text-slate-500">{{ $progress }}% complete</span>
                                        </div>
                                        <h3 class="mt-2 truncate text-sm font-black text-slate-950">{{ $topic->title }}</h3>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $description }}</p>
                                    </div>
                                    <a href="{{ route('research.show', $topic) }}#project-monitoring" class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border border-slate-200 px-3 text-[11px] font-black text-slate-700 transition hover:border-rose-200 hover:bg-rose-50 hover:text-[#7A0019]">
                                        {{ $action }}
                                        <span class="ml-1" aria-hidden="true">&rarr;</span>
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    @endif
            </section>

            <section aria-labelledby="research-schedule-heading">
                <div class="mb-3">
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#7A0019]">Research schedule</p>
                    <h2 id="research-schedule-heading" class="mt-1 text-lg font-black text-slate-950">Dates and reminders</h2>
                </div>
                <livewire:dashboard-calendar />
            </section>
        </div>
    </div>
</x-app-layout>