<div class="space-y-4 text-slate-900" wire:key="research-head-dashboard">
    <section data-dashboard-section-navigation class="sticky top-[128px] z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white/95 px-4 py-3 shadow-md backdrop-blur">
        <div><h2 class="text-sm font-bold">Research analytics</h2><p class="text-xs text-slate-500">Current workload and recorded research outcomes.</p></div>
        <nav class="flex flex-wrap gap-2 text-xs font-semibold" aria-label="Dashboard sections">
            @foreach(['needs-attention' => 'Needs attention', 'received-proposals' => 'Proposal inbox', 'active-projects' => 'Active projects', 'research-calendar' => 'Calendar'] as $id => $label)<a href="#{{ $id }}" class="rounded px-3 py-2 hover:bg-rose-50 hover:text-[#800000]">{{ $label }}</a>@endforeach
        </nav>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-label="Analytics filters">
        <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-3">
            <div class="min-w-44 flex-1"><label for="analytics-year" class="block text-xs font-semibold">Academic year</label><select id="analytics-year" wire:model.live="academicYear" class="mt-1 w-full rounded-md border-slate-300 text-sm focus:ring-[#800000]"><option value="">All academic years</option>@foreach ($academicYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></div>
            <div><label for="analytics-from" class="block text-xs font-semibold">First submitted from</label><input id="analytics-from" type="date" wire:model="fromDate" class="mt-1 rounded-md border-slate-300 text-sm focus:ring-[#800000]"></div>
            <div><label for="analytics-to" class="block text-xs font-semibold">First submitted through</label><input id="analytics-to" type="date" wire:model="toDate" class="mt-1 rounded-md border-slate-300 text-sm focus:ring-[#800000]"></div>
            <button class="rounded-md bg-[#800000] px-4 py-2 text-sm font-semibold text-white" wire:loading.attr="disabled">Apply</button>
            <button type="button" wire:click="resetAnalyticsFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Reset</button>
        </form>
        @foreach (['academicYear', 'fromDate', 'toDate', 'submissionMonth'] as $field) @error($field)<p role="alert" class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror @endforeach
        <p class="mt-3 text-xs text-slate-500">Current status of the selected submission cohort; revisions do not move a proposal into a new cohort. Academic year uses the research call, or configured year dates for independent submissions. Calendar deadlines remain institution-wide.</p>
        @if ($academicYear && ! $analytics['target'])<p class="mt-2 text-xs text-amber-800">Set this academic year's dates below to calculate its monthly trend and achievements accurately.</p>@endif
    </section>

    <section class="grid grid-cols-2 gap-3 xl:grid-cols-6" aria-label="Research analytics KPIs">
        @foreach ([['review', 'Awaiting your review', 'awaiting_review'], ['active', 'Active projects', ''], ['delayed', 'Delayed / overdue projects', ''], ['completed', 'Completed projects', ''], ['faculty', 'Faculty currently involved', '']] as [$key, $label, $filter])
            @if ($filter)<button type="button" wire:click="setPipeline('{{ $filter }}')" aria-pressed="{{ $pipeline === $filter ? 'true' : 'false' }}" class="rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-[#800000]">
            @else<a href="#active-projects" wire:click="showProjects('{{ in_array($key, ['active', 'faculty'], true) ? 'active' : $key }}')" class="rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-[#800000]">@endif
                <strong class="block text-2xl font-bold tabular-nums text-[#800000]">{{ $analytics['kpis'][$key] }}</strong><span class="mt-2 block text-xs font-semibold">{{ $label }}</span>
                @if($key === 'faculty')<span class="mt-1 block text-[10px] text-slate-500">Distinct faculty on active issued projects</span>@endif
            @if($filter)</button>@else</a>@endif
        @endforeach
        <a href="#reported-budget" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-[#800000]"><strong class="block text-2xl font-bold tabular-nums text-[#800000]">{{ $analytics['budget']['percentage'] !== null ? number_format($analytics['budget']['percentage'], 1).'%' : '—' }}</strong><span class="mt-2 block text-xs font-semibold">Reported budget utilization</span><span class="mt-1 block text-[10px] text-slate-500">{{ $analytics['budget']['reported'] }} / {{ $analytics['budget']['total'] }} projects with usable figures</span></a>
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2" aria-labelledby="monthly-trend-heading">
            <h3 id="monthly-trend-heading" class="text-sm font-bold">Monthly submission trend</h3><p class="mt-1 text-xs text-slate-500">{{ $analytics['trendStart'] }} to {{ $analytics['trendEnd'] }} · first submissions vs revision events</p>
            <div class="mt-3 flex flex-wrap gap-4 text-xs"><span><span class="inline-block h-2 w-2 rounded-sm bg-[#800000]"></span> New proposals: {{ $analytics['trend']->sum('new') }}</span><span><span class="inline-block h-2 w-2 rounded-sm bg-slate-500"></span> Resubmissions / revisions: {{ $analytics['trend']->sum('revision') }}</span></div>
            @if ($analytics['trend']->sum('new') + $analytics['trend']->sum('revision') === 0)<p class="mt-4 text-sm text-slate-500">No data yet{{ $analytics['periodAvailable'] ? ' for this period.' : ' — configure academic-year dates.' }}</p>@endif
            @if ($analytics['trend']->isNotEmpty())
                <div class="mt-4 overflow-x-auto"><div class="flex min-h-44 items-end gap-2">
                    @foreach ($analytics['trend'] as $month)
                        <button type="button" wire:click="$set('submissionMonth', '{{ $submissionMonth === $month['key'] ? '' : $month['key'] }}')" class="min-w-[52px] flex-1 rounded-md p-1 text-center hover:bg-rose-50 focus:ring-2 focus:ring-[#800000] {{ $submissionMonth === $month['key'] ? 'bg-rose-50 ring-1 ring-[#800000]' : '' }}" aria-label="{{ $month['label'] }}: {{ $month['new'] }} new proposals and {{ $month['revision'] }} revisions. Filter inbox." aria-pressed="{{ $submissionMonth === $month['key'] ? 'true' : 'false' }}">
                            <span class="text-[10px] tabular-nums">{{ $month['new'] }} / {{ $month['revision'] }}</span>
                            <svg viewBox="0 0 48 110" class="mx-auto h-28 w-12" aria-hidden="true"><path d="M0 109H48" stroke="#e2e8f0"/><rect x="7" y="{{ 108 - $month['new'] / $analytics['trendMax'] * 100 }}" width="14" height="{{ $month['new'] / $analytics['trendMax'] * 100 }}" rx="2" fill="#800000"/><rect x="25" y="{{ 108 - $month['revision'] / $analytics['trendMax'] * 100 }}" width="14" height="{{ $month['revision'] / $analytics['trendMax'] * 100 }}" rx="2" fill="#64748b"/></svg><span class="block text-[10px] text-slate-600">{{ $month['label'] }}</span>
                        </button>
                    @endforeach
                </div></div>
            @endif
            <p class="mt-3 text-[11px] text-slate-500">Counts recorded events within the period, including revisions of older proposals. Legacy proposals without submission history are not assigned fabricated events. Click a month to open its proposals, including those now approved or closed.</p>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="pipeline-heading">
            <div class="flex items-center justify-between gap-2"><h3 id="pipeline-heading" class="text-sm font-bold">Proposal pipeline</h3><span class="text-xs text-slate-500">{{ $analytics['pipeline']->sum('count') }} total</span></div><p class="mt-1 text-xs text-slate-500">Current proposals grouped by workflow stage.</p>
            <div class="mt-3 space-y-1">@foreach ($analytics['pipeline'] as $stage)<button type="button" wire:click="setPipeline('{{ $stage['key'] }}')" aria-pressed="{{ $pipeline === $stage['key'] ? 'true' : 'false' }}" class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-xs hover:bg-rose-50 {{ $pipeline === $stage['key'] ? 'bg-rose-50 text-[#800000]' : 'bg-slate-50' }}"><span>{{ $stage['label'] }}</span><strong class="tabular-nums">{{ $stage['count'] }}</strong></button>@endforeach</div>
            @if ($analytics['pipeline']->sum('count') === 0)<p class="mt-3 text-xs text-slate-500">No data yet.</p>@endif
            <p class="mt-3 text-[11px] text-slate-500">Submitted = unopened pending proposals. Approved without an issued Notice to Proceed stays in final signing. Closed/rejected proposals are excluded.</p>
        </section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="project-status-heading">
            <h3 id="project-status-heading" class="text-sm font-bold">Project status analytics</h3><p class="mt-1 text-xs text-slate-500">Issued projects only. Each project appears in one category.</p>
            <div class="mt-3 grid grid-cols-2 gap-2">@foreach ($analytics['projectStatuses'] as $stage)<a href="#active-projects" wire:click="showProjects('{{ $projectStatus === $stage['key'] ? '' : $stage['key'] }}')" class="rounded-md border border-slate-200 p-3 hover:border-[#800000]"><strong class="block text-xl tabular-nums text-[#800000]">{{ $stage['count'] }}</strong><span class="text-xs">{{ $stage['label'] }}</span></a>@endforeach</div>
            <p class="mt-3 text-[11px] text-slate-500">Completed projects stay completed; reported 100% is awaiting a completion decision, not delayed implementation. Other projects are checked for delay, then missing reports/review, then ongoing status. Delay uses saved status, implementation end dates, or incomplete reported approved-plan milestones.</p>
            @if ($analytics['unknownSchedules'])<p class="mt-2 text-xs text-amber-800">{{ $analytics['unknownSchedules'] }} active projects lack an end date or duration; deadline-based delay and missing-report checks are unavailable for them.</p>@endif
        </section>
        <section id="reported-budget" class="scroll-mt-64 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <h3 class="text-sm font-bold">Reported budget utilization</h3><p class="mt-1 text-xs text-slate-500">Latest submitted monitoring-tool snapshot per project; not official accounting.</p>
            @if ($analytics['budget']['percentage'] !== null)<strong class="mt-4 block text-2xl text-[#800000]">{{ number_format($analytics['budget']['percentage'], 1) }}%</strong><p class="mt-1 text-sm">₱{{ number_format($analytics['budget']['utilized'], 2) }} reported / ₱{{ number_format($analytics['budget']['budget'], 2) }} recorded project budget</p>@else<p class="mt-4 text-sm text-slate-500">No data yet — submitted utilization figures and a positive project budget are required.</p>@endif
            <p class="mt-3 text-xs text-slate-500">Coverage: {{ $analytics['budget']['reported'] }} of {{ $analytics['budget']['total'] }} projects. Unreported or missing-budget projects are excluded from the percentage, not treated as zero utilization.</p><p class="mt-2 text-[11px] text-slate-500">Reports do not identify period-only versus cumulative spending, so ATHENA does not sum quarters or claim a lifetime spend. Remaining-budget alerts are snapshot checks for ended/completed projects, not verified unspent funds.</p>
        </section>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="targets-heading">
        <h3 id="targets-heading" class="text-sm font-bold">Research target vs achievement</h3><p class="mt-1 text-xs text-slate-500">{{ $academicYear ?: 'Select an academic year to compare saved annual targets.' }} · events within {{ $analytics['trendStart'] }} to {{ $analytics['trendEnd'] }}</p>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">@foreach ($analytics['targets'] as $metric)<div class="rounded-md bg-slate-50 p-3"><p class="text-xs font-semibold">{{ $metric['label'] }}</p><p class="mt-2 text-xl font-bold tabular-nums">{{ $metric['actual'] ?? '—' }} <span class="text-sm font-normal text-slate-500">/ {{ $metric['target'] ?? 'No target set' }}</span></p>@if($metric['target'] > 0 && $metric['actual'] !== null)<p class="mt-1 text-xs text-[#800000]">{{ round(100 * $metric['actual'] / $metric['target'], 1) }}% achieved</p>@elseif($metric['target'] === 0)<p class="mt-1 text-xs text-slate-500">Zero target; percentage not applicable.</p>@endif</div>@endforeach</div>
        <p class="mt-3 text-[11px] text-slate-500">Achievements use events in the selected period, independently of the first-submission cohort. Projects = Notices to Proceed issued. Participation = currently recorded faculty on projects issued or scheduled to overlap this period; collaborators must have accepted by the period end. Membership departures and actual completion dates are not historically logged, so this is recorded participation, not a historical attendance count. Publications = dated published journal outputs, deduplicated by normalized manuscript title; acceptances are not publications.</p>
        @if ($analytics['undatedPublications'])<p class="mt-2 text-xs text-amber-800">{{ $analytics['undatedPublications'] }} publication records lack an exact publication date (including year-only profile metadata); they cannot reliably be assigned to an academic year and are excluded.</p>@endif
        @if ($academicYear && $analytics['target'] && ! $analytics['targetMatches'])<p class="mt-2 text-xs text-amber-800">This date range differs from the saved academic year. Annual targets are hidden to avoid a misleading partial-year comparison.</p>@endif
        <details @if($editingTargets) open @endif class="mt-4 border-t border-slate-200 pt-3"><summary wire:click.prevent="editTargets" class="cursor-pointer text-xs font-semibold text-[#800000]">Configure academic-year dates and targets</summary>
            <form wire:submit="saveTargets" class="mt-3 grid gap-3 sm:grid-cols-3">
                @foreach (['academic_year' => ['Academic year', 'text'], 'starts_on' => ['Year starts', 'date'], 'ends_on' => ['Year ends', 'date'], 'projects_target' => ['Research projects target', 'number'], 'publications_target' => ['Publications target', 'number'], 'faculty_target' => ['Faculty participation target', 'number']] as $field => [$label, $type])<div><label for="target-{{ $field }}" class="block text-xs font-semibold">{{ $label }}</label><input id="target-{{ $field }}" type="{{ $type }}" wire:model="targetForm.{{ $field }}" @if($type === 'number') min="0" max="1000000" step="1" @endif class="mt-1 w-full rounded-md border-slate-300 text-sm focus:ring-[#800000]">@error('targetForm.'.$field)<p role="alert" class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>@endforeach
                <div class="flex flex-wrap items-center gap-3 sm:col-span-3"><button wire:loading.attr="disabled" class="rounded-md bg-[#800000] px-4 py-2 text-xs font-semibold text-white">Save year and targets</button><span class="text-xs text-slate-500">Leave an unknown target blank; zero is an intentional target.</span>@if(session('targets_saved'))<span role="status" class="text-xs text-emerald-700">{{ session('targets_saved') }}</span>@endif</div>
            </form>
        </details>
    </section>

    <section id="needs-attention" class="scroll-mt-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3"><h3 class="text-sm font-bold">Needs attention</h3><span class="text-xs text-slate-500">{{ $analytics['attention']->count() }} actionable issues · oldest first</span></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr>@foreach(['Research / Proposal', 'Issue', 'Current stage / status', 'Days waiting / overdue', 'Action'] as $heading)<th scope="col" class="px-4 py-3 font-semibold">{{ $heading }}</th>@endforeach</tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($attentionItems as $item)<tr><td class="min-w-40 px-4 py-3 font-semibold">{{ $item['title'] }}</td><td class="min-w-48 px-4 py-3">{{ $item['issue'] }}</td><td class="px-4 py-3">{{ $item['status'] }}</td><td class="px-4 py-3"><strong class="tabular-nums">{{ $item['days'] ?? '—' }}</strong><span class="block text-[10px] text-slate-500">{{ $item['days'] !== null ? $item['basis'] : 'No recorded stage/submission date' }}</span></td><td class="px-4 py-3"><a href="{{ $item['url'] }}" aria-label="Open {{ $item['title'] }}: {{ $item['issue'] }}" class="font-semibold text-[#800000] hover:underline">Open &rarr;</a></td></tr>@empty<tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No data yet or no items need attention in this cohort.</td></tr>@endforelse
        </tbody></table></div>@if($attentionItems->hasPages())<div class="border-t border-slate-200 p-3">{{ $attentionItems->links() }}</div>@endif
    </section>

    <section id="received-proposals" class="scroll-mt-64 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3"><div><h3 class="text-sm font-bold">Received proposal inbox</h3><p class="text-xs text-slate-500">{{ $topics->total() }} proposals{{ $submissionMonth ? ' · '.$submissionMonth : '' }}{{ $pipeline ? ' · selected pipeline stage' : '' }}</p></div>
            <div class="flex flex-wrap gap-2" aria-label="Inbox controls"><label for="proposal-search" class="sr-only">Search proposals</label><input id="proposal-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search proposal or faculty" class="rounded-md border-slate-300 text-xs focus:ring-[#800000]"><label for="proposal-status" class="sr-only">Review status</label><select id="proposal-status" wire:model.live="status" class="rounded-md border-slate-300 text-xs focus:ring-[#800000]"><option value="">All active review stages</option>@foreach($stageLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><button type="button" wire:click="clearPipeline" class="rounded-md border border-slate-300 px-3 py-2 text-xs">Clear stage</button>@if($submissionMonth)<button type="button" wire:click="$set('submissionMonth', '')" class="rounded-md border border-slate-300 px-3 py-2 text-xs">Clear month</button>@endif</div>
        </div>
        <div class="overflow-x-auto"><table class="w-full table-fixed text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th scope="col" class="w-1/3 px-4 py-3">Proposal and lead</th><th scope="col" class="px-4 py-3">Research call</th><th scope="col" class="px-4 py-3">Version</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3 text-right">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse($topics as $topic)
                @php
                    $version = $topic->latestVersion;
                    $proposalUrl = route('topics.show', $topic).($topic->hasIssuedNoticeToProceed() ? '#project-monitoring' : '#proposal-review');
                @endphp
                <tr wire:key="proposal-row-{{ $topic->id }}">
                    <td class="px-4 py-3"><a href="{{ $proposalUrl }}" class="block truncate font-semibold hover:text-[#800000]">{{ $topic->title }}</a><span class="block truncate text-slate-500">{{ $topic->user?->name }} · {{ $version?->created_at?->format('M d, Y') ?? 'No submission date' }}</span></td>
                    <td class="px-4 py-3"><span class="block truncate">{{ $topic->researchCall?->title ?? 'Independent submission' }}</span></td>
                    <td class="px-4 py-3">{{ $version ? 'v'.$version->version_number.' · '.$version->files_count.' files' : '—' }}</td>
                    <td class="px-4 py-3">{{ $topic->researchHeadQueueStatusLabel($version) }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ $proposalUrl }}" aria-label="Open {{ $topic->title }}" class="font-semibold text-[#800000]">{{ $topic->status === 'ready_for_signature' ? 'Sign' : 'Open' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No proposals found. Try changing the search or filters.</td></tr>
            @endforelse
        </tbody></table></div>@if($topics->hasPages())<div class="border-t border-slate-200 p-3">{{ $topics->links() }}</div>@endif
    </section>

    <section id="active-projects" class="scroll-mt-64 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h3 class="text-sm font-bold">Project completion</h3><p class="text-xs text-slate-500">Latest submitted progress and derived status.</p></div>
            <div class="flex flex-wrap gap-2">
                <label for="project-status" class="sr-only">Project status filter</label>
                <select id="project-status" wire:model.live="projectStatus" class="rounded-md border-slate-300 text-xs">
                    <option value="">All issued projects</option><option value="active">Active projects</option>
                    @foreach($analytics['projectStatuses'] as $stage)<option value="{{ $stage['key'] }}">{{ $stage['label'] }}</option>@endforeach
                </select>
                <a href="{{ route('research_head.projects.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-[#800000]">Open monitoring &rarr;</a>
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">@forelse($projectItems as $project)<a href="{{ route('topics.show', $project['id']) }}#project-monitoring" class="rounded-md border border-slate-200 p-3 hover:border-[#800000]"><strong class="block truncate text-xs">{{ $project['title'] }}</strong><span class="block text-xs text-slate-500">{{ $project['lead'] }} · {{ str($project['status'])->headline() }}</span><span class="mt-3 block text-xs">{{ $project['progress'] === null ? 'No monitoring tool submitted' : $project['progress'].'% reported completion' }}</span><progress class="mt-1 h-2 w-full accent-[#800000]" value="{{ min(100, max(0, $project['progress'] ?? 0)) }}" max="100" aria-label="{{ $project['title'] }} completion" aria-valuenow="{{ $project['progress'] ?? 0 }}"></progress><span class="mt-2 block text-[11px] text-slate-500">End date: {{ $project['deadline'] ?? 'Not recorded' }}</span><span class="mt-1 block text-[11px] text-slate-500">{{ $project['percentage'] !== null ? number_format($project['percentage'], 1).'% · Latest report' : 'No utilization reported' }} · Recorded budget {{ $project['budget'] !== null ? '₱'.number_format($project['budget'], 2) : 'not recorded' }}</span></a>@empty<p class="py-6 text-center text-xs text-slate-500 md:col-span-2">No data yet for this project filter.</p>@endforelse</div>@if($projectItems->hasPages())<div class="mt-3">{{ $projectItems->links() }}</div>@endif
    </section>

    <details id="research-calendar" class="scroll-mt-64 rounded-lg border border-slate-200 bg-white p-4"><summary class="cursor-pointer text-sm font-bold">Research calendar and official deadlines <span class="text-xs font-normal text-slate-500">· {{ $deadlines->count() }} deadlines in 14 days</span></summary><div class="mt-4"><livewire:dashboard-calendar /></div></details>
</div>
