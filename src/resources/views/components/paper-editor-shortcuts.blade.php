<div
    x-data="{ open: false }"
    x-on:keydown.escape.window="if (open) { open = false; $nextTick(() => $refs.trigger.focus()) }"
    x-on:click.outside="open = false"
    class="relative w-full sm:w-auto"
>
    <button
        x-ref="trigger"
        data-paper-shortcuts-trigger
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open"
        aria-controls="paper-editor-shortcuts-dropdown"
        aria-haspopup="true"
        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-bold text-gray-700 shadow-sm transition hover:border-gray-400 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-slate-500 dark:hover:bg-slate-700 sm:w-auto"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path stroke-linecap="round" d="M7 9h.01M11 9h.01M15 9h.01M19 9h.01M7 13h.01M11 13h.01M15 13h.01M8 16h8" />
        </svg>
        <span>Shortcuts</span>
        <svg aria-hidden="true" class="h-3.5 w-3.5 transition" x-bind:class="open ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
    </button>

    <section
        x-cloak
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="-translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="-translate-y-1 opacity-0"
        id="paper-editor-shortcuts-dropdown"
        data-paper-shortcuts-dropdown
        aria-labelledby="paper-editor-shortcuts-title"
        class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white text-gray-900 shadow-2xl dark:border-slate-700 dark:bg-slate-900 dark:text-white"
    >
        <header class="flex items-start justify-between gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-slate-800">
            <div>
                <h2 id="paper-editor-shortcuts-title" class="text-sm font-black">Editor shortcuts</h2>
                <p class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-slate-400">Quick actions available while a proposal paper editor is open.</p>
            </div>
            <button type="button" x-on:click="open = false; $refs.trigger.focus()" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-600 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close editor shortcuts">
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </header>

        <dl class="grid gap-2 p-3">
            @foreach (config('proposal_editor.shortcuts', []) as $shortcut)
                <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-3 dark:border-blue-950 dark:bg-blue-950/30">
                    <dt class="flex flex-wrap items-center gap-2">
                        <kbd class="rounded-md border border-blue-300 bg-white px-2 py-1 font-mono text-[10px] font-black text-blue-900 shadow-sm dark:border-blue-800 dark:bg-slate-900 dark:text-blue-100">{{ $shortcut['keys'] }}</kbd>
                        <span class="text-xs font-black text-blue-950 dark:text-blue-100">{{ $shortcut['action'] }}</span>
                    </dt>
                    <dd class="mt-1.5 text-[11px] leading-4 text-blue-800 dark:text-blue-200">{{ $shortcut['description'] }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="border-t border-gray-100 bg-gray-50 px-4 py-3 text-[10px] font-semibold leading-4 text-gray-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">Unsaved changes are confirmed before they are discarded.</p>
    </section>
</div>
