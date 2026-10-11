@props(['compact' => false])

<div x-show="!$store.researchAssistant.hasConversation()" @class(['mx-auto flex min-h-full w-full max-w-3xl flex-col items-center justify-center text-center', 'px-5 py-8' => $compact, 'px-6 py-12 sm:px-10' => ! $compact])>
    <div class="mb-6 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300">
        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z" /></svg>
    </div>
    <p class="mb-2 text-xs font-medium tracking-wide text-gray-500 dark:text-slate-400">Your research, a little clearer.</p>
    <h4 @class(['font-semibold tracking-tight text-gray-900 dark:text-white', 'text-[1.75rem] leading-tight' => $compact, 'text-3xl sm:text-4xl' => ! $compact]) x-text="$store.researchAssistant.starterPromptHeading()"></h4>
    <p class="mt-3 max-w-md text-sm leading-6 text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.starterPromptDescription()"></p>
    <div x-show="$store.researchAssistant.hasStarterPrompts()" x-cloak @class(['mt-8 grid w-full gap-2 text-left', 'sm:grid-cols-2' => ! $compact])>
        <template x-for="item in $store.researchAssistant.starterPrompts()" :key="item.prompt">
            <button type="button" @click="item.action?.type === 'review_proposal' ? $store.researchAssistant.startSuggestedReview(item, $event.currentTarget) : $store.researchAssistant.sendPrompt(item.prompt, item.action || null)" :disabled="$store.researchAssistant.reviewLaunchBusy()" :title="item.evidence" class="group flex items-start gap-3 rounded-2xl bg-gray-50 px-4 py-3.5 text-left transition hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-800/50 dark:hover:bg-slate-800">
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-medium text-gray-800 dark:text-slate-100" x-text="item.label"></span>
                    <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="item.description"></span>
                    <span class="sr-only" x-text="item.evidence"></span>
                </span>
                <svg class="mt-1 h-4 w-4 shrink-0 text-gray-400 transition group-hover:text-red-600 dark:group-hover:text-red-300" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17 17 7M7 7h10v10" /></svg>
            </button>
        </template>
    </div>
</div>
