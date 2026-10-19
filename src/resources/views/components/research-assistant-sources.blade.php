<details x-show="Array.isArray(message.sources) && message.sources.length" x-cloak class="group mt-4 border-t border-stone-200 pt-2 dark:border-stone-700" data-assistant-sources>
    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-md py-2 text-sm font-semibold text-red-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-red-200">
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75c-2.6-1.75-5.4-2.25-9-1.5V19.5c3.6-.75 6.4-.25 9 1.5m0-14.25c2.6-1.75 5.4-2.25 9-1.5V19.5c-3.6-.75-6.4-.25-9 1.5m0-14.25V21" /></svg>
        <span>Sources</span>
        <span class="font-normal text-stone-500 dark:text-stone-400" x-text="`(${message.sources?.length || 0})`"></span>
        <svg class="ml-auto h-4 w-4 shrink-0 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
    </summary>
    <p class="pb-2 text-xs leading-5 text-stone-500 dark:text-stone-400">Reference material available for this reply.</p>
    <ul class="divide-y divide-stone-200 dark:divide-stone-700" aria-label="Sources and reference material">
        <template x-for="(source, index) in message.sources || []" :key="index">
            <li class="min-w-0" data-assistant-source>
                <a :href="$store.researchAssistant.sourceUrl(source) || null" target="_blank" rel="noopener noreferrer"
                    @click="$store.researchAssistant.openSourcePreview(source, $event)"
                    :aria-label="$store.researchAssistant.sourceUrl(source) ? `${({ 'Official Research Manual': 'Open manual', proposal_paper: 'Open paper', project_record: 'Open project' })[source.type] || 'Open source'}: ${source.title || source.reference || 'Reference material'}` : null"
                    :class="$store.researchAssistant.sourceUrl(source) ? 'hover:bg-red-50/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:hover:bg-red-950/20' : ''"
                    class="flex min-h-11 min-w-0 items-start gap-3 rounded-md py-3 text-left">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-stone-400 dark:text-stone-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z" /><path d="M14 3v6h6M8 13h8M8 17h5" /></svg>
                    <span class="min-w-0 flex-1">
                        <span class="block break-words text-sm font-medium leading-6 text-stone-900 dark:text-stone-100" x-text="source.title || source.reference || 'Reference material'"></span>
                        <span class="mt-0.5 flex flex-wrap gap-x-2 text-xs leading-5 text-stone-500 dark:text-stone-400">
                            <span class="min-w-0 break-words" x-text="source.category || 'ATHENA reference'"></span>
                            <span x-show="source.reference && source.reference !== source.title" class="min-w-0 break-words text-red-900 dark:text-red-200" x-text="source.reference"></span>
                        </span>
                    </span>
                    <svg x-show="$store.researchAssistant.sourceUrl(source)" class="mt-1 h-4 w-4 shrink-0 text-red-900 dark:text-red-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3h7v7m0-7L10 14M10 4H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-5" /></svg>
                </a>
            </li>
        </template>
    </ul>
</details>
