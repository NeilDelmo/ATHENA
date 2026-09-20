<x-app-layout>
    <x-slot name="header"><div><h2 class="text-2xl font-black tracking-tight text-gray-900">Project Monitoring</h2><p class="mt-1 text-xs text-gray-500">Track approved projects and review submitted progress updates.</p></div></x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([['Ongoing', $summary['ongoing'], 'bg-blue-50 text-blue-700'], ['Delayed', $summary['delayed'], 'bg-red-50 text-red-700'], ['Completion pending', $summary['completion_pending'], 'bg-violet-50 text-violet-700'], ['Completed', $summary['completed'], 'bg-green-50 text-green-700'], ['Reports awaiting review', $summary['pending_reports'], 'bg-amber-50 text-amber-700']] as [$label, $count, $style])
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p><p class="mt-2 inline-flex rounded-xl px-3 py-1 text-2xl font-black {{ $style }}">{{ $count }}</p></div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('research_head.projects.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_190px_210px_auto]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search project or researcher..." class="block w-full rounded-xl border-gray-200 text-sm">
            <select name="status" class="block w-full rounded-xl border-gray-200 text-sm font-semibold"><option value="">All project statuses</option>@foreach (['ongoing' => 'Ongoing', 'delayed' => 'Delayed', 'completion_pending' => 'Completion pending', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
            <select name="attention" class="block w-full rounded-xl border-gray-200 text-sm font-semibold"><option value="">All report states</option><option value="needs_attention" @selected($attention === 'needs_attention')>Needs attention</option><option value="pending_reports" @selected($attention === 'pending_reports')>Reports awaiting review</option></select>
            <div class="flex gap-2"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Filter</button>@if ($search !== '' || $status !== '' || $attention !== '')<a href="{{ route('research_head.projects.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600">Clear</a>@endif</div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h3 class="text-base font-black text-gray-900">Research Projects Under Monitoring</h3><p class="mt-1 text-xs text-gray-400">Review implementation status and submitted reports. Delayed projects and projects with reports awaiting review appear first.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Project</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Status</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Latest progress</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Research Secretary</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Reports</th><th class="px-5 py-3"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($projects as $project)
                            @php
                                $latestProgressPercentage = $project->latestProgressReport?->progress_percentage;
                                $projectStatus = $project->monitoringStatusForProgress($latestProgressPercentage);
                                $projectStatusLabel = $project->monitoringStatusLabelForProgress($latestProgressPercentage);
                                $pendingReportCount = $project->pending_reports_count + $project->pending_narrative_reports_count;
                            @endphp
                            <tr class="{{ $projectStatus === 'delayed' || $pendingReportCount > 0 ? 'bg-amber-50/30' : '' }}">
                                <td class="px-5 py-4"><p class="text-sm font-black text-gray-900">{{ $project->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $project->user->name }} &middot; {{ $project->researchCall?->title ?? 'Independent submission' }}</p></td>
                                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $projectStatus === 'completed' ? 'bg-green-50 text-green-700' : ($projectStatus === 'completion_pending' ? 'bg-violet-50 text-violet-700' : ($projectStatus === 'delayed' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700')) }}">{{ $projectStatusLabel }}</span></td>
                                <td class="px-5 py-4">@if ($project->latestProgressReport)<p class="text-sm font-black text-gray-800">{{ $project->latestProgressReport->progress_percentage }}%</p><p class="mt-1 text-[11px] text-gray-400">Tool · {{ $project->latestProgressReport->reporting_date->format('M d, Y') }}</p>@elseif ($project->latestNarrativeReport)<p class="text-xs font-black text-gray-800">Progress report</p><p class="mt-1 text-[11px] text-gray-400">{{ $project->latestNarrativeReport->submission_date->format('M d, Y') }}</p>@else<span class="text-xs text-gray-400">No report yet</span>@endif</td>
                                <td class="px-5 py-4 align-top">
                                    <div x-data="researchSecretaryPicker({ candidates: @js($secretaryCandidates), selectedId: @js($project->research_secretary_id) })" class="relative min-w-56" data-research-secretary-picker>
                                        <form x-ref="form" method="POST" action="{{ route('research_head.projects.research-secretary', $project) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="research_secretary_id" :value="selectedId || ''">
                                        </form>

                                        @if ($project->researchSecretary)
                                            <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-2.5">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-[10px] font-black text-emerald-800 ring-1 ring-emerald-200">
                                                    @if ($project->researchSecretary->avatar)
                                                        <img src="{{ $project->researchSecretary->avatar }}" alt="" class="h-full w-full object-cover">
                                                    @else
                                                        {{ collect(explode(' ', $project->researchSecretary->name))->filter()->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}
                                                    @endif
                                                </span>
                                                <span class="min-w-0 flex-1"><span class="block truncate text-xs font-black text-gray-900">{{ $project->researchSecretary->name }}</span><span class="block truncate text-[10px] text-gray-500">{{ $project->researchSecretary->email }}</span></span>
                                            </div>
                                        @else
                                            <p class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">Not assigned</p>
                                        @endif

                                        <div class="mt-2 flex gap-2">
                                            <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.search.focus())" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-[11px] font-bold text-gray-700 hover:border-red-200 hover:text-red-700">{{ $project->researchSecretary ? 'Replace' : 'Assign secretary' }}</button>
                                            @if ($project->researchSecretary)
                                                <button type="button" @click="clearSelection" class="rounded-lg px-2 py-1.5 text-[11px] font-bold text-red-700 hover:bg-red-50">Remove</button>
                                            @endif
                                        </div>

                                        <div x-show="open" x-transition.origin.top x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-2 w-80 rounded-2xl border border-gray-200 bg-white p-3 shadow-2xl shadow-gray-900/15">
                                            <label class="sr-only" for="secretary-search-{{ $project->id }}">Search Research Secretary profiles</label>
                                            <input x-ref="search" id="secretary-search-{{ $project->id }}" x-model="query" type="search" autocomplete="off" placeholder="Search name, email, or college" class="block w-full rounded-xl border-gray-200 text-sm focus:border-red-600 focus:ring-red-600">
                                            <div class="mt-2 max-h-64 space-y-1 overflow-y-auto" role="listbox">
                                                <template x-for="candidate in filteredCandidates()" :key="candidate.id">
                                                    <button type="button" role="option" @click="select(candidate.id)" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left hover:bg-red-50 focus:bg-red-50 focus:outline-none">
                                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-black text-red-700 ring-1 ring-red-200">
                                                            <img x-show="candidate.avatar" :src="candidate.avatar" alt="" x-on:error="candidate.avatar = ''" class="h-full w-full object-cover">
                                                            <span x-show="!candidate.avatar" x-text="initials(candidate.name)"></span>
                                                        </span>
                                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-gray-900" x-text="candidate.name"></span><span class="block truncate text-xs text-gray-500" x-text="candidate.email"></span><span x-show="candidate.college" class="mt-0.5 block truncate text-[10px] font-bold uppercase tracking-wide text-gray-400" x-text="candidate.college"></span></span>
                                                    </button>
                                                </template>
                                                <p x-show="filteredCandidates().length === 0" class="px-3 py-5 text-center text-xs font-semibold text-gray-500">No Research Secretary profile matches this search.</p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4"><p class="text-xs font-bold text-gray-700">{{ $project->progress_reports_count + $project->narrative_reports_count }} total</p><p class="mt-1 text-[11px] {{ $pendingReportCount ? 'font-bold text-amber-700' : 'text-gray-400' }}">{{ $pendingReportCount }} awaiting review</p></td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('topics.show', $project) }}#project-monitoring" class="inline-flex rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white">Open monitoring</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><p class="text-sm font-bold text-gray-700">No projects found</p><p class="mt-1 text-xs text-gray-400">Approved projects appear here when implementation monitoring begins.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($projects->hasPages())<div class="border-t border-gray-100 px-5 py-4">{{ $projects->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
