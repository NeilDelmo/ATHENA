@props(['projects'])

<section data-notice-to-proceed-queue {{ $attributes->class(['dashboard-panel']) }} aria-labelledby="notice-release-queue-heading">
    <div class="dashboard-panel-heading">
        <div>
            <h2 id="notice-release-queue-heading" class="text-lg font-bold text-slate-950 dark:text-white">Notices to Proceed</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Projects cleared by the Research Head. Prepare the notice and upload signed documents for release.</p>
        </div>
        <span class="text-sm font-semibold tabular-nums text-slate-600 dark:text-slate-300">{{ $projects->total() }} awaiting release</span>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        @forelse ($projects as $project)
            <article class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-950 dark:text-white">{{ $project->title }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $project->user?->name }} · {{ $project->user?->college }} · {{ $project->hasPreparedNoticeToProceed() ? 'Notice details saved' : 'Ready for preparation' }}</p>
                </div>
                <a href="{{ route('topics.show', $project) }}#notice-to-proceed" class="dashboard-action shrink-0">Prepare Notice to Proceed</a>
            </article>
        @empty
            <p class="px-5 py-6 text-sm text-slate-500 dark:text-slate-400">No projects are awaiting document release.</p>
        @endforelse
    </div>
    @if ($projects->hasPages())
        <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">{{ $projects->links() }}</div>
    @endif
</section>
