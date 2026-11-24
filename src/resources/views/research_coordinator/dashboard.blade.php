<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero" eyebrow="Research Coordinator" title="Coordinator dashboard"
            :subtitle="(\App\Models\User::COLLEGES[$coordinator->college] ?? $coordinator->college ?? 'College research').' · LREC reviews and research reporting.'">
            <x-slot name="actions">
                <a href="{{ route('research_coordinator.proposals.index') }}" class="dashboard-action">
                    <x-report-library-icon name="list" class="!h-5 !w-5" /> Open LREC queue
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="role-dashboard" data-dashboard-palette="maroon-slate-white" data-coordinator-workspace="dashboard" data-dashboard-layout="office-overview">
        <section aria-label="College workload">
            <dl class="dashboard-metrics md:!grid-cols-3" data-dashboard-metrics>
                <x-dashboard-stat label="Ready for comments" :value="$lrecReviewCount" description="Committee reviews needing your input"
                    icon="rename" :href="route('research_coordinator.proposals.index', ['status' => 'review'])" action="View reviews" priority />
                <x-dashboard-stat label="LREC proposals" :value="$lrecProposals->total()" description="College proposals at the committee stage" icon="publication" />
                <x-dashboard-stat label="College members" :value="$memberCount" description="Faculty in your college directory" icon="college" />
            </dl>
        </section>

        <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]" data-dashboard-columns>
            <x-coordinator-lrec-queue :proposals="$lrecProposals" :coordinator="$coordinator" compact />
            <aside class="grid gap-5" aria-label="College work">
                <x-dashboard-shortcut title="Quarterly report" :description="'Prepare activities and attach evidence for Q'.now()->quarter.'.'"
                    :href="route('research_coordinator.reports.index')" icon="list" action="Open quarterly reporting" />
                <x-dashboard-shortcut title="Research targets" description="Track annual goals and recorded accomplishments."
                    :href="route('research_coordinator.targets.index')" icon="calendar" action="View targets" />
                <x-dashboard-shortcut title="Faculty directory" description="Find contact details for faculty in your college."
                    :href="route('research_coordinator.members.index')" icon="college" action="Open directory" />
            </aside>
        </div>
    </div>
</x-app-layout>
