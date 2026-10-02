@props(['attentionCounts' => []])

@php
    $isResearcher = Auth::user()->isUsingWorkspace('faculty_researcher');
    $icons = [
        'dashboard' => 'M3 3h7v7H3V3Zm11 0h7v7h-7V3ZM3 14h7v7H3v-7Zm11 0h7v7h-7v-7Z',
        'calendar' => 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z',
        'plus' => 'M12 4.5v15m7.5-7.5h-15',
        'document' => 'M4 3h10l6 6v12H4V3Zm10 0v6h6M8 13h8m-8 4h5',
        'projects' => 'M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z',
        'clock' => 'M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'check' => 'm8 12 3 3 5-6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'search' => 'm21 21-5.2-5.2M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z',
        'shield' => 'M12 3 4 6v6c0 4.4 3.4 7.4 8 9 4.6-1.6 8-4.6 8-9V6l-8-3Zm-4 9 3 3 5-6',
        'library' => 'M4 4h4v16H4V4Zm7 0h4v16h-4V4Zm7 0 4 1-4 15-4-1 4-15Z',
    ];
    $sections = [
        'Overview' => [
            ['Dashboard', 'faculty.dashboard', [], 'dashboard', null],
            ['Calendar', 'faculty.calendar', [], 'calendar', null],
        ],
        ...($isResearcher ? [
            'Monitoring' => [
                ['My Projects', 'research.index', [], 'projects', 'my_projects'],
                ['Active projects', 'research.index', ['status' => 'active'], 'projects', null],
                ['Awaiting release', 'research.index', ['status' => 'waiting'], 'clock', null],
            ],
            'Completion' => [
                ['Completed projects', 'research.index', ['status' => 'completed'], 'check', null],
            ],
        ] : [
            'Submission' => [
                ['Research calls', 'research-calls.index', [], 'calendar', null],
                ['New proposal', 'faculty.proposal-drafts.create', [], 'plus', null],
                ['Draft proposals', 'faculty.proposal-drafts.index', [], 'document', 'proposal_workspace'],
            ],
            'Review' => [
                ['Submitted proposals', 'faculty.submissions', [], 'check', 'submitted_proposals'],
            ],
        ]),
        'Resources' => [
            ['Saved literature', 'research-support.index', [], 'library', null],
            ['Literature search', 'research-support.index', [], 'search', null],
            ['Turnitin', 'research-support.index', [], 'shield', null],
            ...($isResearcher ? [['Journal Finder', 'research-support.index', [], 'document', null]] : []),
        ],
    ];
    $resourceAnchors = [
        'Saved literature' => '#shared-literature-library',
        'Literature search' => '#rrl-finder',
        'Turnitin' => '#turnitin',
        'Journal Finder' => '#journal-finder',
    ];
    $linkClasses = 'relative flex min-h-[44px] w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-red-950/30 dark:!text-red-200';
@endphp

<nav aria-label="{{ $isResearcher ? 'Faculty Researcher' : 'Faculty' }} navigation" class="space-y-4" x-data="{ activeHash: window.location.hash || '#rrl-finder' }" @hashchange.window="activeHash = window.location.hash || '#rrl-finder'" x-on:livewire:navigated.window="activeHash = window.location.hash || '#rrl-finder'" data-faculty-navigation>
    @foreach ($sections as $heading => $links)
        <section aria-label="{{ $heading }}" @if ($heading === 'Resources') data-research-help-menu @endif>
            <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                {{ $heading }}<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
            </h2>
            <div class="space-y-1">
                @foreach ($links as [$label, $routeName, $parameters, $icon, $attentionArea])
                    @php
                        $resourceAnchor = $resourceAnchors[$label] ?? '';
                        $url = route($routeName, $parameters).$resourceAnchor;
                        $isProjectLink = $routeName === 'research.index';
                    @endphp
                    <a wire:navigate href="{{ $url }}"
                       @if ($isProjectLink)
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === @js(parse_url(route('research.index'), PHP_URL_PATH)) && (new URLSearchParams(window.location.search).get('status') || 'all') === @js($parameters['status'] ?? 'all') ? @js($activeClasses) : '',
                           ]"
                       @elseif ($resourceAnchor)
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === @js(parse_url(route('research-support.index'), PHP_URL_PATH)) && activeHash === @js($resourceAnchor) ? @js($activeClasses) : '',
                           ]"
                       @else
                           wire:current.exact="{{ $activeClasses }}"
                           :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
                       @endif
                       @if ($attentionArea) data-sidebar-attention-url="{{ route('sidebar-attention.open', $attentionArea) }}" @endif
                       @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                       aria-label="{{ $label === 'Dashboard' ? ($isResearcher ? 'Faculty Researcher Dashboard' : 'Faculty Dashboard') : $label }}" title="{{ $label }}" class="{{ $linkClasses }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$icon] }}" />
                        </svg>
                        <span x-show="$store.sidebar.open" class="whitespace-nowrap">{{ $label }}</span>
                        @if ($attentionArea)<x-sidebar-attention-badge :count="$attentionCounts[$attentionArea] ?? 0" />@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</nav>