<section
    id="athena-assistant-workspace"
    class="athena-readable flex h-full min-h-0 w-full overflow-hidden bg-stone-100 dark:bg-stone-950"
    x-data="{ historyVisible: true }"
    aria-labelledby="assistant-heading"
    role="dialog"
    aria-modal="true"
    x-trap.inert.noreturn="$store.researchAssistant.workspaceOpen"
    @keydown.escape.prevent="$store.researchAssistant.closeWorkspace()"
    data-assistant-workspace
>
    <aside id="assistant-history-sidebar" x-show="historyVisible" class="hidden w-56 shrink-0 flex-col border-r border-stone-300 bg-stone-100 dark:border-stone-700 dark:bg-stone-900 lg:flex" data-assistant-history-sidebar>
        <div class="p-4">
            <div class="flex items-center gap-3 px-1 py-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-900 text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Athena AI</p>
                    <p class="text-xs text-gray-500 dark:text-slate-400">Research copilot</p>
                </div>
            </div>

            <button type="button" @click="$store.researchAssistant.newConversation()" class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-lg bg-red-900 px-3 py-3 text-sm font-medium text-white transition hover:bg-red-950 focus:outline-none focus:ring-2 focus:ring-red-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                New chat
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <div class="flex items-center justify-between gap-2 px-2">
                <p class="text-xs font-medium text-gray-500">Chat history</p>
                <span class="text-xs font-semibold text-gray-500" x-text="$store.researchAssistant.history.length ? `${$store.researchAssistant.history.length} chats` : ''"></span>
            </div>

            <button type="button" @click="$store.researchAssistant.openHistorySearch()" class="mt-3 flex w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-left text-xs font-semibold text-gray-600 transition hover:border-gray-300 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white">
                <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                Search history
                <kbd class="ml-auto rounded border border-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-500 dark:border-slate-700">Ctrl K</kbd>
            </button>

            <div class="mt-3 space-y-1">
                <template x-for="conversation in $store.researchAssistant.history" :key="conversation.id">
                    <button type="button" @click="$store.researchAssistant.openConversation(conversation.id)" :class="Number($store.researchAssistant.currentConversationId) === Number(conversation.id) ? 'bg-red-900 text-white' : 'text-stone-700 hover:bg-stone-200 dark:text-stone-200 dark:hover:bg-stone-800'" class="w-full rounded-lg px-3 py-2.5 text-left transition">
                        <span class="block truncate text-xs font-medium" x-text="conversation.title"></span>
                        <span class="mt-1 block text-xs font-normal" :class="Number($store.researchAssistant.currentConversationId) === Number(conversation.id) ? 'text-red-100' : 'text-stone-500 dark:text-stone-400'" x-text="$store.researchAssistant.formatHistoryDate(conversation.updated_at)"></span>
                    </button>
                </template>
                <p x-show="$store.researchAssistant.historyLoading" role="status" class="px-2 py-6 text-center text-xs leading-5 text-gray-500 dark:text-slate-400">Loading saved chats…</p>
                <div x-show="$store.researchAssistant.historyLoadError" class="px-2 py-4 text-center">
                    <p class="text-xs text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.historyLoadError"></p>
                    <button type="button" @click="$store.researchAssistant.loadHistory()" class="mt-3 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Retry loading history</button>
                </div>
                <p x-show="!$store.researchAssistant.historyLoading && !$store.researchAssistant.historyLoadError && !$store.researchAssistant.history.length" class="px-2 py-6 text-center text-xs leading-5 text-gray-500 dark:text-slate-400">Your saved chats will appear here.</p>
            </div>

            <div x-show="$store.researchAssistant.hasPaperContext()" x-cloak class="mt-6 rounded-lg bg-stone-200 px-3 py-2.5 text-xs text-red-900 dark:bg-stone-800 dark:text-red-200">
                <p class="font-semibold uppercase tracking-wider">Paper help</p>
                <p class="mt-1 font-semibold leading-4" x-text="$store.researchAssistant.paperContextLabel()"></p>
                <p x-show="$store.researchAssistant.hasLiveFormContext()" class="mt-1.5 font-medium text-red-700 dark:text-red-300">Focused field and current row context included</p>
            </div>

        </div>

        <div class="space-y-1 border-t border-gray-200 p-4 dark:border-slate-800">
            <button type="button" @click="$store.researchAssistant.copyConversation()" :disabled="!$store.researchAssistant.messages.length" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-left text-xs font-semibold text-stone-700 hover:bg-stone-100 disabled:opacity-40 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800" x-text="$store.researchAssistant.copiedConversation ? 'Copied' : 'Copy chat'"></button>
            <button type="button" @click="$store.researchAssistant.exportConversation()" :disabled="!$store.researchAssistant.messages.length" class="min-h-11 w-full rounded-lg border border-stone-300 bg-white px-3 py-2 text-left text-xs font-semibold text-stone-700 hover:bg-stone-100 disabled:opacity-40 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800">Export .txt</button>
            <p class="px-3 pt-3 text-xs leading-4 text-gray-500"><span class="font-medium">Privacy:</span> Chats are saved to your ATHENA account. Each workspace has its own history.</p>
        </div>
    </aside>

    <div class="flex min-h-0 min-w-0 flex-1 flex-col bg-stone-100 dark:bg-stone-950">
        <header class="relative flex min-h-16 shrink-0 items-center justify-between border-b-2 border-red-900 bg-white px-4 sm:px-6 dark:border-red-800 dark:bg-stone-900" data-assistant-header>
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="historyVisible = !historyVisible" :aria-expanded="historyVisible" aria-controls="assistant-history-sidebar" aria-label="Toggle chat history" title="Toggle chat history" class="hidden h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-stone-300 text-stone-700 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:text-stone-200 dark:hover:bg-stone-800 lg:inline-flex" data-assistant-history-toggle>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16" /></svg>
                </button>
                <div class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-900 text-white sm:flex lg:hidden">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div class="min-w-0">
                    <h3 id="assistant-heading" class="truncate text-sm font-semibold text-gray-900 dark:text-white">Athena</h3>
                    <p class="hidden items-center gap-1.5 text-xs sm:flex text-gray-500 dark:text-slate-400" aria-live="polite">
                        <span x-text="$store.researchAssistant.isLoading ? 'Thinking…' : 'Research assistant'"></span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <x-research-assistant-menu id="workspace" />

                <button type="button" @click="$store.researchAssistant.newConversation()" aria-label="Start a new chat" :class="historyVisible ? 'lg:hidden' : ''" class="inline-flex h-11 items-center gap-2 rounded-lg bg-red-900 px-3 text-xs font-medium text-white hover:bg-red-950">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    <span class="hidden sm:inline">New chat</span>
                </button>
                <button type="button" @click="$store.researchAssistant.collapseWorkspace()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Collapse to side panel" title="Collapse to side panel">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3" /></svg>
                </button>
                <button type="button" @click="$store.researchAssistant.closeWorkspace()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Back to Research Support" title="Back to Research Support">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </header>

        <div
            data-assistant-messages
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain scroll-smooth motion-reduce:scroll-auto"
            aria-live="polite"
            :aria-busy="$store.researchAssistant.isLoading"
        >
            <x-research-assistant-proposal-help />
            <div x-show="!$store.researchAssistant.proposalHelpOpen"><x-research-assistant-welcome /></div>

            <div x-show="$store.researchAssistant.hasConversation()" x-cloak class="mx-auto w-full max-w-6xl px-4 py-5 sm:px-6" data-assistant-thread>
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <article :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="mb-6 flex">
                        <div data-assistant-user-message x-show="message.role === 'user'" class="max-w-[85%] rounded-3xl bg-stone-100 px-5 py-3 text-sm leading-6 text-stone-900 dark:bg-stone-800 dark:text-stone-100 sm:max-w-[75%]">
                            <p class="whitespace-pre-wrap" x-text="message.content"></p>
                        </div>
                        <div x-show="message.role === 'assistant'" class="flex w-full gap-3 sm:gap-4">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-900 text-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                            </div>
                            <div data-assistant-response class="min-w-0 flex-1 text-base leading-relaxed text-stone-950 dark:text-stone-100">
                                <p class="mb-1 text-xs font-semibold text-red-900 dark:text-red-200">Athena</p>
                                <x-research-assistant-evidence />
                                <x-research-assistant-document-reading />
                                <div x-html="$store.researchAssistant.renderMessage(message)"></div>
                                <x-research-assistant-literature />
                                <x-research-assistant-proposal-changes />
                                <x-research-assistant-report-changes />
                                <x-research-assistant-dissemination />
                                <details x-show="Array.isArray(message.sources) && message.sources.length" x-cloak class="group mt-4 border-t border-gray-100 pt-3 dark:border-slate-800">
                                    <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75c-4.5 0-8.25 5.25-8.25 5.25S7.5 17.25 12 17.25 20.25 12 20.25 12 16.5 6.75 12 6.75Z" /><circle cx="12" cy="12" r="2.25" /></svg>
                                        <span>Reference material</span>
                                        <span class="text-gray-500" x-text="`(${message.sources?.length || 0})`"></span>
                                        <svg class="ml-auto h-3.5 w-3.5 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                                    </summary>
                                    <p class="mt-2 text-xs leading-5 text-stone-600 dark:text-stone-300">These are the records and references Athena could consult for this reply. The number counts reference items, not verified citations.</p>
                                    <div class="mt-2 grid gap-1.5">
                                        <template x-for="source in message.sources" :key="source.reference">
                                            <span class="flex flex-wrap items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-xs leading-5 text-stone-700 dark:border-stone-700 dark:bg-stone-800 dark:text-stone-200">
                                                <span class="min-w-0 flex-1"><span class="block font-medium" x-text="source.title || source.reference"></span><span class="block text-stone-600 dark:text-stone-300" x-text="source.category || source.reference"></span></span>
                                                <a x-show="$store.researchAssistant.sourceUrl(source)" :href="$store.researchAssistant.sourceUrl(source)" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-stone-300 bg-white px-3 py-2 text-red-900 hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-red-200 dark:hover:bg-stone-800">Open source
                                                </a>
                                            </span>
                                        </template>
                                    </div>
                                </details>
                                <button type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-3 py-2 text-xs font-medium text-stone-700 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800" x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy response'"></button>
                            </div>
                        </div>
                    </article>
                </template>

                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex gap-4" role="status" aria-label="Athena is preparing a response">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-900 text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg>
                    </div>
                    <div class="flex h-8 items-center gap-1.5">
                        <span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.3s]"></span>
                        <span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400 [animation-delay:-0.15s]"></span>
                        <span class="h-2 w-2 animate-bounce motion-reduce:animate-none rounded-full bg-gray-400"></span>
                    </div>
                </div>
            </div>
        </div>

        <footer class="shrink-0 border-t border-stone-300 bg-stone-100 px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-3 dark:border-stone-700 dark:bg-stone-900 sm:px-6 sm:pb-3">
            <div class="mx-auto max-w-6xl">
                <div x-show="$store.researchAssistant.error" x-cloak role="alert" class="mb-3 flex items-start justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                    <div><p class="font-semibold" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p><p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p></div>
                    <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg border border-red-700 bg-red-900 px-3 py-2 font-medium text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:border-red-400" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry'"></button>
                </div>

                <div x-show="$store.researchAssistant.hasPaperContext()" x-cloak class="mb-2 flex items-center gap-2 rounded-lg bg-stone-200 px-3 py-2 text-xs text-red-900 dark:bg-stone-800 dark:text-red-200 lg:hidden">
                    <span class="shrink-0 font-semibold uppercase tracking-wider">Paper help</span>
                    <span class="min-w-0 truncate font-semibold" x-text="$store.researchAssistant.paperContextLabel()"></span>
                    <span x-show="$store.researchAssistant.hasLiveFormContext()" class="ml-auto shrink-0 rounded bg-white px-2 py-0.5 font-semibold dark:bg-stone-900">Live context</span>
                </div>

                <x-research-assistant-composer id="workspace" input-id="research-assistant-message" />
                <p class="mt-2 text-center text-xs leading-4 text-gray-500">Athena can make mistakes. Check important answers. Shift+Enter for a new line.</p>
            </div>
        </footer>
    </div>

    <div x-cloak x-show="$store.researchAssistant.historySearchOpen" x-trap.inert.noreturn="$store.researchAssistant.historySearchOpen && $store.researchAssistant.workspaceOpen" @click.self="$store.researchAssistant.closeHistorySearch()" @keydown.escape.stop.prevent="$store.researchAssistant.closeHistorySearch()" class="fixed inset-0 z-[100] flex items-start justify-center bg-stone-200 px-4 py-[12vh] dark:bg-stone-950" role="presentation">
        <div x-show="$store.researchAssistant.historySearchOpen" x-transition role="dialog" aria-modal="true" aria-labelledby="assistant-history-search-title" class="flex max-h-[min(70vh,38rem)] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-stone-300 bg-white dark:border-stone-700 dark:bg-stone-900">
            <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-slate-800">
                <svg class="h-5 w-5 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                <label id="assistant-history-search-title" for="assistant-history-search" class="sr-only">Search chat history</label>
                <input id="assistant-history-search" type="search" x-model="$store.researchAssistant.historySearchQuery" @input="$store.researchAssistant.queueHistorySearch()" placeholder="Search your chats" autocomplete="off" class="min-w-0 flex-1 border-0 bg-transparent text-sm text-gray-900 placeholder:text-gray-500 focus:ring-0 dark:text-white">
                <span x-show="$store.researchAssistant.historySearchLoading || $store.researchAssistant.historyLoading" class="h-4 w-4 animate-spin rounded-full border-2 border-gray-200 border-t-red-600" aria-label="Loading history"></span>
                <button type="button" @click="$store.researchAssistant.closeHistorySearch()" class="rounded-lg px-2 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white">Esc</button>
            </div>
            <div class="overflow-y-auto p-2">
                <template x-for="conversation in $store.researchAssistant.historySearchResults" :key="`search-${conversation.id}`">
                    <button type="button" @click="$store.researchAssistant.openConversation(conversation.id)" class="w-full rounded-xl px-3 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-slate-800">
                        <span class="block text-sm font-medium text-gray-800 dark:text-slate-100" x-html="$store.researchAssistant.highlightHistoryText(conversation.title)"></span>
                        <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-slate-400" x-html="$store.researchAssistant.highlightHistoryText(conversation.preview)"></span>
                        <span class="mt-1 block text-xs font-normal text-gray-500" x-text="$store.researchAssistant.formatHistoryDate(conversation.updated_at)"></span>
                    </button>
                </template>
                <p x-show="$store.researchAssistant.historyLoadError" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.historyLoadError"></p>
                <p x-show="!$store.researchAssistant.historySearchLoading && !$store.researchAssistant.historyLoading && !$store.researchAssistant.historyLoadError && !$store.researchAssistant.historySearchResults.length" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-slate-400">No chats found.</p>
            </div>
            <div class="border-t border-gray-100 px-4 py-2.5 text-xs text-gray-500 dark:border-slate-800">Search matches words in your chat titles and messages.</div>
        </div>
    </div>
</section>
