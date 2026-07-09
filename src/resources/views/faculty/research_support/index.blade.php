<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-600">Faculty support</span>
                    <span class="rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-700">AI ready</span>
                </div>
                <h2 class="mt-3 text-2xl font-black tracking-tight text-gray-900">Research Help Facility</h2>
                <p class="mt-1 text-xs text-gray-500">Practical tools for developing, organizing, and improving your research work.</p>
            </div>
            <button type="button" @click="$store.researchAssistant.openDrawer()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-black text-gray-700 shadow-sm transition hover:border-red-200 hover:text-red-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 5.25h15A2.25 2.25 0 0 1 21.75 7.5v9A2.25 2.25 0 0 1 19.5 18.75H9L4.5 21v-2.25A2.25 2.25 0 0 1 2.25 16.5v-9A2.25 2.25 0 0 1 4.5 5.25Z" /></svg>
                Open compact assistant
            </button>
        </div>
    </x-slot>

    <div class="mb-5 overflow-x-auto border-b border-gray-200">
        <nav class="flex min-w-max gap-6" aria-label="Research help tools">
            <a href="{{ route('research-support.index') }}" aria-current="page" class="flex items-center gap-2 border-b-2 border-red-600 px-1 pb-3 text-xs font-black text-red-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                AI Research Assistant
            </a>
            <span class="flex items-center gap-2 border-b-2 border-transparent px-1 pb-3 text-xs font-bold text-gray-400" aria-disabled="true">
                Research Guides
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[8px] font-black uppercase">Soon</span>
            </span>
            <span class="flex items-center gap-2 border-b-2 border-transparent px-1 pb-3 text-xs font-bold text-gray-400" aria-disabled="true">
                Document Insights
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[8px] font-black uppercase">Planned</span>
            </span>
        </nav>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
        <section class="flex min-h-[620px] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="assistant-heading">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-600 to-rose-500 text-white shadow-sm">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                    </div>
                    <div>
                        <h3 id="assistant-heading" class="text-sm font-black text-gray-900">Athena Research Assistant</h3>
                        <p class="mt-0.5 flex items-center gap-1.5 text-[11px] font-semibold text-gray-400">
                            <span :class="$store.researchAssistant.isLoading ? 'animate-pulse bg-amber-400' : 'bg-green-500'" class="h-1.5 w-1.5 rounded-full"></span>
                            <span x-text="$store.researchAssistant.isLoading ? 'Thinking...' : 'Ready to help'"></span>
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-1.5">
                    <button type="button" @click="$store.researchAssistant.copyConversation()" class="rounded-lg px-2.5 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:bg-gray-100 hover:text-gray-700" x-text="$store.researchAssistant.copiedConversation ? 'Copied' : 'Copy chat'"></button>
                    <button type="button" @click="$store.researchAssistant.exportConversation()" class="rounded-lg px-2.5 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">Export .txt</button>
                    <button type="button" @click="$store.researchAssistant.clearConversation()" class="rounded-lg px-2.5 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">Clear</button>
                </div>
            </div>

            <div class="border-b border-blue-100 bg-blue-50 px-5 py-3 text-xs leading-5 text-blue-800">
                <span class="font-black">Privacy:</span> Only this conversation is sent securely to Groq. Athena does not automatically read your uploaded proposals or save this chat.
            </div>

            <div x-show="$store.researchAssistant.hasContextOptions()" x-cloak class="border-b border-gray-100 bg-white px-5 py-4">
                <div class="rounded-2xl border border-blue-100 bg-blue-50/70 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-xs font-black text-blue-900">
                            <input type="checkbox" x-model="$store.researchAssistant.contextEnabled" class="rounded border-blue-200 text-red-600 focus:ring-red-500">
                            Use proposal context
                        </label>
                        <select x-model.number="$store.researchAssistant.selectedContextId" :disabled="!$store.researchAssistant.contextEnabled" class="w-full rounded-xl border-blue-100 bg-white text-xs font-bold text-blue-900 shadow-sm focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 sm:max-w-sm">
                            <template x-for="context in $store.researchAssistant.contextOptions" :key="context.id">
                                <option :value="context.id" x-text="`${context.label} (${context.status})`"></option>
                            </template>
                        </select>
                    </div>
                    <p class="mt-2 text-[11px] leading-5 text-blue-800" x-text="$store.researchAssistant.contextEnabled ? 'Athena will use the selected proposal title, status, latest summary, and recent reviewer comments. Uploaded files are not read.' : 'Context is off. Athena will only use the messages in this chat.'"></p>
                </div>
            </div>

            <div data-assistant-messages class="flex-1 space-y-5 overflow-y-auto bg-gray-50/70 p-5" aria-live="polite">
                <template x-for="message in $store.researchAssistant.messages" :key="message.id">
                    <div :class="message.role === 'user' ? 'justify-end' : 'justify-start'" class="flex">
                        <div :class="message.role === 'user' ? 'max-w-[82%] bg-red-600 text-white' : 'group max-w-[88%] border border-gray-200 bg-white text-gray-700'" class="rounded-2xl px-4 py-3 text-sm leading-6 shadow-sm">
                            <p class="whitespace-pre-wrap" x-text="message.content"></p>
                            <button x-show="message.role === 'assistant'" type="button" @click="$store.researchAssistant.copyMessage(message)" class="mt-2 text-[10px] font-bold text-gray-400 transition hover:text-gray-700" x-text="$store.researchAssistant.copiedMessageId === message.id ? 'Copied' : 'Copy response'"></button>
                        </div>
                    </div>
                </template>
                <div x-show="$store.researchAssistant.isLoading" x-cloak class="flex justify-start">
                    <div class="flex items-center gap-1.5 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm" aria-label="Athena is preparing a response">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-red-400 [animation-delay:-0.3s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-red-500 [animation-delay:-0.15s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-red-600"></span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 bg-white p-4">
                <div x-show="$store.researchAssistant.error" x-cloak class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black" x-text="$store.researchAssistant.errorTitle || 'Athena needs attention'"></p>
                            <p class="mt-1 leading-5" x-text="$store.researchAssistant.error"></p>
                        </div>
                        <button type="button" @click="$store.researchAssistant.retry()" :disabled="$store.researchAssistant.isLoading || $store.researchAssistant.retryAfter > 0" class="shrink-0 font-black disabled:opacity-50" x-text="$store.researchAssistant.retryAfter > 0 ? `Retry in ${$store.researchAssistant.retryAfter}s` : 'Retry'"></button>
                    </div>
                </div>
                <div class="mb-3 space-y-2">
                    <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Research prompt groups">
                        <template x-for="group in $store.researchAssistant.promptGroups" :key="group.key">
                            <button type="button" @click="$store.researchAssistant.setPromptGroup(group.key)" :class="$store.researchAssistant.activePromptGroup === group.key ? 'border-red-600 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-500 hover:border-red-200 hover:text-red-700'" class="shrink-0 rounded-full border px-3 py-1.5 text-[10px] font-black uppercase tracking-wider transition" x-text="group.label"></button>
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="prompt in $store.researchAssistant.activePrompts()" :key="prompt">
                            <button type="button" @click="$store.researchAssistant.usePrompt(prompt)" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-[10px] font-bold text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700" x-text="prompt"></button>
                        </template>
                    </div>
                </div>
                <form @submit.prevent="$store.researchAssistant.send()" class="flex items-end gap-3">
                    <label for="research-assistant-message" class="sr-only">Message Athena Research Assistant</label>
                    <textarea id="research-assistant-message" x-model="$store.researchAssistant.draft" @keydown.enter.exact.prevent="$store.researchAssistant.send()" rows="2" maxlength="2000" placeholder="Ask about research planning, methods, or proposal preparation..." class="min-h-12 flex-1 resize-none rounded-xl border-gray-200 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"></textarea>
                    <button x-show="!$store.researchAssistant.isLoading" type="submit" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Send message">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12 15-7.5-4.5 15-3-6-7.5-1.5Zm7.5 1.5 3-3" /></svg>
                    </button>
                    <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-800 text-white shadow-sm hover:bg-gray-900" aria-label="Stop response"><span class="h-3.5 w-3.5 rounded-sm bg-white"></span></button>
                </form>
                <p class="mt-2 text-center text-[10px] text-gray-400">AI can make mistakes. Verify research guidance with your adviser and official university policies.</p>
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-600">Designed for research</p>
                <h3 class="mt-2 text-sm font-black text-gray-900">What you will be able to ask</h3>
                <ul class="mt-4 space-y-3 text-xs leading-5 text-gray-600">
                    <li class="flex gap-2"><span class="mt-1 text-red-600">&#10003;</span><span>Clarify research concepts and methodologies</span></li>
                    <li class="flex gap-2"><span class="mt-1 text-red-600">&#10003;</span><span>Improve proposal structure and readability</span></li>
                    <li class="flex gap-2"><span class="mt-1 text-red-600">&#10003;</span><span>Brainstorm objectives, questions, and keywords</span></li>
                    <li class="flex gap-2"><span class="mt-1 text-red-600">&#10003;</span><span>Navigate Athena submission requirements</span></li>
                </ul>
            </section>

            <section class="rounded-2xl border border-purple-100 bg-purple-50 p-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-purple-700 shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4.5 6v5.25c0 4.6 3.2 8.9 7.5 9.75 4.3-.85 7.5-5.15 7.5-9.75V6L12 3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="m9.5 12 1.6 1.6 3.7-4.1" /></svg>
                </div>
                <h3 class="mt-3 text-sm font-black text-purple-900">Use it responsibly</h3>
                <p class="mt-2 text-xs leading-5 text-purple-800">Do not paste confidential participant data or unpublished sensitive information. The assistant supports your judgment; it does not replace your adviser or ethics review.</p>
            </section>

            @role('faculty_researcher')
                <a href="{{ route('research.index') }}" class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-5 text-xs font-black text-gray-700 shadow-sm transition hover:border-red-200 hover:text-red-600">
                    View my research
                    <span aria-hidden="true">&rarr;</span>
                </a>
            @else
                <a href="{{ route('faculty.dashboard') }}" class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white p-5 text-xs font-black text-gray-700 shadow-sm transition hover:border-red-200 hover:text-red-600">
                    Return to dashboard
                    <span aria-hidden="true">&rarr;</span>
                </a>
            @endrole
        </aside>
    </div>
</x-app-layout>
