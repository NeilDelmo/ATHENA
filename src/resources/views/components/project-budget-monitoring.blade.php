@props(['projects', 'budgetMonitoring', 'workspace'])

<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" title="Budget monitoring" subtitle="Project budgets and disbursements from the latest submitted Monitoring Tool.">
            <x-slot name="actions"><x-back-link href="{{ route($workspace.'.dashboard') }}">Back to dashboard</x-back-link></x-slot>
        </x-page-header>
    </x-slot>
    <div class="secretary-workspace" data-budget-monitoring-workspace="{{ $workspace }}" @if ($workspace === 'research_secretary') data-secretary-workspace="budgets" @endif>
        <section class="secretary-panel" aria-label="Project budget monitoring">
            <form method="GET" action="{{ route($workspace.'.budgets.index') }}" class="secretary-directory-filters" role="search">
                <div class="secretary-search"><label for="secretary-budget-search" class="sr-only">Search project budgets</label><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="secretary-budget-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search projects or project leaders" maxlength="200"></div>
                <button type="submit" class="secretary-button-primary">Find budgets</button>
                @if (request()->filled('q'))<a href="{{ route($workspace.'.budgets.index') }}" class="secretary-button-secondary">Clear search</a>@endif
            </form>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[48rem] text-left text-sm">
                    <caption class="sr-only">Project budgets and latest reported disbursements</caption>
                    <thead class="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"><tr><th scope="col" class="px-6 py-4 font-semibold">Project / project leader</th><th scope="col" class="px-6 py-4 font-semibold">Project budget</th><th scope="col" class="px-6 py-4 font-semibold">Reported disbursements</th><th scope="col" class="px-6 py-4 font-semibold">Latest submitted report</th></tr></thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse ($projects as $project)
                            @php
                                $snapshot = $budgetMonitoring[$project->id];
                                $report = $workspace === 'research_head' ? $project->latestProgressReportForResearchHead : $project->latestProgressReport;
                            @endphp
                            <tr>
                                <th scope="row" class="max-w-sm px-6 py-5 font-semibold text-slate-900 dark:text-slate-100">{{ $project->title }}<span class="mt-1 block font-normal text-slate-500 dark:text-slate-400">{{ $project->user->name }}</span></th>
                                <td class="px-6 py-5 tabular-nums text-slate-900 dark:text-slate-100">{{ $snapshot['budget'] !== null ? '₱'.number_format($snapshot['budget'], 2) : 'Not recorded' }}@if ($snapshot['budget_source'] === 'estimated')<span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Proposal estimate</span>@endif</td>
                                <td class="px-6 py-5 tabular-nums text-slate-900 dark:text-slate-100">{{ $snapshot['utilized'] !== null ? '₱'.number_format($snapshot['utilized'], 2) : 'Not reported' }}</td>
                                <td class="px-6 py-5 text-slate-600 dark:text-slate-300">{{ $report?->reporting_period_label ?: ($report?->reporting_date?->format('M j, Y') ?? 'No submitted Monitoring Tool') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">{{ request()->filled('q') ? 'No projects match your search.' : 'Project budgets appear here after a Notice to Proceed is issued.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
