<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" title="Projects" subtitle="Monitor project status and reported budgets."><x-slot name="actions"><x-back-link href="{{ route('research_secretary.dashboard') }}">Back to dashboard</x-back-link></x-slot></x-page-header></x-slot>
    <div class="secretary-workspace" data-secretary-workspace="projects">

        <section class="secretary-panel" aria-label="Project directory">
            <form method="GET" action="{{ route('research_secretary.projects.index') }}" class="secretary-directory-filters" role="search">
                <div class="secretary-search"><label for="secretary-project-search" class="sr-only">Search projects</label><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><input id="secretary-project-search" type="search" name="q" value="{{ request('q') }}" placeholder="Search projects or project leaders" maxlength="200"></div>
                <div><label for="secretary-project-status" class="sr-only">Project status</label><select id="secretary-project-status" name="status"><option value="">All project statuses</option>@foreach (['ongoing' => 'Ongoing', 'delayed' => 'Delayed', 'completion_pending' => 'Completion pending', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
                <button type="submit" class="secretary-button-primary">Find projects</button>
                @if (request()->filled('q') || request()->filled('status'))<a href="{{ route('research_secretary.projects.index') }}" class="secretary-button-secondary">Clear filters</a>@endif
            </form>
            <header class="secretary-directory-labels" aria-hidden="true"><span>Project / project leader</span><span>Project budget</span><span>Reported disbursements</span></header>
            <div class="secretary-project-list">
                @forelse ($projects as $project)
                    @php($budgetSnapshot = $budgetMonitoring[$project->id])
                    <article class="secretary-project-row">
                        <div class="secretary-project-identity"><div class="min-w-0"><span class="secretary-project-status {{ $project->project_status === 'delayed' ? 'is-delayed' : '' }}">{{ str($project->project_status ?? 'ongoing')->headline() }}</span><h3>{{ $project->title }}</h3><p class="secretary-muted">{{ $project->user->name }}</p></div></div>
                        <div class="secretary-project-budget"><span class="secretary-mobile-label">Project budget</span><strong>{{ $budgetSnapshot['budget'] !== null ? '₱'.number_format($budgetSnapshot['budget'], 2) : 'Not recorded' }}</strong><span class="secretary-muted block">{{ $budgetSnapshot['budget_source'] === 'estimated' ? 'Proposal estimate' : 'Approved budget' }}</span></div>
                        <div class="secretary-project-records"><span class="secretary-mobile-label">Reported disbursements</span><strong>{{ $budgetSnapshot['utilized'] !== null ? '₱'.number_format($budgetSnapshot['utilized'], 2) : 'Not reported' }}</strong><span class="secretary-muted block">{{ $project->latestProgressReport?->reporting_period_label }}</span></div>
                    </article>
                @empty
                    <div class="secretary-empty"><span class="secretary-empty-icon" aria-hidden="true">⌑</span><h3>{{ $projectCount ? 'No projects match your search.' : 'No projects to monitor.' }}</h3><p>{{ $projectCount ? 'Try another project title, project leader, or status.' : 'Projects appear here after their Notice to Proceed is issued.' }}</p></div>
                @endforelse
            </div>
            <footer class="secretary-directory-footer">{{ $projects->count() }} {{ str('project')->plural($projects->count()) }} shown</footer>
        </section>
    </div>
</x-app-layout>
