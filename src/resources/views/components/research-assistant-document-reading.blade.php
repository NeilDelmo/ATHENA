<details data-assistant-document-reading x-show="message.document_reading" x-cloak class="mb-3 text-xs leading-5 text-stone-600 dark:text-stone-300">
    <summary class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-stone-200 px-3 py-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-700">
        <svg class="h-4 w-4 shrink-0 text-red-900 dark:text-red-200" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6M8 13h8M8 17h5"/></svg>
        <span class="min-w-0 flex-1 break-words" x-text="message.document_reading?.label"></span>
        <span class="shrink-0 font-medium" x-text="message.document_reading?.format"></span>
    </summary>
    <div class="grid gap-1 px-3 py-2">
        <p x-text="message.document_reading?.basis === 'saved_draft_projection' ? 'Read from saved paper values before PDF generation. Unsaved edits are excluded.' : message.document_reading?.basis === 'submitted_snapshot' ? 'Read from the submitted paper snapshot.' : 'Read from the selected uploaded file.'"></p>
        <p x-show="message.document_reading?.version !== null && message.document_reading?.version !== undefined" x-text="`Saved version: ${message.document_reading?.version}`"></p>
        <p x-text="`${message.document_reading?.coverage?.selected_passages || 0} of ${message.document_reading?.coverage?.total_passages || 0} readable passages used for this answer.`"></p>
        <p x-show="message.document_reading?.coverage?.omitted_passages || message.document_reading?.coverage?.truncated" class="font-medium">This answer uses selected excerpts; it is not a complete review of every passage.</p>
        <template x-for="(limitation, index) in message.document_reading?.coverage?.limitations || []" :key="index"><p x-text="limitation"></p></template>
    </div>
</details>
