<x-app-layout>
    <x-slot name="header"><div><h2 class="text-2xl font-black tracking-tight text-gray-900">Project Monitoring</h2><p class="mt-1 text-xs text-gray-500">Track approved projects and review submitted progress updates.</p></div></x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['Ongoing', $summary['ongoing'], 'bg-blue-50 text-blue-700'], ['Delayed', $summary['delayed'], 'bg-red-50 text-red-700'], ['Completed', $summary['completed'], 'bg-green-50 text-green-700'], ['Reports awaiting review', $summary['pending_reports'], 'bg-amber-50 text-amber-700']] as [$label, $count, $style])
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wider text-gray-400">{{ $label }}</p><p class="mt-2 inline-flex rounded-xl px-3 py-1 text-2xl font-black {{ $style }}">{{ $count }}</p></div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('research_head.projects.index') }}" class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:grid-cols-[1fr_190px_210px_auto]">
            <input name="search" type="search" value="{{ $search }}" placeholder="Search project or researcher..." class="block w-full rounded-xl border-gray-200 text-sm">
            <select name="status" class="block w-full rounded-xl border-gray-200 text-sm font-semibold"><option value="">All project statuses</option>@foreach (['ongoing' => 'Ongoing', 'delayed' => 'Delayed', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
            <select name="attention" class="block w-full rounded-xl border-gray-200 text-sm font-semibold"><option value="">All report states</option><option value="needs_attention" @selected($attention === 'needs_attention')>Needs attention</option><option value="pending_reports" @selected($attention === 'pending_reports')>Reports awaiting review</option></select>
            <div class="flex gap-2"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Filter</button>@if ($search !== '' || $status !== '' || $attention !== '')<a href="{{ route('research_head.projects.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600">Clear</a>@endif</div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h3 class="text-base font-black text-gray-900">Approved projects</h3><p class="mt-1 text-xs text-gray-400">Delayed projects and projects with pending reports appear first.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Project</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Status</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Latest progress</th><th class="px-5 py-3 text-left text-[11px] font-black uppercase text-gray-400">Reports</th><th class="px-5 py-3"><span class="sr-only">Open</span></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($projects as $project)
                            @php($projectStatus = $project->project_status ?: 'ongoing')
                            <tr class="{{ $projectStatus === 'delayed' || $project->pending_reports_count > 0 ? 'bg-amber-50/30' : '' }}">
                                <td class="px-5 py-4"><p class="text-sm font-black text-gray-900">{{ $project->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $project->user->name }} &middot; {{ $project->researchCall->title }}</p></td>
                                <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $projectStatus === 'completed' ? 'bg-green-50 text-green-700' : ($projectStatus === 'delayed' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700') }}">{{ $projectStatus }}</span></td>
                                <td class="px-5 py-4">@if ($project->latestProgressReport)<p class="text-sm font-black text-gray-800">{{ $project->latestProgressReport->progress_percentage }}%</p><p class="mt-1 text-[11px] text-gray-400">{{ $project->latestProgressReport->reporting_date->format('M d, Y') }}</p>@else<span class="text-xs text-gray-400">No report yet</span>@endif</td>
                                <td class="px-5 py-4"><p class="text-xs font-bold text-gray-700">{{ $project->progress_reports_count }} total</p><p class="mt-1 text-[11px] {{ $project->pending_reports_count ? 'font-bold text-amber-700' : 'text-gray-400' }}">{{ $project->pending_reports_count }} awaiting review</p></td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('topics.show', $project) }}#project-monitoring" class="inline-flex rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white">Open monitoring</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center"><p class="text-sm font-bold text-gray-700">No projects found</p><p class="mt-1 text-xs text-gray-400">Approved research projects will appear here.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($projects->hasPages())<div class="border-t border-gray-100 px-5 py-4">{{ $projects->links() }}</div>@endif
        </div>
    </div>
</x-app-layout>
