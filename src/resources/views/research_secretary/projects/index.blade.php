<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-red-600">Financial monitoring</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Research Secretary Workspace</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">Complete budget utilization only for projects assigned to you.</p>
        </div>
    </x-slot>

    @php
        $preparedReports = $projects->sum(fn ($project) => $project->preparedProgressReports->count());
        $completedBudgets = $projects->sum(fn ($project) => $project->preparedProgressReports->filter->hasSecretaryPreparedBudget()->count());
    @endphp

    <div class="space-y-6">
        <section class="grid gap-4 sm:grid-cols-3">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950 p-5 text-white shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">Assigned portfolio</p>
                <p class="mt-3 text-3xl font-black tabular-nums">{{ $projects->count() }}</p>
                <p class="mt-1 text-xs text-slate-400">research {{ Str::plural('project', $projects->count()) }}</p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900 dark:bg-amber-950/30">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">Awaiting budget</p>
                <p class="mt-3 text-3xl font-black tabular-nums text-amber-950 dark:text-amber-100">{{ max(0, $preparedReports - $completedBudgets) }}</p>
                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">prepared monitoring tools</p>
            </div>
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">Budget confirmed</p>
                <p class="mt-3 text-3xl font-black tabular-nums text-emerald-950 dark:text-emerald-100">{{ $completedBudgets }}</p>
                <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">ready for researcher submission</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-gray-100 px-5 py-5 dark:border-slate-800 sm:px-6">
                <h3 class="text-base font-black text-gray-950 dark:text-white">Assigned projects</h3>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-slate-400">A budget becomes editable after the researcher prepares the quarterly Monitoring Tool.</p>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                @forelse ($projects as $project)
                    <article class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str_replace('_', ' ', $project->project_status ?? 'ongoing') }}</span>
                                <span class="text-[11px] font-semibold text-gray-400">Approved budget · ₱{{ number_format((float) $project->estimated_budget, 2) }}</span>
                            </div>
                            <h4 class="mt-3 text-base font-black leading-6 text-gray-950 dark:text-white">{{ $project->title }}</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Project Leader · {{ $project->user->name }}</p>
                        </div>

                        <div class="space-y-2">
                            @forelse ($project->preparedProgressReports as $report)
                                <a href="{{ route('project-budget.edit', [$project, $report]) }}" class="group flex items-center justify-between gap-3 rounded-xl border p-3 transition {{ $report->hasSecretaryPreparedBudget() ? 'border-emerald-200 bg-emerald-50 hover:border-emerald-300 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-amber-200 bg-amber-50 hover:border-amber-300 dark:border-amber-900 dark:bg-amber-950/30' }}">
                                    <span><span class="block text-xs font-black text-gray-950 dark:text-white">{{ $report->quarter_label }} · {{ $report->version_label }}</span><span class="mt-0.5 block text-[11px] text-gray-500 dark:text-slate-400">Prepared by {{ $report->submitter->name }}</span></span>
                                    <span class="shrink-0 rounded-lg bg-white px-2.5 py-1.5 text-[10px] font-black {{ $report->hasSecretaryPreparedBudget() ? 'text-emerald-700' : 'text-amber-700' }} shadow-sm dark:bg-slate-900">{{ $report->hasSecretaryPreparedBudget() ? 'Review budget' : 'Complete budget' }}</span>
                                </a>
                            @empty
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-3 text-xs leading-5 text-gray-500 dark:border-slate-700 dark:text-slate-400">No prepared quarterly report is waiting for financial entry.</div>
                            @endforelse
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-16 text-center">
                        <p class="text-sm font-black text-gray-800 dark:text-white">No projects assigned</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Projects will appear here after the Research Head assigns you in Project Monitoring.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
