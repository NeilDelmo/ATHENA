@props(['id'])

<div x-show="$store.researchAssistant.canSelectDocuments()" x-cloak @keydown.escape.stop.prevent="$store.researchAssistant.closeDocumentPicker(); $refs.documentTrigger.focus()" x-data="{}" class="shrink-0">
    <button x-ref="documentTrigger" type="button" @click="$store.researchAssistant.toggleDocumentPicker()" :aria-expanded="$store.researchAssistant.documentPickerOpen" aria-controls="assistant-document-picker-{{ $id }}" aria-label="Analyze document" title="Analyze document" class="inline-flex h-11 w-11 items-center justify-center rounded-full text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5 8.8 12.2a2.5 2.5 0 1 0 3.5 3.5l6.1-6.1a4.5 4.5 0 0 0-6.4-6.4l-6.6 6.6a6.5 6.5 0 0 0 9.2 9.2l5.4-5.4" /></svg>
    </button>
    <div id="assistant-document-picker-{{ $id }}" x-show="$store.researchAssistant.documentPickerOpen" x-cloak @click.outside="$store.researchAssistant.closeDocumentPicker()" x-transition.opacity.duration.150ms class="absolute bottom-full left-0 right-0 z-20 mb-2 rounded-2xl border border-gray-200 bg-white p-3 shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="pl-2 text-sm font-medium text-gray-900 dark:text-white">Analyze a document</p>
            <button type="button" @click="$store.researchAssistant.closeDocumentPicker(); $refs.documentTrigger.focus()" class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-500 hover:bg-gray-100 dark:text-slate-400 dark:hover:bg-slate-800" aria-label="Close document picker"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg></button>
        </div>
        <p x-show="$store.researchAssistant.documentLoading" role="status" class="px-2 py-4 text-sm text-gray-500 dark:text-slate-400">Loading documents…</p>
        <div class="max-h-[min(12rem,25dvh)] overflow-y-auto">
            <template x-for="documentOption in $store.researchAssistant.documentOptions" :key="documentOption.token">
                <button type="button" @click="$store.researchAssistant.selectedDocumentToken = documentOption.token" :aria-pressed="$store.researchAssistant.selectedDocumentToken === documentOption.token" class="flex min-h-11 w-full items-start gap-3 rounded-xl px-3 py-3 text-left text-sm text-gray-700 hover:bg-gray-100 aria-pressed:bg-gray-100 dark:text-slate-200 dark:hover:bg-slate-800 dark:aria-pressed:bg-slate-800">
                    <span class="min-w-0 flex-1 break-words" x-text="documentOption.label"></span>
                    <span class="shrink-0 text-xs font-medium text-gray-500 dark:text-slate-400" x-text="documentOption.format"></span>
                    <svg x-show="$store.researchAssistant.selectedDocumentToken === documentOption.token" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                </button>
            </template>
        </div>
        <p x-show="$store.researchAssistant.documentError" role="alert" class="px-2 py-3 text-sm text-red-600 dark:text-red-300" x-text="$store.researchAssistant.documentError"></p>
        <p class="px-2 py-3 text-xs leading-5 text-gray-500 dark:text-slate-400">Choose a saved paper before PDF generation, or a PDF or DOCX. Type a question below or request a summary. Only relevant readable passages from this document are sent; it stays selected for follow-up questions. Current edits must finish saving first.</p>
        <button type="button" @click="$store.researchAssistant.analyzeSelectedDocument()" :disabled="$store.researchAssistant.documentLoading || $store.researchAssistant.documentAnalysisPending || !$store.researchAssistant.selectedDocumentToken || $store.researchAssistant.isLoading" class="min-h-11 w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-slate-900" x-text="$store.researchAssistant.documentLoading ? 'Loading…' : $store.researchAssistant.documentAnalysisPending ? 'Saving paper…' : 'Analyze document'"></button>
    </div>
</div>
