@props(['attentionCounts' => []])
@php
    $links = [
        ['Dashboard', 'research_coordinator.dashboard', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        ['LREC proposals', 'research_coordinator.proposals.index', 'M9 12l2 2 4-4M7 3h10l3 3v15H4V3h3m7 0v5h6'],
        ['Quarterly Reports', 'research_coordinator.reports.index', 'M8 3H5v18h14V3h-3M8 2h8v4H8zM8 11h8M8 15h8'],
        ['Faculty Members', 'research_coordinator.members.index', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z'],
        ['Research targets', 'research_coordinator.targets.index', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM17 12a5 5 0 1 1-10 0 5 5 0 0 1 10 0ZM13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z'],
        ['Post-implementation', 'research-outcomes.index', 'm8 12 3 3 5-6M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ];
    $linkClass = 'relative flex min-h-11 w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClass = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-rose-950/40 dark:!text-rose-200';
@endphp
<nav aria-label="Research Coordinator navigation" data-research-office-navigation class="space-y-1">
    <p x-show="$store.sidebar.open" class="mb-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Research office</p>
    @foreach ($links as [$label, $routeName, $icon])
        @php($active = $routeName === 'research-outcomes.index' ? request()->routeIs('research-outcomes.*') : request()->routeIs(str_replace('.index', '.*', $routeName)))
        <a wire:navigate @if ($routeName === 'research-outcomes.index') wire:current="{{ $activeClass }}" @else wire:current.exact="{{ $activeClass }}" @endif
            href="{{ route($routeName) }}" @if ($active) aria-current="page" @endif
            @if ($routeName === 'research-outcomes.index') data-sidebar-attention-url="{{ route('sidebar-attention.open', 'research_outcomes') }}" @endif
            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)" :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
            aria-label="{{ $label }}" title="{{ $label }}" class="{{ $linkClass }} {{ $active ? $activeClass : '' }}">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path d="{{ $icon }}" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span x-show="$store.sidebar.open" class="whitespace-nowrap">{{ $label }}</span>
            @if ($routeName === 'research-outcomes.index')<x-sidebar-attention-badge :count="$attentionCounts['research_outcomes'] ?? 0" />@endif
        </a>
    @endforeach
</nav>
