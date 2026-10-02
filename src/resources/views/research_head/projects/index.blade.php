@php
    $projectRoute = ($completedOnly ?? false) ? 'research_head.completed-projects.index' : 'research_head.projects.index';
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header class="rh-review-page-header [&_h1]:text-3xl [&_p]:text-base [&_p]:leading-6" :title="($completedOnly ?? false) ? 'Completed projects' : 'Research projects'" subtitle="Track approved projects and review submitted progress updates." />
    </x-slot>

    <div class="rh-review-page space-y-5" data-project-monitoring-list>
        <x-kpi-strip class="[&_dt]:text-sm [&_dt]:leading-5 [&_dd]:!text-[28px] [&_svg]:h-4 [&_svg]:w-4" :items="[
            ['label' => 'Ongoing', 'value' => $summary['ongoing'], 'icon' => 'play'],
            ['label' => 'Delayed', 'value' => $summary['delayed'], 'icon' => 'alert-triangle'],
            ['label' => 'Completion pending', 'value' => $summary['completion_pending'], 'icon' => 'hourglass'],
            ['label' => 'Completed', 'value' => $summary['completed'], 'icon' => 'circle-check'],
            ['label' => 'Reports awaiting review', 'value' => $summary['pending_reports'], 'icon' => 'file-text'],
        ]" />

        <form method="GET" action="{{ route($projectRoute) }}" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_240px_260px_auto]">
            <label class="sr-only" for="project-search">Search project or researcher</label>
            <input id="project-search" name="search" type="search" value="{{ $search }}" placeholder="Search project or researcher..." class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white">
            <label class="sr-only" for="project-status">Project status</label>
            <select id="project-status" @if ($completedOnly ?? false) disabled aria-label="Completed project status" @endif name="status" class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">All project statuses</option>@foreach (['ongoing' => 'Ongoing', 'delayed' => 'Delayed', 'completion_pending' => 'Completion pending', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
            <label class="sr-only" for="project-attention">Report state</label>
            <select id="project-attention" name="attention" class="block w-full rounded-xl border-gray-200 text-base focus:border-brand focus:ring-brand dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option value="">All report states</option><option value="needs_attention" @selected($attention === 'needs_attention')>Needs attention</option><option value="pending_reports" @selected($attention === 'pending_reports')>Reports awaiting review</option></select>
            <div class="flex gap-2"><button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Filter</button>@if ($search !== '' || $status !== '' || $attention !== '')<a href="{{ route($projectRoute) }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>@endif</div>
        </form>

        <section aria-labelledby="project-records-heading" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-gray-100 px-5 py-4 dark:border-slate-800"><h3 id="project-records-heading" class="text-xl font-black text-gray-900 dark:text-white">{{ ($completedOnly ?? false) ? 'Completed project records' : 'Research projects under monitoring' }}</h3><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Review implementation status and submitted reports. Delayed projects and projects with reports awaiting review appear first.</p></div>
            <div data-project-list-layout="rows" class="overflow-hidden rounded-b-xl border-x border-b border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <div aria-hidden="true" class="hidden grid-cols-[minmax(0,1fr)_160px_170px_220px_160px_196px] items-center gap-4 border-b border-brand bg-brand px-4 py-3 text-sm font-semibold text-white dark:border-red-900 2xl:grid">
                    <span>Project and faculty</span><span>Status</span><span>Latest progress</span><span>Project secretary</span><span>Reports</span><span class="text-right">Action</span>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($projects as $project)
                        @php
                            $latestProgressPercentage = $project->latestProgressReport?->progress_percentage;
                            $projectStatus = $project->monitoringStatusForProgress($latestProgressPercentage);
                            $projectStatusLabel = $project->monitoringStatusLabelForProgress($latestProgressPercentage);
                            $pendingReportCount = $project->pending_reports_count + $project->pending_narrative_reports_count;
                        @endphp
                        <article data-project-id="{{ $project->id }}" class="grid min-w-0 gap-3 bg-white px-4 py-3 hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800/50 sm:grid-cols-2 2xl:grid-cols-[minmax(0,1fr)_160px_170px_220px_160px_196px] 2xl:items-center 2xl:gap-4">
                            <div class="min-w-0 sm:col-span-2 2xl:col-span-1">
                                <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white">{{ $project->title }}</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $project->user->name }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ $project->researchCall?->title ?? 'Independent submission' }}</p>
                            </div>
                            <div class="min-w-0">
                                <span class="sr-only">Status:</span>
                                <span @class([
                                    'inline-flex max-w-full rounded-md px-2 py-1 text-sm font-medium leading-5',
                                    'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300' => $projectStatus === 'completed',
                                    'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300' => $projectStatus === 'completion_pending',
                                    'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300' => $projectStatus === 'delayed',
                                    'border border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200' => ! in_array($projectStatus, ['completed', 'completion_pending', 'delayed'], true),
                                ])>{{ $projectStatusLabel }}</span>
                            </div>
                            <div class="min-w-0 text-sm text-gray-500 dark:text-slate-400">
                                <span class="sr-only">Latest progress:</span>
                                @if ($project->latestProgressReport)
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $project->latestProgressReport->progress_percentage }}%</p>
                                    <p class="mt-1">Tool · {{ $project->latestProgressReport->reporting_date->format('M d, Y') }}</p>
                                @elseif ($project->latestNarrativeReport)
                                    <p class="font-medium text-gray-700 dark:text-slate-200">Progress report</p>
                                    <p class="mt-1">{{ $project->latestNarrativeReport->submission_date->format('M d, Y') }}</p>
                                @else
                                    <span>No report yet</span>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <span class="sr-only">Project secretary:</span>
                                @if ($project->researchSecretary)
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-100 text-sm font-semibold text-gray-700 dark:bg-slate-800 dark:text-slate-200">
                                            @if ($project->researchSecretary->avatar)
                                                <img src="{{ $project->researchSecretary->avatar }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                {{ collect(explode(' ', $project->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                            @endif
                                        </span>
                                        <span class="min-w-0 flex-1"><span class="block break-words text-sm font-medium text-gray-900 dark:text-white">{{ $project->researchSecretary->name }}</span><span class="mt-1 block text-sm leading-5 text-gray-500 dark:text-slate-400">Selected by project group</span></span>
                                    </div>
                                @else
                                    <p class="text-sm leading-5 text-gray-500 dark:text-slate-400">Awaiting selection by the project group.</p>
                                @endif
                            </div>
                            <div class="min-w-0 text-sm text-gray-500 dark:text-slate-400">
                                <span class="sr-only">Reports:</span>
                                <p class="font-medium text-gray-700 dark:text-slate-200">{{ $project->progress_reports_count + $project->narrative_reports_count }} total</p>
                                <p @class(['mt-1', 'font-medium text-red-700 dark:text-red-300' => $pendingReportCount > 0])>{{ $pendingReportCount }} awaiting review</p>
                            </div>
                            <div class="text-right sm:col-span-2 2xl:col-span-1">
                                <a href="{{ route('topics.show', $project) }}#project-monitoring" aria-label="Monitor {{ $project->title }}" class="inline-flex min-h-11 w-48 whitespace-nowrap items-center justify-center gap-2 rounded-lg bg-brand px-4 text-base font-semibold text-white hover:bg-brand-soft focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:focus-visible:outline-red-400">Monitor <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @empty
                        <div class="px-4 py-8 text-center"><h4 class="text-base font-semibold text-gray-900 dark:text-white">No projects found</h4><p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Projects appear here after the final papers are completed and the Notice to Proceed is issued.</p></div>
                    @endforelse
                </div>
            </div>
            @if ($projects->hasPages())<div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $projects->links() }}</div>@endif
        </section>
    </div>
</x-app-layout>
