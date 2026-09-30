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
        'library' => 'M4 4h4v16H4V4Zm7 0h4v16h-4V4Zm7 0 4 1-4 15-4-1 4-15Z',
    ];
    $sections = [
        'Overview' => [
            ['Dashboard', 'faculty.dashboard', [], 'dashboard', null],
        ],
        'Research' => $isResearcher ? [
            ['My Projects', 'research.index', [], 'projects', 'my_projects'],
            ['Active projects', 'research.index', ['status' => 'active'], 'projects', null],
            ['Awaiting release', 'research.index', ['status' => 'waiting'], 'clock', null],
            ['Completed projects', 'research.index', ['status' => 'completed'], 'check', null],
        ] : [
            ['New proposal', 'faculty.proposal-drafts.create', [], 'plus', null],
            ['Draft proposals', 'faculty.proposal-drafts.index', [], 'document', 'proposal_workspace'],
            ['Submitted proposals', 'faculty.submissions', [], 'check', null],
        ],
        'Planning' => array_merge(
            [['Calendar', 'faculty.calendar', [], 'calendar', null]],
            $isResearcher ? [] : [['Research calls', 'research-calls.index', [], 'calendar', null]],
        ),
        'Resources' => [
            ['Saved literature', 'research-support.index', [], 'library', null],
        ],
    ];
    $linkClasses = 'relative flex min-h-[44px] w-full items-center gap-3 rounded-xl px-4 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-50 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white';
    $activeClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-0.5 before:-translate-y-1/2 before:rounded-full before:bg-brand dark:!bg-red-950/30 dark:!text-red-200';
@endphp

<nav aria-label="{{ $isResearcher ? 'Faculty Researcher' : 'Faculty' }} navigation" class="space-y-4" x-data="{ activeHash: window.location.hash }" @hashchange.window="activeHash = window.location.hash" data-faculty-navigation>
    @foreach ($sections as $heading => $links)
        <section aria-label="{{ $heading }}">
            <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                {{ $heading }}<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
            </h2>
            <div class="space-y-1">
                @foreach ($links as [$label, $routeName, $parameters, $icon, $attentionArea])
                    @php
                        $isLibrary = $label === 'Saved literature';
                        $url = route($routeName, $parameters).($isLibrary ? '#shared-literature-library' : '');
                        $isProjectLink = $routeName === 'research.index';
                    @endphp
                    <a wire:navigate href="{{ $url }}"
                       @if ($isProjectLink)
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === @js(parse_url(route('research.index'), PHP_URL_PATH)) && (new URLSearchParams(window.location.search).get('status') || 'all') === @js($parameters['status'] ?? 'all') ? @js($activeClasses) : '',
                           ]"
                       @elseif ($isLibrary)
                           :class="[
                               $store.sidebar.open ? '' : '!justify-center !gap-0 !px-0',
                               $store.sidebar.currentPath === @js(parse_url(route('research-support.index'), PHP_URL_PATH)) && activeHash === '#shared-literature-library' ? @js($activeClasses) : '',
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