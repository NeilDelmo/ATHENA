<button
    id="research-assistant-launcher"
    x-cloak
    x-show="!$store.researchAssistant.workspaceOpen"
    type="button"
    @click="$store.researchAssistant.toggleDrawer($event.currentTarget)"
    :aria-expanded="$store.researchAssistant.drawerOpen"
    :aria-label="$store.researchAssistant.drawerOpen ? 'Close Athena AI research assistant' : 'Open Athena AI research assistant'"
    aria-controls="research-assistant-panel"
    aria-label="Open Athena AI research assistant"
    class="group fixed bottom-[calc(1rem+env(safe-area-inset-bottom))] right-[calc(1rem+env(safe-area-inset-right))] z-[71] inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-900 dark:bg-slate-200 dark:text-slate-900 text-white shadow-lg shadow-gray-950/20 transition hover:bg-gray-700 dark:hover:bg-white hover:shadow-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-4 dark:focus-visible:ring-offset-slate-950 sm:bottom-[calc(1.5rem+env(safe-area-inset-bottom))] sm:right-[calc(1.5rem+env(safe-area-inset-right))] print:hidden"
>
    <span x-show="!$store.researchAssistant.drawerOpen" class="pointer-events-none absolute right-full mr-3 whitespace-nowrap rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100 dark:bg-slate-700" aria-hidden="true">Ask ATHENA</span>
    <svg x-show="!$store.researchAssistant.drawerOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1ZM5.2 13.2 6 11l.8 2.2L9 14l-2.2.8L6 17l-.8-2.2L3 14l2.2-.8Z" />
    </svg>
    <svg x-show="$store.researchAssistant.drawerOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" />
    </svg>
</button>

<div
    x-cloak
    x-show="$store.researchAssistant.drawerOpen"
    @keydown.escape.window="if ($store.researchAssistant.drawerOpen) $store.researchAssistant.closeDrawer()"
    class="athena-readable pointer-events-none fixed inset-0 z-[70] print:hidden"
>
    <aside
        id="research-assistant-panel"
        x-show="$store.researchAssistant.drawerOpen"
        x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150 motion-reduce:transition-none"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 scale-95"
        role="dialog"
        aria-labelledby="research-assistant-drawer-title"
        class="athena-chat-panel pointer-events-auto absolute bottom-[calc(5.25rem+env(safe-area-inset-bottom))] left-[calc(1rem+env(safe-area-inset-left))] right-[calc(1rem+env(safe-area-inset-right))] flex h-[42rem] max-h-[calc(100dvh-6.25rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] origin-bottom-right flex-col overflow-hidden rounded-[1.75rem] border border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:bottom-[calc(5.75rem+env(safe-area-inset-bottom))] sm:left-auto sm:right-[calc(1.5rem+env(safe-area-inset-right))] sm:max-h-[calc(100dvh-7.25rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] sm:w-[32rem]"
    >
        <header class="relative flex min-h-20 shrink-0 items-center justify-between border-b border-gray-100 px-4 dark:border-slate-800 sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-xl sm:flex bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900 shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div class="min-w-0">
                    <h2 id="research-assistant-drawer-title" class="truncate text-lg font-medium text-gray-900 dark:text-white">Athena</h2>
                    <p class="hidden items-center gap-1.5 text-xs sm:flex text-gray-500 dark:text-slate-400" aria-live="polite"><span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-emerald-500'" class="h-1.5 w-1.5 rounded-full"></span><span x-text="$store.researchAssistant.isLoading ? 'Thinking…' : 'Research assistant'"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <x-research-assistant-menu id="drawer" />

                <button x-show="!$store.researchAssistant.workspaceOpen" type="button" @click="$store.researchAssistant.openWorkspace()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Expand to full workspace" title="Expand to full workspace">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3" /></svg>
                </button>

                <button type="button" @click="$store.researchAssistant.closeDrawer()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close research assistant">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </header>

        <div data-assistant-messages class="min-h-0 flex-1 overflow-y-auto overscroll-contain scroll-smooth" aria-live="polite" :aria-busy="$store.researchAssistant.isLoading">
            <x-research-assistant-welcome compact />

            <div x-show="$store.researchAssistant.hasConversation()" x-cloak class="px-4 py-6 sm:px-6">
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <article :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="mb-6 flex">
                        <div data-assistant-user-message x-show="message.role === 'user'" class="max-w-[85%] rounded-3xl bg-gray-100 px-4 py-3 text-sm leading-6 text-gray-800 dark:bg-slate-800 dark:text-slate-100"><p class="whitespace-pre-wrap" x-text="message.content"></p></div>
                        <div x-show="message.role === 'assistant'" class="flex w-full gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg></div>
                            <div data-assistant-response class="min-w-0 flex-1 text-sm leading-7 text-gray-700 dark:text-slate-200">
                                <p class="mb-1 text-xs font-semibold text-gray-900 dark:text-white">Athena</p>
                                <div x-html="$store.researchAssistant.renderMessage(message)"></div>
                                <x-research-assistant-literature />
                                <details x-show="Array.isArray(message.sources) && message.sources.length" x-cloak class="group mt-4 border-t border-gray-100 pt-3 dark:border-slate-800">
                                    <summary class="flex cursor-pointer list-none items-center gap-2 text-xs font-semibold text-gray-500 hover:text-gray-800 dark:text-slate-400 dark:hover:text-slate-200">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75c-4.5 0-8.25 5.25-8.25 5.25S7.5 17.25 12 17.25 20.25 12 20.25 12 16.5 6.75 12 6.75Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                                        <span>Sources</span>
                                        <span class="text-gray-500" x-text="message.sources?.length || 0"></span>
                                        <svg class="ml-auto h-3.5 w-3.5 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                    </summary>
                                    <div class="mt-2 grid gap-1.5">
                                        <template x-for="source in message.sources" :key="source.reference">
                                            <span class="flex items-start gap-2 rounded-lg bg-gray-50 px-2.5 py-2 text-xs font-semibold leading-4 text-gray-600 dark:bg-slate-800/70 dark:text-slate-300">
                                                <span class="min-w-0 flex-1" x-text="`${source.reference} · ${source.title}`"></span>
                                                <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="shrink-0 text-red-600 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200" aria-label="Open source">
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M19 5 11 13M10 5H6.5A1.5 1.5 0 0 0 5 6.5v11A1.5 1.5 0 0 0 6.5 19h11a1.5 1.5 0 0 0 1.5-1.5V14" /></svg>
                                                </a>
                                            </span>
                                        </template>
                                    </div>
                                </details>
                                <button type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-3 inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path stroke-linecap="round" d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></svg>
                                    <span x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy response'"></span>
                                </button>
                            </div>
                        </div>
                    </article>
                </template>
                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex items-center gap-3" role="status" aria-label="Athena is preparing a response">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-slate-200 dark:text-slate-900"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg></div>
                    <div class="flex gap-1.5"><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.3s]"></span><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.15s]"></span><span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400"></span></div>
                </div>
            </div>
        </div>

        <footer class="shrink-0 bg-white px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-3 dark:bg-slate-900 sm:px-5 sm:pb-4">
            <div x-show="$store.researchAssistant.error" x-cloak role="alert" class="mb-3 flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <div><p class="font-semibold" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p><p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p></div>
                <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="shrink-0 font-semibold disabled:opacity-50" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry'"></button>
            </div>

            <div x-show="$store.researchAssistant.hasPaperContext()" x-cloak class="mb-2 flex items-center gap-2 rounded-xl bg-red-50 px-3 py-2 text-xs text-red-800 dark:bg-red-950/40 dark:text-red-200">
                <span class="shrink-0 font-semibold uppercase tracking-wider">Paper help</span>
                <span class="min-w-0 truncate font-semibold" x-text="$store.researchAssistant.paperContextLabel()"></span>
                <span x-show="$store.researchAssistant.hasLiveFormContext()" class="ml-auto shrink-0 rounded-full bg-white/80 px-2 py-0.5 font-semibold dark:bg-red-950">Live context</span>
            </div>

            <x-research-assistant-composer id="drawer" input-id="research-assistant-drawer-message" />
            <p class="mt-3 text-center text-xs text-gray-500 dark:text-slate-400">Athena can make mistakes. Check important answers.</p>
        </footer>
    </aside>
</div>
