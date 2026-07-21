<section
    id="rrl-finder"
    x-data="{ filtersOpen: window.innerWidth >= 768 }"
    class="athena-readable mb-5 scroll-mt-36 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
    aria-labelledby="rrl-finder-heading"
    data-rrl-workspace
>
    <header class="border-b border-gray-200 bg-gray-50/70 px-5 py-5 dark:border-slate-800 dark:bg-slate-950/40 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-md bg-red-600 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white">RRL Finder</span>
                    <span class="rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">Academic metadata</span>
                </div>
                <h3 id="rrl-finder-heading" class="mt-3 text-xl font-black tracking-tight text-gray-950 dark:text-white">Search related literature</h3>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-slate-400">Search real academic records, compare the evidence in a structured table, and send the results to Athena for RRL organization.</p>
            </div>
            <p class="shrink-0 text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Semantic Scholar + Crossref + OpenAlex</p>
        </div>
    </header>

    <div class="border-b border-gray-200 p-4 dark:border-slate-800 sm:p-5">
        <form @submit.prevent="$store.literatureSearch.search()" class="space-y-3">
            <div class="rounded-2xl border border-gray-300 bg-white p-2 shadow-sm transition focus-within:border-red-500 focus-within:ring-2 focus-within:ring-red-100 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:border-red-500 dark:focus-within:ring-red-950/50">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3 px-2">
                        <svg class="h-5 w-5 shrink-0 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        <div class="min-w-0 flex-1">
                            <label for="literature-search-query" class="block text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Research question, topic, or working title</label>
                            <input
                                id="literature-search-query"
                                type="search"
                                x-model="$store.literatureSearch.query"
                                maxlength="180"
                                placeholder="e.g. How does community participation affect mangrove restoration?"
                                class="block w-full border-0 bg-transparent px-0 py-1 text-sm font-semibold text-gray-900 shadow-none placeholder:font-normal placeholder:text-gray-400 focus:border-0 focus:ring-0 dark:text-white dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="$store.literatureSearch.isLoading || $store.literatureSearch.query.trim().length < 3"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-red-600 px-6 text-xs font-black text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40 dark:focus:ring-offset-slate-900"
                    >
                        <svg x-show="!$store.literatureSearch.isLoading" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        <svg x-show="$store.literatureSearch.isLoading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="$store.literatureSearch.isLoading ? 'Searching' : 'Search papers'"></span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <button
                    type="button"
                    @click="filtersOpen = !filtersOpen"
                    :aria-expanded="filtersOpen"
                    aria-controls="literature-search-filters"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-[11px] font-black text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-300"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10m-7 6h4" /></svg>
                    Filters
                    <span x-show="$store.literatureSearch.activeFilterCount()" x-cloak class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[9px] text-white" x-text="$store.literatureSearch.activeFilterCount()"></span>
                    <svg :class="filtersOpen ? 'rotate-180' : ''" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                </button>

                <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500">Up to 12 deduplicated records per search</p>
            </div>

            <div
                id="literature-search-filters"
                x-show="filtersOpen"
                x-cloak
                x-transition
                class="grid gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-slate-800 dark:bg-slate-950/50 md:grid-cols-4"
            >
                <div>
                    <label for="literature-search-year-from" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">From year</label>
                    <input id="literature-search-year-from" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_from" placeholder="Any" class="block w-full rounded-lg border-gray-200 bg-white text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-year-to" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">To year</label>
                    <input id="literature-search-year-to" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_to" placeholder="{{ now()->year }}" class="block w-full rounded-lg border-gray-200 bg-white text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-min-citations" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Min citations</label>
                    <input id="literature-search-min-citations" type="number" min="0" max="1000000" x-model.number="$store.literatureSearch.filters.min_citations" placeholder="0" class="block w-full rounded-lg border-gray-200 bg-white text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <label class="flex min-h-10 items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 text-xs font-black text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 md:mt-5">
                    <input type="checkbox" x-model="$store.literatureSearch.filters.open_access" class="rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-slate-600">
                    Open access only
                </label>
            </div>
        </form>
    </div>

    <div x-show="$store.literatureSearch.error" x-cloak class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:m-5">
        <p class="font-black">Search unavailable</p>
        <p class="mt-1" x-text="$store.literatureSearch.error"></p>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.failedSources.length && $store.literatureSearch.results.length" x-cloak class="m-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-700 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200 sm:m-5">
        <span class="font-black">Partial results:</span>
        <span x-text="`Could not reach ${$store.literatureSearch.failedSources.join(', ')} this time.`"></span>
    </div>

    <div x-show="$store.literatureSearch.isLoading" x-cloak class="p-4 sm:p-5" role="status" aria-label="Searching academic sources">
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-slate-800">
            <div class="grid grid-cols-[2fr_3fr_5rem_6rem] gap-px bg-gray-200 dark:bg-slate-800">
                <template x-for="column in 4" :key="`loading-heading-${column}`"><div class="h-10 animate-pulse bg-gray-100 dark:bg-slate-800"></div></template>
                <template x-for="cell in 12" :key="`loading-cell-${cell}`"><div class="h-20 animate-pulse bg-white p-3 dark:bg-slate-900"><div class="h-2.5 rounded bg-gray-100 dark:bg-slate-800"></div><div class="mt-2 h-2 w-2/3 rounded bg-gray-100 dark:bg-slate-800"></div></div></template>
            </div>
        </div>
    </div>

    <div x-show="!$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading" class="p-4 sm:p-5">
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-950/40">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-300">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5A3.375 3.375 0 0 0 10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15A2.25 2.25 0 0 0 6.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25V14.25Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 13.5h6m-6 3h6" /></svg>
            </div>
            <p class="mt-4 text-base font-black text-gray-900 dark:text-white">Build a literature comparison table</p>
            <p class="mx-auto mt-2 max-w-xl text-xs leading-5 text-gray-500 dark:text-slate-400">Start with a focused topic or research question. ATHENA will arrange titles, abstract summaries, publication details, citations, and access information side by side.</p>
            <div class="mx-auto mt-6 grid max-w-3xl gap-3 text-left sm:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><span class="text-[10px] font-black text-red-600 dark:text-red-300">01</span><p class="mt-1 text-xs font-black text-gray-800 dark:text-slate-100">Search papers</p><p class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-slate-400">Use a topic, title, or clear research question.</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><span class="text-[10px] font-black text-red-600 dark:text-red-300">02</span><p class="mt-1 text-xs font-black text-gray-800 dark:text-slate-100">Compare records</p><p class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-slate-400">Scan structured metadata in the results matrix.</p></div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"><span class="text-[10px] font-black text-red-600 dark:text-red-300">03</span><p class="mt-1 text-xs font-black text-gray-800 dark:text-slate-100">Ask Athena</p><p class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-slate-400">Organize themes, gaps, and an RRL outline.</p></div>
            </div>
        </div>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading && !$store.literatureSearch.results.length && !$store.literatureSearch.error" x-cloak class="p-4 sm:p-5">
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center dark:border-slate-800 dark:bg-slate-950/40">
            <p class="text-sm font-black text-gray-800 dark:text-slate-100">No literature records found</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Try a broader phrase, a key variable, or a shorter version of the working title.</p>
        </div>
    </div>

    <div x-show="$store.literatureSearch.results.length && !$store.literatureSearch.isLoading" x-cloak data-rrl-results-workspace>
        <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-black text-gray-900 dark:text-white"><span x-text="$store.literatureSearch.results.length"></span> papers found</p>
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:bg-slate-800 dark:text-slate-400">Relevance order</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="$store.literatureSearch.clear()" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-500 transition hover:bg-gray-50 hover:text-red-600 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-300">Clear</button>
                <button type="button" @click="$store.literatureSearch.askAthena()" :disabled="$store.researchAssistant.isLoading" class="rounded-lg bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Ask Athena about results</button>
            </div>
        </div>

        <div class="grid min-h-[520px] xl:grid-cols-[minmax(0,1fr)_21rem]">
            <div class="min-w-0 overflow-x-auto border-b border-gray-200 dark:border-slate-800 xl:border-b-0 xl:border-r" data-rrl-results-table>
                <table class="min-w-[920px] table-fixed border-collapse text-left">
                    <caption class="sr-only">Related literature comparison results</caption>
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-slate-950">
                        <tr class="border-b border-gray-200 dark:border-slate-800">
                            <th scope="col" class="w-14 px-3 py-3 text-center text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">#</th>
                            <th scope="col" class="w-72 border-l border-gray-200 px-4 py-3 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Paper</th>
                            <th scope="col" class="w-96 border-l border-gray-200 px-4 py-3 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Abstract summary</th>
                            <th scope="col" class="w-20 border-l border-gray-200 px-3 py-3 text-center text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Year</th>
                            <th scope="col" class="w-24 border-l border-gray-200 px-3 py-3 text-center text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Citations</th>
                            <th scope="col" class="w-28 border-l border-gray-200 px-3 py-3 text-center text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Access</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-800">
                        <template x-for="(result, index) in $store.literatureSearch.results" :key="`${result.source}-${result.doi || result.url || result.title}`">
                            <tr
                                @click="$store.literatureSearch.selectResult(index)"
                                @keydown.enter.prevent="$store.literatureSearch.selectResult(index)"
                                @keydown.space.prevent="$store.literatureSearch.selectResult(index)"
                                :aria-selected="$store.literatureSearch.selectedIndex === index"
                                :class="$store.literatureSearch.selectedIndex === index ? 'bg-red-50/80 dark:bg-red-950/20' : 'bg-white hover:bg-gray-50 dark:bg-slate-900 dark:hover:bg-slate-800/70'"
                                class="cursor-pointer align-top transition focus-within:bg-red-50 dark:focus-within:bg-red-950/20"
                                tabindex="0"
                            >
                                <td class="px-3 py-4 text-center text-[11px] font-black text-gray-400 dark:text-slate-500" x-text="index + 1"></td>
                                <td class="border-l border-gray-200 px-4 py-4 dark:border-slate-800">
                                    <p class="text-xs font-black leading-5 text-gray-900 dark:text-white" x-text="result.title"></p>
                                    <p class="mt-1 line-clamp-2 text-[10px] leading-4 text-gray-500 dark:text-slate-400" x-text="result.authors"></p>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="rounded bg-red-50 px-1.5 py-0.5 text-[9px] font-black text-red-700 dark:bg-red-950/40 dark:text-red-300" x-text="result.source"></span>
                                        <span x-show="result.venue" class="max-w-36 truncate text-[9px] font-semibold text-gray-400 dark:text-slate-500" x-text="result.venue"></span>
                                    </div>
                                </td>
                                <td class="border-l border-gray-200 px-4 py-4 dark:border-slate-800">
                                    <p class="line-clamp-4 text-[11px] leading-5 text-gray-600 dark:text-slate-300" x-text="result.description"></p>
                                </td>
                                <td class="border-l border-gray-200 px-3 py-4 text-center text-xs font-bold text-gray-700 dark:border-slate-800 dark:text-slate-200" x-text="result.year || '—'"></td>
                                <td class="border-l border-gray-200 px-3 py-4 text-center text-xs font-bold text-gray-700 dark:border-slate-800 dark:text-slate-200" x-text="Number.isInteger(result.citation_count) ? result.citation_count : '—'"></td>
                                <td class="border-l border-gray-200 px-3 py-4 text-center dark:border-slate-800">
                                    <span x-show="result.is_open_access" class="inline-flex rounded-full bg-green-50 px-2 py-1 text-[9px] font-black text-green-700 dark:bg-green-950/40 dark:text-green-300">Open</span>
                                    <span x-show="!result.is_open_access" class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-[9px] font-black text-gray-500 dark:bg-slate-800 dark:text-slate-400">Record</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <aside class="bg-gray-50/70 p-5 dark:bg-slate-950/40" aria-label="Selected paper details" data-rrl-paper-details>
                <div x-show="$store.literatureSearch.selectedResult()" x-cloak>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-gray-400 dark:text-slate-500">Paper details</p>
                        <span class="rounded-md bg-red-50 px-2 py-1 text-[9px] font-black text-red-700 dark:bg-red-950/40 dark:text-red-300" x-text="$store.literatureSearch.selectedResult()?.source"></span>
                    </div>
                    <h4 class="mt-4 text-base font-black leading-6 text-gray-950 dark:text-white" x-text="$store.literatureSearch.selectedResult()?.title"></h4>
                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.selectedResult()?.authors"></p>

                    <dl class="mt-5 grid grid-cols-2 gap-3 border-y border-gray-200 py-4 text-[11px] dark:border-slate-800">
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Year</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.year || 'Not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Citations</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="Number.isInteger($store.literatureSearch.selectedResult()?.citation_count) ? $store.literatureSearch.selectedResult().citation_count : 'Not listed'"></dd></div>
                        <div class="col-span-2"><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Publication</dt><dd class="mt-1 font-bold leading-5 text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.venue || 'Source venue not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Type</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.type || 'Not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Access</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.is_open_access ? 'Open access' : 'Source record'"></dd></div>
                    </dl>

                    <div class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Abstract summary</p>
                        <p class="mt-2 text-xs leading-6 text-gray-600 dark:text-slate-300" x-text="$store.literatureSearch.selectedResult()?.description"></p>
                    </div>

                    <div x-show="$store.literatureSearch.selectedResult()?.doi" class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">DOI</p>
                        <p class="mt-1 break-all text-[11px] font-semibold text-gray-600 dark:text-slate-300" x-text="$store.literatureSearch.selectedResult()?.doi"></p>
                    </div>

                    <a x-show="$store.literatureSearch.selectedResult()?.url" :href="$store.literatureSearch.selectedResult()?.url" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-3 text-xs font-black text-white transition hover:bg-black focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 dark:focus:ring-offset-slate-950">
                        Open source record
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18m0 0v4.5M18 6l-7.5 7.5M6 7.5v10.125A1.875 1.875 0 0 0 7.875 19.5H18" /></svg>
                    </a>
                </div>
            </aside>
        </div>
    </div>
</section>
