@props([
    'endpoint',
    'initialQuery' => '',
    'initialContext' => '',
    'canTrack' => false,
    'heading' => 'Find journals for your paper',
    'description' => 'Describe the paper, then review journals that have published related indexed articles.',
])

<section
    id="journal-finder"
    aria-labelledby="journal-finder-heading"
    x-data="journalFinder(@js([
        'endpoint' => $endpoint,
        'query' => $initialQuery,
        'context' => $initialContext,
    ]))"
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-950']) }}
>
    <header class="border-b border-gray-200 px-5 py-5 dark:border-slate-800 sm:px-7">
        <p class="text-sm font-bold uppercase tracking-wider text-red-700 dark:text-red-300">Journal Finder</p>
        <h3 id="journal-finder-heading" class="mt-2 text-xl font-black tracking-tight text-gray-950 dark:text-white">{{ $heading }}</h3>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-gray-600 dark:text-slate-300">{{ $description }}</p>
    </header>

    <div class="space-y-6 p-5 sm:p-7">
        <form @submit.prevent="search()" class="space-y-5">
            <div>
                <label for="journal-search-query" class="block text-sm font-bold text-gray-900 dark:text-white">Paper title, research topic, or keywords</label>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">Use the most specific terms from the manuscript. A complete title usually produces better evidence.</p>
                <input
                    id="journal-search-query"
                    type="search"
                    x-model="query"
                    minlength="3"
                    maxlength="500"
                    placeholder="e.g. Community-based coastal water quality monitoring"
                    class="mt-2 block h-11 w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                >
            </div>

            <div>
                <label for="journal-search-context" class="block text-sm font-bold text-gray-900 dark:text-white">Abstract or additional keywords</label>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-slate-400">Include the method, population, discipline, and setting to improve the recommendations.</p>
                <textarea
                    id="journal-search-context"
                    x-model="context"
                    rows="4"
                    maxlength="6000"
                    placeholder="Paste an abstract, or enter methods and subject-area keywords"
                    class="mt-2 block w-full rounded-xl border-gray-300 text-sm leading-7 shadow-sm focus:border-red-700 focus:ring-red-700 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                ></textarea>
            </div>

            <div class="grid gap-4 rounded-xl bg-gray-50 p-4 dark:bg-slate-900 sm:grid-cols-3 sm:items-end">
                <label for="journal-indexing" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">
                    Scopus preference
                    <select id="journal-indexing" x-model="indexing" class="mt-2 block h-11 w-full rounded-xl border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                        <option value="prefer_scopus">Prefer Scopus-covered journals</option>
                        <option value="scopus_only" @disabled(!filled(config('services.scopus.key')) && !app(\App\Services\ScopusSourceRegistry::class)->metadata())>Scopus sources only</option>
                        <option value="any">All topic matches</option>
                    </select>
                </label>
                <label for="journal-recent-years" class="block text-sm font-semibold text-gray-800 dark:text-slate-100">
                    Evidence period
                    <select id="journal-recent-years" x-model="recentYears" class="mt-2 block h-11 w-full rounded-xl border-gray-300 text-sm dark:border-slate-600 dark:bg-slate-950 dark:text-white">
                        <option value="5">Past 5 years</option>
                        <option value="10">Past 10 years</option>
                        <option value="0">All available years</option>
                    </select>
                </label>
                <label class="flex min-h-11 items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <input type="checkbox" x-model="openAccessOnly" class="h-5 w-5 rounded border-gray-300 text-red-700 focus:ring-red-700">
                    Only show open-access journals
                </label>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button
                    type="submit"
                    :disabled="isLoading || (query.trim().length < 3 && context.trim().length < 3)"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-red-700 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg x-show="!isLoading" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path stroke-linecap="round" d="m20 20-4-4"></path></svg>
                    <svg x-show="isLoading" x-cloak class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                    <span x-text="isLoading ? 'Finding journal matches…' : 'Recommend journals'"></span>
                </button>
                <button type="button" x-show="hasSearched || query || context" @click="clear()" class="min-h-11 rounded-xl px-4 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-100 hover:text-red-700 dark:text-slate-300 dark:hover:bg-slate-900">Clear search</button>
            </div>
        </form>

        <div class="rounded-xl border border-gray-200 p-4 text-sm dark:border-slate-700">
            <p class="font-semibold text-gray-900 dark:text-white">Compare with official journal matchers</p>
            <p class="mt-1 leading-6 text-gray-600 dark:text-slate-300">Paste your title and abstract into Elsevier Journal Finder or Web of Science Manuscript Matcher. Check each journal’s current coverage by ISSN before submitting.</p>
            <div class="mt-3 flex flex-wrap items-center gap-4 font-semibold text-red-700 dark:text-red-300">
                <button type="button" @click="copyManuscript()" :disabled="!query.trim() && !context.trim()" class="hover:underline disabled:opacity-50">Copy title &amp; abstract</button>
                <a href="https://journalfinder.elsevier.com/" target="_blank" rel="noopener noreferrer" class="hover:underline">Elsevier Journal Finder ↗</a>
                <a href="https://mjl.clarivate.com/" target="_blank" rel="noopener noreferrer" class="hover:underline">Web of Science / Manuscript Matcher ↗</a>
                <a href="https://www.scopus.com/sources" target="_blank" rel="noopener noreferrer" class="hover:underline">Scopus Sources ↗</a>
            </div>
            <p x-show="copyMessage" x-cloak x-text="copyMessage" class="mt-2 text-gray-600 dark:text-slate-300" role="status"></p>
        </div>

        <template x-for="warning in warnings" :key="warning">
            <p class="rounded-xl bg-gray-100 p-3 text-sm leading-6 text-gray-700 dark:bg-slate-900 dark:text-slate-200" role="status" x-text="warning"></p>
        </template>

        <div x-show="isLoading" x-cloak class="rounded-xl border border-red-100 bg-red-50 px-5 py-6 text-center dark:border-red-950 dark:bg-red-950/30" role="status" aria-live="polite">
            <p class="text-sm font-bold text-red-900 dark:text-red-100">Reviewing related scholarly articles</p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-300">Grouping their journals and calculating transparent topic-fit signals.</p>
        </div>

        <div x-show="error" x-cloak class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm leading-7 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200" role="alert" x-text="error"></div>

        <div x-show="!hasSearched && !isLoading" class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900/60">
            <p class="text-lg font-bold text-gray-900 dark:text-white">Recommendations will appear here</p>
            <p class="mx-auto mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-slate-300">ATHENA finds related indexed articles, groups the journals where they appeared, and shows the evidence behind every recommendation.</p>
        </div>

        <div x-show="hasSearched && !isLoading && !results.length && !error" x-cloak class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-8 text-center dark:border-slate-700 dark:bg-slate-900">
            <p class="text-lg font-bold text-gray-900 dark:text-white">No journal matches were found</p>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-slate-300">Try a broader paper title, remove the open-access filter, or use fewer specialized terms.</p>
        </div>

        <section x-show="results.length" x-cloak class="space-y-4" aria-labelledby="journal-results-heading">
            <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h4 id="journal-results-heading" class="text-xl font-black text-gray-950 dark:text-white">
                        <span x-text="results.length"></span> recommended journals
                    </h4>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-slate-300">
                        Compared <span class="font-bold" x-text="relatedArticles"></span> distinct articles across multiple OpenAlex searches.
                    </p>
                </div>
                <button type="button" @click="askAthena()" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">Ask Athena to compare</button>
            </div>
            <p class="text-sm text-gray-600 dark:text-slate-300" x-text="'Search terms: ' + keywords.join(', ')"></p>

            <template x-for="(journal, index) in results" :key="journal.id">
                <article class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-950 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-500 dark:text-slate-400" x-text="'Recommendation ' + (index + 1)"></p>
                            <h5 class="mt-1 break-words text-lg font-bold leading-6 text-gray-950 dark:text-white" x-text="journal.name"></h5>
                            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300" x-text="journal.publisher || 'Publisher not listed by OpenAlex'"></p>
                        </div>
                        <span :class="fitClasses(journal.fit_score)" class="inline-flex shrink-0 items-center rounded-full px-3 py-1.5 text-sm font-bold ring-1 ring-inset">
                            <span x-text="journal.fit_score + '/100 · ' + journal.fit_label"></span>
                        </span>
                    </div>
                    <div class="mt-3 space-y-1 text-sm">
                        <p class="font-semibold text-gray-800 dark:text-slate-100" x-text="journal.scopus.label"></p>
                        <p class="text-gray-500 dark:text-slate-400" x-show="journal.scopus.coverage" x-text="'Scopus coverage: ' + journal.scopus.coverage"></p>
                        <p class="text-gray-500 dark:text-slate-400">Coverage follows the dated source list when available. API source records may include past coverage. Check current Scopus and Web of Science coverage before submission.</p>
                        <p x-show="journal.scopus.checked_at" class="text-gray-500 dark:text-slate-400" x-text="'Scopus record checked ' + new Date(journal.scopus.checked_at).toLocaleString()"></p>
                    </div>

                    <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.7fr)]">
                        <div>
                            <h6 class="text-sm font-bold text-gray-900 dark:text-white">Why ATHENA recommended it</h6>
                            <ul class="mt-2 space-y-2 text-sm leading-6 text-gray-700 dark:text-slate-200">
                                <template x-for="reason in journal.reasons" :key="reason">
                                    <li class="flex gap-2">
                                        <svg class="mt-1 h-4 w-4 shrink-0 text-red-700 dark:text-red-300" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"></path></svg>
                                        <span x-text="reason"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 rounded-xl bg-gray-50 p-4 text-sm dark:bg-slate-900">
                            <div>
                                <dt class="font-semibold text-gray-500 dark:text-slate-400">ISSN</dt>
                                <dd class="mt-1 font-bold text-gray-900 dark:text-white" x-text="journal.issn || 'Not listed'"></dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-gray-500 dark:text-slate-400">Open access</dt>
                                <dd class="mt-1 font-bold text-gray-900 dark:text-white" x-text="journal.is_open_access ? 'Yes' : 'Not confirmed'"></dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-gray-500 dark:text-slate-400">Related evidence</dt>
                                <dd class="mt-1 font-bold text-gray-900 dark:text-white" x-text="journal.evidence_count + (journal.evidence_count === 1 ? ' article' : ' articles')"></dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-gray-500 dark:text-slate-400">OpenAlex works</dt>
                                <dd class="mt-1 font-bold text-gray-900 dark:text-white" x-text="formatNumber(journal.works_count)"></dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mt-5 border-t border-gray-200 pt-4 dark:border-slate-800">
                        <h6 class="text-sm font-bold text-gray-900 dark:text-white">Related articles used as evidence</h6>
                        <ul class="mt-2 grid gap-2 text-sm leading-6 text-gray-700 dark:text-slate-200 lg:grid-cols-3">
                            <template x-for="article in journal.sample_articles" :key="article.title">
                                <li class="rounded-xl bg-gray-50 p-3 dark:bg-slate-900">
                                    <a x-show="article.url" :href="article.url" target="_blank" rel="noopener noreferrer" class="font-semibold text-red-700 hover:underline dark:text-red-300" x-text="article.title"></a>
                                    <span x-show="!article.url" class="font-semibold" x-text="article.title"></span>
                                    <span class="mt-1 block text-gray-500 dark:text-slate-400" x-text="article.year || 'Year not listed'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-3">
                        <a x-show="journal.homepage_url" :href="journal.homepage_url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl bg-gray-950 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-800 dark:bg-white dark:text-gray-950">Visit journal website</a>
                        <a x-show="journal.openalex_url" :href="journal.openalex_url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-900">Review OpenAlex record</a>
                        <a :href="journal.scopus.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-sm font-semibold text-red-700 hover:underline dark:text-red-300">Check Scopus coverage ↗</a>
                        <a :href="journal.wos_url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center text-sm font-semibold text-red-700 hover:underline dark:text-red-300">Check Web of Science ↗</a>
                        @if ($canTrack)
                            <button type="button" @click="$dispatch('journal-selected', journal)" class="inline-flex min-h-11 items-center rounded-xl border border-red-200 px-4 py-2.5 text-sm font-bold text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300">Track this journal</button>
                        @endif
                    </div>
                </article>
            </template>

            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <p class="font-bold">Verify before submitting</p>
                <p class="mt-1">Topic fit does not confirm peer-review quality, current indexing, fees, acceptance likelihood, or active calls for papers. Confirm those details on the journal’s official website.</p>
                <p class="mt-1" x-text="methodology"></p>
                <p x-show="checkedAt" class="mt-2 text-amber-800 dark:text-amber-200" x-text="'OpenAlex checked ' + new Date(checkedAt).toLocaleString()"></p>
            </div>
        </section>
    </div>
</section>
