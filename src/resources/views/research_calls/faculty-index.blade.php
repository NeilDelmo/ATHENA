<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-black text-gray-950 dark:text-white">Research announcements</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Updates and schedules from the Research Office. Proposal submissions are available anytime.</p>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-5">
        @can('create', \App\Models\ProposalDraft::class)
            <a href="{{ route('faculty.proposal-drafts.create') }}" class="inline-flex rounded-xl bg-red-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">New proposal</a>
        @endcan
        <div class="divide-y divide-gray-100 overflow-hidden rounded-2xl border border-gray-200 bg-white dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-950">
            @forelse ($calls as $call)
                <a href="{{ route('research-calls.index', ['call' => $call->id]) }}" class="flex items-start justify-between gap-4 p-5 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 dark:hover:bg-gray-900">
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-950 dark:text-white">{{ $call->title }}</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $call->academic_year }}{{ $call->term ? ' · '.$call->term : '' }}</p>
                        <p class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $call->description }}</p>
                    </div>
                    <span class="shrink-0 text-xs font-semibold text-red-700 dark:text-red-300">View schedule &rarr;</span>
                </a>
            @empty
                <p class="p-6 text-sm text-gray-600 dark:text-gray-400">No announcements yet. You can still create and submit a proposal.</p>
            @endforelse
        </div>
        {{ $calls->links() }}
    </div>
</x-app-layout>
