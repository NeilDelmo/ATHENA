<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero"
            eyebrow="Research Coordinator"
            title="Research Coordinator Dashboard"
            :subtitle="'Record LREC feedback and prepare Notices to Proceed for projects from '.($coordinator->college ?: 'your college').'.'"
        />
    </x-slot>

    <div class="space-y-5" data-dashboard-layout="office-overview">
        <section aria-label="College summary">
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <x-dashboard-stat label="Awaiting release" :value="$signingProjects->total()" description="Prepare notices and signed documents" href="#notice-release-queue" :priority="$signingProjects->total() > 0" icon="M4 4h10l6 6v10H4V4Zm10 0v6h6M8 14h8M8 17h5" />
                <x-dashboard-stat label="LREC proposals" :value="$lrecProposals->total()" description="Committee reviews and follow-up" href="#office-lrec-proposals" icon="M4 4h16v16H4V4Zm4 5h8M8 12h8M8 15h5" />
                <x-dashboard-stat label="College members" :value="$memberCount" description="View your college directory" :href="route('research_coordinator.members.index')" icon="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
            </dl>
        </section>

        <div class="grid items-start gap-5 xl:grid-cols-2" data-dashboard-columns>
        <x-notice-to-proceed-queue :projects="$signingProjects" />
        <section id="office-lrec-proposals" class="dashboard-panel scroll-mt-32" aria-labelledby="office-lrec-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-5 dark:border-slate-800 sm:px-6">
            <div>
            <h2 id="office-lrec-heading" class="text-lg font-bold text-gray-950 dark:text-white">LREC proposals</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Open a proposal to view its papers and record committee comments once the Research Head starts LREC review.</p>
            </div>
            <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ $lrecProposals->total() }} in your college</span>
        </div>

        @if (! $coordinator->college)
            <p class="px-5 py-8 text-sm text-gray-600 dark:text-slate-300 sm:px-6">Your account needs an assigned college before its LREC proposals can be shown.</p>
        @elseif ($lrecProposals->isEmpty())
            <p class="px-5 py-8 text-sm text-gray-600 dark:text-slate-300 sm:px-6">No proposals from your college have reached LREC yet.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                @foreach ($lrecProposals as $proposal)
                    @php
                        $waitingSince = $proposal->status_started_at;
                        $waitingDays = $waitingSince ? (int) max(0, $waitingSince->diffInDays(now(), false)) : null;
                    @endphp
                    <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h3 class="break-words font-semibold text-gray-950 dark:text-white">{{ $proposal->title }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $proposal->user?->name }} · Version {{ $proposal->latestVersion?->version_number ?? '—' }} · {{ $proposal->workflowStatusLabel($proposal->latestVersion) }}</p>
                            <p class="mt-2 text-sm font-medium tabular-nums text-brand dark:text-rose-300">{{ $waitingDays !== null ? $waitingDays.' '.str('day')->plural($waitingDays).' in this stage' : 'Stage start date unavailable' }}</p>
                        </div>
                        <a href="{{ route('topics.show', $proposal) }}#proposal-review" class="dashboard-action shrink-0">{{ $proposal->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW ? 'Record comments' : 'View review status' }}</a>
                    </article>
                @endforeach
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800 sm:px-6">{{ $lrecProposals->links() }}</div>
        @endif
    </section>
        </div>
    </div>
</x-app-layout>
