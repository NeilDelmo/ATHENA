<x-app-layout>
    <x-slot name="header"><x-page-header variant="hero" title="Secretary dashboard" :subtitle="now()->format('l, F j, Y')"><x-slot name="actions"><a href="{{ route('research_secretary.reports.index') }}" class="secretary-button-primary"><x-report-library-icon name="list" class="h-5 w-5" /> Review reports <x-report-library-icon name="open" class="h-4 w-4" /></a></x-slot></x-page-header></x-slot>
    @php($attentionTotal = $reportReviewTasks->count() + $signingProjects->total())
    <div class="secretary-workspace" data-secretary-workspace="dashboard" data-dashboard-layout="secretary-overview">
        <section aria-label="Office workload">
            <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-brand bg-white/20 sm:grid-cols-4">
                @foreach ([['Items that need attention', $attentionTotal, 'history'], ['Report reviews', $reportReviewTasks->count(), 'list'], ['Awaiting release', $signingProjects->total(), 'submit'], ['Projects monitored', $projectCount, 'folder-open']] as [$label, $count, $icon])
                    <div class="min-w-0 bg-brand px-4 py-4 text-white">
                        <dt class="flex items-start justify-between gap-3 text-sm font-medium leading-5"><span>{{ $label }}</span><x-report-library-icon :name="$icon" class="h-5 w-5" /></dt>
                        <dd class="mt-2 text-3xl font-bold leading-8 tabular-nums">{{ $count }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
        <div class="secretary-dashboard-columns" data-dashboard-columns>
            <x-secretary-report-queue :reports="$reportReviewTasks" compact />
            <aside class="secretary-office-links" aria-label="Office work">
                <section class="secretary-panel secretary-release-summary" aria-labelledby="secretary-release-heading">
                    <header class="secretary-section-heading"><div><h2 id="secretary-release-heading"><x-report-library-icon name="submit" class="h-5 w-5" /> Notices to Proceed</h2></div><span class="secretary-count">{{ $signingProjects->total() }}</span></header>
                    @forelse ($signingProjects->take(2) as $project)
                        <article class="secretary-release-row"><h3>{{ $project->title }}</h3><p class="secretary-muted">{{ $project->user?->name }}</p><p class="secretary-waiting">{{ $project->status_started_at ? ((int) max(0, $project->status_started_at->diffInDays(now(), false))).' days waiting' : 'Clearance date unavailable' }}</p><a href="{{ route('topics.show', $project) }}#notice-to-proceed" class="secretary-button-secondary"><x-report-library-icon name="rename" class="h-4 w-4" /> {{ $project->hasPreparedNoticeToProceed() ? 'Upload signed documents' : 'Prepare Notice to Proceed' }} <x-report-library-icon name="open" class="h-4 w-4" /></a></article>
                    @empty<div class="secretary-empty"><h3>No documents waiting.</h3><p>Cleared projects appear here for release.</p></div>@endforelse
                    <a href="{{ route('research_secretary.notices.index') }}" class="secretary-panel-footer secretary-button-secondary">View Notices to Proceed <x-report-library-icon name="open" class="h-4 w-4" /></a>
                </section>
            </aside>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <a href="{{ route('research_secretary.budgets.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="presentation" class="h-5 w-5" /></span><span><strong>Budget monitoring</strong><small>View project budgets and reported disbursements.</small></span><x-report-library-icon name="open" class="h-5 w-5" /></a>
            <a href="{{ route('research_secretary.targets.index') }}" class="secretary-target-link"><span class="secretary-file-mark" aria-hidden="true"><x-report-library-icon name="calendar" class="h-5 w-5" /></span><span><strong>Research targets</strong><small>Annual college targets and recorded accomplishments.</small></span><x-report-library-icon name="open" class="h-5 w-5" /></a>
        </div>
    </div>
</x-app-layout>
