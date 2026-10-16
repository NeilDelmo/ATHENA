<section x-show="message.proposal_changes" x-cloak {{ $attributes->merge(['class' => 'mt-4 overflow-hidden rounded-xl border border-stone-300 text-sm leading-6 dark:border-stone-700']) }} aria-label="Review proposal changes" data-assistant-proposal-changes>
    <template x-if="message.proposal_changes">
        <div>
            <header class="border-b border-stone-200 bg-stone-50 px-4 py-3 dark:border-stone-700 dark:bg-stone-800">
                <h4 class="font-semibold text-stone-950 dark:text-stone-100" x-text="message.proposal_changes.suggestions.length ? 'Review proposal changes' : 'Saved proposal check'"></h4>
                <p class="mt-1 break-words text-xs text-stone-600 dark:text-stone-300" x-text="message.proposal_changes.proposal_title"></p>
                <p x-show="message.proposal_changes.suggestions.length" class="mt-1 text-xs text-stone-600 dark:text-stone-300">Review each suggestion before applying it to your detailed proposal.</p>
            </header>
            <div x-show="message.proposal_changes.assessment?.rrl" class="border-b border-stone-200 px-4 py-3 dark:border-stone-700">
                <p class="text-stone-800 dark:text-stone-200" x-text="$store.researchAssistant.proposalRrlAssessmentText(message)"></p>
                <p class="mt-1 text-xs text-stone-600 dark:text-stone-300">A source count does not verify quality or guarantee approval.</p>
                <p x-show="$store.researchAssistant.proposalRrlRequirementSource(message)" class="mt-1 break-words text-xs text-stone-600 dark:text-stone-300" x-text="'Manual basis: ' + ($store.researchAssistant.proposalRrlRequirementSource(message)?.title || '')"></p>
                <button type="button" @click="$store.researchAssistant.findProposalStudies(message)" :disabled="$store.researchAssistant.proposalChangesBusy() || !!$store.researchAssistant.draft.trim()" class="mt-2 inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-red-900 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-stone-600 dark:text-red-200 dark:hover:bg-stone-800">Find additional studies</button>
            </div>
            <div class="divide-y divide-stone-200 dark:divide-stone-700">
                <template x-for="suggestion in message.proposal_changes.suggestions" :key="suggestion.id">
                    <article class="min-w-0 px-4 py-4" data-proposal-change-suggestion>
                        <h5 class="font-semibold text-stone-950 dark:text-stone-100" x-text="suggestion.label"></h5>
                        <p x-show="suggestion.field === 'related_literature'" class="mt-1 text-xs text-stone-600 dark:text-stone-300">Wording and paragraph order only. Compare the claims with your sources before accepting; this does not read or verify the cited papers.</p>
                        <p x-show="suggestion.reason" class="mt-1 text-xs text-stone-600 dark:text-stone-300" x-text="suggestion.reason"></p>
                        <details x-show="suggestion.before" class="mt-3 text-xs text-stone-600 dark:text-stone-300">
                            <summary class="disclosure-summary cursor-pointer py-2 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Current saved text</summary>
                            <p class="whitespace-pre-wrap break-words leading-6" x-text="$store.researchAssistant.proposalChangeBeforeText(suggestion)"></p>
                        </details>
                        <p class="mt-3 text-xs font-semibold text-stone-700 dark:text-stone-300">Suggested replacement</p>
                        <p class="mt-1 whitespace-pre-wrap break-words rounded-lg bg-stone-50 p-3 text-stone-900 dark:bg-stone-800 dark:text-stone-100" x-text="$store.researchAssistant.proposalChangeReplacementText(suggestion)"></p>
                        <button type="button" @click="$store.researchAssistant.applyProposalChange(message, suggestion)" :disabled="$store.researchAssistant.proposalChangesBusy() || !!suggestion.applied || !!suggestion.stale" class="mt-3 inline-flex min-h-11 items-center rounded-lg bg-red-900 px-3 py-2 text-xs font-semibold text-white hover:bg-red-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-stone-900" x-text="suggestion.saved ? 'Applied and saved' : suggestion.applied ? 'Added to editor' : suggestion.is_working ? 'Applying…' : 'Apply to proposal'"></button>
                        <button type="button" x-show="!suggestion.applied" @click="$store.researchAssistant.keepCurrentProposalText(message, suggestion)" :disabled="$store.researchAssistant.proposalChangesBusy()" class="mt-3 inline-flex min-h-11 items-center rounded-lg border border-stone-300 bg-stone-50 px-3 py-2 text-xs font-semibold text-stone-700 hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:border-stone-600 dark:bg-stone-800 dark:text-stone-200 dark:hover:bg-stone-700">Keep current</button>
                        <p x-show="suggestion.notice" role="status" class="mt-2 text-xs text-stone-600 dark:text-stone-300" x-text="suggestion.notice"></p>
                        <p x-show="suggestion.error" role="alert" class="mt-2 text-xs text-red-800 dark:text-red-200" x-text="suggestion.error"></p>
                    </article>
                </template>
            </div>
            <div x-show="message.proposal_changes.suggestions.some(suggestion => suggestion.stale)" class="border-t border-stone-200 px-4 py-3 dark:border-stone-700">
                <button type="button" @click="$store.researchAssistant.refreshProposalChanges(message)" :disabled="$store.researchAssistant.proposalChangesBusy() || !!$store.researchAssistant.draft.trim()" class="inline-flex min-h-11 items-center rounded-lg border border-stone-300 px-3 py-2 text-xs font-semibold text-red-900 hover:bg-stone-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-stone-600 dark:text-red-200 dark:hover:bg-stone-800">Request updated suggestions</button>
            </div>
        </div>
    </template>
</section>
