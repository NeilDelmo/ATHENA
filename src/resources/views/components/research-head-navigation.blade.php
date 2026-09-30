@props(['attentionCounts' => []])
@php
    $icons = [
        'dashboard' => 'M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z',
        'calendar' => 'M6.75 3v2.25M17.25 3v2.25M3.75 18.75V7.5A2.25 2.25 0 016 5.25h12a2.25 2.25 0 012.25 2.25v11.25M3.75 18.75A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25M3.75 18.75v-7.5h16.5v7.5',
        'proposals' => 'M12 6v6l4.5 2.25M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'chart' => 'M3.75 13.5h4.5v6.75h-4.5V13.5Zm6-4.5h4.5v11.25h-4.5V9Zm6-5.25h4.5v16.5h-4.5V3.75Z',
        'faculty' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75c-2.68 0-5.22-.59-7.5-1.65Z',
        'signatories' => 'M15.75 5.25a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0M18 9.75v6m3-3h-6',
        'document' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-5.25Z M8.25 15h7.5m-7.5 3h4.5',
    ];
    $sections = [
        'Overview' => [
            ['Dashboard', 'research_head.dashboard', 'dashboard', null],
            ['Calendar', 'research_head.calendar', 'calendar', null],
        ],
        'Research' => [
            ['Proposals', 'research_head.proposal-submissions.index', 'proposals', 'proposal_submissions'],
            ['Projects', 'research_head.projects.index', 'chart', 'project_monitoring'],
            ['Faculty', 'research_head.faculty-directory.index', 'faculty', null],
        ],
        'Planning' => [
            ['Analytics', 'research_head.analytics', 'chart', null],
            ['Research calls', 'research-calls.index', 'calendar', null],
        ],
        'Resources' => [
            ['Signatories', 'signatories.index', 'signatories', null],
            ['Templates', 'research_head.proposal-templates.index', 'document', null],
            ['Knowledge base', 'research_head.assistant-knowledge.index', 'document', null],
        ],
    ];
    $linkClasses = 'relative flex min-h-[44px] w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-rose-950/40 dark:!text-rose-200';
@endphp
<nav aria-label="Research Head navigation" class="space-y-4" data-research-head-navigation>
    @foreach ($sections as $heading => $links)
        <section aria-label="{{ $heading }}">
            <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ $heading }}<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
            </h2>
            <div class="space-y-1">
                @foreach ($links as [$label, $routeName, $icon, $attentionArea])
                    <a wire:navigate
                       wire:current.exact="{{ $activeClasses }}"
                       href="{{ route($routeName) }}"
                       @if ($attentionArea) data-sidebar-attention-url="{{ route('sidebar-attention.open', $attentionArea) }}" @endif
                       @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)"
                       :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
                       aria-label="{{ $label }}" title="{{ $label }}"
                       class="{{ $linkClasses }}">
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
