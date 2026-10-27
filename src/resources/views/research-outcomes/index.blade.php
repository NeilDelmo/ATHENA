<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Post-implementation" subtitle="Completed research projects">
            <x-slot name="actions">
                @can('create', App\Models\ResearchOutcome::class)
                    <a wire:navigate href="{{ route('research-outcomes.create') }}" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Register other research</a>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-5">
        <p class="text-sm text-slate-600 dark:text-slate-300">School-funded projects appear first. Team leaders update labels; college coordinators receive the changes.</p>
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-950">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Completed projects and their post-implementation labels, with school-funded projects first</caption>
                <thead class="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                    <tr><th scope="col" class="px-5 py-3 font-semibold">Project / team leader</th><th scope="col" class="px-5 py-3 font-semibold">Funding</th><th scope="col" class="px-5 py-3 font-semibold">Labels</th><th scope="col" class="px-5 py-3 font-semibold">Last update</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($outcomes as $outcome)
                        <tr>
                            <th scope="row" class="max-w-md px-5 py-4 font-normal">
                                <a wire:navigate href="{{ $outcome->detailUrl() }}" class="dashboard-action !justify-start !text-left">{{ $outcome->projectTitle() }}</a>
                                <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $outcome->projectLeader()->name }} <span class="text-slate-400">({{ $outcome->projectCollege() ?: 'College not set' }})</span></p>
                            </th>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ App\Models\ResearchOutcome::FUNDING_SOURCES[$outcome->funding_source] }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($outcome->labels as $label)
                                        <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-brand dark:bg-red-950/40 dark:text-red-200">{{ App\Models\ResearchOutcome::LABELS[$label] }}</span>
                                    @empty
                                        <span class="text-slate-500 dark:text-slate-400">No labels recorded</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-500 dark:text-slate-400">{{ $outcome->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Not updated' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-slate-600 dark:text-slate-300">No completed research projects are available in your workspace.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $outcomes->links() }}
    </div>
</x-app-layout>
