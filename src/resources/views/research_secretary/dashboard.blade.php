<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" title="Secretary dashboard" :subtitle="now()->format('l, F j, Y')"><x-slot name="actions"><a href="{{ route('research_secretary.reports.index') }}" class="secretary-button-primary">Review reports <span aria-hidden="true">→</span></a></x-slot></x-page-header></x-slot>
    @php($attentionTotal = $reportReviewTasks->count() + $signingProjects->total())
    <div class="secretary-workspace" data-secretary-workspace="dashboard" data-dashboard-layout="secretary-overview">
        <section aria-label="Office workload">
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 sm:grid-cols-4 dark:border-slate-800 dark:bg-slate-800">
                @foreach ([['Items that need attention', $attentionTotal], ['Report reviews', $reportReviewTasks->count()], ['Awaiting release', $signingProjects->total()], ['Projects monitored', $projectCount]] as [$label, $count])
                    <div class="min-w-0 bg-white px-4 py-3 dark:bg-slate-900">
                        <dt class="text-sm font-medium leading-5 text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-1 text-2xl font-semibold leading-8 tabular-nums text-slate-900 dark:text-slate-100">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <div class="secretary-dashboard-columns" data-dashboard-columns>
            <x-secretary-report-queue :reports="$reportReviewTasks" compact />
            <aside class="secretary-office-links" aria-label="Office work">
                <section class="secretary-panel secretary-release-summary" aria-labelledby="secretary-release-heading">
                    <header class="secretary-section-heading"><div><h2 id="secretary-release-heading">Notices to Proceed</h2></div><span class="secretary-count">{{ $signingProjects->total() }}</span></header>
                    @forelse ($signingProjects->take(2) as $project)
                        <article class="secretary-release-row"><h3>{{ $project->title }}</h3><p class="secretary-muted">{{ $project->user?->name }}</p><p class="secretary-waiting">{{ $project->status_started_at ? ((int) max(0, $project->status_started_at->diffInDays(now(), false))).' days waiting' : 'Clearance date unavailable' }}</p><a href="{{ route('topics.show', $project) }}#notice-to-proceed" class="secretary-button-secondary">{{ $project->hasPreparedNoticeToProceed() ? 'Upload signed documents' : 'Prepare Notice to Proceed' }} <span aria-hidden="true">→</span></a></article>
                    @empty<div class="secretary-empty"><h3>No documents waiting.</h3><p>Cleared projects appear here for release.</p></div>@endforelse
                    <a href="{{ route('research_secretary.notices.index') }}" class="secretary-panel-footer secretary-button-secondary">View Notices to Proceed <span aria-hidden="true">→</span></a>
                </section>
            </aside>
        </div>
        <a href="{{ route('research_secretary.budgets.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="list" class="h-5 w-5" /></span><span><strong>Budget monitoring</strong><small>View project budgets and reported disbursements.</small></span><span aria-hidden="true">→</span></a>
        <a href="{{ route('research_secretary.targets.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="calendar" class="h-5 w-5" /></span><span><strong>Research targets</strong><small>Annual college targets and recorded accomplishments.</small></span><span aria-hidden="true">→</span></a>
    </div>
</x-app-layout>
