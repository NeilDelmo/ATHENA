<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" eyebrow="VCRDES" title="Monitoring overview"
            subtitle="Monitor issued research projects, forwarded progress, and university-wide spending.">
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    <a href="#projects" class="dashboard-action">
                        <x-report-library-icon name="folder-open" class="!h-5 !w-5" /> View research projects
                    </a>
                    <a href="{{ route('vcrdes.dashboard') }}" aria-label="Refresh monitoring overview" class="secondary-action">
                        <x-report-library-icon name="refresh" class="!h-5 !w-5" /> Refresh
                    </a>
                </div>
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $statusLabels = ['ongoing' => 'Ongoing', 'delayed' => 'Delayed / overdue', 'awaiting' => 'Awaiting report / review', 'completed' => 'Completed'];
        $statusStyles = [
            'ongoing' => 'bg-sky-50 text-sky-800 dark:bg-sky-950/50 dark:text-sky-200',
            'delayed' => 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-200',
            'awaiting' => 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            'completed' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        ];
    @endphp
    <div data-vcrdes-dashboard data-dashboard-palette="maroon-slate-white" class="role-dashboard">
        @if ($errors->any())
            <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">{{ $errors->first() }}</p>
        @endif

        <section id="overview" aria-label="Research at a glance" class="scroll-mt-40">
            <dl class="dashboard-metrics" data-dashboard-metrics>
                <x-dashboard-stat label="Active projects" :value="$total - $completed" :description="($filters['college'] ?? 'All colleges').' · Issued projects'" icon="folder-open"
                    href="#projects" action="View portfolio" priority />
                @foreach ([['ongoing', 'Ongoing', 'list'], ['delayed', 'Delayed / overdue', 'history'], ['awaiting', 'Awaiting report / review', 'calendar']] as [$status, $label, $icon])
                    <x-dashboard-stat :label="$label" :value="$counts->get($status, 0)" :icon="$icon"
                        :href="route('vcrdes.dashboard', array_filter(['college' => $filters['college'] ?? null, 'status' => $status])).'#projects'"
                        :selected="($filters['status'] ?? null) === $status" action="View projects" />
                @endforeach
            </dl>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-2">
            <section id="attention" aria-labelledby="attention-heading" class="dashboard-panel scroll-mt-40">
                <div class="dashboard-panel-heading">
                    <h2 id="attention-heading" class="dashboard-section-title flex items-center gap-2.5"><x-report-library-icon name="history" class="h-5 w-5 text-slate-500 dark:text-slate-400" /> Needs attention</h2>
                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-sm font-bold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $attention->count() }}</span>
                </div>
                <div class="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($attention as $issue)
                        <article class="monitoring-action-row">
                            <div class="monitoring-action-copy">
                                <h3 class="text-base font-semibold">{{ $issue['title'] }}</h3>
                                <p class="mt-0.5 text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $issue['issue'] }}</p>
                                @if ($issue['days'] !== null)<p class="mt-0.5 text-sm tabular-nums text-slate-600 dark:text-slate-400">{{ $issue['days'] }} {{ str('day')->plural($issue['days']) }} {{ $issue['basis'] }}</p>@endif
                            </div>
                        </article>
                    @empty
                        <div class="flex min-h-52 flex-col items-center justify-center gap-3 px-3 py-7 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><x-report-library-icon name="preview" /></span><p class="text-sm font-semibold">No flagged items</p><p class="max-w-52 text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $total ? 'No issues found in the current monitoring records.' : 'Monitoring alerts appear when projects are issued.' }}</p></div>
                    @endforelse
                </div>
            </section>
            <section id="budget" aria-labelledby="budget-heading" class="dashboard-panel scroll-mt-40">
                <div class="dashboard-panel-heading">
                    <h2 id="budget-heading" class="dashboard-section-title">Budget utilization</h2>
                    <span class="dashboard-icon"><x-report-library-icon name="presentation" class="!h-5 !w-5" /></span>
                </div>
                <div class="space-y-4 p-5">
                    <p class="text-4xl font-bold tracking-tight tabular-nums text-brand dark:text-rose-300">{{ $budgetPercentage ?? '–' }}@if ($budgetPercentage !== null)<span class="text-xl">%</span>@endif</p>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" aria-label="{{ $budgetPercentage !== null ? $budgetPercentage.' percent budget utilization' : 'Budget utilization unavailable' }}">
                        <div class="h-full rounded-full bg-brand dark:bg-rose-300" style="width: {{ max(0, min(100, $budgetPercentage ?? 0)) }}%"></div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4">
                        <div><dt class="text-sm rh-muted">Reported spending</dt><dd class="mt-1 break-words font-semibold tabular-nums">{{ $reportedCount ? '₱'.number_format($utilized, 2) : 'Not reported' }}</dd></div>
                        <div><dt class="text-sm rh-muted">{{ $budgetCount < $total ? 'Known approved / estimated' : 'Approved / estimated' }}</dt><dd class="mt-1 break-words font-semibold tabular-nums">{{ $budgetCount ? '₱'.number_format($budget, 2) : 'No budgets recorded' }}</dd></div>
                    </dl>
                    <p class="text-sm rh-muted">{{ $budgetCount }} of {{ $total }} budgets recorded · {{ $reportedCount }} with forwarded spending.</p>
                    <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">Project completion</p>
                            <p class="text-sm tabular-nums rh-muted">{{ $completed }} of {{ $total }} completed · {{ $completionPercentage }}%</p>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand dark:bg-rose-300" style="width: {{ $completionPercentage }}%"></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section id="projects" aria-labelledby="projects-heading" class="dashboard-panel scroll-mt-40">
            <div class="dashboard-panel-heading !items-end">
                <div><h2 id="projects-heading" class="dashboard-section-title flex items-center gap-2.5"><x-report-library-icon name="folder-open" class="h-5 w-5 shrink-0" /> Research portfolio <span class="rounded-lg bg-slate-50 px-2 py-1 text-sm font-semibold tabular-nums dark:bg-slate-800">{{ $projects->total() }}</span></h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Deadlines and the latest forwarded progress.</p></div>
                <form action="{{ route('vcrdes.dashboard') }}#projects" method="GET" class="flex w-full flex-wrap items-center gap-2">
                    <div class="min-w-0 flex-1 basis-full sm:basis-56"><label for="portfolio-search" class="sr-only">Search projects or project leaders</label><input id="portfolio-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search projects…" class="rh-control w-full !rounded-lg"></div>
                    <div class="contents">
                        <label for="portfolio-college" class="sr-only">College</label><select id="portfolio-college" name="college" class="rh-control min-w-0 max-w-full !rounded-lg"><option value="">All colleges</option>@foreach (\App\Models\User::COLLEGES as $code => $name)<option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>@endforeach</select>
                        <label for="portfolio-status" class="sr-only">Project status</label><select id="portfolio-status" name="status" class="rh-control min-w-0 max-w-full !rounded-lg"><option value="">All statuses</option>@foreach ($statusLabels as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <button type="submit" aria-label="Apply project filters" class="dashboard-action">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="5.5" /><path d="m15 15 4 4" /></svg> Apply filters
                    </button>
                    @if (array_filter($filters))
                        <a href="{{ route('vcrdes.dashboard') }}#projects" aria-label="Clear project filters" class="secondary-action">
                            <x-report-library-icon name="undo" class="!h-4 !w-4" /> Reset
                        </a>
                    @endif
                </form>
            </div>
            <div class="hidden grid-cols-[minmax(0,1fr)_10rem_8rem_7rem_6rem] items-center gap-3 border-b border-slate-100 bg-slate-50 px-5 py-3 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 lg:grid"><span>Project / lead</span><span>Status</span><span>Progress</span><span>Deadline</span><span></span></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($projects as $project)
                    <details id="project-{{ $project['id'] }}" class="group scroll-mt-36 open:bg-slate-50/60 dark:open:bg-slate-800/30">
                        <summary class="grid cursor-pointer list-none rounded-lg border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900 grid-cols-[minmax(0,1fr)_6rem] items-center gap-3 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#7A0019] dark:hover:bg-slate-800/50 sm:grid-cols-[minmax(0,1fr)_8rem_6rem] lg:grid-cols-[minmax(0,1fr)_10rem_8rem_7rem_6rem] [&::-webkit-details-marker]:hidden">
                            <div class="flex min-w-0 items-center gap-3"><span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 sm:flex"><x-report-library-icon name="folder-open" class="h-5 w-5" /></span><div class="min-w-0"><h3 class="break-words text-base font-semibold leading-6">{{ $project['title'] }}</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $project['college'] ?: 'College not recorded' }} <span class="px-1 text-slate-300" aria-hidden="true">/</span> {{ $project['lead'] }}</p><span class="mt-2 inline-block rounded-md px-2 py-1 text-xs font-semibold sm:hidden {{ $statusStyles[$project['status']] }}">{{ $statusLabels[$project['status']] }}</span></div></div>
                            <span class="hidden w-fit rounded-md px-2 py-1.5 text-xs font-semibold sm:block {{ $statusStyles[$project['status']] }}">{{ $statusLabels[$project['status']] }}</span>
                            <div class="hidden lg:block"><span class="text-sm font-bold tabular-nums">{{ $project['progress'] !== null ? $project['progress'].'%' : 'Not reported' }}</span><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700" aria-hidden="true"><div class="h-full rounded-full {{ $project['status'] === 'delayed' ? 'bg-rose-500' : 'bg-[#7A0019] dark:bg-red-400' }}" style="width: {{ max(0, min(100, $project['progress'] ?? 0)) }}%"></div></div></div>
                            <span class="hidden text-sm tabular-nums text-slate-500 dark:text-slate-400 lg:block">{{ $project['deadline'] ? \Carbon\Carbon::parse($project['deadline'])->format('M j, Y') : 'No schedule' }}</span>
                            <span class="secondary-action px-2">Details <x-report-library-icon name="open" class="!h-4 !w-4 transition-transform group-open:rotate-90 motion-reduce:transition-none" /></span>
                        </summary>
                        <div class="grid gap-4 border-t border-dashed border-slate-200 p-5 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-700">
                            @foreach ([['calendar', 'Deadline', $project['deadline'] ?: 'No schedule recorded'], ['presentation', 'Approved / estimated budget', $project['budget'] !== null ? '₱'.number_format($project['budget'], 2) : 'Not recorded'], ['export', 'Reported utilization', $project['utilized'] !== null ? '₱'.number_format($project['utilized'], 2) : 'Not reported'], ['history', 'Latest forwarded progress', $project['progress'] !== null ? $project['progress'].'% · '.$project['report_date'] : 'Not reported']] as [$icon, $label, $value])
                                <div class="flex gap-2.5"><x-report-library-icon :name="$icon" class="mt-0.5 h-4 w-4 text-slate-400" /><div><p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-1 text-sm font-semibold">{{ $value }}</p></div></div>
                            @endforeach
                            @if ($project['issues']->isNotEmpty())<ul class="space-y-1 text-sm leading-5 text-amber-800 dark:text-amber-200 sm:col-span-2 lg:col-span-4">@foreach ($project['issues'] as $issue)<li>{{ $issue['issue'] }}</li>@endforeach</ul>@endif
                        </div>
                    </details>
                @empty
                    <div class="flex flex-col items-center gap-3 px-5 py-12 text-center"><span class="flex h-14 w-14 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><x-report-library-icon name="folder-open" /></span><h3 class="text-sm font-bold">{{ array_filter($filters) ? 'No projects match your filters' : 'No issued projects yet' }}</h3><p class="max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400">{{ array_filter($filters) ? 'Try another college, status, or project name.' : 'Projects appear here once their Notice to Proceed is issued.' }}</p></div>
                @endforelse
            </div>
            @if ($projects->hasPages())<div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $projects->links() }}</div>@endif
        </section>

        <section id="colleges" aria-labelledby="colleges-heading" class="dashboard-panel scroll-mt-40">
            <div class="dashboard-panel-heading"><h2 id="colleges-heading" class="dashboard-section-title">Across the colleges</h2><x-report-library-icon name="college" class="h-5 w-5 text-slate-500 dark:text-slate-400" /></div>
            <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($colleges as $college)
                    <a href="{{ route('vcrdes.dashboard', ['college' => $college['code']]).'#projects' }}" @if (($filters['college'] ?? '') === $college['code']) aria-current="true" @endif title="{{ $college['name'] }}: {{ $college['completed'] }} of {{ $college['total'] }} projects completed, {{ $college['delayed'] }} delayed" class="dashboard-metric block transition-colors hover:border-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand aria-[current=true]:ring-2 aria-[current=true]:ring-brand dark:hover:border-rose-400 dark:aria-[current=true]:ring-rose-300">
                        <div class="flex items-center gap-2 text-brand dark:text-red-300"><x-report-library-icon name="college" class="h-4 w-4" /><span class="flex-1 text-sm font-bold">{{ $college['code'] }}</span><span class="secondary-action">View <x-report-library-icon name="open" class="!h-4 !w-4" /></span></div>
                        <strong class="mt-2 block text-2xl font-extrabold tabular-nums">{{ $college['total'] }}</strong>
                        <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700" aria-hidden="true"><span class="bg-emerald-500" style="width: {{ $college['total'] ? $college['completed'] / $college['total'] * 100 : 0 }}%"></span><span class="bg-rose-500" style="width: {{ $college['total'] ? $college['delayed'] / $college['total'] * 100 : 0 }}%"></span></div>
                        <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400">{{ $college['completed'] }} completed @if ($college['delayed'])<span>· {{ $college['delayed'] }} delayed</span>@endif</p>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
