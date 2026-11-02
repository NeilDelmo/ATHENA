<div x-show="message.literature" x-cloak {{ $attributes->merge(['class' => 'mt-4 text-sm leading-6']) }}>
    <template x-if="message.literature">
        <section class="overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700" aria-label="Literature review assistance">
            <template x-if="message.literature.kind === 'choose_proposal'">
                <div class="flex flex-col gap-4 p-4" x-data="{ proposalQuery: '' }">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Choose a proposal</h3>
                    <label x-show="message.literature.proposals.length > 5" class="block text-sm text-gray-600 dark:text-slate-300">Search your proposals<input x-model="proposalQuery" type="search" class="mt-2 block w-full rounded-lg border-gray-300 text-base dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                    <div class="flex flex-col gap-2"><template x-for="proposal in message.literature.proposals.filter(item => item.title.toLowerCase().includes(proposalQuery.toLowerCase()))" :key="proposal.id"><button type="button" @click="$store.researchAssistant.chooseLiteratureProposal(message, proposal.id)" :disabled="$store.researchAssistant.literatureBusy(message)" class="min-h-11 rounded-lg border border-gray-200 px-3 py-3 text-left text-base font-medium text-gray-900 hover:border-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:border-slate-700 dark:text-white" x-text="proposal.title"></button></template></div>
                    <button x-show="message.literature.workspace_required" type="button" @click="$store.researchAssistant.switchLiteratureWorkspace(message)" :disabled="$store.researchAssistant.literatureBusy(message)" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-900 px-4 py-2 text-base font-semibold text-white focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:bg-red-700">Choose proposal in faculty workspace</button>
                    <a x-show="!message.literature.workspace_required && !message.literature.proposals.length && message.literature.proposals_url" :href="$store.researchAssistant.literatureProposalsUrl(message)" class="inline-flex min-h-11 items-center rounded text-base font-medium text-red-800 focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-300">Open proposals</a>
                </div>
            </template>

            <template x-if="message.literature.kind === 'results'">
                <div>
                    <header class="flex flex-col gap-1 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white" x-text="message.literature.intent === 'draft' ? 'RRL draft not ready' : 'Sources for your RRL'"></h3>
                        <p class="break-words text-base leading-6 text-gray-600 dark:text-slate-300" x-text="message.literature.topic || message.literature.query"></p>

                        <p x-show="message.literature.intent !== 'draft'" class="text-sm leading-6 text-gray-500 dark:text-slate-400">Choose up to 3 papers for an editable draft.</p>
                    </header>

                    <div x-show="message.literature.intent === 'draft'" class="flex flex-col gap-4 px-4 py-4">
                        <p class="text-base leading-7 text-gray-700 dark:text-slate-200">A draft needs complete readable papers. You can attach a source PDF or look for other papers.</p>
                        <div class="flex flex-wrap gap-2"><button type="button" @click="$store.researchAssistant.openLiteratureSources(message)" :disabled="$store.researchAssistant.literatureBusy(message)" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-red-900 px-4 py-2 text-base font-semibold text-white hover:bg-red-950 focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:bg-red-700">Attach source PDF</button><button type="button" @click="$store.researchAssistant.openLiteratureSources(message, 'search')" :disabled="$store.researchAssistant.literatureBusy(message)" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-base font-medium text-gray-800 focus-visible:ring-2 focus-visible:ring-red-600 disabled:opacity-50 dark:border-slate-600 dark:text-slate-100">Change search</button></div>
                        <details x-show="message.literature.read_failures.length"><summary class="disclosure-summary cursor-pointer py-2 text-base font-medium text-gray-800 dark:text-slate-100">Why the papers could not be read</summary><template x-for="(failure, index) in message.literature.read_failures" :key="index"><div class="mt-4 text-sm leading-6 text-gray-600 dark:text-slate-300"><p class="font-medium" x-text="failure.title"></p><p x-text="failure.reason"></p></div></template></details>
                    </div>
                    <details :open="message.literature.intent !== 'draft'" class="border-t border-gray-200 dark:border-slate-700">
                    <summary class="disclosure-summary cursor-pointer px-4 py-3 text-base font-medium text-gray-800 dark:text-slate-100" x-show="message.literature.intent === 'draft'">Found studies (<span x-text="message.literature.results.length"></span>)</summary>
                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <template x-for="source in message.literature.results" :key="source.source_token">
                            <article class="flex gap-3 px-4 py-4" :class="$store.researchAssistant.literatureSourceSelected(message, source.source_token) ? 'bg-red-50/50 dark:bg-red-950/20' : ''">
                                <input
                                    x-show="message.literature.intent !== 'draft'" type="checkbox"
                                    :checked="$store.researchAssistant.literatureSourceSelected(message, source.source_token)"
                                    @change="$store.researchAssistant.toggleLiteratureSource(message, source.source_token)"
                                    :disabled="$store.researchAssistant.literatureSourceDisabled(message, source)"
                                    :aria-label="`Select study: ${source.title}`"
                                    class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-red-700 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-900"
                                >
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="break-words text-base font-semibold leading-6 text-gray-900 dark:text-white" x-text="source.title"></h4>
                                            <p class="mt-1 break-words text-sm leading-6 text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.literatureSourceMetadata(source)"></p>
                                        </div>
                                        <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" :aria-label="`Open study: ${source.title}`" class="shrink-0 rounded px-1 text-sm font-medium text-red-700 hover:underline focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300">Open study</a>
                                    </div>
                                    <p class="text-sm leading-6 text-gray-600 dark:text-slate-300" x-text="$store.researchAssistant.literatureEvidenceLabel(source)"></p>
                                    <details x-show="source.description || source.relevance.reason" class="text-sm leading-6 text-gray-600 dark:text-slate-300">
                                        <summary class="disclosure-summary cursor-pointer py-1 font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Abstract and search match</summary>
                                        <p x-show="source.description" class="mt-3 whitespace-pre-wrap break-words" x-text="source.description"></p>
                                        <p x-show="source.relevance.reason" class="mt-3"><span class="font-medium">Search match: </span><span x-text="source.relevance.reason"></span></p>
                                    </details>
                                </div>
                            </article>
                        </template>
                    </div>

                    </details>
                    <p x-show="!message.literature.results.length" class="px-4 py-4 text-xs text-gray-600 dark:text-slate-300">No studies found. Try a more specific method, population, or research term.</p>
                    <footer x-show="message.literature.intent !== 'draft'" class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-slate-700">
                        <p x-show="message.literature.notice" class="text-sm leading-6 text-gray-500 dark:text-slate-400" x-text="message.literature.notice"></p>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-gray-600 dark:text-slate-300" aria-live="polite" x-text="`${$store.researchAssistant.literatureSelectedCount(message)} of 3 selected`"></p>
                            <button type="button" @click="$store.researchAssistant.draftSelectedLiterature(message)" :disabled="!$store.researchAssistant.canDraftLiterature(message)" class="inline-flex items-center gap-2 min-h-11 rounded-lg bg-red-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:focus:ring-offset-slate-900"><x-assistant-icon /><span x-text="message.literature.is_working ? 'Reading papers and drafting…' : 'Generate from papers'"></span></button>
                        </div>
                    </footer>
                </div>
            </template>

            <template x-if="message.literature.kind === 'draft'">
                <article x-data x-id="['rrl-draft-sources']" :data-rrl-artifact="message.id" tabindex="-1" aria-label="Editable RRL draft" class="bg-white outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-600 dark:bg-slate-950">
                    <header class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-200 px-4 py-3 dark:border-slate-700">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <svg class="mt-1 h-5 w-5 shrink-0 text-red-900 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6ZM14 3v6h6M8 13h8M8 17h5"/></svg>
                            <div class="min-w-0"><h3 class="text-base font-semibold text-gray-900 dark:text-white">RRL draft</h3><p class="mt-1 break-words text-base leading-6 text-gray-600 dark:text-slate-300" x-text="message.literature.topic || message.literature.proposal_title"></p></div>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <x-research-assistant-copy />
                            <button x-show="!$store.researchAssistant.workspaceOpen" type="button" @click="$store.researchAssistant.expandLiteratureDraft(message)" aria-label="Expand RRL draft" title="Expand RRL draft" class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-stone-500 hover:bg-stone-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-stone-300 dark:hover:bg-slate-800"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5M3 3l6 6m12-6-6 6M3 21l6-6m12 6-6-6"/></svg></button>
                        </div>
                    </header>
                    <div class="mx-auto flex max-w-[75ch] flex-col gap-6 px-4 py-6 sm:px-6" data-rrl-draft-document>
                        <template x-for="(draft, index) in message.literature.drafts" :key="draft.draft_token">
                            <div>
                                <textarea x-model="draft.paragraph"
                                    x-effect="draft.paragraph; $store.researchAssistant.drawerOpen; $store.researchAssistant.workspaceOpen; $nextTick(() => $store.researchAssistant.resizeLiteratureParagraph($el))"
                                    @resize.window.debounce.100ms="$store.researchAssistant.resizeLiteratureParagraph($el)"
                                    @focus="$store.researchAssistant.resizeLiteratureParagraph($el)"
                                    @input="$store.researchAssistant.resizeLiteratureParagraph($el)"
                                    @input.debounce.500ms="$store.researchAssistant.saveConversation()"
                                    :disabled="$store.researchAssistant.literatureBusy(message) || message.literature.confirmed"
                                    :aria-label="`RRL paragraph based on ${draft.title}`" rows="6" minlength="40" maxlength="5000"
                                    class="block w-full resize-none rounded-md border border-transparent bg-transparent px-2 py-1 text-base font-normal leading-7 text-gray-900 shadow-none hover:border-gray-200 focus:border-red-600 focus:ring-1 focus:ring-red-600 disabled:cursor-default dark:text-slate-100 dark:hover:border-slate-700"
                                ></textarea>
                                <button type="button" @click="$refs.draftSources.open = true; $nextTick(() => $refs.draftSources.scrollIntoView({block: 'nearest'}))" :aria-controls="$id('rrl-draft-sources')" :aria-label="`View source ${index + 1}: ${draft.title}`" :title="draft.title" class="inline-flex min-h-10 items-center rounded px-2 text-sm font-semibold text-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-300" x-text="`[${index + 1}]`"></button>
                                <p x-show="draft.paragraph.trim().length < 40" class="px-2 text-base text-red-800 dark:text-red-200">Write at least 40 characters before adding this paragraph.</p>
                            </div>
                        </template>
                    </div>
                    <details x-ref="draftSources" :id="$id('rrl-draft-sources')" class="border-t border-gray-200 px-4 py-3 dark:border-slate-700 sm:px-6">
                        <summary class="disclosure-summary cursor-pointer rounded py-2 text-base font-medium text-gray-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-slate-100" x-text="`Sources (${message.literature.drafts.length})`"></summary>
                        <ol class="mt-3 flex flex-col gap-5 text-sm leading-6 text-gray-600 dark:text-slate-300">
                            <template x-for="(draft, index) in message.literature.drafts" :key="draft.draft_token"><li class="break-words">
                                <p class="font-medium text-gray-900 dark:text-white" x-text="draft.reference || `[${index + 1}] ${draft.title}`"></p>
                                <p x-text="$store.researchAssistant.literatureSourceMetadata(draft)"></p>
                                <p x-text="draft.evidence_basis === 'full_text' ? 'Drafted from source-paper text' : 'Based on an abstract; complete paper reading is required.'"></p>
                                <a x-show="draft.url" :href="draft.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded font-medium text-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 dark:text-red-300" :aria-label="`Read source: ${draft.title}`">Read source</a>
                                <details x-show="draft.notice"><summary class="disclosure-summary cursor-pointer py-1">Reading details</summary><p class="mt-2" x-text="draft.notice"></p></details>
                            </li></template>
                        </ol>
                    </details>
                    <details x-show="message.literature.read_failures.length" class="border-t border-gray-200 px-4 py-3 text-sm leading-6 text-gray-600 dark:border-slate-700 dark:text-slate-300 sm:px-6"><summary class="disclosure-summary cursor-pointer py-2">Papers that could not be read</summary><template x-for="(failure, index) in message.literature.read_failures" :key="index"><div class="mt-3"><p class="font-medium" x-text="failure.title"></p><p x-text="failure.reason"></p></div></template></details>
                    <footer class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 px-4 py-4 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
                        <p class="text-sm leading-6 text-gray-600 dark:text-slate-300">Starting draft from <span x-text="message.literature.drafts.length"></span> <span x-text="message.literature.drafts.length === 1 ? 'source' : 'sources'"></span>. Review and edit it before adding it. References will follow your proposal’s numbering.</p>
                        <p x-show="message.literature.drafts.some(draft => draft.evidence_basis !== 'full_text')" class="text-base leading-7 text-gray-700 dark:text-slate-200">This saved draft was based on abstracts. Generate a new draft from complete papers before adding it.</p>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-gray-500 dark:text-slate-400" x-show="message.literature.confirmed">Review confirmed. Continue in the proposal to finish any pending save.</p>
                            <button type="button" @click="$store.researchAssistant.confirmLiteratureDraft(message)" :disabled="!$store.researchAssistant.canConfirmLiterature(message)" class="ml-auto inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-red-900 px-4 py-2 text-base font-semibold text-white hover:bg-red-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-600" x-text="message.literature.is_working ? 'Adding…' : message.literature.confirmed ? 'Review confirmed' : 'Add to proposal'"></button>
                        </div>
                    </footer>
                </article>
            </template>

            <template x-if="message.literature.kind === 'insertion'">
                <div class="flex flex-col items-start gap-3 px-4 py-4">
                    <p class="text-sm font-bold text-gray-900 dark:text-white" x-text="message.literature.applied ? 'RRL added to your paper' : 'Reviewed RRL is ready for your paper'"></p>
                    <p x-show="message.literature.notice" class="text-xs leading-5 text-gray-600 dark:text-slate-300" x-text="message.literature.notice"></p>
                    <p x-show="!message.literature.applied" class="text-xs leading-5 text-gray-500 dark:text-slate-400">To improve paragraph flow, open Use AI in the RRL section and choose Revise RRL.</p>
                    <div class="flex flex-wrap gap-2">
                        <button x-show="message.literature.editor_url" type="button" @click="$store.researchAssistant.openLiteraturePaper(message)" :disabled="$store.researchAssistant.isLoading" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-40 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800" x-text="message.literature.applied ? 'View in paper' : 'Open paper'"></button>
                        <button x-show="message.literature.applied" type="button" @click="$store.researchAssistant.reviewLiteratureFlow(message)" :disabled="!$store.researchAssistant.canReviewLiteratureFlow(message)" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-800 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 dark:hover:bg-red-950/50"><x-assistant-icon />Review RRL flow</button>
                    </div>
                </div>
            </template>

            <p x-show="message.literature.error" role="alert" class="px-4 pb-3 text-xs text-red-700 dark:text-red-300" x-text="message.literature.error"></p>
        </section>
    </template>
</div>
