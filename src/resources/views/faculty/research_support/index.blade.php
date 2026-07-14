<x-app-layout>
    <x-slot name="header">
        <div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-600">ATHENA assistant</span>
                </div>
                <h2 class="mt-3 text-2xl font-black tracking-tight text-gray-900">Athena AI Workspace</h2>
                <p class="mt-1 text-xs text-gray-500">Plan, refine, and strengthen your research with Athena and integrated discovery tools.</p>
            </div>
        </div>
    </x-slot>

    <div class="mb-5 overflow-x-auto border-b border-gray-200">
        <nav class="flex min-w-max gap-6" aria-label="Research help tools">
            <a href="{{ route('research-support.index') }}" aria-current="page" class="flex items-center gap-2 border-b-2 border-red-600 px-1 pb-3 text-xs font-black text-red-600">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.8 4.8 11 2l1.2 2.8L15 6l-2.8 1.2L11 10 9.8 7.2 7 6l2.8-1.2ZM16.9 13.9 18 11l1.1 2.9L22 15l-2.9 1.1L18 19l-1.1-2.9L14 15l2.9-1.1Z" /></svg>
                AI Research Assistant
            </a>
            <span class="flex items-center gap-2 border-b-2 border-transparent px-1 pb-3 text-xs font-bold text-gray-400" aria-disabled="true">
                Research Guides
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[8px] font-black uppercase">Soon</span>
            </span>
            <span class="flex items-center gap-2 border-b-2 border-transparent px-1 pb-3 text-xs font-bold text-gray-400" aria-disabled="true">
                Document Insights
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[8px] font-black uppercase">Planned</span>
            </span>
        </nav>
    </div>

    <x-research-assistant-workspace />

    @hasanyrole('faculty|faculty_researcher')
    <div class="mb-3 mt-8">
        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-gray-400">Research discovery tools</p>
    </div>

    <section class="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="rrl-finder-heading">
        <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-blue-700">RRL Finder</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-500">Source metadata</span>
                    </div>
                    <h3 id="rrl-finder-heading" class="mt-3 text-base font-black text-gray-900">Search related literature</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500">Find real paper records from academic metadata sources before asking Athena to help organize your review.</p>
                </div>
                <div class="text-[10px] font-black uppercase tracking-wider text-gray-400">
                    Semantic Scholar + Crossref + OpenAlex
                </div>
            </div>
        </div>

        <div class="p-5">
            <form @submit.prevent="$store.literatureSearch.search()" class="space-y-3">
                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                    <div>
                        <label for="literature-search-query" class="sr-only">Research topic or title</label>
                        <input
                            id="literature-search-query"
                            type="search"
                            x-model="$store.literatureSearch.query"
                            maxlength="180"
                            placeholder="Search a topic or working title, e.g. mangrove monitoring community participation"
                            class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                        >
                    </div>
                    <button
                        type="submit"
                        :disabled="$store.literatureSearch.isLoading || $store.literatureSearch.query.trim().length < 3"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-gray-900 px-5 text-xs font-black text-white shadow-sm transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                    >
                        <svg x-show="!$store.literatureSearch.isLoading" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        <svg x-show="$store.literatureSearch.isLoading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="$store.literatureSearch.isLoading ? 'Searching' : 'Search'"></span>
                    </button>
                </div>
                <div class="grid gap-3 md:grid-cols-4">
                    <div>
                        <label for="literature-search-year-from" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400">From year</label>
                        <input id="literature-search-year-from" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_from" placeholder="Any" class="block w-full rounded-xl border-gray-200 text-xs shadow-sm focus:border-red-500 focus:ring-red-500">
                    </div>
                    <div>
                        <label for="literature-search-year-to" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400">To year</label>
                        <input id="literature-search-year-to" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_to" placeholder="{{ now()->year }}" class="block w-full rounded-xl border-gray-200 text-xs shadow-sm focus:border-red-500 focus:ring-red-500">
                    </div>
                    <div>
                        <label for="literature-search-min-citations" class="mb-1 block text-[10px] font-black uppercase tracking-wider text-gray-400">Min citations</label>
                        <input id="literature-search-min-citations" type="number" min="0" max="1000000" x-model.number="$store.literatureSearch.filters.min_citations" placeholder="0" class="block w-full rounded-xl border-gray-200 text-xs shadow-sm focus:border-red-500 focus:ring-red-500">
                    </div>
                    <label class="flex min-h-11 items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 text-xs font-black text-gray-700">
                        <input type="checkbox" x-model="$store.literatureSearch.filters.open_access" class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                        Open access only
                    </label>
                </div>
            </form>

            <div x-show="$store.literatureSearch.error" x-cloak class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700">
                <p class="font-black">Search unavailable</p>
                <p class="mt-1" x-text="$store.literatureSearch.error"></p>
            </div>

            <div x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.failedSources.length && $store.literatureSearch.results.length" x-cloak class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-700">
                <span class="font-black">Partial results:</span>
                <span x-text="`Could not reach ${$store.literatureSearch.failedSources.join(', ')} this time.`"></span>
            </div>

            <div x-show="!$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading" class="mt-5 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center">
                <p class="text-sm font-black text-gray-800">Start with a research topic or title</p>
                <p class="mt-1 text-xs text-gray-500">Results will show titles, descriptions, authors, source, year, DOI, and links when the source provides them.</p>
            </div>

            <div x-show="$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading && !$store.literatureSearch.results.length && !$store.literatureSearch.error" x-cloak class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-center">
                <p class="text-sm font-black text-gray-800">No literature records found</p>
                <p class="mt-1 text-xs text-gray-500">Try a broader phrase, a key variable, or a shorter version of the working title.</p>
            </div>

            <div x-show="$store.literatureSearch.results.length" x-cloak class="mt-5 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-black text-gray-900">
                        <span x-text="$store.literatureSearch.results.length"></span>
                        <span>RRL candidates</span>
                    </p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="$store.literatureSearch.askAthena()" :disabled="$store.researchAssistant.isLoading" class="rounded-lg bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Ask Athena about results</button>
                        <button type="button" @click="$store.literatureSearch.clear()" class="rounded-lg px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:bg-gray-100 hover:text-red-600">Clear</button>
                    </div>
                </div>

                <template x-for="result in $store.literatureSearch.results" :key="`${result.source}-${result.doi || result.url || result.title}`">
                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-blue-700" x-text="result.source"></span>
                            <span x-show="result.year" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-500" x-text="result.year"></span>
                            <span x-show="Number.isInteger(result.citation_count)" class="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-bold text-green-700" x-text="`${result.citation_count} citations`"></span>
                            <span x-show="result.is_open_access" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Open access</span>
                        </div>
                        <h4 class="mt-3 text-sm font-black leading-6 text-gray-900" x-text="result.title"></h4>
                        <p class="mt-2 text-xs leading-5 text-gray-600" x-text="result.description"></p>
                        <dl class="mt-3 grid gap-2 text-[11px] leading-4 text-gray-500 md:grid-cols-2">
                            <div>
                                <dt class="font-black uppercase tracking-wider text-gray-400">Authors</dt>
                                <dd class="mt-0.5 font-semibold text-gray-700" x-text="result.authors"></dd>
                            </div>
                            <div>
                                <dt class="font-black uppercase tracking-wider text-gray-400">Publication</dt>
                                <dd class="mt-0.5 font-semibold text-gray-700" x-text="result.venue || 'Source venue not listed'"></dd>
                            </div>
                            <div x-show="result.doi">
                                <dt class="font-black uppercase tracking-wider text-gray-400">DOI</dt>
                                <dd class="mt-0.5 break-all font-semibold text-gray-700" x-text="result.doi"></dd>
                            </div>
                            <div x-show="result.url">
                                <dt class="font-black uppercase tracking-wider text-gray-400">Link</dt>
                                <dd class="mt-0.5">
                                    <a :href="result.url" target="_blank" rel="noopener noreferrer" class="font-black text-red-600 hover:text-red-700">Open source record</a>
                                </dd>
                            </div>
                        </dl>
                    </article>
                </template>
            </div>
        </div>
    </section>

    <section class="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="conference-finder-heading">
        <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-700">Conference Finder</span>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-500">HTML scraping</span>
                        <span class="rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-700">Relevance ranked</span>
                    </div>
                    <h3 id="conference-finder-heading" class="mt-3 text-base font-black text-gray-900">Find conferences for publication</h3>
                    <p class="mt-1 text-xs leading-5 text-gray-500">Scrape public call-for-paper listings to discover possible local and international venues for a topic or title.</p>
                </div>
                <div class="text-[10px] font-black uppercase tracking-wider text-gray-400">
                    Scraped source: WikiCFP
                </div>
            </div>
        </div>

        <div class="p-5">
            <form @submit.prevent="$store.conferenceSearch.search()" class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto]">
                <div>
                    <label for="conference-search-query" class="sr-only">Conference topic or paper title</label>
                    <input
                        id="conference-search-query"
                        type="search"
                        x-model="$store.conferenceSearch.query"
                        maxlength="140"
                        placeholder="Search a field, topic, or paper title, e.g. educational technology assessment"
                        class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                    >
                </div>
                <button
                    type="submit"
                    :disabled="$store.conferenceSearch.isLoading || $store.conferenceSearch.query.trim().length < 3"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-amber-600 px-5 text-xs font-black text-white shadow-sm transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <svg x-show="!$store.conferenceSearch.isLoading" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3.75h15A1.5 1.5 0 0 1 21 5.25v10.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 15.75V5.25a1.5 1.5 0 0 1 1.5-1.5Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9M7.5 12h5.25" /></svg>
                    <svg x-show="$store.conferenceSearch.isLoading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                    <span x-text="$store.conferenceSearch.isLoading ? 'Scraping' : 'Scrape conferences'"></span>
                </button>
            </form>

            <div x-show="$store.conferenceSearch.error" x-cloak class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700">
                <p class="font-black">Conference scraping unavailable</p>
                <p class="mt-1" x-text="$store.conferenceSearch.error"></p>
            </div>

            <div x-show="!$store.conferenceSearch.hasSearched && !$store.conferenceSearch.isLoading" class="mt-5 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-6 text-center">
                <p class="text-sm font-black text-gray-800">Search for possible publication venues</p>
                <p class="mt-1 text-xs text-gray-500">Results will show relevance, local/international scope, deadlines, locations, and source links when available.</p>
            </div>

            <div x-show="$store.conferenceSearch.hasSearched && !$store.conferenceSearch.isLoading && !$store.conferenceSearch.results.length && !$store.conferenceSearch.error" x-cloak class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-6 text-center">
                <p class="text-sm font-black text-gray-800">No conference listings found</p>
                <p class="mt-1 text-xs text-gray-500">Try a broader topic, a field name, or fewer title words.</p>
            </div>

            <div x-show="$store.conferenceSearch.results.length" x-cloak class="mt-5 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-black text-gray-900">
                        <span x-text="$store.conferenceSearch.results.length"></span>
                        <span>scraped conference candidates</span>
                    </p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="button" @click="$store.conferenceSearch.askAthena()" :disabled="$store.researchAssistant.isLoading" class="rounded-lg bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Ask Athena to compare</button>
                        <button type="button" @click="$store.conferenceSearch.clear()" class="rounded-lg px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-400 transition hover:bg-gray-100 hover:text-red-600">Clear</button>
                    </div>
                </div>

                <template x-for="result in $store.conferenceSearch.results" :key="`${result.source}-${result.url || result.title}`">
                    <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-amber-700" x-text="result.source"></span>
                            <span x-show="result.scope_label" :class="{
                                'bg-green-50 text-green-700': result.scope === 'local',
                                'bg-indigo-50 text-indigo-700': result.scope === 'international',
                                'bg-cyan-50 text-cyan-700': result.scope === 'online',
                                'bg-gray-100 text-gray-500': !['local', 'international', 'online'].includes(result.scope),
                            }" class="rounded-full px-2 py-0.5 text-[10px] font-bold" x-text="result.scope_label"></span>
                            <span x-show="Number.isInteger(result.relevance_score)" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600" x-text="`${result.relevance_score}% relevance`"></span>
                            <span x-show="result.deadline" class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700" x-text="`Deadline: ${result.deadline}`"></span>
                            <span x-show="result.location" class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700" x-text="result.location"></span>
                        </div>
                        <h4 class="mt-3 text-sm font-black leading-6 text-gray-900" x-text="result.title"></h4>
                        <p class="mt-2 text-xs leading-5 text-gray-600" x-text="result.description"></p>
                        <dl class="mt-3 grid gap-2 text-[11px] leading-4 text-gray-500 md:grid-cols-2">
                            <div>
                                <dt class="font-black uppercase tracking-wider text-gray-400">Event date</dt>
                                <dd class="mt-0.5 font-semibold text-gray-700" x-text="result.event_date || 'Event date not listed'"></dd>
                            </div>
                            <div>
                                <dt class="font-black uppercase tracking-wider text-gray-400">Matched terms</dt>
                                <dd class="mt-0.5 font-semibold text-gray-700" x-text="Array.isArray(result.matched_keywords) && result.matched_keywords.length ? result.matched_keywords.join(', ') : 'No direct keyword match listed'"></dd>
                            </div>
                            <div x-show="result.url">
                                <dt class="font-black uppercase tracking-wider text-gray-400">Source link</dt>
                                <dd class="mt-0.5">
                                    <a :href="result.url" target="_blank" rel="noopener noreferrer" class="font-black text-red-600 hover:text-red-700">Open CFP listing</a>
                                </dd>
                            </div>
                        </dl>
                    </article>
                </template>
            </div>
        </div>
    </section>

    @endhasanyrole

</x-app-layout>
