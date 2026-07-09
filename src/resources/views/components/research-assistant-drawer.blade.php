<div
    x-cloak
    x-show="$store.researchAssistant.drawerOpen"
    @keydown.escape.window="$store.researchAssistant.closeDrawer()"
    class="fixed inset-0 z-[70]"
>
    <div
        x-show="$store.researchAssistant.drawerOpen"
        x-transition.opacity
        @click="$store.researchAssistant.closeDrawer()"
        class="absolute inset-0 bg-gray-950/40 backdrop-blur-[1px]"
        aria-hidden="true"
    ></div>

    <aside
        x-show="$store.researchAssistant.drawerOpen"
        x-transition:enter="transform transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transform transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        aria-modal="true"
        aria-labelledby="research-assistant-drawer-title"
        class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-slate-800">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-600 to-rose-500 text-white shadow-sm">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                </div>
                <div>
                    <h2 id="research-assistant-drawer-title" class="text-sm font-black text-gray-900 dark:text-white">Ask Athena</h2>
                    <p class="mt-0.5 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400"><span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-green-500'" class="h-1.5 w-1.5 rounded-full"></span><span x-text="$store.researchAssistant.isLoading ? 'Thinking' : 'Ready'"></span></p>
                </div>
            </div>
            <button type="button" @click="$store.researchAssistant.closeDrawer()" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-red-500 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close research assistant">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" /></svg>
            </button>
        </div>

        <div class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-[11px] leading-5 text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-200">
            This conversation is not saved. Avoid sharing confidential participant data.
        </div>

        <div data-assistant-messages class="flex-1 space-y-4 overflow-y-auto bg-gray-50/70 p-5 dark:bg-slate-950/50" aria-live="polite">
            <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                <div :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="flex">
                    <div :class="message.role === 'user' ? 'max-w-[85%] bg-red-600 text-white' : 'max-w-[90%] border border-gray-200 bg-white text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200'" class="rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm">
                        <p class="whitespace-pre-wrap" x-text="message.content"></p>
                        <button x-show="message.role === 'assistant'" type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-2 text-[10px] font-bold text-gray-400 hover:text-gray-700 dark:hover:text-white" x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy'"></button>
                    </div>
                </div>
            </template>
            <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex justify-start"><div class="flex items-center gap-1.5 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"><span class="h-2 w-2 animate-bounce rounded-full bg-red-400 [animation-delay:-0.3s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-red-500 [animation-delay:-0.15s]"></span><span class="h-2 w-2 animate-bounce rounded-full bg-red-600"></span></div></div>
        </div>

        <div class="border-t border-gray-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div x-show="$store.researchAssistant.error" x-cloak class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"><p x-text="$store.researchAssistant.error"></p><button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.retryAfter > 0" class="mt-1 font-black disabled:opacity-50" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry response'"></button></div>
            <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                <template x-for="prompt in $store.researchAssistant.quickPrompts" :key="prompt">
                    <button type="button" @click="$store.researchAssistant.usePrompt(prompt)" class="shrink-0 rounded-full border border-gray-200 px-3 py-1.5 text-[10px] font-bold text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-red-950/40" x-text="prompt"></button>
                </template>
            </div>
            <form @submit.prevent="$store.researchAssistant.send()" class="flex items-end gap-2">
                <label for="research-assistant-drawer-message" class="sr-only">Message Athena Research Assistant</label>
                <textarea id="research-assistant-drawer-message" x-model="$store.researchAssistant.draft" @keydown.enter.exact.prevent="$store.researchAssistant.send()" rows="2" maxlength="2000" placeholder="Ask a research question..." class="min-h-12 flex-1 resize-none rounded-xl border-gray-200 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                <button x-show="!$store.researchAssistant.isLoading" type="submit" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Send message">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12 15-7.5-4.5 15-3-6-7.5-1.5Zm7.5 1.5 3-3" /></svg>
                </button>
                <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-800 text-white" aria-label="Stop response"><span class="h-3.5 w-3.5 rounded-sm bg-white"></span></button>
            </form>
            <div class="mt-3 flex items-center justify-between">
                <a href="{{ route('research-support.index') }}" class="text-[10px] font-black text-red-600 hover:text-red-700">Open full workspace &rarr;</a>
                <button type="button" @click="$store.researchAssistant.clearConversation()" class="text-[10px] font-bold text-gray-400 hover:text-gray-700 dark:hover:text-white">Clear chat</button>
            </div>
        </div>
    </aside>
</div>
