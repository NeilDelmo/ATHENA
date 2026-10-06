<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Submitted proposals" subtitle="Follow each proposal through review, revision, and approval.">
            <x-slot:actions><a wire:navigate href="{{ route('faculty.proposal-drafts.create') }}" class="dashboard-action">New Proposal</a></x-slot:actions>
        </x-page-header>
    </x-slot>
    <div class="space-y-5">
    <nav data-submission-categories aria-label="Proposal categories" class="flex flex-wrap gap-2">
        @foreach ($categories as $key => $label)
            <a wire:navigate href="{{ route('faculty.submissions', ['category' => $key]) }}"
                @if ($category === $key) aria-current="page" @endif
                @class([
                    'inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
                    'border-brand bg-brand text-white' => $category === $key,
                    'border-slate-200 bg-white text-slate-600 hover:border-slate-400 hover:text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-white' => $category !== $key,
                ])>
                {{ $label }} <span class="text-xs tabular-nums opacity-80">{{ $categoryCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>
    @if ($category === 'monitoring')
        <p class="max-w-3xl text-sm leading-6 text-slate-600 dark:text-slate-400">These projects are in monitoring. You can view their submitted proposals here. Manage monitoring and reports in the Faculty Researcher workspace.</p>
    @endif
    <section data-faculty-submissions class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-label="Your submitted proposals">
        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-6">
            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $categories[$category] }}</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400">{{ $topics->total() }} {{ str('proposal')->plural($topics->total()) }}</span>
        </div>
        @forelse ($topics as $topic)
            @php
                $latestVersion = $topic->versions->sortByDesc('version_number')->first();
                $topicCategory = $topic->submissionCategory();
                $statusLabel = $topic->isCompletedProject() ? 'Research completed' : ($topicCategory === 'monitoring' ? 'In monitoring' : ($topic->status === 'rejected' ? 'Proposal rejected' : $topic->workflowStatusLabel($latestVersion)));
            @endphp
            <article data-submitted-proposal="{{ $topic->id }}" data-submission-category="{{ $topicCategory }}" class="flex flex-col gap-4 border-b border-slate-100 px-4 py-5 last:border-b-0 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between sm:px-6">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $topic->title }}</h3>
                        <span @class([
                            'rounded-full px-3 py-1 text-xs font-medium',
                            'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200' => $topicCategory === 'revision',
                            'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200' => $topicCategory === 'monitoring',
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! in_array($topicCategory, ['revision', 'monitoring'], true),
                        ])>{{ $statusLabel }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $topic->researchCall?->title ?? 'Independent submission' }}</p>
                    <p class="mt-1 text-xs text-slate-400">Submitted {{ $topic->created_at->format('M j, Y') }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap items-center gap-2 lg:max-w-sm lg:justify-end">
                    @if ($topic->status === 'revision_requested')
                        <a wire:navigate href="{{ route('faculty.topics.revision', $topic) }}" class="dashboard-action" data-revision-action-required>Revise proposal</a>
                        <a wire:navigate href="{{ route('topics.show', $topic) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">View proposal</a>
                    @else
                        <a wire:navigate href="{{ route('topics.show', $topic) }}" class="dashboard-action">View proposal</a>
                    @endif
                </div>
            </article>
        @empty
            <div class="px-6 py-16 text-center">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $category === 'active' ? 'No active proposals' : 'No proposals in this category' }}</h3>
                @if ($category === 'active')
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Complete a draft proposal to submit new work, or view your projects in the other categories.</p>
                    <a wire:navigate href="{{ route('faculty.proposal-drafts.index') }}" class="dashboard-action mt-5">Open draft proposals</a>
                @else
                    <a wire:navigate href="{{ route('faculty.submissions') }}" class="dashboard-action mt-5">View active proposals</a>
                @endif
            </div>
        @endforelse
    </section>
    <div class="mt-5">{{ $topics->links() }}</div>
    </div>
</x-app-layout>
