<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <span class="hidden h-12 w-12 items-center justify-center rounded-2xl border border-red-100 bg-red-50 text-[#7A0019] dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:flex"><x-report-library-icon name="preview" /></span>
                <div>
                    <p class="text-xs font-semibold text-[#7A0019] dark:text-red-300">VCRDES <span class="px-1.5 text-slate-300" aria-hidden="true">/</span> University-wide</p>
                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950 dark:text-white sm:text-3xl">Monitoring overview</h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="hidden text-xs text-slate-500 dark:text-slate-400 sm:block">{{ now()->timezone('Asia/Manila')->format('M j, Y') }}</span>
                <a href="{{ route('vcrdes.dashboard') }}" aria-label="Refresh monitoring overview" title="Refresh monitoring overview" class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 hover:border-[#7A0019] hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><x-report-library-icon name="refresh" class="h-5 w-5" /></a>
            </div>
        </div>
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
    <div data-vcrdes-dashboard class="space-y-6 text-slate-900 dark:text-slate-100">
        @if ($errors->any())
            <p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200">{{ $errors->first() }}</p>
        @endif

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
            <section id="overview" aria-labelledby="portfolio-heading" class="relative scroll-mt-36 overflow-hidden rounded-2xl bg-[#7A0019] text-white">
                <svg class="pointer-events-none absolute -right-20 -top-20 h-96 w-96 text-white/[0.06]" viewBox="0 0 400 400" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="200" cy="200" r="80" /><circle cx="200" cy="200" r="120" /><circle cx="200" cy="200" r="160" /><path d="M0 200h400M200 0v400" /></svg>
                <div class="relative flex flex-col justify-between gap-6 p-6 sm:flex-row sm:items-center sm:p-7">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-medium text-red-100"><span class="h-1.5 w-1.5 rounded-full bg-amber-300" aria-hidden="true"></span> {{ $filters['college'] ?? 'All colleges' }} · Issued projects</div>
                        <h2 id="portfolio-heading" class="mt-4 text-xl font-semibold tracking-tight">Research at a glance</h2>
                        <p class="mt-2 flex items-baseline gap-3"><span class="text-6xl font-extrabold tracking-tight tabular-nums">{{ $total - $completed }}</span><span class="shrink-0 text-sm leading-5 text-red-100">active research<br>projects</span></p>
                        <a href="#projects" aria-label="View research projects" title="View research projects" class="mt-5 inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/25 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><x-report-library-icon name="open" class="h-4 w-4" /></a>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 sm:shrink-0 sm:flex-col sm:gap-2">
                        <div class="relative h-28 w-28 shrink-0 sm:h-36 sm:w-36" role="img" aria-label="{{ $completionPercentage }} percent of issued projects completed">
                            <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="50" fill="none" stroke="currentColor" stroke-width="7" class="text-white/15" /><circle cx="60" cy="60" r="50" fill="none" stroke="#F4C76B" stroke-width="7" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $completionPercentage }} 100" /></svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center"><strong class="text-3xl font-extrabold tabular-nums">{{ $completionPercentage }}<span class="text-lg">%</span></strong><span class="mt-1 text-[11px] text-red-100">completed</span></div>
                        </div>
                        <span class="text-xs text-red-100">{{ $completed }} of {{ $total }} projects</span>
                    </div>
                </div>
                <div class="relative grid grid-cols-3 divide-x divide-white/15 border-t border-white/15 bg-black/10">
                    @foreach ([['ongoing', 'Ongoing', 'folder-open'], ['delayed', 'Delayed', 'history'], ['awaiting', 'Awaiting report / review', 'calendar']] as [$status, $label, $icon])
                        <a href="{{ route('vcrdes.dashboard', array_filter(['college' => $filters['college'] ?? null, 'status' => $status])).'#projects' }}" aria-label="{{ $label }}: {{ $counts->get($status, 0) }} projects" title="{{ $label }}" class="flex items-center justify-between gap-2 px-4 py-4 hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white sm:px-6"><x-report-library-icon :name="$icon" class="h-5 w-5 text-red-100" /><strong class="text-2xl font-bold tabular-nums">{{ $counts->get($status, 0) }}</strong></a>
                    @endforeach
                </div>
            </section>

            <section id="attention" aria-labelledby="attention-heading" class="scroll-mt-36 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <h2 id="attention-heading" class="flex items-center gap-2.5 text-sm font-bold"><x-report-library-icon name="history" class="h-4 w-4 text-amber-600 dark:text-amber-400" /> Needs attention</h2>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold tabular-nums text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ $attention->count() }}</span>
                </div>
                <div class="max-h-72 overflow-y-auto divide-y divide-slate-100 px-5 dark:divide-slate-800">
                    @forelse ($attention as $issue)
                        <article class="flex gap-3 py-3.5">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $issue['type'] === 'delayed' ? 'bg-rose-500' : 'bg-amber-500' }}" aria-hidden="true"></span>
                            <div class="min-w-0 flex-1"><h3 class="truncate text-xs font-bold" title="{{ $issue['title'] }}">{{ $issue['title'] }}</h3><p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $issue['issue'] }}</p></div>
                            @if ($issue['days'] !== null)<span class="shrink-0 pt-0.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400" title="{{ $issue['days'] }} days {{ $issue['basis'] }}">{{ $issue['days'] }}d</span>@endif
                        </article>
                    @empty
                        <div class="flex min-h-52 flex-col items-center justify-center gap-3 px-3 py-7 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><x-report-library-icon name="preview" /></span><p class="text-sm font-semibold">No flagged items</p><p class="max-w-52 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $total ? 'No issues found in the current monitoring records.' : 'Monitoring alerts appear when projects are issued.' }}</p></div>
                    @endforelse
                </div>
            </section>
        </div>

        <section id="projects" aria-labelledby="projects-heading" class="scroll-mt-36 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5 dark:border-slate-800">
                <div><h2 id="projects-heading" class="text-base font-bold">Research portfolio <span class="ml-2 text-xs font-medium text-slate-400">{{ $projects->total() }}</span></h2><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Delivery, deadlines, and the latest forwarded progress.</p></div>
                <form action="{{ route('vcrdes.dashboard') }}#projects" method="GET" class="grid w-full grid-cols-[minmax(0,1fr)_2.75rem] items-center gap-2 lg:flex lg:w-auto lg:flex-wrap">
                    <div class="relative min-w-0 flex-1 lg:w-48"><label for="portfolio-search" class="sr-only">Search projects or project leaders</label><input id="portfolio-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search projects…" class="h-11 w-full rounded-xl border-slate-200 bg-slate-50 text-xs focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-800 dark:text-white"></div>
                    <div class="col-span-2 row-start-2 grid grid-cols-2 gap-2 lg:contents">
                        <label for="portfolio-college" class="sr-only">College</label><select id="portfolio-college" name="college" class="h-11 min-w-0 max-w-full rounded-xl border-slate-200 bg-white py-2 pl-2.5 pr-6 text-xs focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-900"><option value="">All colleges</option>@foreach (\App\Models\User::COLLEGES as $code => $name)<option value="{{ $code }}" @selected(($filters['college'] ?? '') === $code)>{{ $code }}</option>@endforeach</select>
                        <label for="portfolio-status" class="sr-only">Project status</label><select id="portfolio-status" name="status" class="h-11 min-w-0 max-w-full rounded-xl border-slate-200 bg-white py-2 pl-2.5 pr-6 text-xs focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-700 dark:bg-slate-900"><option value="">All statuses</option>@foreach ($statusLabels as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <button type="submit" aria-label="Apply project filters" title="Apply project filters" class="col-start-2 row-start-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#7A0019] text-white hover:bg-red-950 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019]"><x-report-library-icon name="preview" class="h-5 w-5" /></button>
                    @if (array_filter($filters))<a href="{{ route('vcrdes.dashboard') }}#projects" aria-label="Clear project filters" title="Clear project filters" class="flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:border-slate-700 dark:hover:bg-slate-800"><x-report-library-icon name="undo" class="h-5 w-5" /></a>@endif
                </form>
            </div>
            <div class="hidden grid-cols-[minmax(0,1fr)_10rem_8rem_7rem_1rem] items-center gap-5 border-b border-slate-100 bg-slate-50/80 px-5 py-3 text-[11px] font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400 lg:grid"><span>Project / lead</span><span>Status</span><span>Progress</span><span>Deadline</span><span></span></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($projects as $project)
                    <details id="project-{{ $project['id'] }}" class="group scroll-mt-36 open:bg-slate-50/60 dark:open:bg-slate-800/30">
                        <summary class="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_1rem] items-center gap-5 px-5 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-[#7A0019] dark:hover:bg-slate-800/50 sm:grid-cols-[minmax(0,1fr)_8rem_1rem] lg:grid-cols-[minmax(0,1fr)_10rem_8rem_7rem_1rem] [&::-webkit-details-marker]:hidden">
                            <div class="flex min-w-0 items-center gap-3"><span class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-red-100 bg-red-50/70 text-[#7A0019] dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-200 sm:flex"><x-report-library-icon name="folder-open" class="h-5 w-5" /></span><div class="min-w-0"><h3 class="text-sm font-bold leading-5">{{ $project['title'] }}</h3><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $project['college'] ?: 'College not recorded' }} <span class="px-1 text-slate-300" aria-hidden="true">/</span> {{ $project['lead'] }}</p><span class="mt-2 inline-block rounded-md px-2 py-1 text-[10px] font-semibold sm:hidden {{ $statusStyles[$project['status']] }}">{{ $statusLabels[$project['status']] }}</span></div></div>
                            <span class="hidden w-fit rounded-md px-2 py-1.5 text-[10px] font-semibold sm:block {{ $statusStyles[$project['status']] }}">{{ $statusLabels[$project['status']] }}</span>
                            <div class="hidden lg:block"><span class="text-xs font-bold tabular-nums">{{ $project['progress'] !== null ? $project['progress'].'%' : 'Not reported' }}</span><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700" aria-hidden="true"><div class="h-full rounded-full {{ $project['status'] === 'delayed' ? 'bg-rose-500' : 'bg-[#7A0019] dark:bg-red-400' }}" style="width: {{ max(0, min(100, $project['progress'] ?? 0)) }}%"></div></div></div>
                            <span class="hidden text-xs tabular-nums text-slate-500 dark:text-slate-400 lg:block">{{ $project['deadline'] ? \Carbon\Carbon::parse($project['deadline'])->format('M j, Y') : 'No schedule' }}</span>
                            <x-report-library-icon name="open" class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                        </summary>
                        <div class="grid gap-4 border-t border-dashed border-slate-200 p-5 sm:grid-cols-2 lg:grid-cols-4 dark:border-slate-700">
                            @foreach ([['calendar', 'Deadline', $project['deadline'] ?: 'No schedule recorded'], ['presentation', 'Approved / estimated budget', $project['budget'] !== null ? '₱'.number_format($project['budget'], 2) : 'Not recorded'], ['export', 'Reported utilization', $project['utilized'] !== null ? '₱'.number_format($project['utilized'], 2) : 'Not reported'], ['history', 'Latest forwarded progress', $project['progress'] !== null ? $project['progress'].'% · '.$project['report_date'] : 'Not reported']] as [$icon, $label, $value])
                                <div class="flex gap-2.5"><x-report-library-icon :name="$icon" class="mt-0.5 h-4 w-4 text-slate-400" /><div><p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-1 text-xs font-semibold">{{ $value }}</p></div></div>
                            @endforeach
                            @if ($project['issues']->isNotEmpty())<ul class="space-y-1 text-xs leading-5 text-amber-800 dark:text-amber-200 sm:col-span-2 lg:col-span-4">@foreach ($project['issues'] as $issue)<li>{{ $issue['issue'] }}</li>@endforeach</ul>@endif
                        </div>
                    </details>
                @empty
                    <div class="flex flex-col items-center gap-3 px-5 py-12 text-center"><span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-[#7A0019] dark:bg-red-950/30 dark:text-red-200"><x-report-library-icon name="folder-open" /></span><h3 class="text-sm font-bold">{{ array_filter($filters) ? 'No projects match your filters' : 'No issued projects yet' }}</h3><p class="max-w-sm text-xs leading-5 text-slate-500 dark:text-slate-400">{{ array_filter($filters) ? 'Try another college, status, or project name.' : 'Projects appear here once their Notice to Proceed is issued.' }}</p></div>
                @endforelse
            </div>
            @if ($projects->hasPages())<div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $projects->links() }}</div>@endif
        </section>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.7fr)]">
            <section id="budget" aria-labelledby="budget-heading" class="scroll-mt-36 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between"><h2 id="budget-heading" class="text-sm font-bold">Budget utilization</h2><x-report-library-icon name="presentation" class="h-5 w-5 text-[#7A0019] dark:text-red-300" /></div>
                <p class="mt-5 text-3xl font-extrabold tracking-tight tabular-nums">{{ $budgetPercentage ?? '—' }}@if ($budgetPercentage !== null)<span class="text-lg text-slate-400">%</span>@endif</p>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" aria-label="{{ $budgetPercentage !== null ? $budgetPercentage.' percent budget utilization' : 'Utilization unavailable until budget and spending data are recorded' }}"><div class="h-full rounded-full bg-[#7A0019] dark:bg-red-400" style="width: {{ max(0, min(100, $budgetPercentage ?? 0)) }}%"></div></div>
                <dl class="mt-5 grid grid-cols-2 gap-4"><div><dt class="text-[11px] text-slate-500 dark:text-slate-400">Reported spending</dt><dd class="mt-1 break-all text-sm font-bold tabular-nums">{{ $reportedCount ? '₱'.number_format($utilized, 2) : 'Not reported' }}</dd></div><div><dt class="text-[11px] text-slate-500 dark:text-slate-400">{{ $budgetCount < $total ? 'Known approved / estimated' : 'Approved / estimated' }}</dt><dd class="mt-1 break-all text-sm font-bold tabular-nums">{{ $budgetCount ? '₱'.number_format($budget, 2) : 'No budgets recorded' }}</dd></div></dl>
                <p class="mt-4 border-t border-slate-100 pt-3 text-[11px] leading-5 text-slate-500 dark:border-slate-800 dark:text-slate-400">{{ $budgetCount }} of {{ $total }} budgets recorded · {{ $reportedCount }} with forwarded spending. Latest reported snapshots; unreported spending is excluded.</p>
            </section>

            <section id="colleges" aria-labelledby="colleges-heading" class="scroll-mt-36 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between"><h2 id="colleges-heading" class="text-sm font-bold">Across the colleges</h2><x-report-library-icon name="college" class="h-5 w-5 text-slate-400" /></div>
                <div class="mt-5 grid grid-cols-2 gap-x-5 gap-y-6 sm:grid-cols-3">
                    @foreach ($colleges as $college)
                        <a href="{{ route('vcrdes.dashboard', ['college' => $college['code']]).'#projects' }}" title="{{ $college['name'] }}: {{ $college['completed'] }} of {{ $college['total'] }} projects completed, {{ $college['delayed'] }} delayed" class="rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#7A0019]">
                            <div class="flex items-center justify-between gap-2"><span class="text-xs font-bold {{ ($filters['college'] ?? '') === $college['code'] ? 'text-[#7A0019] dark:text-red-300' : '' }}">{{ $college['code'] }}</span><strong class="text-lg font-extrabold tabular-nums">{{ $college['total'] }}</strong></div>
                            <div class="mt-2 flex h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true"><span class="bg-emerald-500" style="width: {{ $college['total'] ? $college['completed'] / $college['total'] * 100 : 0 }}%"></span><span class="bg-rose-400" style="width: {{ $college['total'] ? $college['delayed'] / $college['total'] * 100 : 0 }}%"></span></div>
                            <p class="mt-1.5 text-[10px] text-slate-500 dark:text-slate-400">{{ $college['completed'] }} completed @if ($college['delayed'])<span class="text-rose-700 dark:text-rose-300">· {{ $college['delayed'] }} delayed</span>@endif</p>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
        <p class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400"><x-report-library-icon name="preview" class="h-3.5 w-3.5" /> Institution-wide monitoring · {{ $filters['college'] ?? 'All colleges' }}</p>
    </div>
</x-app-layout>
