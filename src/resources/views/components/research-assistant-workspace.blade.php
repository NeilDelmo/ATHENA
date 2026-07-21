<section
    id="ai-research-assistant"
    class="athena-readable mb-8 flex min-h-[640px] scroll-mt-36 overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-xl shadow-gray-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 lg:h-[min(780px,calc(100dvh-10rem))]"
    aria-labelledby="assistant-heading"
>
    <aside class="hidden w-64 shrink-0 flex-col border-r border-gray-200 bg-gray-50/80 dark:border-slate-800 dark:bg-slate-950/60 lg:flex">
        <div class="border-b border-gray-200 p-4 dark:border-slate-800">
            <div class="flex items-center gap-3 px-1 py-2">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div>
                    <p class="text-sm font-black text-gray-900 dark:text-white">Athena AI</p>
                    <p class="text-[11px] text-gray-500 dark:text-slate-400">Research copilot</p>
                </div>
            </div>

            <button type="button" @click="$store.researchAssistant.newConversation()" class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-xs font-bold text-gray-700 transition hover:border-red-300 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                New chat
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-4">
            <p class="px-2 text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">Research prompt groups</p>
            <div class="mt-3 space-y-1" role="tablist" aria-label="Research prompt groups">
                <template x-for="group in $store.researchAssistant.promptGroups" :key="group.key">
                    <button
                        type="button"
                        role="tab"
                        @click="$store.researchAssistant.setPromptGroup(group.key)"
                        :aria-selected="$store.researchAssistant.activePromptGroup === group.key"
                        :class="$store.researchAssistant.activePromptGroup === group.key ? 'bg-white text-red-700 shadow-sm ring-1 ring-gray-200 dark:bg-slate-900 dark:text-red-300 dark:ring-slate-700' : 'text-gray-600 hover:bg-white/80 hover:text-gray-900 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white'"
                        class="flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-left text-xs font-bold transition"
                    >
                        <span x-text="group.label"></span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </button>
                </template>
            </div>

            <div class="mt-4 space-y-2">
                <template x-for="prompt in $store.researchAssistant.activePrompts()" :key="prompt">
                    <button type="button" @click="$store.researchAssistant.sendPrompt(prompt)" :disabled="$store.researchAssistant.isLoading" class="w-full rounded-xl px-3 py-2 text-left text-[11px] leading-5 text-gray-500 transition hover:bg-white hover:text-red-700 disabled:opacity-50 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-red-300" x-text="prompt"></button>
                </template>
            </div>

            <div x-show="$store.researchAssistant.hasContextOptions()" x-cloak class="mt-6 border-t border-gray-200 pt-5 dark:border-slate-800">
                <label for="assistant-workspace-context" class="flex items-center gap-2 text-[11px] font-bold text-gray-700 dark:text-slate-300">
                    <input type="checkbox" x-model="$store.researchAssistant.contextEnabled" class="rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-slate-700">
                    Use proposal context
                </label>
                <select id="assistant-workspace-context" x-model.number="$store.researchAssistant.selectedContextId" :disabled="!$store.researchAssistant.contextEnabled" class="mt-2 block w-full rounded-xl border-gray-200 bg-white text-[11px] font-semibold text-gray-700 focus:border-red-500 focus:ring-red-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <template x-for="context in $store.researchAssistant.contextOptions" :key="context.id">
                        <option :value="context.id" x-text="context.label"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="space-y-1 border-t border-gray-200 p-4 dark:border-slate-800">
            <button type="button" @click="$store.researchAssistant.copyConversation()" :disabled="!$store.researchAssistant.messages.length" class="w-full rounded-lg px-3 py-2 text-left text-[11px] font-semibold text-gray-500 hover:bg-white hover:text-gray-900 disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white" x-text="$store.researchAssistant.copiedConversation ? 'Copied' : 'Copy chat'"></button>
            <button type="button" @click="$store.researchAssistant.exportConversation()" :disabled="!$store.researchAssistant.messages.length" class="w-full rounded-lg px-3 py-2 text-left text-[11px] font-semibold text-gray-500 hover:bg-white hover:text-gray-900 disabled:opacity-40 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white">Export .txt</button>
            <p class="px-3 pt-3 text-[10px] leading-4 text-gray-400"><span class="font-bold">Privacy:</span> Chats are not saved by ATHENA.</p>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col bg-white dark:bg-slate-900">
        <header class="flex min-h-16 items-center justify-between border-b border-gray-100 px-4 sm:px-6 dark:border-slate-800">
            <div class="flex min-w-0 items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white lg:hidden">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div class="min-w-0">
                    <h3 id="assistant-heading" class="truncate text-sm font-black text-gray-900 dark:text-white">Athena Research Assistant</h3>
                    <p class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-slate-400" aria-live="polite">
                        <span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-emerald-500'" class="h-1.5 w-1.5 rounded-full"></span>
                        <span x-text="$store.researchAssistant.isLoading ? 'Thinking…' : 'Ready'"></span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" @click="$store.researchAssistant.newConversation()" class="inline-flex h-9 items-center gap-2 rounded-xl px-3 text-xs font-bold text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800 lg:hidden">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    <span class="hidden sm:inline">New chat</span>
                </button>
                <button type="button" @click="$store.researchAssistant.openDrawer($event.currentTarget)" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Open compact assistant" title="Open compact assistant">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3" /></svg>
                </button>
            </div>
        </header>

        <div
            data-assistant-messages
            class="flex-1 overflow-y-auto scroll-smooth"
            aria-live="polite"
            :aria-busy="$store.researchAssistant.isLoading"
        >
            <div x-show="!$store.researchAssistant.hasConversation()" class="mx-auto flex min-h-full w-full max-w-3xl flex-col items-center justify-center px-5 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-900 text-white shadow-lg dark:bg-white dark:text-slate-900">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <h4 class="mt-5 text-2xl font-black tracking-tight text-gray-900 dark:text-white">How can Athena help?</h4>
                <p class="mt-2 max-w-xl text-sm leading-6 text-gray-500 dark:text-slate-400">Plan a study, improve a proposal, explore methods, or turn reviewer feedback into practical next steps.</p>
                <div class="mt-8 grid w-full gap-3 sm:grid-cols-2">
                    <template x-for="item in $store.researchAssistant.starterPrompts()" :key="item.prompt">
                        <button type="button" @click="$store.researchAssistant.sendPrompt(item.prompt)" class="group rounded-2xl border border-gray-200 p-4 text-left transition hover:border-gray-300 hover:bg-gray-50 hover:shadow-sm dark:border-slate-700 dark:hover:border-slate-600 dark:hover:bg-slate-800">
                            <span class="text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400" x-text="item.label"></span>
                            <span class="mt-1 block text-sm font-semibold leading-6 text-gray-700 group-hover:text-gray-950 dark:text-slate-200 dark:group-hover:text-white" x-text="item.prompt"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div x-show="$store.researchAssistant.hasConversation()" x-cloak class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6">
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <article :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="mb-7 flex">
                        <div x-show="message.role === 'user'" class="max-w-[85%] rounded-3xl bg-gray-100 px-5 py-3 text-sm leading-6 text-gray-800 dark:bg-slate-800 dark:text-slate-100 sm:max-w-[75%]">
                            <p class="whitespace-pre-wrap" x-text="message.content"></p>
                        </div>
                        <div x-show="message.role === 'assistant'" class="flex w-full gap-3 sm:gap-4">
                            <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                            </div>
                            <div class="min-w-0 flex-1 text-sm leading-7 text-gray-700 dark:text-slate-200">
                                <p class="mb-1 text-xs font-black text-gray-900 dark:text-white">Athena</p>
                                <p class="whitespace-pre-wrap" x-html="$store.researchAssistant.renderMessage(message.content)"></p>
                                <button type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-2 rounded-lg px-2 py-1 text-[11px] font-semibold text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-slate-800 dark:hover:text-white" x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy response'"></button>
                            </div>
                        </div>
                    </article>
                </template>

                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex gap-4" role="status" aria-label="Athena is preparing a response">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-600 text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2Z" /></svg>
                    </div>
                    <div class="flex h-8 items-center gap-1.5">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400"></span>
                    </div>
                </div>
            </div>
        </div>

        <footer class="border-t border-gray-100 bg-white px-3 pb-3 pt-3 dark:border-slate-800 dark:bg-slate-900 sm:px-6 sm:pb-5">
            <div class="mx-auto max-w-3xl">
                <div x-show="$store.researchAssistant.error" x-cloak role="alert" class="mb-3 flex items-start justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                    <div><p class="font-black" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p><p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p></div>
                    <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="shrink-0 font-black disabled:opacity-50" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry'"></button>
                </div>

                <div x-show="$store.researchAssistant.hasContextOptions()" x-cloak class="mb-2 flex items-center gap-2 lg:hidden">
                    <label class="inline-flex shrink-0 items-center gap-1.5 text-[11px] font-bold text-gray-500 dark:text-slate-400"><input type="checkbox" x-model="$store.researchAssistant.contextEnabled" class="rounded border-gray-300 text-red-600 focus:ring-red-500">Context</label>
                    <select id="assistant-mobile-context" aria-label="Proposal context" x-model.number="$store.researchAssistant.selectedContextId" :disabled="!$store.researchAssistant.contextEnabled" class="min-w-0 flex-1 rounded-xl border-gray-200 py-1.5 text-[11px] font-semibold focus:border-red-500 focus:ring-red-500 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        <template x-for="context in $store.researchAssistant.contextOptions" :key="context.id"><option :value="context.id" x-text="context.label"></option></template>
                    </select>
                </div>

                <form @submit.prevent="$store.researchAssistant.send()" class="flex items-end gap-2 rounded-3xl border border-gray-300 bg-white p-2 shadow-sm transition focus-within:border-gray-400 focus-within:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:focus-within:border-slate-600">
                    <label for="research-assistant-message" class="sr-only">Message Athena Research Assistant</label>
                    <textarea
                        id="research-assistant-message"
                        data-assistant-composer
                        x-model="$store.researchAssistant.draft"
                        @input="$store.researchAssistant.resizeComposer($event)"
                        @keydown="$store.researchAssistant.handleComposerKeydown($event)"
                        rows="1"
                        maxlength="2000"
                        placeholder="Message Athena…"
                        class="max-h-44 min-h-11 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-sm leading-5 text-gray-900 shadow-none placeholder:text-gray-400 focus:border-0 focus:ring-0 dark:text-white"
                    ></textarea>
                    <button x-show="!$store.researchAssistant.isLoading" type="submit" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white transition hover:bg-black disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 dark:disabled:bg-slate-700 dark:disabled:text-slate-500" aria-label="Send message">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5" /></svg>
                    </button>
                    <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white dark:bg-white dark:text-slate-900" aria-label="Stop response"><span class="h-3 w-3 rounded-sm bg-current"></span></button>
                </form>
                <p class="mt-2 text-center text-[10px] leading-4 text-gray-400">Athena can make mistakes. Verify research guidance with your adviser and official university policies. Press Shift+Enter for a new line.</p>
            </div>
        </footer>
    </div>
</section>
