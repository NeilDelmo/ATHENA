<section id="project-monitoring" class="rounded-2xl border border-gray-200 bg-white shadow-sm">
    @php
        $projectStatus = $topic->project_status ?: 'ongoing';
        $projectStatusClass = match ($projectStatus) { 'completed' => 'bg-green-50 text-green-700', 'delayed' => 'bg-red-50 text-red-700', default => 'bg-blue-50 text-blue-700' };
    @endphp
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
        <div><h3 class="text-sm font-black text-gray-900">Project monitoring</h3><p class="mt-1 text-xs text-gray-500">Official monitoring tools and Research Head feedback.</p></div>
        <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $projectStatusClass }}">{{ $projectStatus }}</span>
    </div>
    <div class="space-y-5 p-6">
        @if (Auth::user()->isUsingWorkspace('research_head'))
            <form method="POST" action="{{ route('research_head.projects.update-status', $topic) }}" class="flex flex-col gap-2 rounded-xl bg-gray-50 p-4 sm:flex-row sm:items-end">@csrf @method('PATCH')
                <label class="flex-1 text-[11px] font-bold uppercase text-gray-500">Execution status<select name="project_status" class="mt-1 block w-full rounded-xl border-gray-200 text-xs font-bold">@foreach (['ongoing', 'delayed', 'completed'] as $value)<option value="{{ $value }}" @selected($projectStatus === $value)>{{ ucfirst($value) }}</option>@endforeach</select></label>
                <button class="rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white">Update status</button>
            </form>
        @else
            @if ($topic->user_id === Auth::id() && $projectStatus !== 'completed')
                <div class="space-y-3">
                    <x-monitoring-tool-form :topic="$topic" />
                    <x-progress-report-form :topic="$topic" />
                </div>
            @endif
        @endif

        <div class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Monitoring tools</p><p class="mt-1 text-xs text-gray-400">Work plan and budget utilization submissions.</p></div>
            </div>
            @forelse ($topic->progressReports as $report)
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3"><div><p class="text-sm font-black text-gray-900">{{ $report->progress_percentage }}% complete</p><p class="mt-1 text-[11px] text-gray-400">{{ $report->reporting_date->format('M d, Y') }} · {{ $report->submitter->name }}</p></div><span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ str_replace('_', ' ', $report->review_status) }}</span></div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-blue-600" style="width: {{ $report->progress_percentage }}%"></div></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2"><div><p class="text-[10px] font-black uppercase text-gray-400">Accomplishments</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishments }}</p></div><div><p class="text-[10px] font-black uppercase text-gray-400">Issues or delays</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->issues ?: 'None reported.' }}</p></div></div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        @if (is_array($report->work_plan) && is_array($report->budget_utilization))
                            <a href="{{ route('project-progress.monitoring-tool', $report) }}" class="inline-flex text-xs font-bold text-blue-700">Download official monitoring tool</a>
                        @endif
                        @if ($report->attachment_path)<a href="{{ route('project-progress.download', $report) }}" class="inline-flex text-xs font-bold text-blue-700">Download supporting attachment</a>@endif
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head'))
                        <form method="POST" action="{{ route('research_head.progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No monitoring tools yet</p><p class="mt-1 text-xs text-gray-400">The first faculty submission will appear here.</p></div>
            @endforelse
        </div>

        <div class="space-y-4 border-t border-gray-100 pt-5">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-sm font-black text-gray-900">Progress reports</p><p class="mt-1 text-xs text-gray-400">Narrative accomplishment reports with captioned photo documentation.</p></div>
            </div>
            @forelse ($topic->narrativeReports as $report)
                <article class="rounded-xl border border-gray-200 p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="text-sm font-black text-gray-900">Progress report</p>
                            <p class="mt-1 text-[11px] text-gray-400">{{ $report->submission_date->format('M d, Y') }} · {{ $report->submitter->name }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-[10px] font-black uppercase text-gray-600">{{ str_replace('_', ' ', $report->review_status) }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Monitoring-period accomplishment</p><p class="mt-1 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $report->accomplishment_summary }}</p></div>
                        <div><p class="text-[10px] font-black uppercase text-gray-400">Funding agency</p><p class="mt-1 text-xs leading-5 text-gray-600">{{ $report->funding_agency }}</p></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-4">
                        <a href="{{ route('project-narrative-reports.download', $report) }}" class="inline-flex text-xs font-bold text-emerald-700">Download official progress report</a>
                        @foreach ($report->photos ?? [] as $photoIndex => $photo)
                            <a href="{{ route('project-narrative-reports.photos.download', [$report, $photoIndex]) }}" class="inline-flex text-xs font-bold text-blue-700">Photo {{ $photoIndex + 1 }}: {{ $photo['caption'] }}</a>
                        @endforeach
                    </div>
                    @if ($report->research_head_remarks)<div class="mt-3 rounded-xl bg-gray-50 p-3"><p class="text-[10px] font-black uppercase text-gray-400">Research Head remarks</p><p class="mt-1 text-xs text-gray-600">{{ $report->research_head_remarks }}</p></div>@endif
                    @if (Auth::user()->isUsingWorkspace('research_head'))
                        <form method="POST" action="{{ route('research_head.narrative-progress-reports.review', $report) }}" class="mt-4 grid gap-2 sm:grid-cols-[180px_1fr_auto]">@csrf @method('PATCH')<select name="review_status" class="rounded-xl border-gray-200 text-xs font-bold"><option value="reviewed" @selected($report->review_status === 'reviewed')>Mark reviewed</option><option value="revision_requested" @selected($report->review_status === 'revision_requested')>Request revision</option></select><input name="research_head_remarks" value="{{ $report->research_head_remarks }}" maxlength="5000" class="rounded-xl border-gray-200 text-xs" placeholder="Remarks"><button class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white">Save review</button></form>
                    @endif
                </article>
            @empty
                <div class="rounded-xl bg-gray-50 py-8 text-center"><p class="text-sm font-bold text-gray-700">No progress reports yet</p><p class="mt-1 text-xs text-gray-400">The first narrative progress report will appear here.</p></div>
            @endforelse
        </div>
    </div>
</section>
