@php
    $pages = [
        'research_head.dashboard' => 'Dashboard',
        'research_head.calendar' => 'Calendar',
        'research_head.analytics' => 'Analytics',
    ];
@endphp
<nav aria-label="Research workspace pages" data-research-head-page-navigation
     {{ $attributes->class(['inline-grid max-w-full grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-950']) }}>
    @foreach ($pages as $routeName => $label)
        @php($active = request()->routeIs($routeName))
        <a wire:navigate href="{{ route($routeName) }}"
           @if ($active) aria-current="page" @endif
           @class([
               'inline-flex min-h-[44px] items-center justify-center rounded-lg px-2 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:px-4',
               'bg-brand text-white shadow-sm' => $active,
               'text-slate-600 hover:bg-white hover:text-brand dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
           ])>{{ $label }}</a>
    @endforeach
</nav>
