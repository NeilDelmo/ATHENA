<div x-show="literatureWorkspaceOpen" x-cloak x-on:keydown.escape.window="closeLiteratureWorkspace()" class="fixed inset-0 z-50 flex items-stretch justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="literature-workspace-title">
    <button type="button" x-on:click="closeLiteratureWorkspace()" class="absolute inset-0 cursor-default bg-slate-950/60" aria-label="Close literature workspace"></button>
    <section class="relative z-10 flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl dark:bg-slate-950 sm:h-[min(92vh,58rem)] sm:max-w-6xl sm:rounded-3xl sm:border sm:border-slate-200 dark:sm:border-slate-700">
        <header class="sticky top-0 z-30 flex shrink-0 flex-wrap items-start gap-3 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-700 dark:bg-slate-950 sm:px-6">
            <div class="min-w-0 flex-1">
                <div x-show="literatureWorkspaceView === 'browse'">
                    <h3 id="literature-workspace-title" class="text-lg font-black text-slate-950 dark:text-white">Literature workspace</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Find verified evidence, then connect it to claims throughout the proposal. Nothing is cited automatically.</p>
                </div>
                <div x-show="literatureWorkspaceView === 'review'" x-cloak>
                    <button type="button" x-on:click="returnToLiteratureResults()" class="inline-flex min-h-8 items-center gap-1 rounded-lg px-2 text-[11px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-200 dark:hover:bg-red-950/30">
                        <span aria-hidden="true">←</span> Back to results
                    </button>
                    <h3 id="literature-workspace-title" class="mt-1 text-lg font-black text-slate-950 dark:text-white">Review source and RRL</h3>
                </div>
            </div>
            <div x-show="literatureWorkspaceView === 'browse'" class="flex shrink-0 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                <button type="button" x-on:click="setLiteratureWorkspaceTab('search')" x-bind:class="literatureWorkspaceTab === 'search' ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'" class="rounded-lg px-3 py-2 text-[11px] font-black transition">Find literature</button>
                <button type="button" x-on:click="setLiteratureWorkspaceTab('sources')" x-bind:class="literatureWorkspaceTab === 'sources' ? 'bg-white text-slate-950 shadow-sm dark:bg-slate-950 dark:text-white' : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'" class="rounded-lg px-3 py-2 text-[11px] font-black transition"><span>Saved sources</span> <span x-text="`(${literatureSources.length})`"></span></button>
            </div>
            <button type="button" x-on:click="closeLiteratureWorkspace()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close literature workspace">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div x-show="literatureWorkspaceView === 'browse'" class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-6">
            <section x-show="literatureWorkspaceTab === 'search'" x-cloak aria-labelledby="literature-search-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="block min-w-0 flex-1 text-xs font-black text-slate-700 dark:text-slate-200" for="proposal-literature-query">
                        Search literature
                        <input id="proposal-literature-query" type="search" maxlength="180" x-model="literatureSearchQuery" x-on:input="literatureSearchError = ''" x-on:keydown.enter.prevent="searchSuggestedLiterature()" x-bind:disabled="literatureSearchLoading" placeholder="Enter your own terms or refine the suggested query" class="mt-1.5 block h-11 w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 disabled:cursor-wait disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500 dark:disabled:bg-slate-800">
                    </label>
                    <button type="button" x-on:click="searchSuggestedLiterature()" x-bind:disabled="literatureSearchLoading || literatureSearchQuery.trim().length < 3" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-red-700 px-4 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40">
                        <svg x-show="literatureSearchLoading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-30" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                        </svg>
                        <span x-text="literatureSearchLoading ? 'Searching indexes' : 'Search literature'"></span>
                    </button>
                </div>

                <details class="mt-3 rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-3 dark:border-slate-700 dark:bg-slate-900/60">
                    <summary class="cursor-pointer text-xs font-black text-slate-700 marker:hidden focus:outline-none dark:text-slate-200">Search settings <span class="ml-1 font-semibold text-slate-500">Choose which proposal details inform the suggestion</span></summary>
                    <div class="mt-3 flex flex-col gap-3 border-t border-slate-200 pt-3 dark:border-slate-700">
                        <div class="flex flex-wrap gap-2">
                            <template x-for="context in literatureSearchContextOptions()" :key="context.key">
                                <button type="button" x-show="context.available" x-on:click="toggleLiteratureSearchContext(context.key)" x-bind:aria-pressed="literatureSearchContext[context.key] ? 'true' : 'false'" x-bind:class="literatureSearchContext[context.key] ? 'border-red-600 bg-red-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-red-300 hover:bg-red-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30'" class="rounded-lg border px-2.5 py-1.5 text-[10px] font-black transition focus:outline-none focus:ring-2 focus:ring-red-600" x-text="context.label"></button>
                            </template>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[11px] leading-5 text-slate-500 dark:text-slate-400">The query is editable. ATHENA only contacts indexes when you search.</p>
                            <button type="button" x-on:click="refreshSuggestedLiteratureQuery()" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[10px] font-black text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">Refresh from proposal</button>
                        </div>
                    </div>
                </details>

                <div x-show="literatureSearchLoading" x-cloak x-transition.opacity class="mt-4 overflow-hidden rounded-2xl border border-red-200 bg-red-50/70 p-4 dark:border-red-950 dark:bg-red-950/25" role="status" aria-live="polite" x-bind:aria-busy="literatureSearchLoading" data-literature-search-loading>
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-10 w-10 shrink-0 items-center justify-center" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-300/70 dark:bg-red-900/70"></span>
                            <span class="relative h-7 w-7 animate-spin rounded-full border-[3px] border-red-200 border-t-red-700 bg-white dark:border-red-950 dark:border-t-red-400 dark:bg-slate-950"></span>
                        </span>
                        <div>
                            <p class="text-sm font-black text-slate-950 dark:text-white">Searching verified literature</p>
                            <p class="mt-0.5 text-xs leading-5 text-slate-600 dark:text-slate-300">ATHENA is checking academic indexes and ranking possible matches.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-3" aria-hidden="true">
                        <div class="rounded-xl border border-red-100 bg-white p-3 dark:border-red-950/80 dark:bg-slate-950">
                            <div class="h-3 w-4/5 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
                            <div class="mt-2 h-2.5 w-3/5 animate-pulse rounded bg-slate-100 dark:bg-slate-900"></div>
                        </div>
                        <div class="rounded-xl border border-red-100 bg-white p-3 dark:border-red-950/80 dark:bg-slate-950">
                            <div class="h-3 w-3/4 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
                            <div class="mt-2 h-2.5 w-2/3 animate-pulse rounded bg-slate-100 dark:bg-slate-900"></div>
                        </div>
                        <div class="rounded-xl border border-red-100 bg-white p-3 dark:border-red-950/80 dark:bg-slate-950">
                            <div class="h-3 w-5/6 animate-pulse rounded bg-slate-200 dark:bg-slate-800"></div>
                            <div class="mt-2 h-2.5 w-1/2 animate-pulse rounded bg-slate-100 dark:bg-slate-900"></div>
                        </div>
                    </div>
                </div>

                <p x-show="literatureSearchError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="literatureSearchError" role="alert"></p>
                <p x-show="literatureSearchNotice" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs font-semibold leading-5 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" x-text="literatureSearchNotice" role="status"></p>

                <div x-show="literatureSearchResults.length" x-cloak class="mt-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h4 id="literature-search-heading" class="text-sm font-black text-slate-950 dark:text-white"><span x-text="literatureSearchResults.length"></span> matching papers</h4>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Review evidence before writing or citing.</p>
                    </div>
                    <div class="mt-3 max-h-[34rem] space-y-2 overflow-y-auto pr-1" aria-live="polite">
                        <template x-for="result in literatureSearchResults" :key="literatureResultKey(result)">
                            <article class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-900">
                                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0">
                                        <h5 class="text-sm font-black leading-5 text-slate-950 dark:text-white" x-text="result.title"></h5>
                                        <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"><span x-text="result.authors || 'Authors not listed'"></span><span x-show="result.year"> · <span x-text="result.year"></span></span></p>
                                        <p x-show="result.match_reason" class="mt-2 text-[11px] leading-4 text-slate-600 dark:text-slate-300" x-text="result.match_reason"></p>
                                    </div>
                                    <div class="flex shrink-0 flex-wrap items-center gap-1.5 text-[9px] font-black">
                                        <span class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="result.relevance_label || 'Potential match'"></span>
                                        <span class="rounded bg-slate-200 px-2 py-1 text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="result.access_label || 'Access not listed'"></span>
                                        <span x-show="result._linked" class="rounded bg-slate-900 px-2 py-1 text-white dark:bg-white dark:text-slate-900">Saved</span>
                                        <span x-show="result._linkedSource && literatureSourceUsage(result._linkedSource).usedInProposal" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="`Cited [${literatureSourceUsage(result._linkedSource).referenceNumber}]`"></span>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                    <button type="button" x-on:click="prepareSuggestedLiteratureReview(result)" x-bind:disabled="isSavingSuggestedLiterature(result) || !hasUsableSuggestedAbstract(result)" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 text-[10px] font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40" x-text="isSavingSuggestedLiterature(result) ? 'Opening…' : 'Review evidence'"></button>
                                    <button type="button" x-on:click="saveSuggestedLiterature(result)" x-bind:disabled="isSavingSuggestedLiterature(result) || result._linked" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-[10px] font-black text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200" x-text="result._linked ? 'Saved' : 'Save source'"></button>
                                    <a x-show="result.url" x-bind:href="result.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center justify-center rounded-lg px-2 text-[10px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-200 dark:hover:bg-red-950/30">Open source</a>
                                    <p x-show="result._actionNotice" x-cloak class="basis-full text-[10px] font-semibold text-slate-700 dark:text-slate-200" x-text="result._actionNotice" role="status"></p>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>

                <details x-show="literatureSearchHistory.length" x-cloak class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <summary class="cursor-pointer text-xs font-black text-slate-600 marker:hidden focus:outline-none dark:text-slate-300">Recent searches</summary>
                    <div class="mt-3 space-y-2">
                        <template x-for="entry in literatureSearchHistory" :key="entry.id">
                            <div class="flex flex-col gap-2 rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between">
                                <p class="min-w-0 text-[11px] leading-5 text-slate-600 dark:text-slate-300" x-bind:title="entry.query"><span class="font-black text-slate-900 dark:text-white" x-text="literatureSearchHistoryTitle(entry)"></span><span class="text-slate-400"> · </span><span x-text="literatureSearchHistorySummary(entry)"></span></p>
                                <button type="button" x-on:click="runLiteratureSearchHistory(entry)" class="inline-flex min-h-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white px-2.5 text-[10px] font-black text-slate-700 hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">Run again</button>
                            </div>
                        </template>
                    </div>
                </details>
            </section>

            <section x-show="literatureWorkspaceTab === 'sources'" x-cloak aria-labelledby="saved-literature-sources-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h4 id="saved-literature-sources-heading" class="text-sm font-black text-slate-950 dark:text-white">Saved sources</h4>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Sources are shared-library records linked to this proposal. Highlight a claim in any supported narrative section, choose Support with source, and ATHENA will synchronize its reference.</p>
                    </div>
                    <span class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black text-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="`${literatureSources.length} saved`"></span>
                </div>
                <p x-show="literatureSourceNotice" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs font-semibold leading-5 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" x-text="literatureSourceNotice" role="status"></p>
                <div x-show="!literatureSources.length" class="mt-5 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-700">
                    <p class="text-sm font-black text-slate-800 dark:text-white">No source saved to this proposal yet</p>
                    <button type="button" x-on:click="setLiteratureWorkspaceTab('search')" class="mt-3 inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 text-[10px] font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600">Find literature</button>
                </div>
                <div x-show="literatureSources.length" x-cloak class="mt-5 space-y-2">
                    <template x-for="source in literatureSources" :key="source.id">
                        <article class="rounded-xl border border-slate-200 bg-white p-3.5 dark:border-slate-700 dark:bg-slate-900">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="min-w-0">
                                    <h5 class="text-sm font-black leading-5 text-slate-950 dark:text-white" x-text="source.title"></h5>
                                    <p class="mt-1 text-[11px] leading-4 text-slate-500 dark:text-slate-400"><span x-text="source.authors || 'Authors not listed'"></span><span x-show="source.year"> · <span x-text="source.year"></span></span><span x-show="source.source"> · <span x-text="source.source"></span></span></p>
                                    <div x-show="literatureSourceUsage(source).sections.length" x-cloak class="mt-2 flex flex-wrap gap-1.5">
                                        <template x-for="section in literatureSourceUsage(source).sections" :key="`${source.id}-${section}`">
                                            <span class="rounded-full border border-red-200 bg-red-50 px-2 py-1 text-[9px] font-black text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="section"></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-wrap gap-1.5 text-[9px] font-black">
                                    <span class="rounded bg-slate-900 px-2 py-1 text-white dark:bg-white dark:text-slate-900">Saved</span>
                                    <span x-show="literatureSourceUsage(source).usedInProposal" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200" x-text="`Cited [${literatureSourceUsage(source).referenceNumber}]`"></span>
                                    <span x-show="literatureSourceUsage(source).addedToReferences" class="rounded bg-red-100 px-2 py-1 text-red-800 dark:bg-red-950/50 dark:text-red-200">Reference synchronized</span>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                                <button x-show="!literatureSourceUsage(source).usedInRrl" type="button" x-on:click="source.rrl_draft_status === 'confirmed' ? addLiteratureSourceToRrl(source) : prepareLinkedLiteratureReview(source)" class="inline-flex min-h-9 items-center justify-center rounded-lg bg-red-700 px-3 text-[10px] font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600" x-text="source.rrl_draft_status === 'confirmed' ? 'Add to Section XI' : (source.rrl_draft_status === 'draft' ? 'Review research notes' : 'Review evidence')"></button>
                                <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-9 items-center justify-center rounded-lg px-2 text-[10px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-200 dark:hover:bg-red-950/30">Open source</a>
                                <button x-show="literatureSourceUsage(source).usedInProposal" type="button" x-on:click="removeCitationSource(source)" class="inline-flex min-h-9 items-center justify-center rounded-lg border border-red-200 bg-white px-3 text-[10px] font-black text-red-800 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-red-900 dark:bg-slate-950">Remove proposal citations</button>
                            </div>
                        </article>
                    </template>
                </div>
            </section>
        </div>

        <div x-show="literatureWorkspaceView === 'review'" x-cloak class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:overflow-hidden">
            <section class="border-b border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-900 lg:overflow-y-auto lg:border-b-0 lg:border-r sm:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-slate-200 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="literatureReviewBasis === 'full_text' ? 'Public full text' : 'Indexed abstract'"></span>
                    <span x-show="literatureReviewSource?.reference_incomplete" class="rounded-full bg-red-100 px-2.5 py-1 text-[10px] font-black text-red-800 dark:bg-red-950/50 dark:text-red-200">Review citation metadata</span>
                </div>
                <h4 class="mt-4 text-base font-black leading-6 text-slate-950 dark:text-white" x-text="literatureReviewSource?.title || 'Selected paper'"></h4>
                <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"><span x-text="literatureReviewSource?.authors || 'Authors not listed'"></span><span x-show="literatureReviewSource?.year"> · <span x-text="literatureReviewSource?.year"></span></span></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="button" x-on:click="literatureReviewBasis = 'abstract'" x-bind:class="literatureReviewBasis === 'abstract' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border border-slate-300 bg-white text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200'" class="rounded-lg px-3 py-2 text-[10px] font-black focus:outline-none focus:ring-2 focus:ring-red-600">Abstract</button>
                    <button type="button" x-show="literatureReviewSource?.full_text_token" x-on:click="loadLiteratureReviewFullText()" x-bind:disabled="literatureReviewLoadingFullText" x-bind:class="literatureReviewBasis === 'full_text' ? 'bg-red-700 text-white' : 'border border-red-200 bg-white text-red-800 dark:border-red-900 dark:bg-slate-950 dark:text-red-200'" class="rounded-lg px-3 py-2 text-[10px] font-black focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewLoadingFullText ? 'Loading…' : (literatureReviewFullText ? 'Use full text' : 'Load public full text')"></button>
                </div>
                <p x-show="literatureReviewFullTextError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="literatureReviewFullTextError"></p>
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-950">
                    <p class="text-xs font-black text-slate-800 dark:text-slate-100" x-text="literatureReviewBasis === 'full_text' ? 'Open-access evidence' : 'Abstract evidence'"></p>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-600 dark:text-slate-300" x-text="literatureReviewEvidence()"></p>
                </div>
                <a x-show="literatureReviewSource?.url" x-bind:href="literatureReviewSource?.url" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex text-xs font-black text-red-800 hover:underline dark:text-red-200">Open source record</a>
            </section>

            <section class="flex min-h-[28rem] flex-col p-5 dark:bg-slate-950 lg:overflow-y-auto sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Connection-aware RRL draft</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">ATHENA compares the current RRL ending with this source, then proposes a grounded transition when the evidence supports one.</p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button type="button" x-on:click="generateLiteratureReviewDraft('auto')" x-bind:disabled="literatureReviewGenerating || !hasLiteratureReviewEvidence()" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-red-700 px-3.5 text-xs font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewGenerating ? 'Drafting…' : (literatureReviewPreviousContext ? (literatureReviewDraft ? 'Regenerate connection' : 'Generate connected draft') : 'Draft from evidence')"></button>
                        <button x-show="literatureReviewPreviousContext" x-cloak type="button" x-on:click="generateLiteratureReviewDraft('standalone')" x-bind:disabled="literatureReviewGenerating || !hasLiteratureReviewEvidence()" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-3.5 text-xs font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">Keep standalone</button>
                    </div>
                </div>

                <div x-show="literatureReviewPreviousContext" x-cloak class="mt-4 overflow-hidden rounded-2xl border border-red-200 bg-white shadow-sm dark:border-red-900 dark:bg-slate-900" data-literature-connection-preview>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-red-100 bg-red-50/70 px-4 py-3 dark:border-red-950 dark:bg-red-950/25">
                        <div>
                            <p class="text-xs font-black text-slate-950 dark:text-white">Connection preview</p>
                            <p class="mt-0.5 text-[10px] text-slate-500 dark:text-slate-400">Review the relationship before inserting the paragraph.</p>
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-black" x-bind:class="literatureReviewHasConnection() ? 'bg-red-700 text-white' : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-100'" x-text="literatureReviewDraft ? literatureReviewRelationshipLabel() : 'Ready to connect'"></span>
                    </div>
                    <div class="grid gap-3 p-4 sm:grid-cols-[minmax(0,1fr)_2rem_minmax(0,1fr)] sm:items-stretch">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Current RRL ending</p>
                            <p class="mt-2 text-xs font-semibold leading-5 text-slate-700 dark:text-slate-200" x-text="literatureReviewPreviousContext"></p>
                        </div>
                        <div class="hidden items-center justify-center text-xl font-black text-red-700 sm:flex" aria-hidden="true">→</div>
                        <div class="rounded-xl border border-red-100 bg-red-50/60 p-3 dark:border-red-950 dark:bg-red-950/20">
                            <p class="text-[9px] font-black uppercase tracking-wider text-red-800 dark:text-red-200">Proposed relationship</p>
                            <p class="mt-2 text-xs font-black text-slate-900 dark:text-white" x-text="literatureReviewDraft ? literatureReviewRelationshipLabel() : 'Generate to analyze the connection'"></p>
                            <p class="mt-1 text-[11px] leading-5 text-slate-600 dark:text-slate-300" x-text="literatureReviewDraft ? literatureReviewRelationshipDescription() : 'ATHENA will use the previous ending for flow, but the new paper remains the only evidence for its claims.'"></p>
                            <p x-show="literatureReviewTransition" x-cloak class="mt-2 rounded-lg bg-white px-2.5 py-2 text-[10px] font-bold italic text-red-800 dark:bg-slate-950 dark:text-red-200"><span class="not-italic text-slate-500 dark:text-slate-400">Transition: </span><span x-text="literatureReviewTransition"></span></p>
                        </div>
                    </div>
                </div>

                <label for="literature-review-draft" class="mt-4 block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400" x-text="literatureReviewHasConnection() ? 'Connected research notes' : 'Research notes'"></label>
                <textarea id="literature-review-draft" x-model="literatureReviewDraft" rows="12" maxlength="5000" placeholder="Write your research notes. Abstract-based notes can be saved; review full-paper evidence before inserting text." class="mt-2 min-h-64 w-full flex-1 resize-y rounded-2xl border-slate-300 bg-white p-4 text-sm leading-7 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500"></textarea>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <p class="font-medium text-slate-500 dark:text-slate-400"><span x-text="literatureReviewWordCount()"></span> words · Review required before saving</p>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-700 dark:bg-slate-800 dark:text-slate-200" x-text="literatureReviewBasis === 'full_text' ? 'Full-text based' : 'Abstract based'"></span>
                </div>
                <p x-show="literatureReviewNotice" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-3 text-xs font-semibold leading-5 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" x-text="literatureReviewNotice" role="status"></p>
                <p x-show="literatureReviewError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="literatureReviewError" role="alert"></p>
                <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:justify-end">
                    <button type="button" x-on:click="saveLiteratureReview()" x-bind:disabled="literatureReviewSaving || literatureReviewDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200" x-text="literatureReviewSaving ? 'Saving…' : 'Save research notes'"></button>
                    <button type="button" x-on:click="saveLiteratureReview(true)" x-bind:disabled="literatureReviewSaving || literatureReviewDraft.trim().length < 40 || literatureReviewBasis !== 'full_text'" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-4 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40" x-text="literatureReviewSaving ? 'Adding…' : (literatureReviewHasConnection() ? 'Insert connected paragraph' : 'Add standalone paragraph')"></button>
                </div>
            </section>
        </div>
    </section>
</div>
