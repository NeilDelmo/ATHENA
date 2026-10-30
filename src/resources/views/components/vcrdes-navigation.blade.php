<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[76px] flex-col items-center border-r border-slate-200 bg-white py-5 dark:border-slate-800 dark:bg-slate-900">
    <a wire:navigate href="{{ route('vcrdes.dashboard') }}" aria-label="ATHENA dashboard" title="ATHENA dashboard" class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:bg-red-950/30">
        <img src="{{ asset('images/athenalogo-transparent.png') }}" alt="" class="h-10 w-10 object-contain">
    </a>
    <div class="my-6 h-px w-8 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></div>
    <nav data-vcrdes-navigation aria-label="VCRDES monitoring" class="flex flex-1 flex-col gap-3">
        @foreach ([['Overview', 'grid', '#overview'], ['Research projects', 'folder-open', '#projects'], ['Needs attention', 'history', '#attention'], ['Budget utilization', 'presentation', '#budget'], ['College performance', 'college', '#colleges']] as [$label, $icon, $anchor])
            <a href="{{ route('vcrdes.dashboard').$anchor }}" title="{{ $label }}" aria-label="{{ $label }}" class="group relative flex h-12 w-12 items-center justify-center rounded-xl transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] {{ $loop->first ? 'bg-[#7A0019] text-white shadow-md shadow-red-950/15' : 'text-slate-400 hover:bg-red-50 hover:text-[#7A0019] dark:hover:bg-slate-800 dark:hover:text-red-200' }}">
                <x-report-library-icon :name="$icon" class="h-5 w-5" />
            </a>
        @endforeach
    </nav>
    <div class="flex flex-col items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        @if (Auth::user()->hasMultipleWorkspaces())
            <a wire:navigate href="{{ route('workspace.select') }}" aria-label="Switch workspace" title="Switch workspace" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-400 hover:bg-red-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#7A0019] dark:hover:bg-slate-800 dark:hover:text-red-200"><x-report-library-icon name="move" class="h-5 w-5" /></a>
        @endif
        <a wire:navigate href="{{ route('profile.edit') }}" aria-label="Account profile" title="Account profile" class="rounded-full focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019]">
            <x-user-avatar :user="Auth::user()" class="h-9 w-9 rounded-full bg-[#7A0019] text-xs text-white" />
        </a>
    </div>
</aside>
