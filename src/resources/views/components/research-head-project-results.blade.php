@props(['items', 'statuses', 'filter' => '', 'scope' => 'All academic years'])
@php
    $title = match ($filter) {
        'active' => 'Active projects',
        'delayed' => 'Delayed / overdue projects',
        'completed' => 'Completed projects',
        'ongoing' => 'Ongoing projects',
        'awaiting' => 'Projects awaiting required report / review',
        default => 'All issued projects',
    };
@endphp
<section id="active-projects" tabindex="-1" class="rh-panel scroll-mt-40 p-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-labelledby="projects-heading" wire:key="analytics-project-results">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 id="projects-heading" class="rh-title">{{ $title }}</h3>
            <p class="mt-1 text-sm rh-muted" role="status">{{ $items->total() }} matching {{ str('project')->plural($items->total()) }} · {{ $scope }}</p>
        </div>
        <div class="flex w-full min-w-0 flex-wrap gap-2 sm:w-auto">
            <label for="project-status" class="sr-only">Project status filter</label>
            <select id="project-status" wire:model.live="projectStatus" class="rh-control w-full sm:w-auto sm:max-w-xs">
                <option value="">All issued projects</option><option value="active">Active projects</option>
                @foreach ($statuses as $stage)<option value="{{ $stage['key'] }}">{{ $stage['label'] }}</option>@endforeach
            </select>
            @if ($filter)<button type="button" wire:click="showProjects('')" class="rh-button-secondary">Clear project filter</button>@endif
        </div>
    </div>
    <p class="mt-3 text-sm rh-muted">{{ $filter === 'active' ? 'Issued projects that have not been marked completed, including delayed projects and projects awaiting reports or review.' : ($filter === 'delayed' ? 'Unfinished projects with a recorded delay or a passed project deadline or incomplete reported milestone.' : 'Current monitoring health for issued projects in the selected submission cohort.') }}</p>
    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($items as $project)
            <article class="flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800" wire:key="analytics-project-{{ $project['id'] }}">
                <a href="{{ route('topics.show', $project['id']) }}#project-monitoring" class="break-words font-semibold hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:text-rose-300">{{ $project['title'] }}</a>
                <p class="break-words text-sm rh-muted">Lead: {{ $project['lead'] }}</p>
                <strong class="text-sm {{ $project['status'] === 'delayed' ? 'text-red-700 dark:text-red-300' : 'text-brand dark:text-rose-300' }}">{{ $statuses->firstWhere('key', $project['status'])['label'] ?? str($project['status'])->headline() }}</strong>
                @if ($project['delay_reason'])
                    <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800 dark:bg-red-950/30 dark:text-red-200">{{ $project['delay_reason'] }}<span class="mt-1 block font-semibold">{{ $project['delay_days'] !== null ? $project['delay_days'].' '.str('day')->plural($project['delay_days']).' overdue' : 'Delay start date not recorded' }}</span></p>
                @endif
                @if (! $project['has_schedule'])<p class="text-sm text-amber-800 dark:text-amber-300">Schedule not recorded; deadline cannot be checked.</p>@endif
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="rh-muted">Project ends</dt><dd class="mt-1 font-medium">{{ $project['deadline'] ?? 'Not recorded' }}</dd></div>
                    <div><dt class="rh-muted">Reported completion</dt><dd class="mt-1 font-medium tabular-nums">{{ $project['progress'] !== null ? $project['progress'].'%' : 'Not reported' }}</dd></div>
                </dl>
                @if ($project['progress'] !== null)
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-label="{{ $project['title'] }} reported completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, max(0, $project['progress'])) }}"><div class="h-full rounded-full bg-brand dark:bg-rose-400" style="width: {{ min(100, max(0, $project['progress'])) }}%"></div></div>
                @endif
                @foreach ($project['issues']->reject(fn ($issue) => $issue['type'] === 'delayed')->take(2) as $issue)
                    <a href="{{ $issue['url'] }}" class="break-words text-sm font-medium text-brand underline decoration-brand/30 underline-offset-4 hover:decoration-brand dark:text-rose-300">{{ $issue['issue'] }}</a>
                @endforeach
                <p class="text-sm rh-muted">{{ $project['report_date'] ? 'Latest Monitoring Tool: '.$project['report_date'] : 'No monitoring tool submitted' }}</p>
                <a href="{{ route('topics.show', $project['id']) }}#project-monitoring" aria-label="{{ $project['status'] === 'completed' ? 'Open project archive' : 'Inspect monitoring' }}: {{ $project['title'] }}" class="mt-auto inline-flex min-h-[44px] items-center gap-2 self-start text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-rose-300">{{ $project['status'] === 'completed' ? 'Open project archive' : 'Inspect monitoring' }} <span aria-hidden="true">&rarr;</span></a>
            </article>
        @empty
            <p class="py-6 text-center rh-muted md:col-span-2 xl:col-span-3">No projects match this status in the selected cohort. Choose another status or adjust the academic year and submission dates.</p>
        @endforelse
    </div>
    @if ($items->hasPages())
        <div class="mt-4"><p class="mb-2 text-sm rh-muted">{{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} projects</p>{{ $items->links('livewire::simple-tailwind', ['scrollTo' => '#active-projects']) }}</div>
    @endif
</section>
