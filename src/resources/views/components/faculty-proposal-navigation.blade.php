@props(['active' => 'drafts'])

<nav data-proposal-workspace-navigation aria-label="Proposal workspace" class="flex gap-6 border-b border-slate-200 dark:border-slate-800">
    @foreach (['drafts' => ['Drafts', 'faculty.proposal-drafts.index'], 'submitted' => ['Submitted', 'faculty.submissions']] as $key => [$label, $route])
        <a wire:navigate href="{{ route($route) }}" @if ($active === $key) aria-current="page" @endif @class([
            'inline-flex min-h-12 items-center border-b-2 px-1 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand',
            'border-brand text-brand dark:border-red-400 dark:text-red-300' => $active === $key,
            'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $active !== $key,
        ])>{{ $label }}</a>
    @endforeach
</nav>
