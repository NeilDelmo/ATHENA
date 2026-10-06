@props(['user', 'workspaces', 'action', 'field' => 'workspace', 'currentWorkspace' => null])

@php
    $workspaceCount = count($workspaces);
    $workspaceCopy = [
        'research_head' => ['label' => 'Research Head', 'description' => 'Manage research calls, review proposals, and oversee institutional research.'],
        'faculty_researcher' => ['label' => 'Research Projects', 'description' => 'Manage approved projects, submit monitoring reports, and access released documents.'],
        'faculty' => ['label' => 'Faculty', 'description' => 'Prepare proposals, submit research papers, and respond to review feedback.'],
    ];
@endphp

<main data-workspace-selector class="flex min-h-screen items-center justify-center bg-[#F5F7FA] px-4 py-8 text-slate-900 dark:bg-slate-950 dark:text-white sm:px-8 sm:py-12">
    <section aria-labelledby="workspace-selection-title" @class([
        'w-full min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900',
        'max-w-4xl' => $workspaceCount <= 2,
        'max-w-5xl' => $workspaceCount > 2,
    ])>
        <header class="flex flex-wrap items-center justify-between gap-4 border-b border-[#7A0019] bg-[#7A0019] px-5 py-4 text-white sm:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/athenalogo-transparent.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-lg bg-white object-contain p-1">
                <span class="text-base font-extrabold tracking-tight text-white">ATHENA</span>
            </div>
            <div class="flex min-w-0 items-center gap-3 sm:gap-5">
                <div class="min-w-0 text-right">
                    <p class="max-w-52 break-words text-xs font-bold sm:max-w-72">{{ $user->name }}</p>
                    <p class="mt-0.5 max-w-52 truncate text-xs text-red-100 sm:max-w-72">{{ $user->email }}</p>
                </div>
                <button id="workspace-theme-toggle" data-theme-toggle type="button" aria-label="Toggle light and dark theme" title="Toggle theme" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-white/30 bg-white/10 text-white hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    <svg class="h-5 w-5 dark:hidden" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.75A9 9 0 118.25 2.25a7.5 7.5 0 0013.5 13.5z" /></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5m0 15V21m9-9h-1.5M4.5 12H3m15.364 6.364-1.061-1.061M6.697 6.697 5.636 5.636m12.728 0-1.061 1.061M6.697 17.303l-1.061 1.061M16.5 12a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" /></svg>
                </button>
            </div>
        </header>

        <div class="px-5 py-6 sm:px-8 sm:py-8">
            <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 id="workspace-selection-title" class="text-2xl font-extrabold tracking-tight sm:text-3xl">Choose your workspace</h1>
                    <p class="mt-2 max-w-xl text-sm leading-6 text-slate-600 dark:text-slate-400">Choose the workspace for what you want to work on.</p>
                </div>
                <x-back-link href="{{ route('dashboard') }}">Back to current workspace</x-back-link>
            </div>

            @error($field)
                <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">{{ $message }}</div>
            @enderror

            <div data-workspace-switch-status role="status" aria-live="polite" hidden class="mb-5 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                <span data-workspace-switch-message></span>
                <a data-workspace-switch-continue hidden class="ml-2 font-semibold text-[#7A0019] underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-red-300">Open workspace</a>
            </div>

            <div data-workspace-options @class([
                'grid grid-cols-1 gap-4',
                'sm:grid-cols-2' => $workspaceCount <= 2,
                'md:grid-cols-3' => $workspaceCount > 2,
            ])>
                @foreach ($workspaces as $key => $workspace)
                    @php
                        $copy = $workspaceCopy[$key] ?? $workspace;
                        $isCurrent = $key === $currentWorkspace;
                        $titleId = 'workspace-option-'.$key;
                    @endphp
                    <article data-workspace-option="{{ $key }}" data-current-workspace="{{ $isCurrent ? 'true' : 'false' }}" @class([
                        'flex min-w-0 flex-col rounded-xl border p-5',
                        'border-[#7A0019] bg-red-50/40 dark:border-red-400 dark:bg-red-950/20' => $isCurrent,
                        'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => ! $isCurrent,
                    ]) aria-labelledby="{{ $titleId }}">
                        <div class="flex min-h-11 flex-wrap items-center justify-between gap-2">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-red-50 text-[#7A0019] dark:bg-red-950/50 dark:text-red-300">
                                @if ($key === 'research_head')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v6m-9.75 3.75h13.5M4.5 5.25h15a.75.75 0 0 1 .75.75v12.75H3.75V6a.75.75 0 0 1 .75-.75Z" /></svg>
                                @elseif ($key === 'faculty_researcher')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5h6l2-3h10v15H3V7.5Zm0 3h18M8 14.25h8M8 16.75h5" /></svg>
                                @elseif ($key === 'faculty')
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14.25 3.75 9 12 3.75 20.25 9 12 14.25Zm0 0v6m-5.25-8.25v4.5c2.9 2.3 7.6 2.3 10.5 0V12" /></svg>
                                @else
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5H5.25v15h13.5v-15H15m-6 0V3h6v3H9V4.5Zm0 6h6m-6 4h6" /></svg>
                                @endif
                            </span>
                            @if ($isCurrent)
                                <span data-current-workspace-badge class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-[#7A0019] dark:border-red-900 dark:bg-slate-900 dark:text-red-300">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                    Current workspace
                                </span>
                            @endif
                        </div>
                        <h2 id="{{ $titleId }}" class="mt-5 break-words text-lg font-bold tracking-tight">{{ $copy['label'] }}</h2>
                        <p class="mt-2 grow text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $copy['description'] }}</p>
                        <form method="POST" action="{{ $action }}" data-workspace-switch class="mt-6">
                            @csrf
                            <input type="hidden" name="{{ $field }}" value="{{ $key }}">
                            <button id="{{ $titleId }}-action" type="submit" aria-labelledby="{{ $titleId }}-action {{ $titleId }}" @class([
                                'inline-flex min-h-11 w-full items-center justify-center rounded-lg border px-4 py-2.5 text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] disabled:cursor-wait disabled:opacity-60 dark:focus-visible:outline-red-300',
                                'border-[#7A0019] bg-[#7A0019] text-white hover:bg-[#5C0013] dark:border-red-700 dark:bg-red-700 dark:hover:bg-red-800' => $isCurrent,
                                'border-slate-300 bg-white text-[#7A0019] hover:border-[#7A0019] hover:bg-red-50 dark:border-slate-600 dark:bg-slate-800 dark:text-red-200 dark:hover:border-red-400 dark:hover:bg-slate-700' => ! $isCurrent,
                            ])>{{ $isCurrent ? 'Continue in workspace' : 'Enter workspace' }}</button>
                        </form>
                    </article>
                @endforeach
            </div>

            <footer class="mt-6 flex flex-col items-start justify-between gap-4 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400 sm:flex-row sm:items-center">
                <p class="max-w-xl leading-5">You can switch workspaces anytime from your account menu.</p>
                <form method="POST" action="{{ route('logout') }}" data-proposal-confirm data-confirm-title="Log out of ATHENA?" data-confirm-text="You will need to sign in again to continue working in ATHENA." data-confirm-button="Log out" data-cancel-button="Stay signed in" data-confirm-icon="warning">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-600 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7A0019] dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:focus-visible:outline-red-300">Sign out</button>
                </form>
            </footer>
        </div>
    </section>
</main>
