@props(['id', 'inputId'])

<div class="relative rounded-xl border border-stone-400 bg-white p-2 transition focus-within:border-red-700 dark:border-stone-600 dark:bg-stone-950 dark:focus-within:border-red-400" data-assistant-composer-shell>
    <div data-assistant-active-document x-show="$store.researchAssistant.activeDocument" x-cloak class="mx-2 mb-1 flex min-w-0 items-center gap-2 border-b border-stone-200 pb-1 text-xs text-stone-700 dark:border-stone-700 dark:text-stone-200">
        <svg class="h-4 w-4 shrink-0 text-red-900 dark:text-red-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
        <span class="min-w-0 flex-1"><span class="block text-[11px] text-stone-500 dark:text-stone-400">Asking about this document</span><span class="block truncate font-medium" :title="$store.researchAssistant.activeDocument?.label" x-text="$store.researchAssistant.activeDocument?.label"></span></span>
        <span class="shrink-0 text-[11px] text-stone-500 dark:text-stone-400" x-text="$store.researchAssistant.activeDocument?.format"></span>
        <button type="button" @click="$store.researchAssistant.clearActiveDocument()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:hover:bg-stone-800" aria-label="Stop asking about this document" title="Stop asking about this document"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <div data-assistant-review-target x-show="$store.researchAssistant.reviewContext" x-cloak class="mx-2 mb-1 border-b border-stone-200 pb-1 text-xs leading-5 text-red-900 dark:border-stone-700 dark:text-red-200">
        <p x-show="$store.researchAssistant.reviewContext?.proposal_draft_id || !$store.researchAssistant.hasContextOptions()" :title="$store.researchAssistant.reviewTargetLabel()" class="truncate font-semibold" x-text="$store.researchAssistant.reviewTargetLabel()"></p>
        <p class="font-medium" x-text="$store.researchAssistant.reviewFocusLabel()"></p>
    </div>
    <form id="assistant-send-{{ $id }}" @submit.prevent="$store.researchAssistant.send()">
        <label for="{{ $inputId }}" class="sr-only">Message Athena Research Assistant</label>
        <textarea id="{{ $inputId }}" data-assistant-composer x-model="$store.researchAssistant.draft" @input="$store.researchAssistant.resizeComposer($event)" @keydown="$store.researchAssistant.handleComposerKeydown($event)" rows="1" maxlength="8000" placeholder="Ask Athena about your research…" class="block max-h-44 min-h-12 w-full resize-none border-0 bg-white px-3 py-3 text-base leading-6 text-stone-950 shadow-none placeholder:text-stone-500 focus:border-0 focus:ring-0 dark:bg-stone-950 dark:text-stone-100 dark:placeholder:text-stone-400"></textarea>
    </form>
    <div class="flex items-end justify-between gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-1">
            <div class="min-w-0" x-show="!$store.researchAssistant.reviewContext?.proposal_draft_id"><x-research-assistant-project-picker :id="$id" /></div>
            <x-research-assistant-document-action :id="$id" />
            <button x-show="$store.researchAssistant.reviewContext" type="button" @click="$store.researchAssistant.proposalHelpOpen = !$store.researchAssistant.proposalHelpOpen; $nextTick(() => $el.closest('[data-assistant-workspace], .athena-chat-panel')?.querySelector('[data-assistant-messages]')?.scrollTo({ top: 0, behavior: 'auto' }))" :aria-expanded="$store.researchAssistant.proposalHelpOpen" aria-label="Help options" title="Help options" class="inline-flex h-11 shrink-0 items-center gap-2 rounded-lg border border-stone-300 bg-white px-3 text-xs text-red-900 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-red-200 dark:hover:bg-stone-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4m0 3h.01" /></svg>
                <span class="hidden sm:inline">Help options</span>
            </button>
        </div>
        <button x-show="!$store.researchAssistant.isLoading" type="submit" form="assistant-send-{{ $id }}" :disabled="!$store.researchAssistant.draft.trim() || $store.researchAssistant.retryAfter > 0" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-800 text-white transition hover:bg-red-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-stone-100 disabled:text-stone-400 dark:disabled:bg-stone-800 dark:disabled:text-stone-500" aria-label="Send message">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 7-7 7 7M12 19V5" /></svg>
        </button>
        <button x-show="$store.researchAssistant.isLoading" x-cloak type="button" @click="$store.researchAssistant.stop()" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-800 text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2" aria-label="Stop response"><span class="h-3.5 w-3.5 rounded-sm bg-current"></span></button>
    </div>
</div>
