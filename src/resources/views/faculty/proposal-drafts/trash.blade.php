<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proposal Trash" subtitle="Only you can restore your deleted proposal drafts.">
            <x-slot:actions>
                <x-back-link href="{{ route('faculty.proposal-drafts.index') }}">Back to drafts</x-back-link>
            </x-slot:actions>
        </x-page-header>
    </x-slot>
    <div class="space-y-5">
        <x-proposal-alert>Deleted drafts can be restored for 30 days, including saved papers, history, and project staff. After 30 days, standalone drafts are permanently removed. Drafts linked to submitted proposals are retained to protect official records.</x-proposal-alert>
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Your deleted proposal drafts">
            @forelse ($proposalDrafts as $proposalDraft)
                <article class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 px-6 py-5 last:border-b-0 dark:border-slate-800">
                    <div class="min-w-0">
                        <h2 class="break-words text-base font-semibold text-slate-950 dark:text-white">{{ $proposalDraft->project_title ?: 'Untitled proposal' }}</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Deleted {{ $proposalDraft->deleted_at->diffForHumans() }} &middot; Restore before {{ $proposalDraft->deleted_at->copy()->addDays(\App\Models\ProposalDraft::TRASH_RETENTION_DAYS)->format('M j, Y g:i A') }}</p>
                    </div>
                    @can('restore', $proposalDraft)
                        <form method="POST" action="{{ route('faculty.proposal-drafts.restore', $proposalDraft) }}">
                            @csrf
                            <button type="submit" class="dashboard-action text-base">Restore</button>
                        </form>
                    @else
                        <span class="text-sm text-slate-500 dark:text-slate-400">Recovery period ended</span>
                    @endcan
                </article>
            @empty
                <div class="px-6 py-16 text-center">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Trash is empty</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Drafts you delete will appear here during their recovery period.</p>
                </div>
            @endforelse
        </section>
        @if ($proposalDrafts->hasPages())
            <div>{{ $proposalDrafts->links() }}</div>
        @endif
    </div>
</x-app-layout>
