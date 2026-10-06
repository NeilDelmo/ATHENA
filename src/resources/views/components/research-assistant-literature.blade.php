<div x-show="message.literature" x-cloak {{ $attributes->merge(['class' => 'mt-4 text-sm leading-6']) }}>
    <template x-if="message.literature">
        <section class="overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700" aria-label="Literature review assistance">
            <template x-if="message.literature.kind === 'results'">
                <div>
                    <header class="flex flex-col gap-1 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="font-bold text-gray-900 dark:text-white">Studies for your RRL</p>
                        <p x-show="message.literature.proposal_title" class="text-xs text-gray-600 dark:text-slate-300" x-text="message.literature.proposal_title"></p>
                        <p x-show="message.literature.context_basis" class="text-xs text-gray-500 dark:text-slate-400" x-text="message.literature.context_basis"></p>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Choose up to 3 studies to prepare an editable draft.</p>
                    </header>

                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <template x-for="source in message.literature.results" :key="source.source_token">
                            <article class="flex gap-3 px-4 py-4" :class="$store.researchAssistant.literatureSourceSelected(message, source.source_token) ? 'bg-red-50/50 dark:bg-red-950/20' : ''">
                                <input
                                    type="checkbox"
                                    :checked="$store.researchAssistant.literatureSourceSelected(message, source.source_token)"
                                    @change="$store.researchAssistant.toggleLiteratureSource(message, source.source_token)"
                                    :disabled="$store.researchAssistant.literatureSourceDisabled(message, source)"
                                    :aria-label="`Select study: ${source.title}`"
                                    class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-red-700 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-900"
                                >
                                <div class="flex min-w-0 flex-1 flex-col gap-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="break-words text-sm font-bold leading-5 text-gray-900 dark:text-white" x-text="source.title"></h4>
                                            <p class="mt-1 break-words text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.literatureSourceMetadata(source)"></p>
                                        </div>
                                        <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" :aria-label="`Open study: ${source.title}`" class="shrink-0 rounded px-1 text-xs font-semibold text-red-700 hover:underline focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300">Open study</a>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px] leading-5">
                                        <span class="font-semibold text-gray-700 dark:text-slate-200" x-text="source.relevance.label"></span>
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-slate-800 dark:text-slate-300" x-text="$store.researchAssistant.literatureEvidenceLabel(source)"></span>
                                    </div>
                                    <p x-show="source.relevance.reason" class="text-xs leading-5 text-gray-600 dark:text-slate-300"><span class="font-semibold">Why it fits: </span><span x-text="source.relevance.reason"></span></p>
                                    <details x-show="source.description" class="text-xs text-gray-600 dark:text-slate-300">
                                        <summary class="cursor-pointer font-semibold focus:outline-none focus:ring-2 focus:ring-red-500">Read available evidence</summary>
                                        <p class="mt-2 whitespace-pre-wrap break-words leading-5" x-text="source.description"></p>
                                    </details>
                                    <p x-show="!source.can_synthesize" class="text-xs leading-5 text-amber-800 dark:text-amber-200">Evidence is unavailable for drafting. Open the study to review it.</p>
                                </div>
                            </article>
                        </template>
                    </div>

                    <p x-show="!message.literature.results.length" class="px-4 py-4 text-xs text-gray-600 dark:text-slate-300">No studies found. Try a more specific method, population, or research term.</p>
                    <footer class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-slate-700">
                        <p x-show="message.literature.notice" class="text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="message.literature.notice"></p>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-gray-600 dark:text-slate-300" aria-live="polite" x-text="`${$store.researchAssistant.literatureSelectedCount(message)} of 3 selected`"></p>
                            <button type="button" @click="$store.researchAssistant.draftSelectedLiterature(message)" :disabled="!$store.researchAssistant.canDraftLiterature(message)" class="rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:focus:ring-offset-slate-900" x-text="message.literature.is_working ? 'Preparing RRL draft…' : 'Generate RRL draft'"></button>
                        </div>
                    </footer>
                </div>
            </template>

            <template x-if="message.literature.kind === 'draft'">
                <div>
                    <header class="flex flex-col gap-1 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/60">
                        <p class="font-bold text-gray-900 dark:text-white">Review your RRL draft</p>
                        <p x-show="message.literature.proposal_title" class="text-xs text-gray-600 dark:text-slate-300" x-text="message.literature.proposal_title"></p>
                        <p class="text-xs text-gray-500 dark:text-slate-400">Edit the text below. Adding it will include its citations and references.</p>
                    </header>

                    <div class="divide-y divide-gray-200 dark:divide-slate-700">
                        <template x-for="draft in message.literature.drafts" :key="draft.draft_token">
                            <div class="flex flex-col gap-3 px-4 py-4">
                                <div>
                                    <h4 class="break-words text-sm font-bold leading-5 text-gray-900 dark:text-white" x-text="draft.title"></h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-slate-400" x-text="$store.researchAssistant.literatureSourceMetadata(draft)"></p>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-[11px]">
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-slate-800 dark:text-slate-300" x-text="$store.researchAssistant.literatureEvidenceLabel(draft)"></span>
                                        <a x-show="draft.url" :href="draft.url" target="_blank" rel="noopener noreferrer" :aria-label="`Open study: ${draft.title}`" class="rounded font-semibold text-red-700 hover:underline focus:outline-none focus:ring-2 focus:ring-red-500 dark:text-red-300">Open study</a>
                                    </div>
                                </div>
                                <p x-show="draft.relationship" class="text-xs leading-5 text-gray-600 dark:text-slate-300" x-text="draft.relationship"></p>
                                <label class="flex flex-col gap-2 text-xs font-semibold text-gray-700 dark:text-slate-200">
                                    RRL paragraph
                                    <textarea
                                        x-model="draft.paragraph"
                                        :disabled="$store.researchAssistant.literatureBusy(message) || message.literature.confirmed"
                                        :aria-label="`RRL paragraph based on ${draft.title}`"
                                        rows="6"
                                        minlength="40"
                                        maxlength="5000"
                                        class="w-full resize-y rounded-lg border-gray-300 bg-white px-3 py-2 text-sm font-normal leading-6 text-gray-800 focus:border-red-600 focus:ring-red-600 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                    ></textarea>
                                </label>
                                <p x-show="draft.paragraph.trim().length < 40" class="text-xs text-amber-800 dark:text-amber-200">Write at least 40 characters before adding this paragraph.</p>
                                <p x-show="draft.notice" class="text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="draft.notice"></p>
                            </div>
                        </template>
                    </div>

                    <footer class="flex flex-col gap-3 border-t border-gray-200 px-4 py-3 dark:border-slate-700">
                        <p x-show="message.literature.notice" class="text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="message.literature.notice"></p>
                        <button type="button" @click="$store.researchAssistant.confirmLiteratureDraft(message)" :disabled="!$store.researchAssistant.canConfirmLiterature(message)" class="self-start rounded-lg bg-red-700 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:focus:ring-offset-slate-900" x-text="message.literature.confirmed ? 'Reviewed text added' : (message.literature.is_working ? 'Adding reviewed text…' : 'Add reviewed text to paper')"></button>
                    </footer>
                </div>
            </template>

            <template x-if="message.literature.kind === 'insertion'">
                <div class="flex flex-col items-start gap-3 px-4 py-4">
                    <p class="text-sm font-bold text-gray-900 dark:text-white" x-text="message.literature.applied ? 'RRL added to your paper' : 'Reviewed RRL is ready for your paper'"></p>
                    <p x-show="message.literature.notice" class="text-xs leading-5 text-gray-600 dark:text-slate-300" x-text="message.literature.notice"></p>
                    <button x-show="message.literature.editor_url" type="button" @click="$store.researchAssistant.openLiteraturePaper(message)" :disabled="$store.researchAssistant.isLoading" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-bold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-40 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800" x-text="message.literature.applied ? 'View in paper' : 'Open paper'"></button>
                </div>
            </template>

            <p x-show="message.literature.error" role="alert" class="px-4 pb-3 text-xs text-red-700 dark:text-red-300" x-text="message.literature.error"></p>
        </section>
    </template>
</div>
