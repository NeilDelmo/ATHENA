<x-app-layout>
    @php
        $unknownSchedules = $projectHealth->where('has_schedule', false)->count();
    @endphp
    <x-slot name="header">
        <x-page-header data-workspace-header-banner data-faculty-researcher-dashboard title="Dashboard">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <a wire:navigate href="{{ route('faculty.calendar') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-700 transition-colors hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800">Calendar</a>
                    <a wire:navigate href="{{ route('research.index') }}" class="dashboard-action">Projects</a>
                </div>
            </x-slot:actions>
        </x-page-header>
    </x-slot>

    <div class="space-y-7" data-dashboard-layout="research-overview" data-dashboard-palette="red-black-white">
        <section aria-labelledby="portfolio-summary-heading">
            <h2 id="portfolio-summary-heading" class="sr-only">Portfolio summary</h2>
            <dl data-project-summary class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-stone-300 bg-stone-200 xl:grid-cols-4 dark:border-stone-700 dark:bg-stone-700">
                @foreach ([
                    ['Active projects', $activeProjects->count(), 'active'],
                    ['Needs attention', $attentionProjects->count(), 'attention'],
                    ['Completed projects', $completedProjects->count(), 'completed'],
                    ['Average completion', $averageProgress === null ? '—' : $averageProgress.'%', 'active'],
                ] as [$label, $value, $status])
                    <div class="relative min-w-0 bg-white p-4 focus-within:ring-2 focus-within:ring-inset focus-within:ring-brand sm:px-5 dark:bg-stone-900 dark:focus-within:ring-red-400">
                        <dt class="text-sm font-medium text-stone-600 dark:text-stone-300">
                            <a @if ($status !== 'attention') wire:navigate @endif href="{{ $status === 'attention' ? '#needs-attention' : route('research.index', ['status' => $status]) }}" class="after:absolute after:inset-0 hover:text-brand focus:outline-none dark:hover:text-red-300">{{ $label }}<span class="sr-only">: {{ $value }}</span></a>
                        </dt>
                        <dd class="mt-2 text-3xl font-semibold tracking-tight tabular-nums text-stone-950 dark:text-white">{{ $value }}</dd>
                        @if ($label === 'Average completion')
                            <dd class="mt-1 text-xs leading-5 text-stone-600 dark:text-stone-400">{{ $reportedProjects->count() }} of {{ $activeProjects->count() }} projects reported</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
        </section>

        <div data-dashboard-columns>
            <section id="needs-attention" class="scroll-mt-32 space-y-4" aria-labelledby="focus-heading" x-data="{ showAll: false }">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 id="focus-heading" class="text-xl font-semibold tracking-tight text-stone-950 dark:text-white">Needs attention</h2>
                    <span class="rounded-md bg-stone-200 px-2.5 py-1 text-sm font-medium tabular-nums text-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ $attentionProjects->count() }} {{ str('project')->plural($attentionProjects->count()) }}</span>
                </div>
                <div class="grid gap-4 xl:grid-cols-2" id="faculty-project-actions">
                    @forelse ($attentionProjects as $topic)
                        @php
                            $health = $projectHealth[$topic->id];
                            $primaryIssue = $health['actions']->first();
                            $action = match ($primaryIssue['type']) {
                                'quarterly' => 'Complete Monitoring Tool',
                                'progress_quarterly' => 'Complete Progress Report',
                                'report_review' => 'Correct Monitoring Tool',
                                'narrative_review' => 'Correct Progress Report',
                                'terminal_review' => 'Correct Terminal Report',
                                'terminal' => 'Prepare Terminal Report',
                                'budget' => 'Review budget',
                                default => 'Update monitoring',
                            };
                        @endphp
                        <article data-project-attention="{{ $topic->id }}" class="flex min-w-0 flex-col gap-4 rounded-xl border border-stone-300 bg-white p-5 dark:border-stone-700 dark:bg-stone-900" @if ($loop->index >= 4) x-show="showAll" x-cloak @endif>
                            <div class="space-y-3">
                                <h3 class="break-words text-lg font-semibold leading-7 text-stone-950 dark:text-white"><a wire:navigate href="{{ route('research.show', $topic) }}#project-monitoring" class="hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:hover:text-red-300 dark:focus-visible:outline-red-400">{{ $topic->title }}</a></h3>
                                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs leading-5 text-stone-600 dark:text-stone-400">
                                    <p>{{ $topic->latestProgressReport ? 'Monitoring Tool · '.$topic->latestProgressReport->reporting_date->format('M j, Y') : 'No submitted Monitoring Tool' }}</p>
                                    <span class="rounded-md bg-stone-100 px-2 py-1 font-medium tabular-nums text-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ $health['progress'] === null ? 'Progress unreported' : $health['progress'].'% reported' }}</span>
                                </div>
                            </div>
                            <ul data-project-issues class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-200 bg-stone-50 dark:divide-stone-700 dark:border-stone-700 dark:bg-stone-800/60">
                                @foreach ($health['actions'] as $issue)
                                    <li class="flex flex-col items-start gap-2 p-3 sm:flex-row sm:justify-between sm:gap-3">
                                        <a wire:navigate href="{{ $issue['url'] }}" class="min-w-0 break-words text-sm font-medium leading-6 text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300 dark:focus-visible:outline-red-400">{{ $issue['issue'] }}</a>
                                        @if ($issue['days'] !== null)
                                            <span class="shrink-0 rounded-md bg-white px-2 py-1 text-xs leading-5 tabular-nums text-stone-600 dark:bg-stone-900 dark:text-stone-300">{{ $issue['days'] }} {{ str('day')->plural($issue['days']) }} {{ $issue['basis'] === 'overdue' ? 'overdue' : 'waiting' }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-auto flex justify-end">
                                <a wire:navigate data-project-action href="{{ $primaryIssue['url'] }}" class="dashboard-action max-w-full text-center">{{ $action }}</a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-stone-300 bg-white px-5 py-8 xl:col-span-2 dark:border-stone-700 dark:bg-stone-900">
                            <p class="text-base font-semibold text-stone-950 dark:text-white">No actions currently flagged</p>
                            <p class="mt-2 text-sm text-stone-600 dark:text-stone-400">Check your projects for reporting periods and review updates.</p>
                            <a wire:navigate href="{{ route('research.index') }}" class="mt-3 inline-flex min-h-11 items-center rounded text-sm font-semibold text-brand hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-red-300 dark:focus-visible:outline-red-400">View projects</a>
                        </div>
                    @endforelse
                </div>
                @if ($attentionProjects->count() > 4)
                    <div class="flex justify-center">
                        <button type="button" @click="showAll = !showAll" :aria-expanded="showAll" aria-controls="faculty-project-actions" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold text-stone-700 transition-colors hover:bg-stone-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800 dark:focus-visible:outline-red-400" x-text="showAll ? 'Show fewer projects' : 'Show {{ $attentionProjects->count() - 4 }} more projects'">Show {{ $attentionProjects->count() - 4 }} more projects</button>
                    </div>
                @endif
            </section>
        </div>

        @if ($unknownSchedules)
            <p class="rounded-lg border border-stone-300 bg-stone-100 px-4 py-3 text-sm leading-6 text-stone-600 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-400">{{ $unknownSchedules }} active {{ str('project')->plural($unknownSchedules) }} without a recorded schedule. Reporting dates cannot be checked.</p>
        @endif
    </div>
</x-app-layout>
