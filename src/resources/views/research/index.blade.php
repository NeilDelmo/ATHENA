<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-[#7A0019] dark:text-red-300">Faculty Researcher</p>
                <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Approved Research Projects</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Notices to Proceed, approved papers, monitoring, and completed research in one workspace.</p>
            </div>
            <form method="GET" action="{{ route('research.index') }}" class="flex w-full gap-2 sm:w-auto">
                <label for="research_search" class="sr-only">Search approved projects</label>
                <input id="research_search" name="search" type="search" value="{{ $search }}" placeholder="Search projects..." class="min-w-0 flex-1 rounded-xl border-gray-300 text-sm shadow-sm focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-gray-700 dark:bg-gray-950 dark:text-white sm:w-64">
                <button class="rounded-xl bg-gray-950 px-4 py-2 text-xs font-black text-white transition hover:bg-[#7A0019] dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">Search</button>
            </form>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($search !== '')
            <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                <p class="font-semibold text-gray-700 dark:text-gray-300">Results for “{{ $search }}”</p>
                <a href="{{ route('research.index') }}" class="text-xs font-black text-[#7A0019] dark:text-red-300">Clear search</a>
            </div>
        @endif

        @php
            $sections = [
                [
                    'id' => 'active-projects',
                    'eyebrow' => 'In implementation',
                    'title' => 'Active Projects',
                    'description' => 'Approved projects with an issued Notice to Proceed. Monitoring is open for ongoing and delayed projects.',
                    'projects' => $activeProjects,
                    'empty' => 'No active projects right now.',
                    'tone' => 'active',
                ],
                [
                    'id' => 'awaiting-ntp',
                    'eyebrow' => 'Approved papers',
                    'title' => 'Awaiting Notice to Proceed',
                    'description' => 'These approved projects occupy a capacity slot, but monitoring remains closed until the Research Head issues the NTP.',
                    'projects' => $awaitingProjects,
                    'empty' => 'No approved projects are waiting for an NTP.',
                    'tone' => 'awaiting',
                ],
                [
                    'id' => 'completed-projects',
                    'eyebrow' => 'Reporting complete',
                    'title' => 'Completed / Archive',
                    'description' => 'Reports are archived and capacity is released. Continue finding conferences, tracking submissions, and linking publications.',
                    'projects' => $completedProjects,
                    'empty' => 'No completed projects have been archived yet.',
                    'tone' => 'completed',
                ],
            ];
        @endphp

        @foreach ($sections as $section)
            <section id="{{ $section['id'] }}" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-950">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-5 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between">
                    <div class="border-l-4 border-[#7A0019] pl-3 dark:border-red-500">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-[#7A0019] dark:text-red-300">{{ $section['eyebrow'] }}</p>
                        <h3 class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ $section['title'] }}</h3>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $section['description'] }}</p>
                    </div>
                    <span class="self-start rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:self-auto">{{ $section['projects']->count() }}</span>
                </div>

                <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($section['projects'] as $topic)
                        <article class="flex min-h-64 flex-col rounded-2xl border border-gray-200 p-5 transition hover:border-red-200 hover:shadow-md dark:border-gray-800 dark:hover:border-red-900">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($section['tone'] === 'active')
                                    <span class="rounded-full bg-gray-950 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">{{ $topic->project_status }}</span>
                                @elseif ($section['tone'] === 'awaiting')
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-[#7A0019] ring-1 ring-inset ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900">Awaiting Notice to Proceed</span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-600 dark:bg-gray-900 dark:text-gray-400">Completed · Reports archived</span>
                                @endif
                            </div>

                            <h4 class="mt-4 text-base font-black leading-6 text-gray-950 dark:text-white">{{ $topic->title }}</h4>
                            <p class="mt-2 line-clamp-3 text-xs leading-5 text-gray-600 dark:text-gray-400">{{ $topic->description ?: 'No project description provided.' }}</p>

                            <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                                <div>
                                    <dt class="font-black uppercase tracking-wider text-gray-400">Budget</dt>
                                    <dd class="mt-1 font-bold text-gray-800 dark:text-gray-200">{{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}</dd>
                                </div>
                                <div>
                                    <dt class="font-black uppercase tracking-wider text-gray-400">Academic year</dt>
                                    <dd class="mt-1 font-bold text-gray-800 dark:text-gray-200">{{ $topic->researchCall?->academic_year ?: 'Not provided' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-auto flex flex-col gap-2 pt-5">
                                @if ($topic->isDisseminationAvailable())
                                    <a href="{{ route('research.dissemination.show', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-red-200 px-3 py-2.5 text-xs font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950">Conferences &amp; Publications</a>
                                @endif
                                <a href="{{ route('research.show', $topic) }}{{ in_array($section['tone'], ['active', 'completed'], true) ? '#project-monitoring' : '#notice-to-proceed' }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-gray-950 px-3 py-2.5 text-xs font-black text-white transition hover:bg-[#7A0019] dark:bg-white dark:text-gray-950 dark:hover:bg-red-200">
                                    {{ $section['tone'] === 'active' ? 'Project & monitoring' : 'View project record' }}
                                </a>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-gray-300 px-5 py-10 text-center md:col-span-2 xl:col-span-3 dark:border-gray-700">
                            <p class="text-sm font-black text-gray-700 dark:text-gray-300">{{ $section['empty'] }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</x-app-layout>
