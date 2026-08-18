@props(['id'])

<div x-show="$store.researchAssistant.canSelectDocuments()" x-cloak class="mb-2">
    <button
        type="button"
        @click="$store.researchAssistant.toggleDocumentPicker()"
        :aria-expanded="$store.researchAssistant.documentPickerOpen"
        aria-controls="assistant-document-picker-{{ $id }}"
        class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-bold text-gray-500 transition hover:bg-gray-100 hover:text-gray-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
    >
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5 8.8 12.2a2.5 2.5 0 1 0 3.5 3.5l6.1-6.1a4.5 4.5 0 0 0-6.4-6.4l-6.6 6.6a6.5 6.5 0 0 0 9.2 9.2l5.4-5.4" /></svg>
        Analyze document
    </button>

    <div
        id="assistant-document-picker-{{ $id }}"
        x-show="$store.researchAssistant.documentPickerOpen"
        x-transition.opacity.duration.150ms
        class="mt-1.5 rounded-xl border border-gray-200 bg-gray-50 p-2.5 dark:border-slate-700 dark:bg-slate-800/70"
    >
        <div class="flex items-center gap-2">
            <select
                id="assistant-document-select-{{ $id }}"
                x-model="$store.researchAssistant.selectedDocumentToken"
                :disabled="$store.researchAssistant.documentLoading || !$store.researchAssistant.documentOptions.length"
                aria-label="Document to analyze"
                class="min-w-0 flex-1 rounded-lg border-gray-200 bg-white py-1.5 text-[11px] font-semibold text-gray-700 focus:border-red-500 focus:ring-red-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200"
            >
                <template x-for="documentOption in $store.researchAssistant.documentOptions" :key="documentOption.token">
                    <option :value="documentOption.token" x-text="`${documentOption.label} · ${documentOption.format}`"></option>
                </template>
            </select>
            <button
                type="button"
                @click="$store.researchAssistant.analyzeSelectedDocument()"
                :disabled="$store.researchAssistant.documentLoading || !$store.researchAssistant.selectedDocumentToken || $store.researchAssistant.isLoading"
                class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-[11px] font-black text-white transition hover:bg-black disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-slate-900"
                x-text="$store.researchAssistant.documentLoading ? 'Loading…' : 'Analyze'"
            ></button>
        </div>
        <p x-show="$store.researchAssistant.documentError" class="mt-1.5 text-[10px] font-semibold text-red-600 dark:text-red-300" x-text="$store.researchAssistant.documentError"></p>
        <p x-show="!$store.researchAssistant.documentError" class="mt-1.5 text-[10px] leading-4 text-gray-400">Only the selected PDF or DOCX is sent to Athena for this request.</p>
    </div>
</div>
