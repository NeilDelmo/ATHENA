<x-app-layout>
    @php
        $projects = $awaitingProjects->concat($activeProjects)->concat($completedProjects)->sortByDesc('updated_at')->values();

        $filterableProjects = $projects->map(function ($topic): array {
            $category = match (true) {
                $topic->isAwaitingNoticeToProceed() => 'waiting',
                $topic->isCompletedProject() => 'completed',
                default => 'active',
            };

            return [
                'category' => $category,
                'search' => Illuminate\Support\Str::lower(implode(' ', array_filter([
                    $topic->title,
                    $topic->description,
                    $topic->researchCall?->academic_year,
                ]))),
            ];
        })->values();

        $filters = [
            ['key' => 'all', 'label' => 'All projects', 'count' => $projects->count()],
            ['key' => 'waiting', 'label' => 'Waiting', 'count' => $awaitingProjects->count()],
            ['key' => 'active', 'label' => 'Active', 'count' => $activeProjects->count()],
            ['key' => 'completed', 'label' => 'Completed', 'count' => $completedProjects->count()],
        ];
    @endphp

    <x-slot name="header">
        <div data-faculty-researcher-dashboard class="mx-auto max-w-4xl">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#7A0019]">Faculty Researcher</p>
            <h2 class="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Approved Research Projects</h2>
            <p class="mt-1.5 text-sm text-slate-500">Track deliverables, monitor progress, and complete pending work.</p>
        </div>
    </x-slot>

    <div
        class="-mx-4 -my-6 min-h-[calc(100vh-12rem)] bg-[#FAFBFD] px-4 py-8 text-slate-900 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
        data-dashboard-layout="project-list"
        x-data="{
            filter: 'all',
            query: {{ Illuminate\Support\Js::from($search) }},
            items: {{ Illuminate\Support\Js::from($filterableProjects) }},
            matches(category, haystack) {
                const matchesFilter = this.filter === 'all' || this.filter === category;
                const matchesSearch = haystack.includes(this.query.toLowerCase().trim());

                return matchesFilter && matchesSearch;
            },
            get visibleCount() {
                return this.items.filter((item) => this.matches(item.category, item.search)).length;
            },
        }"
    >
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:w-72">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
                    </svg>
                    <label for="research_search" class="sr-only">Search approved projects</label>
                    <input
                        id="research_search"
                        x-model.debounce.150ms="query"
                        type="search"
                        placeholder="Search title or academic year"
                        class="block w-full rounded-lg border-slate-200 bg-white py-2.5 pl-9 pr-3 text-xs text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-[#7A0019] focus:ring-[#7A0019]"
                    >
                </div>

                <nav class="inline-flex w-full items-center gap-1 rounded-xl border border-slate-200 bg-slate-100 p-1 sm:w-auto" aria-label="Project status filter">
                    @foreach ($filters as $filter)
                        <button
                            type="button"
                            @click="filter = '{{ $filter['key'] }}'"
                            :class="filter === '{{ $filter['key'] }}' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                            class="inline-flex min-w-0 flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition sm:flex-none"
                            :aria-pressed="filter === '{{ $filter['key'] }}'"
                        >
                            <span>{{ $filter['label'] }}</span>
                            <span class="hidden min-w-5 items-center justify-center rounded-full border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[9px] font-black text-slate-500 sm:inline-flex">{{ $filter['count'] }}</span>
                        </button>
                    @endforeach
                </nav>
            </div>

            @if ($projects->isEmpty())
                <div class="rounded-xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                    <svg class="mx-auto h-9 w-9 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75a1.125 1.125 0 0 0 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="mt-3 text-sm font-bold text-slate-700">No approved research projects yet</p>
                    <p class="mt-1 text-xs text-slate-400">Projects appear here after approval.</p>
                </div>
            @else
                <div class="space-y-4" aria-label="Approved research projects">
                    @foreach ($projects as $topic)
                        @php
                            $isWaiting = $topic->isAwaitingNoticeToProceed();
                            $isCompleted = $topic->isCompletedProject();
                            $completion = min(100, max(0, (int) ($topic->latestProgressReport?->progress_percentage ?? 0)));
                            $monitoringStatus = $topic->monitoringStatusForProgress($topic->latestProgressReport?->progress_percentage);
                            $isCompletionPending = $monitoringStatus === App\Models\TopicProposal::PROJECT_STATUS_COMPLETION_PENDING;
                            $isDelayed = $monitoringStatus === App\Models\TopicProposal::PROJECT_STATUS_DELAYED;
                            $category = $isWaiting ? 'waiting' : ($isCompleted ? 'completed' : 'active');
                            $searchableText = Illuminate\Support\Str::lower(implode(' ', array_filter([
                                $topic->title,
                                $topic->description,
                                $topic->researchCall?->academic_year,
                            ])));
                            $projectUrl = route('research.show', $topic).($isWaiting ? '#notice-to-proceed' : '#project-monitoring');

                            [$statusLabel, $statusClass, $dotClass] = match (true) {
                                $isWaiting => ['Awaiting NTP', 'border-amber-200 bg-amber-50 text-amber-800', 'bg-amber-500'],
                                $isCompleted => ['Completed', 'border-emerald-200 bg-emerald-50 text-emerald-800', 'bg-emerald-600'],
                                $isCompletionPending => ['Ready to close out', 'border-rose-200 bg-rose-50 text-[#7A0019]', 'bg-[#7A0019]'],
                                $isDelayed => ['Delayed', 'border-amber-200 bg-amber-50 text-amber-800', 'bg-amber-500'],
                                default => ['Ongoing monitoring', 'border-slate-200 bg-slate-100 text-slate-700', 'bg-emerald-500'],
                            };

                            $summary = match (true) {
                                $isWaiting => 'Approved and waiting for the Notice to Proceed. Monitoring opens after release.',
                                $isCompleted => 'Project completed. Final reports and monitoring records remain available.',
                                $isCompletionPending => 'Monitoring reached 100%. A signed terminal report is required before completion.',
                                $isDelayed => "Monitoring is delayed at {$completion}%. Open the project record to document the delay and accomplishment.",
                                $topic->latestProgressReport !== null => "Latest monitoring progress is {$completion}%.",
                                default => 'Monitoring is open. No progress report has been recorded yet.',
                            };

                            $actionLabel = match (true) {
                                $isWaiting => 'View status',
                                $isCompleted => 'View archive',
                                $isCompletionPending => 'Submit terminal report',
                                default => 'View milestones',
                            };
                        @endphp

                        <article
                            x-show="matches('{{ $category }}', {{ Illuminate\Support\Js::from($searchableText) }})"
                            x-transition.opacity.duration.150ms
                            data-project-status="{{ $topic->project_status }}"
                            class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition duration-150 hover:border-slate-300 hover:shadow-md"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1 space-y-2.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-md border px-2.5 py-1 text-[10px] font-black {{ $statusClass }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}" aria-hidden="true"></span>
                                            {{ $statusLabel }}
                                        </span>
                                        <span class="text-slate-300" aria-hidden="true">&bull;</span>
                                        <span class="whitespace-nowrap text-[11px] font-bold text-slate-500">A.Y. {{ $topic->researchCall?->academic_year ?: 'Not provided' }}</span>
                                        <span class="text-slate-300" aria-hidden="true">&bull;</span>
                                        <span class="whitespace-nowrap text-[11px] font-black tabular-nums text-slate-700">{{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Budget not provided' }}</span>
                                    </div>

                                    <h3 class="text-base font-black leading-snug text-slate-950 transition-colors group-hover:text-[#7A0019]">
                                        <a href="{{ $projectUrl }}" class="focus:outline-none focus-visible:underline focus-visible:underline-offset-4">{{ $topic->title }}</a>
                                    </h3>

                                    <p class="text-xs leading-5 text-slate-500">{{ $summary }}</p>

                                    @if (! $isWaiting && ! $isCompleted)
                                        <div class="flex max-w-sm items-center gap-3 pt-1" aria-label="{{ $completion }} percent monitored">
                                            <div class="h-2 flex-1 overflow-hidden rounded-full border border-slate-200/70 bg-slate-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $completion }}">
                                                <div class="h-full rounded-full {{ $isDelayed ? 'bg-amber-500' : ($isCompletionPending ? 'bg-emerald-600' : 'bg-slate-700') }}" style="width: {{ $completion }}%"></div>
                                            </div>
                                            <span class="shrink-0 whitespace-nowrap text-[11px] font-black tabular-nums {{ $isCompletionPending ? 'text-emerald-700' : 'text-slate-600' }}">{{ $completion }}% monitored</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-100 pt-3 sm:min-w-40 sm:flex-col sm:items-end sm:border-0 sm:pt-0">
                                    <a href="{{ $projectUrl }}" class="inline-flex min-h-9 items-center justify-center whitespace-nowrap rounded-lg border px-3 text-[11px] font-black transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#7A0019] focus-visible:ring-offset-2 {{ $isCompletionPending ? 'border-[#7A0019] bg-[#7A0019] text-white hover:bg-[#650015]' : 'border-slate-200 bg-white text-slate-700 hover:border-rose-200 hover:bg-rose-50 hover:text-[#7A0019]' }}">
                                        {{ $actionLabel }}
                                        <span class="ml-1" aria-hidden="true">&rarr;</span>
                                    </a>
                                    <span class="whitespace-nowrap text-[10px] font-semibold text-slate-400">Project #{{ $topic->id }}</span>
                                </div>
                            </div>
                        </article>
                    @endforeach

                    <div x-cloak x-show="visibleCount === 0" class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center shadow-sm">
                        <svg class="mx-auto h-8 w-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625A1.125 1.125 0 0 0 4.5 3.375v17.25c0 .621.504 1.125 1.125 1.125h12.75a1.125 1.125 0 0 0 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <p class="mt-2 text-xs font-bold text-slate-700">No matching research projects</p>
                        <button type="button" @click="query = ''; filter = 'all'" class="mt-2 text-xs font-bold text-[#7A0019] hover:underline hover:underline-offset-4">Clear filters</button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
