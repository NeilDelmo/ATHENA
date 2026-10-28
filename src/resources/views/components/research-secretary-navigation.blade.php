@php
    $links = [
        ['Dashboard', 'research_secretary.dashboard', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z'],
        ['Report reviews', 'research_secretary.reports.index', 'M9 12l2 2 4-4M7 3h10l3 3v15H4V3h3m7 0v5h6'],
        ['Notices to Proceed', 'research_secretary.notices.index', 'M14 3v5h5M6 3h8l5 5v13H5V3h1m3 10h7m-7 4h4'],
        ['Budget monitoring', 'research_secretary.budgets.index', 'M4 3h16v18H4zM8 7h8M8 12h2m4 0h2m-8 4h2m4 0h2'],
        ['Research targets', 'research_secretary.targets.index', 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM17 12a5 5 0 1 1-10 0 5 5 0 0 1 10 0ZM13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z'],
    ];
    $linkClass = 'relative flex min-h-11 w-full items-center gap-3 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
    $activeClass = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-rose-950/40 dark:!text-rose-200';
@endphp
<nav aria-label="Research Secretary navigation" data-research-secretary-navigation class="space-y-1">
    <p x-show="$store.sidebar.open" class="mb-3 px-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Research office</p>
    @foreach ($links as [$label, $routeName, $icon])
        <a wire:navigate wire:current.exact="{{ $activeClass }}" href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif
            @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)" :class="$store.sidebar.open ? '' : '!justify-center !gap-0 !px-0'"
            aria-label="{{ $label }}" title="{{ $label }}" class="{{ $linkClass }} {{ request()->routeIs($routeName) ? $activeClass : '' }}">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" aria-hidden="true"><path d="{{ $icon }}" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <span x-show="$store.sidebar.open" class="whitespace-nowrap">{{ $label }}</span>
        </a>
    @endforeach
</nav>
