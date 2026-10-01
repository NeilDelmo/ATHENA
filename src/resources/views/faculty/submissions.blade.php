<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Submitted proposals" subtitle="Follow each proposal through review, revision, and approval.">
            <x-slot:actions><a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action">New Proposal</a></x-slot:actions>
        </x-page-header>
    </x-slot>
    <div class="space-y-5">
    <section data-faculty-submissions class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Your submitted proposals">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Submission history</h2>
            <span class="text-xs text-slate-500">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}</span>
        </div>
        @forelse ($topics as $topic)
            @php($latestVersion = $topic->versions->sortByDesc('version_number')->first())
            <article data-submitted-proposal="{{ $topic->id }}" class="flex flex-col gap-4 border-b border-slate-100 px-6 py-5 last:border-b-0 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $topic->title }}</h3>
                        <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-medium text-[#7A0019] dark:bg-red-950/40 dark:text-red-200">{{ $topic->workflowStatusLabel($latestVersion) }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                    <p class="mt-1 text-xs text-slate-400">Submitted {{ $topic->created_at->format('M j, Y') }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @if ($topic->status === 'revision_requested')
                        <a wire:navigate href="{{ route('faculty.topics.revision', $topic) }}" class="dashboard-action" data-revision-action-required>Revise proposal</a>
                        <a wire:navigate href="{{ route('topics.show', $topic) }}" class="inline-flex min-h-11 items-center rounded-lg px-3 text-xs font-semibold text-slate-600 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300">View proposal</a>
                    @else
                        <a wire:navigate href="{{ route('topics.show', $topic) }}" class="dashboard-action">View proposal</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-6 py-16 text-center">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">No submitted proposals yet</h3>
                <p class="mt-2 text-sm text-slate-500">Complete a draft proposal and submit its package for review.</p>
                <a wire:navigate href="{{ route('faculty.proposal-drafts.index') }}" class="dashboard-action mt-5">Open draft proposals</a>
            </div>
        @endforelse
    </section>
    <div class="mt-5">{{ $topics->links() }}</div>
    </div>
</x-app-layout>
