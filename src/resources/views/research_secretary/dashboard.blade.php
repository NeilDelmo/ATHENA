<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" eyebrow="Research Office Secretary" title="Secretary dashboard"
            subtitle="Review reports, release cleared documents, and track project budgets.">
            <x-slot name="actions">
                <a href="{{ route('research_secretary.reports.index') }}" class="dashboard-action">
                    <x-report-library-icon name="list" class="!h-5 !w-5" /> Review reports
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="role-dashboard" data-dashboard-palette="maroon-slate-white" data-secretary-workspace="dashboard" data-dashboard-layout="secretary-overview">
        <section aria-label="Office workload">
            <dl class="dashboard-metrics" data-dashboard-metrics>
                <x-dashboard-stat label="Items that need attention" :value="$reportReviewTasks->count() + $signingProjects->total()"
                    description="Report reviews and pending releases" icon="history" priority />
                <x-dashboard-stat label="Report reviews" :value="$reportReviewTasks->count()" description="Submitted reports awaiting review"
                    icon="list" href="#secretary-report-reviews" action="View reviews" />
                <x-dashboard-stat label="Awaiting release" :value="$signingProjects->total()" description="Cleared Notices to Proceed"
                    icon="submit" href="#secretary-releases" action="View releases" />
                <x-dashboard-stat label="Projects monitored" :value="$projectCount" description="Projects assigned to your office" icon="folder-open" />
            </dl>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-columns>
            <x-secretary-report-queue :reports="$reportReviewTasks" compact />
            <section id="secretary-releases" class="dashboard-panel scroll-mt-40" aria-labelledby="secretary-release-heading">
                <header class="dashboard-panel-heading">
                    <div>
                        <h2 id="secretary-release-heading" class="dashboard-section-title">Notices to Proceed</h2>
                        <p class="mt-1 text-sm rh-muted">{{ $signingProjects->total() }} awaiting release</p>
                    </div>
                    <a href="{{ route('research_secretary.notices.index') }}" class="secondary-action">
                        View all notices <x-report-library-icon name="open" class="!h-4 !w-4" />
                    </a>
                </header>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($signingProjects->take(2) as $project)
                        <article class="flex flex-wrap items-center justify-between gap-3 p-5">
                            <div class="min-w-0">
                                <h3 class="break-words font-semibold">{{ $project->title }}</h3>
                                <p class="mt-1 text-sm rh-muted">{{ $project->user?->name }}</p>
                                <p class="mt-1 text-sm font-medium text-brand dark:text-rose-300">{{ $project->status_started_at ? ((int) max(0, $project->status_started_at->diffInDays(now(), false))).' days waiting' : 'Clearance date unavailable' }}</p>
                            </div>
                            <a href="{{ route('topics.show', $project) }}#notice-to-proceed" class="dashboard-action">
                                <x-report-library-icon name="rename" class="!h-4 !w-4" /> {{ $project->hasPreparedNoticeToProceed() ? 'Upload signed documents' : 'Prepare Notice to Proceed' }}
                            </a>
                        </article>
                    @empty
                        <div class="p-5">
                            <h3 class="font-semibold">No documents waiting.</h3>
                            <p class="mt-1 text-sm rh-muted">Cleared projects appear here for release.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2" aria-label="Office work">
            <x-dashboard-shortcut title="Budget monitoring" description="View project budgets and reported disbursements."
                :href="route('research_secretary.budgets.index')" icon="presentation" action="Open budget monitoring" />
            <x-dashboard-shortcut title="Research targets" description="View annual college targets and recorded accomplishments."
                :href="route('research_secretary.targets.index')" icon="calendar" action="View targets" />
        </div>
    </div>
</x-app-layout>
