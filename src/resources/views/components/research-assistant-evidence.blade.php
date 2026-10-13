<details x-show="message.review" x-cloak class="group mb-4 border-b border-stone-200 pb-3 text-stone-800 dark:border-stone-700 dark:text-stone-100" data-assistant-review-coverage>
    <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
        <span class="font-semibold" x-text="message.review?.record_basis === 'private_saved_draft' ? 'Reviewed saved draft' : (message.review?.version_number ? `Reviewed submission v${message.review.version_number}` : 'Reviewed submitted record')"></span>
        <span class="text-xs text-stone-600 dark:text-stone-300">Saved fields only</span>
        <svg class="ml-auto h-4 w-4 shrink-0 transition group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
    </summary>
    <div class="border-t border-stone-200 pt-3 text-sm leading-6 dark:border-stone-700">
        <p x-show="message.review?.reviewed_at" class="mb-2 text-xs text-stone-600 dark:text-stone-300" x-text="message.review?.reviewed_at ? `Checked ${new Date(message.review.reviewed_at).toLocaleString()}` : ''"></p>
        <p class="mb-3" x-text="message.review?.findings?.length ? 'Evidence quotations are matched to the supplied saved fields. Findings are Athena’s interpretation and require your review.' : 'This response has no verified quotation links. Open the papers to compare it with the source.'"></p>
        <ul class="grid gap-3 sm:grid-cols-2">
            <template x-for="paper in message.review?.papers || []" :key="paper.reference">
                <li class="min-w-0 border-b border-stone-200 py-3 dark:border-stone-700">
                    <p class="font-medium" x-text="paper.reference"></p>
                    <p class="text-xs text-stone-600 dark:text-stone-300" x-text="paper.coverage === 'saved_structured_fields' ? 'Available saved fields reviewed' : (paper.coverage === 'uploaded_content_not_read' ? 'Uploaded content was not read' : 'Paper unavailable')"></p>
                    <p x-show="paper.omitted_fields || paper.truncated_fields?.length" class="mt-1 text-xs text-red-800 dark:text-red-200" x-text="`${paper.omitted_fields || 0} fields omitted; ${paper.truncated_fields?.length || 0} fields shortened`"></p>
                    <a x-show="$store.researchAssistant.sourceUrl(paper)" :href="$store.researchAssistant.sourceUrl(paper)" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-white px-3 py-2 text-xs text-red-900 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:border-stone-600 dark:bg-stone-900 dark:text-red-200 dark:hover:bg-stone-800">Open paper</a>
                </li>
            </template>
        </ul>
        <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-stone-600 dark:text-stone-300">
            <template x-for="(limit, index) in message.review?.limitations || []" :key="index"><li x-text="limit"></li></template>
        </ul>
    </div>
</details>
