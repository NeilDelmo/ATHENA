<div
    x-cloak
    x-show="$store.researchAssistant.drawerOpen"
    @keydown.escape.window="$store.researchAssistant.closeDrawer()"
    class="athena-readable pointer-events-none fixed inset-0 z-[70]"
>
    <div x-show="$store.researchAssistant.drawerOpen" x-transition.opacity @click="$store.researchAssistant.closeDrawer()" class="pointer-events-auto absolute inset-0 bg-gray-950/45 backdrop-blur-sm xl:hidden" aria-hidden="true"></div>

    <aside
        id="research-assistant-panel"
        x-show="$store.researchAssistant.drawerOpen"
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        :aria-modal="$store.researchAssistant.isOverlayViewport() ? 'true' : null"
        aria-labelledby="research-assistant-drawer-title"
        class="pointer-events-auto absolute inset-y-0 right-0 flex max-h-[100dvh] w-full flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900 sm:w-[26rem] xl:w-[28rem]"
    >
        <header class="flex min-h-16 items-center justify-between border-b border-gray-100 px-4 dark:border-slate-800 sm:px-5">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div class="min-w-0">
                    <h2 id="research-assistant-drawer-title" class="truncate text-sm font-black text-gray-900 dark:text-white">Athena</h2>
                    <p class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-slate-400" aria-live="polite"><span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-emerald-500'" class="h-1.5 w-1.5 rounded-full"></span><span x-text="$store.researchAssistant.isLoading ? 'Thinking…' : 'Research assistant'"></span></p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('research-support.index') }}" class="hidden rounded-xl px-3 py-2 text-[11px] font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white sm:inline-flex">Full workspace</a>
                <button type="button" @click="$store.researchAssistant.newConversation()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Start a new chat" title="New chat">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                </button>
                <button type="button" @click="$store.researchAssistant.closeDrawer()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close research assistant">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
                </button>
            </div>
        </header>

        <div class="border-b border-blue-100 bg-blue-50 px-4 py-2.5 text-[10px] leading-4 text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-200 sm:px-5">
            <span class="font-black">Grounded assistance:</span> Matching approved ATHENA knowledge is retrieved automatically and disclosed with grounded answers. This conversation is not saved. Avoid sharing confidential participant data.
        </div>

        <div data-assistant-messages class="flex-1 overflow-y-auto scroll-smooth" aria-live="polite" :aria-busy="$store.researchAssistant.isLoading">
            <div x-show="!$store.researchAssistant.hasConversation()" class="flex min-h-full flex-col items-center justify-center px-5 py-10 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gray-900 text-white dark:bg-white dark:text-slate-900">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <h3 class="mt-4 text-xl font-black tracking-tight text-gray-900 dark:text-white">What are you working on?</h3>
                <p class="mt-2 max-w-md text-sm leading-6 text-gray-500 dark:text-slate-400">Ask about research questions, methodology, writing, or proposal revisions.</p>
                <div class="mt-7 grid w-full max-w-lg gap-2 sm:grid-cols-2">
                    <template x-for="item in $store.researchAssistant.starterPrompts()" :key="item.prompt">
                        <button type="button" @click="$store.researchAssistant.sendPrompt(item.prompt)" class="rounded-2xl border border-gray-200 p-3 text-left transition hover:border-gray-300 hover:bg-gray-50 dark:border-slate-700 dark:hover:bg-slate-800">
                            <span class="text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400" x-text="item.label"></span>
                            <span class="mt-1 block text-xs font-semibold leading-5 text-gray-700 dark:text-slate-200" x-text="item.prompt"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div x-show="$store.researchAssistant.hasConversation()" x-cloak class="px-4 py-6 sm:px-6">
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <article :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="mb-6 flex">
                        <div x-show="message.role === 'user'" class="max-w-[85%] rounded-3xl bg-gray-100 px-4 py-3 text-sm leading-6 text-gray-800 dark:bg-slate-800 dark:text-slate-100"><p class="whitespace-pre-wrap" x-text="message.content"></p></div>
                        <div x-show="message.role === 'assistant'" class="flex w-full gap-3">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg></div>
                            <div class="min-w-0 flex-1 text-sm leading-7 text-gray-700 dark:text-slate-200">
                                <p class="mb-1 text-xs font-black text-gray-900 dark:text-white">Athena</p>
                                <p class="whitespace-pre-wrap" x-html="$store.researchAssistant.renderMessage(message.content)"></p>
                                <div x-show="Array.isArray(message.sources) && message.sources.length" x-cloak class="mt-3 border-t border-gray-100 pt-3 dark:border-slate-800">
                                    <p class="text-[9px] font-black uppercase tracking-wider text-green-700 dark:text-green-300">Grounded with ATHENA knowledge</p>
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        <template x-for="source in message.sources" :key="source.reference">
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-[9px] font-bold text-green-800 ring-1 ring-green-200 dark:bg-green-950/40 dark:text-green-200 dark:ring-green-900">
                                                <span x-text="`${source.reference} · ${source.title}`"></span>
                                                <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="font-black text-green-700 hover:text-green-900 dark:text-green-300" aria-label="Open grounding source">↗</a>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <button type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-2 rounded-lg px-2 py-1 text-[11px] font-semibold text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white" x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy'"></button>
                            </div>
                        </div>
                    </article>
                </template>
                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex items-center gap-3" role="status" aria-label="Athena is preparing a response">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-600 text-white"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg></div>
                    <div class="flex gap-1.5"><span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-gray-400"></span></div>
                </div>
            </div>
        </div>

        <footer class="border-t border-gray-100 bg-white px-3 pb-[max(.75rem,env(safe-area-inset-bottom))] pt-3 dark:border-slate-800 dark:bg-slate-900 sm:px-5 sm:pb-4">
            <div x-show="$store.researchAssistant.error" x-cloak role="alert" class="mb-3 flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <div><p class="font-black" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p><p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p></div>
                <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="shrink-0 font-black disabled:opacity-50" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry'"></button>
            </div>

            <div x-show="$store.researchAssistant.hasContextOptions()" x-cloak class="mb-2 flex items-center gap-2">
                <label class="inline-flex shrink-0 items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-slate-400"><input type="checkbox" x-model="$store.researchAssistant.contextEnabled" class="rounded border-gray-300 text-red-600 focus:ring-red-500">Context</label>
                <select id="assistant-drawer-context" aria-label="Proposal context" x-model.number="$store.researchAssistant.selectedContextId" :disabled="!$store.researchAssistant.contextEnabled" class="min-w-0 flex-1 rounded-xl border-gray-200 py-1.5 text-[11px] font-semibold focus:border-red-500 focus:ring-red-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                    <template x-for="context in $store.researchAssistant.contextOptions" :key="context.id"><option :value="context.id" x-text="context.label"></option></template>
                </select>
            </div>

            <form @submit.prevent="$store.researchAssistant.send()" class="flex items-end gap-2 rounded-3xl border border-gray-300 bg-white p-2 shadow-sm focus-within:border-gray-400 focus-within:shadow-md dark:border-slate-700 dark:bg-slate-800">
                <label for="research-assistant-drawer-message" class="sr-only">Message Athena Research Assistant</label>
                <textarea id="research-assistant-drawer-message" data-assistant-composer x-model="$store.researchAssistant.draft" @input="$store.researchAssistant.resizeComposer($event)" @keydown.enter.exact="if (!$event.isComposing) { $event.preventDefault(); $store.researchAssistant.send(); }" rows="1" maxlength="8000" placeholder="Message Athena…" class="max-h-44 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm leading-5 text-gray-900 shadow-none placeholder:text-gray-400 focus:border-0 focus:ring-0 dark:text-white"></textarea>
                <button x-show="!$store.researchAssistant.isLoading" type="submit" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white transition hover:bg-black disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 dark:bg-white dark:text-slate-900 dark:disabled:bg-slate-700 dark:disabled:text-slate-500" aria-label="Send message"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5" /></svg></button>
                <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white dark:bg-white dark:text-slate-900" aria-label="Stop response"><span class="h-3 w-3 rounded-sm bg-current"></span></button>
            </form>
            <div class="mt-2 flex items-center justify-between gap-3 px-1">
                <p class="text-[10px] text-gray-400">AI can make mistakes. Avoid confidential data.</p>
                <div class="flex shrink-0 items-center gap-2">
                    <button type="button" @click="$store.researchAssistant.copyConversation()" :disabled="!$store.researchAssistant.messages.length" class="text-[10px] font-bold text-gray-400 hover:text-gray-700 disabled:opacity-40 dark:hover:text-white" x-text="$store.researchAssistant.copiedConversation ? 'Copied' : 'Copy chat'"></button>
                    <button type="button" @click="$store.researchAssistant.exportConversation()" :disabled="!$store.researchAssistant.messages.length" class="text-[10px] font-bold text-gray-400 hover:text-gray-700 disabled:opacity-40 dark:hover:text-white">Export</button>
                </div>
            </div>
        </footer>
    </aside>
</div>
