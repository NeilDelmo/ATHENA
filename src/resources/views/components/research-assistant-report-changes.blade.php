<section x-show="message.report_changes?.suggestions?.length" x-cloak class="mt-4 overflow-hidden rounded-xl border border-stone-300 text-sm leading-6 dark:border-stone-700" aria-label="Review report changes" data-assistant-report-changes>
    <template x-if="message.report_changes">
        <div>
            <header class="border-b border-stone-200 bg-stone-50 px-4 py-3 dark:border-stone-700 dark:bg-stone-800">
                <h4 class="font-semibold text-stone-950 dark:text-stone-100">Review report changes</h4>
                <p class="mt-1 text-xs text-stone-600 dark:text-stone-300" x-text="message.report_changes.title"></p>
                <p class="mt-1 text-xs text-stone-600 dark:text-stone-300">Revisions change written content only. Names, dates, amounts, signatures, and approvals stay unchanged. Accept changes individually and check the wording against your supporting evidence.</p>
            </header>
            <div class="divide-y divide-stone-200 dark:divide-stone-700">
                <template x-for="suggestion in message.report_changes.suggestions" :key="suggestion.id">
                    <article class="min-w-0 px-4 py-4">
                        <h5 class="font-semibold text-stone-950 dark:text-stone-100" x-text="suggestion.label"></h5>
                        <p x-show="suggestion.reason" class="mt-1 text-xs text-stone-600 dark:text-stone-300" x-text="suggestion.reason"></p>
                        <details x-show="suggestion.before" class="mt-3 text-xs text-stone-600 dark:text-stone-300">
                            <summary class="disclosure-summary cursor-pointer py-2 font-medium focus-visible:ring-2 focus-visible:ring-red-500">Current text</summary>
                            <p class="whitespace-pre-wrap break-words leading-6" x-text="$store.researchAssistant.proposalChangeBeforeText(suggestion)"></p>
                        </details>
                        <p class="mt-3 text-xs font-semibold text-stone-700 dark:text-stone-300">Suggested replacement</p>
                        <p class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-stone-50 p-3 text-stone-900 dark:bg-stone-800 dark:text-stone-100" x-text="$store.researchAssistant.proposalChangeBeforeText({before:suggestion.value})"></p>
                        <button type="button" @click="$store.researchAssistant.applyReportChange(message, suggestion)" :disabled="$store.researchAssistant.proposalChangesBusy() || !!suggestion.applied || !!suggestion.stale" class="mt-3 inline-flex min-h-11 items-center rounded-lg bg-red-900 px-3 py-2 text-xs font-semibold text-white hover:bg-red-950 focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50" x-text="suggestion.saved ? 'Applied and saved' : suggestion.applied ? 'Added to form' : suggestion.is_working ? 'Applying…' : 'Apply change'"></button>
                        <button type="button" x-show="!suggestion.applied" @click="$store.researchAssistant.keepCurrentReportText(message, suggestion)" :disabled="$store.researchAssistant.proposalChangesBusy()" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-50 focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:border-stone-600 dark:text-stone-200">Keep current</button>
                        <p x-show="suggestion.notice" x-text="suggestion.notice" role="status" class="mt-2 text-xs text-stone-600 dark:text-stone-300"></p>
                        <p x-show="suggestion.error" x-text="suggestion.error" role="alert" class="mt-2 text-xs text-red-800 dark:text-red-200"></p>
                    </article>
                </template>
            </div>
            <button x-show="message.report_changes.suggestions.some(item => item.stale)" type="button" @click="$store.researchAssistant.refreshReportChanges(message)" :disabled="$store.researchAssistant.proposalChangesBusy() || !!$store.researchAssistant.draft.trim()" class="m-4 inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-red-900 focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:text-red-200">Request updated suggestions</button>
        </div>
    </template>
</section>
