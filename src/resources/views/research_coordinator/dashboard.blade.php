<x-app-layout>
    <x-slot name="header">
        <x-page-header variant="hero"
            eyebrow="Research Office"
            title="Research Office Dashboard"
            :subtitle="'Record LREC feedback and prepare Notices to Proceed for projects from '.($coordinator->college ?: 'your college').'.'"
        />
    </x-slot>

    <x-notice-to-proceed-queue :projects="$signingProjects" class="mb-6" />

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="College summary">
        <div class="flex items-center justify-between rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div>
                <span class="block text-[10px] font-black uppercase tracking-wider text-gray-400">College members</span>
                <span class="mt-1 block text-2xl font-black text-gray-900 dark:text-white sm:text-3xl">{{ $memberCount }}</span>
            </div>
            <div class="rounded-xl bg-red-50 p-2.5 text-red-600 dark:bg-red-950/40 dark:text-red-300">
                <svg class="h-5 w-5 sm:h-6 sm:w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="office-lrec-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-5 dark:border-slate-800 sm:px-6">
            <div>
                <h3 id="office-lrec-heading" class="text-lg font-black text-gray-950 dark:text-white">LREC proposals</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">Open a proposal to view its papers and record committee comments once the Research Head starts LREC review.</p>
            </div>
            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-800 dark:bg-red-950/40 dark:text-red-200">{{ $lrecProposals->total() }} in your college</span>
        </div>

        @if (! $coordinator->college)
            <p class="px-5 py-8 text-sm text-gray-600 dark:text-slate-300 sm:px-6">Your account needs an assigned college before its LREC proposals can be shown.</p>
        @elseif ($lrecProposals->isEmpty())
            <p class="px-5 py-8 text-sm text-gray-600 dark:text-slate-300 sm:px-6">No proposals from your college have reached LREC yet.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                @foreach ($lrecProposals as $proposal)
                    <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div class="min-w-0">
                            <h4 class="font-bold text-gray-950 dark:text-white">{{ $proposal->title }}</h4>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $proposal->user?->name }} · Version {{ $proposal->latestVersion?->version_number ?? '—' }} · {{ $proposal->workflowStatusLabel($proposal->latestVersion) }}</p>
                        </div>
                        <a href="{{ route('topics.show', $proposal) }}#proposal-review" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-red-200 px-4 py-2 text-sm font-bold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300">{{ $proposal->status === \App\Models\TopicProposal::STATUS_LREC_REVIEW ? 'Record comments' : 'Open proposal' }}</a>
                    </article>
                @endforeach
            </div>
            <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800 sm:px-6">{{ $lrecProposals->links() }}</div>
        @endif
    </section>
</x-app-layout>
