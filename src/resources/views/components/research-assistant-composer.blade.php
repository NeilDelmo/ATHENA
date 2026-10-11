@props(['id', 'inputId'])

<div class="relative rounded-[1.75rem] border border-gray-200 bg-white p-2 shadow-[0_4px_24px_-8px_rgba(0,0,0,0.12)] transition focus-within:border-gray-400 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:border-slate-500" data-assistant-composer-shell>
    <form id="assistant-send-{{ $id }}" @submit.prevent="$store.researchAssistant.send()">
        <label for="{{ $inputId }}" class="sr-only">Message Athena Research Assistant</label>
        <textarea id="{{ $inputId }}" data-assistant-composer x-model="$store.researchAssistant.draft" @input="$store.researchAssistant.resizeComposer($event)" @keydown="$store.researchAssistant.handleComposerKeydown($event)" rows="2" maxlength="8000" placeholder="Ask Athena anything about your research…" class="block max-h-44 min-h-[4.5rem] w-full resize-none border-0 bg-transparent px-3 py-3 text-base leading-6 text-gray-900 shadow-none placeholder:text-gray-500 focus:border-0 focus:ring-0 dark:text-white dark:placeholder:text-slate-400"></textarea>
    </form>
    <div class="flex items-end justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-1">
            <x-research-assistant-project-picker :id="$id" />
            <x-research-assistant-document-action :id="$id" />
        </div>
        <button x-show="!$store.researchAssistant.isLoading" type="submit" form="assistant-send-{{ $id }}" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white transition hover:bg-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200 dark:disabled:bg-slate-800 dark:disabled:text-slate-500" aria-label="Send message">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5" /></svg>
        </button>
        <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gray-900 text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 dark:bg-white dark:text-slate-900" aria-label="Stop response"><span class="h-3.5 w-3.5 rounded-sm bg-current"></span></button>
    </div>
</div>
