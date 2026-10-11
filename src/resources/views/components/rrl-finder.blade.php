@props([
    'proposalDrafts' => collect(),
    'literatureCollections' => collect(),
    'sharedLiteratureSources' => collect(),
])

@php
    $proposalOptions = $proposalDrafts->map(fn ($draft) => [
        'value' => (string) $draft->id,
        'label' => $draft->project_title,
        'description' => $draft->isOwnedBy(Auth::user()) ? 'Your draft' : 'Shared by '.($draft->owner?->name ?? 'another faculty member'),
    ])->values();
    $collectionOptions = '$store.literatureSearch.collections.map(collection => ({ value: String(collection.id), label: collection.name }))';
@endphp

<section
    id="rrl-finder"
    x-data="{ workspaceMode: window.location.hash === '#shared-literature-library' ? 'library' : 'find', saveOptionsOpen: false, filtersOpen: false, externalSourceOpen: false }"
    x-init="$store.literatureSearch.initializeLibrary($el)"
    @hashchange.window="workspaceMode = window.location.hash === '#shared-literature-library' ? 'library' : 'find'"
    class="athena-readable mb-5 scroll-mt-36 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
    aria-label="Literature search"
    data-rrl-workspace
    data-literature-library-save-url="{{ route('research-support.literature-library.store') }}"
    data-literature-synthesis-url="{{ route('research-support.literature-synthesis') }}"
    data-literature-full-text-preview-url="{{ route('research-support.literature-full-text-preview') }}"
    data-literature-collection-save-url="{{ route('research-support.literature-collections.store') }}"
    data-literature-attach-url-template="{{ route('faculty.proposal-drafts.literature-sources.store', ['proposalDraft' => '__proposal__', 'literatureSource' => '__source__']) }}"
    data-literature-draft-update-url-template="{{ route('faculty.proposal-drafts.literature-drafts.update', ['proposalDraft' => '__proposal__', 'proposalDraftLiteratureSource' => '__link__']) }}"
    data-literature-draft-discard-url-template="{{ route('faculty.proposal-drafts.literature-drafts.destroy', ['proposalDraft' => '__proposal__', 'proposalDraftLiteratureSource' => '__link__']) }}"
    data-detailed-proposal-url-template="{{ route('faculty.proposal-drafts.detailed-proposal.edit', ['proposalDraft' => '__proposal__']) }}"
    data-library-sources="{{ $sharedLiteratureSources->toJson() }}"
    data-library-collections="{{ $literatureCollections->map->only(['id', 'name', 'slug', 'sources_count'])->values()->toJson() }}"
>
    <div class="border-b border-slate-200 px-4 dark:border-slate-800 sm:px-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex min-w-0 gap-3 sm:gap-7" role="tablist" aria-label="Literature workspace">
            <button id="literature-find-tab" type="button" @click="workspaceMode = 'find'" :aria-selected="workspaceMode === 'find'" :class="workspaceMode === 'find' ? 'border-[#7A0019] text-[#7A0019] dark:border-red-300 dark:text-red-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'" class="inline-flex min-h-14 items-center justify-center gap-2 border-b-2 py-3 text-xs font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-red-600" role="tab" aria-controls="literature-find-workspace">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                Find papers
            </button>
            <button id="literature-library-tab" type="button" @click="workspaceMode = 'library'; saveOptionsOpen = false" :aria-selected="workspaceMode === 'library'" :class="workspaceMode === 'library' ? 'border-[#7A0019] text-[#7A0019] dark:border-red-300 dark:text-red-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'" class="inline-flex min-h-14 items-center justify-center gap-2 border-b-2 py-3 text-xs font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-red-600" role="tab" aria-controls="shared-literature-library">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9h7.5M8.25 12h7.5M8.25 15h4.5" /></svg>
                Shared library
                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="$store.literatureSearch.sharedSources.length"></span>
            </button>
            </div>
        </div>
    </div>

    <div x-show="$store.literatureSearch.saveNotice" x-cloak class="mx-4 mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200 sm:mx-5" x-text="$store.literatureSearch.saveNotice" role="status"></div>
    <div x-show="$store.literatureSearch.saveError" x-cloak class="mx-4 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:mx-5" x-text="$store.literatureSearch.saveError" role="alert"></div>

    <div id="literature-save-options" x-show="workspaceMode === 'find' && saveOptionsOpen" x-cloak x-transition class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/55 sm:px-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#7A0019] ring-1 ring-red-100 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900/60">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9h7.5M8.25 12h7.5M8.25 15h4.5" /></svg>
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Organize shared papers</p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"><span x-text="$store.literatureSearch.sharedSources.length"></span> papers</span>
                        </div>
                        <p class="mt-1 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">Choose an optional collection for papers you save. You can use a paper in a proposal without choosing a collection.</p>
                    </div>
                </div>

                <div class="w-full lg:max-w-sm">
                    <div>
                        <label for="literature-collection" class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">Collection</label>
                        <x-literature-picker id="literature-collection" label="Collection" model="$store.literatureSearch.selectedCollectionId" :options-expression="$collectionOptions" placeholder="Shared library only" search-placeholder="Search collections" />
                    </div>

                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-center">
                <label for="new-literature-collection" class="sr-only">New shared collection name</label>
                <input id="new-literature-collection" type="text" maxlength="120" x-model="$store.literatureSearch.collectionName" @keydown.enter.prevent="$store.literatureSearch.createCollection()" placeholder="Create a collection, e.g. Environment" class="block h-10 min-w-0 flex-1 rounded-xl border-slate-200 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
                <button type="button" @click="$store.literatureSearch.createCollection()" :disabled="$store.literatureSearch.isCreatingCollection || !$store.literatureSearch.collectionName.trim()" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-[10px] font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                    <span x-text="$store.literatureSearch.isCreatingCollection ? 'Creating…' : 'Create collection'"></span>
                </button>
            </div>
            <p x-show="$store.literatureSearch.collectionError" x-cloak class="mt-2 text-[11px] font-semibold text-red-600 dark:text-red-300" x-text="$store.literatureSearch.collectionError"></p>
        </div>
    </div>

    <div id="literature-find-workspace" x-show="workspaceMode === 'find'" class="border-b border-slate-200 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-950/20 sm:p-7" role="tabpanel" aria-labelledby="literature-find-tab">
        <div class="mb-6">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Find literature for your research</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Search by topic, research question, or paper title. Read the evidence, then save what supports your study.</p>
        </div>
        <form @submit.prevent="$store.literatureSearch.search()" class="space-y-4">
            <div class="rounded-2xl border border-slate-300 bg-white p-2 shadow-sm transition-colors focus-within:border-[#7A0019] focus-within:ring-2 focus-within:ring-red-100 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:border-red-400 dark:focus-within:ring-red-950/40">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3 px-2 sm:px-3">
                        <svg class="h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        <div class="min-w-0 flex-1">
                            <label for="literature-search-query" class="sr-only">Search literature</label>
                            <input
                                id="literature-search-query"
                                type="search"
                                x-model="$store.literatureSearch.query"
                                maxlength="180"
                                placeholder="e.g. Community participation in mangrove restoration"
                                class="block h-12 w-full border-0 bg-transparent px-0 text-base text-slate-900 shadow-none placeholder:text-slate-400 focus:border-0 focus:ring-0 dark:text-white dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="$store.literatureSearch.isLoading || $store.literatureSearch.query.trim().length < 3"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-red-700 px-6 text-sm font-semibold text-white transition-colors hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 dark:bg-red-700 dark:hover:bg-red-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-400 dark:focus:ring-offset-slate-950"
                    >
                        <svg x-show="$store.literatureSearch.isLoading" x-cloak class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="$store.literatureSearch.isLoading ? 'Searching…' : 'Search'"></span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                <button type="button" @click="filtersOpen = !filtersOpen" :aria-expanded="filtersOpen.toString()" aria-controls="literature-search-filters" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-1 text-sm font-medium text-slate-600 hover:text-[#7A0019] focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-300 dark:hover:text-red-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M5 7h14M8 12h8M10 17h4" /></svg>
                    Filters <span x-show="$store.literatureSearch.activeFilterCount()" x-cloak class="rounded-full bg-red-50 px-2 text-xs text-red-800 dark:bg-red-950 dark:text-red-200" x-text="$store.literatureSearch.activeFilterCount()"></span>
                    <span aria-hidden="true" x-text="filtersOpen ? '−' : '+'"></span>
                </button>
                <button type="button" @click="externalSourceOpen = !externalSourceOpen" :aria-expanded="externalSourceOpen.toString()" aria-controls="literature-external-source" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-1 text-sm font-medium text-slate-600 hover:text-[#7A0019] focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-300 dark:hover:text-red-300">
                    Add a source <span aria-hidden="true" x-text="externalSourceOpen ? '−' : '+'"></span>
                </button>
            </div>

            <div id="literature-search-filters" x-show="filtersOpen" x-cloak class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/60">

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="literature-search-year-from" class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">From year</label>
                    <input id="literature-search-year-from" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_from" placeholder="Any year" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-year-to" class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-300">To year</label>
                    <input id="literature-search-year-to" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_to" placeholder="{{ now()->year }}" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-min-citations" class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-300">Minimum citations</label>
                    <input id="literature-search-min-citations" type="number" min="0" max="1000000" x-model.number="$store.literatureSearch.filters.min_citations" placeholder="No minimum" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <label class="flex min-h-11 items-center gap-3 self-end rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-bold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <input type="checkbox" x-model="$store.literatureSearch.filters.open_access" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-slate-600">
                    <span>Open access only</span>
                </label>
                </div>

                <button type="button" @click="$store.literatureSearch.filters = { year_from: '', year_to: '', min_citations: '', open_access: false }" class="mt-3 inline-flex min-h-9 items-center rounded-lg px-1 text-xs font-medium text-slate-500 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:text-red-300">Clear filters</button>
            </div>
        </form>

        <div id="literature-external-source" x-show="externalSourceOpen" x-cloak class="mt-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
            <p class="max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Add a paper from another service, or paste its citation to fill in the details.</p>
            <form class="mt-4 space-y-4" @submit.prevent="$store.literatureSearch.saveExternalSource()">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Found via<select x-model="$store.literatureSearch.externalSource.provider" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-xs font-bold text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-white"><option>Google Scholar</option><option>Elicit</option><option>Other</option></select></label>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 sm:col-span-2 lg:col-span-3">Paper title<input x-model="$store.literatureSearch.externalSource.title" required maxlength="500" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 sm:col-span-2">Authors<input x-model="$store.literatureSearch.externalSource.authors" maxlength="2000" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Year<input x-model="$store.literatureSearch.externalSource.year" type="number" min="1900" max="{{ now()->year }}" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Venue<input x-model="$store.literatureSearch.externalSource.venue" maxlength="500" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Enter authors in given-name surname order, separated by commas. Add the publication details below to complete the IEEE reference.</p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach (['volume' => 'Volume', 'issue' => 'Issue / number', 'pages' => 'Pages (e.g. 15-24)', 'publisher' => 'Publisher'] as $field => $label)
                        <label class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ $label }}
                            <input x-model="$store.literatureSearch.externalSource.{{ $field }}" maxlength="{{ $field === 'publisher' ? 500 : 100 }}" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        </label>
                    @endforeach
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">DOI<input x-model="$store.literatureSearch.externalSource.doi" maxlength="255" placeholder="10.xxxx/example" class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                    <label class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Public source URL<input x-model="$store.literatureSearch.externalSource.url" type="url" maxlength="2048" placeholder="https://..." class="mt-1 block h-10 w-full rounded-lg border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"></label>
                </div>
                <label class="block text-[10px] font-semibold text-slate-500 dark:text-slate-400">Paste citation, BibTeX, or RIS <span class="normal-case font-medium tracking-normal">(optional; fields are filled when recognizable)</span><textarea x-model="$store.literatureSearch.externalSource.citation" @blur="$store.literatureSearch.parseExternalCitation()" rows="5" maxlength="10000" class="mt-1 block w-full rounded-lg border-slate-200 bg-white text-xs leading-5 dark:border-slate-700 dark:bg-slate-900 dark:text-white"></textarea></label>
                <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-[10px] leading-4 text-slate-500 dark:text-slate-400">Save for faculty to reuse, then choose Add to proposal from the shared library when needed.</p><button type="submit" :disabled="$store.literatureSearch.isSavingExternalSource" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-black disabled:opacity-50 dark:bg-white dark:text-slate-900"><span x-text="$store.literatureSearch.isSavingExternalSource ? 'Saving...' : 'Save to shared library'"></span></button></div>
            </form>
        </div>
        <div x-show="$store.literatureSearch.externalReference" x-cloak class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950" role="status">
            <label for="manual-ieee-reference" class="text-sm font-bold text-slate-800 dark:text-white">IEEE reference</label>
            <textarea id="manual-ieee-reference" readonly rows="4" x-bind:value="$store.literatureSearch.externalReference" @focus="$el.select()" class="mt-2 w-full rounded-lg border-slate-300 bg-white text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Select to copy. Verify the publication details. ATHENA assigns [1], [2], and subsequent numbers when you cite the source in your proposal.</p>
        </div>
    </div>

    <section id="shared-literature-library" x-show="workspaceMode === 'library'" x-cloak class="p-5 sm:p-7" aria-labelledby="literature-library-tab" role="tabpanel">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 id="shared-literature-heading" class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Browse the shared library</h3>
                </div>
                <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Find and reuse papers saved by faculty.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_14rem] xl:w-[32rem]">
                <label class="relative block">
                    <span class="sr-only">Search the shared literature library</span>
                    <svg class="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                    <input type="search" x-model.debounce.200ms="$store.literatureSearch.libraryQuery" placeholder="Search saved papers" class="block h-11 w-full rounded-xl border-slate-200 bg-white pl-10 text-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
                </label>
                <x-literature-picker id="library-literature-collection" label="Filter shared papers by collection" model="$store.literatureSearch.libraryCollectionFilter" :options-expression="$collectionOptions" placeholder="All collections" search-placeholder="Search collections" described-by="library-collection-help" />
            </div>
        </div>
        <p id="library-collection-help" class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Collections group shared papers by topic. Choose one to narrow the library, or browse all papers.</p>

        <div class="mt-6 flex flex-col gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/50 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <label for="library-literature-proposal" class="text-sm font-semibold text-slate-800 dark:text-slate-100">Use papers in a proposal</label>
                <p id="library-proposal-help" class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                    @if ($proposalDrafts->isEmpty())
                        Create a proposal draft to use saved papers in your RRL or references.
                    @else
                        Choose a draft to add a source or prepare a reviewed RRL paragraph. References are added when you cite the source.
                    @endif
                </p>
            </div>
            <div class="w-full min-w-0 lg:w-96 lg:shrink-0">
                <x-literature-picker id="library-literature-proposal" label="Proposal to use shared papers with" model="$store.literatureSearch.selectedProposalId" :options="$proposalOptions" :placeholder="$proposalDrafts->isEmpty() ? 'No editable proposal' : 'Choose a proposal'" search-placeholder="Search titles or owners" :disabled="$proposalDrafts->isEmpty()" described-by="library-proposal-help" />
            </div>
        </div>

        <div x-show="$store.literatureSearch.filteredLibrarySources().length === 0" class="mt-6 rounded-xl bg-slate-50 px-5 py-12 text-center dark:bg-slate-950/40">
            <svg class="mx-auto mb-4 h-8 w-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75ZM8.25 9h7.5M8.25 12h7.5M8.25 15h4.5" /></svg>
            <p class="text-base font-semibold text-slate-800 dark:text-slate-100" x-text="$store.literatureSearch.sharedSources.length ? 'No shared papers match these filters' : 'The shared library is empty' "></p>
            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="$store.literatureSearch.sharedSources.length ? 'Try a different title, author, or collection.' : 'Find a paper first, then save it here for everyone to reuse.'"></p>
            <button x-show="!$store.literatureSearch.sharedSources.length" type="button" @click="workspaceMode = 'find'" class="mt-5 min-h-11 rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 dark:bg-red-700 dark:hover:bg-red-600">Find new papers</button>
        </div>

        <div x-show="$store.literatureSearch.filteredLibrarySources().length" x-cloak class="mt-6">
            <p class="mb-2 text-xs font-medium tabular-nums text-slate-500 dark:text-slate-400" aria-live="polite" x-text="`${$store.literatureSearch.filteredLibrarySources().length} papers available`"></p>
            <div class="divide-y divide-slate-200 dark:divide-slate-800">
            <template x-for="source in $store.literatureSearch.filteredLibrarySources()" :key="`library-${source.id}`">
                <article class="flex min-w-0 flex-col gap-4 py-5 xl:flex-row xl:items-center xl:gap-6">
                    <div class="flex min-w-0 flex-1 items-start gap-4">
                        <span class="hidden h-12 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 sm:flex" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h12A.75.75 0 0 1 18.75 4.5v15a.75.75 0 0 1-.75.75H6a.75.75 0 0 1-.75-.75v-15A.75.75 0 0 1 6 3.75ZM8.5 8h7M8.5 12h7M8.5 16h4" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h4 class="break-words text-base font-semibold leading-7 tracking-tight text-slate-900 dark:text-white" x-text="source.title"></h4>
                            <p class="mt-1 break-words text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="source.authors || 'Authors not listed'"></p>
                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-medium tabular-nums" x-text="source.year || 'Year not listed'"></span>
                                <span class="h-3 w-px bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
                                <template x-for="collection in source.collections || []" :key="`${source.id}-${collection.id}`">
                                    <span class="rounded-md bg-red-50 px-2 py-1 font-medium text-[#7A0019] dark:bg-red-950/30 dark:text-red-200" x-text="collection.name"></span>
                                </template>
                                <span x-show="!source.collections?.length">Unfiled</span>
                                <span class="basis-full sm:basis-auto" x-text="`Added by ${source.added_by_name || 'a faculty member'}`"></span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 xl:w-80 xl:shrink-0">
                        <button type="button" @click="$store.literatureSearch.useLibrarySource(source, 'reference')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingLibrarySource(source)" class="min-h-11 rounded-lg bg-red-700 px-2 py-2 text-xs font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:bg-red-700 dark:hover:bg-red-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-400">Add to proposal</button>
                        <button type="button" @click="$store.literatureSearch.useLibrarySource(source, 'rrl')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingLibrarySource(source)" class="min-h-11 rounded-lg border border-slate-200 px-2 py-2 text-xs font-semibold text-slate-700 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:border-transparent disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:disabled:bg-slate-800 dark:disabled:text-slate-400">Draft RRL</button>
                        <a x-show="source.url" :href="source.url" target="_blank" rel="noopener noreferrer" class="col-span-2 inline-flex min-h-11 items-center justify-center rounded-lg px-2 py-2 text-xs font-semibold text-red-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:text-red-200">Read publication</a>
                    </div>
                </article>
            </template>
            </div>
        </div>
    </section>

    <div x-show="workspaceMode === 'find'">
    <div x-show="$store.literatureSearch.error" x-cloak class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:m-5">
        <p class="font-semibold">Search unavailable</p>
        <p class="mt-1" x-text="$store.literatureSearch.error"></p>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.providerNotice && $store.literatureSearch.results.length" x-cloak class="m-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300 sm:m-5">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 12 10.5m0 6.75h.008v.008H12v-.008Zm9-5.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        <p><span class="font-semibold text-slate-700 dark:text-slate-200">Source coverage:</span> <span x-text="$store.literatureSearch.providerNotice"></span></p>
    </div>

    <p x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.queryGuidance?.is_broad && !$store.literatureSearch.isLoading" x-cloak class="mx-5 my-4 text-sm leading-6 text-slate-600 dark:text-slate-400 sm:mx-7" role="status" data-literature-query-hint>
        <span x-text="$store.literatureSearch.queryGuidance?.message"></span>
        <span x-text="$store.literatureSearch.queryGuidance?.suggestion"></span>
    </p>

    <div x-show="$store.literatureSearch.isLoading" x-cloak class="flex items-center gap-3 px-5 py-8 sm:px-6" role="status" aria-live="polite" aria-label="Searching academic sources" data-literature-loading>
        <svg class="h-5 w-5 shrink-0 animate-spin text-[#7A0019] motion-reduce:animate-none dark:text-red-300" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" /><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <p class="text-sm text-slate-600 dark:text-slate-300">Searching academic papers…</p>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading && !$store.literatureSearch.results.length && !$store.literatureSearch.error && !$store.literatureSearch.queryGuidance?.is_broad" x-cloak class="p-4 sm:p-5">
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center dark:border-slate-800 dark:bg-slate-950/40">
            <p class="text-sm font-semibold text-gray-800 dark:text-slate-100">No literature records found</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Try a broader phrase, a key variable, or a shorter version of the working title.</p>
        </div>
    </div>

    <div x-show="$store.literatureSearch.results.length && !$store.literatureSearch.isLoading" x-cloak data-rrl-results-workspace>
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white"><span x-text="$store.literatureSearch.results.length"></span> papers found</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="saveOptionsOpen = !saveOptionsOpen; if (saveOptionsOpen) $nextTick(() => document.getElementById('literature-save-options')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }))" :aria-expanded="saveOptionsOpen.toString()" aria-controls="literature-save-options" class="min-h-11 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:border-red-200 hover:bg-red-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/30">Collections</button>
                <button type="button" @click="$store.literatureSearch.clear()" class="min-h-11 rounded-lg px-3 py-2 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50 hover:text-[#7A0019] focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-300">Clear</button>
                <button type="button" @click="$store.literatureSearch.askAthena()" :disabled="$store.researchAssistant.isLoading" class="min-h-11 rounded-lg bg-red-700 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-red-700 dark:hover:bg-red-600">Analyze results</button>
            </div>
        </div>

        <div class="grid lg:grid-cols-[minmax(18rem,0.75fr)_minmax(0,1.25fr)]">
            <div class="min-w-0 border-b border-slate-200 bg-slate-50/60 dark:border-slate-800 dark:bg-slate-950/35 lg:border-b-0 lg:border-r" data-rrl-results-table>
                <p class="px-5 py-3 text-xs font-medium text-slate-500 dark:text-slate-400">Select a paper to read its details</p>
                <ol class="max-h-[26rem] overflow-y-auto px-2 pb-2 lg:max-h-[calc(100dvh-12rem)]" aria-label="Search results">
                        <template x-for="(result, index) in $store.literatureSearch.results" :key="`${result.source}-${result.doi || result.url || result.title}`">
                            <li class="mb-1">
                                <button type="button"
                                    @click="$store.literatureSearch.selectResult(index)"
                                    :aria-pressed="$store.literatureSearch.selectedIndex === index"
                                    aria-controls="literature-selected-paper"
                                    :class="$store.literatureSearch.selectedIndex === index ? 'border-[#7A0019] bg-white dark:border-red-400 dark:bg-slate-800' : 'border-transparent hover:bg-white dark:hover:bg-slate-800/60'"
                                    class="block w-full rounded-r-xl border-l-2 p-4 text-left transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-red-600"
                                >
                                    <span class="flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                                        <span class="font-medium" x-text="result.source"></span>
                                        <span class="tabular-nums" x-text="result.year || 'Year not listed'"></span>
                                    </span>
                                    <span class="mt-2 block break-words text-sm font-semibold leading-6 text-slate-900 dark:text-white" x-text="result.title"></span>
                                    <span class="mt-1 block line-clamp-1 text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="result.authors || 'Authors not listed'"></span>
                                    <span class="mt-2 block line-clamp-2 text-xs leading-5 text-slate-600 dark:text-slate-300" x-text="result.description"></span>
                                    <span class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        <span class="font-medium tabular-nums" x-text="Number.isInteger(result.citation_count) ? `${result.citation_count} citations` : 'Citations not listed'"></span>
                                        <span x-text="$store.literatureSearch.accessLabel(result)"></span>
                                    </span>
                                </button>
                            </li>
                        </template>
                </ol>
            </div>

            <aside id="literature-selected-paper" class="min-w-0 bg-white p-5 dark:bg-slate-900 sm:p-7 lg:max-h-[calc(100dvh-9rem)] lg:overflow-y-auto" aria-label="Selected paper details" data-rrl-paper-details>
                <div x-show="$store.literatureSearch.selectedResult()" x-cloak>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-xs font-medium text-gray-500 dark:text-slate-400">Paper details</p>
                        <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-medium text-[#7A0019] dark:bg-red-950/40 dark:text-red-300" x-text="$store.literatureSearch.selectedResult()?.relevance_label || 'Potential match'"></span>
                    </div>
                    <h4 class="mt-4 break-words text-xl font-semibold leading-8 tracking-tight text-slate-950 dark:text-white" x-text="$store.literatureSearch.selectedResult()?.title"></h4>
                    <p class="mt-3 break-words text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="$store.literatureSearch.selectedResult()?.authors || 'Authors not listed'"></p>
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-medium" x-text="$store.literatureSearch.selectedResult()?.source"></span>
                        <span class="tabular-nums" x-text="$store.literatureSearch.selectedResult()?.year || 'Year not listed'"></span>
                        <span class="tabular-nums" x-text="Number.isInteger($store.literatureSearch.selectedResult()?.citation_count) ? `${$store.literatureSearch.selectedResult().citation_count} citations` : 'Citations not listed'"></span>
                    </div>

                    <div
                        x-data="{ abstractExpanded: true }"
                        x-init="$watch(() => $store.literatureSearch.selectedIndex, () => abstractExpanded = true)"
                        class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800"
                    >
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Abstract</p>
                        <p
                            class="mt-3 break-words text-sm leading-7 text-slate-600 dark:text-slate-300"
                            :class="abstractExpanded ? '' : 'line-clamp-12'"
                            x-text="$store.literatureSearch.selectedResult()?.description"
                        ></p>
                        <button
                            type="button"
                            x-show="String($store.literatureSearch.selectedResult()?.description || '').length > 720"
                            @click="abstractExpanded = !abstractExpanded"
                            :aria-expanded="abstractExpanded.toString()"
                            class="mt-3 inline-flex min-h-9 items-center rounded-lg border border-slate-200 bg-white px-3 text-[11px] font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30 dark:hover:text-red-200"
                        >
                            <span x-text="abstractExpanded ? 'Show less' : 'Show full abstract'"></span>
                        </button>
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/50">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500">Access status</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white" x-text="$store.literatureSearch.accessLabel($store.literatureSearch.selectedResult())"></p>
                            </div>
                            <button type="button" x-show="$store.literatureSearch.selectedResult()?.access_status === 'open_access' && $store.literatureSearch.selectedResult()?.full_text_token" @click="$store.literatureSearch.loadFullText()" :disabled="$store.literatureSearch.isLoadingFullText" class="inline-flex min-h-10 items-center rounded-xl bg-emerald-700 px-4 text-xs font-semibold text-white transition hover:bg-emerald-800 disabled:opacity-50" x-text="$store.literatureSearch.isLoadingFullText ? 'Loading public text...' : ($store.literatureSearch.fullTextPreview ? 'Reload available full text' : 'Load available full text')"></button>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300" x-text="$store.literatureSearch.accessExplanation($store.literatureSearch.selectedResult())"></p>
                        <p x-show="$store.literatureSearch.fullTextError" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs font-semibold leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" x-text="$store.literatureSearch.fullTextError"></p>
                    </div>

                    <div class="mt-5 rounded-xl bg-slate-50 p-4 dark:bg-slate-950/40">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Use in a proposal</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Adding saves this source to the proposal. Cite it while writing to add its numbered reference, or draft an RRL paragraph for review.</p>
                        </div>

                        @if ($proposalDrafts->isNotEmpty())
                            <label for="detail-literature-proposal" class="mt-4 block text-xs font-bold text-slate-700 dark:text-slate-200">Proposal</label>
                            <x-literature-picker id="detail-literature-proposal" label="Proposal" model="$store.literatureSearch.selectedProposalId" :options="$proposalOptions" placeholder="Choose a proposal" search-placeholder="Search titles or owners" class="mt-1.5" />
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <button type="button" @click="$store.literatureSearch.saveResult($store.literatureSearch.selectedResult(), 'reference')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="min-h-11 rounded-xl bg-red-700 px-3.5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-500 dark:bg-red-700 dark:hover:bg-red-600 dark:disabled:bg-slate-800 dark:disabled:text-slate-400">Add to proposal</button>
                                <button type="button" @click="$store.literatureSearch.prepareSynthesis($store.literatureSearch.selectedResult())" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="min-h-11 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-slate-800 transition hover:border-red-200 hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:disabled:border-slate-800 dark:disabled:bg-slate-800 dark:disabled:text-slate-400 dark:text-slate-100 dark:hover:border-red-900 dark:hover:bg-red-950/30">Draft RRL</button>
                            </div>
                        @endif

                        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Read or save for later</p>
                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <button type="button" @click="$store.literatureSearch.saveResult($store.literatureSearch.selectedResult())" :disabled="$store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="inline-flex min-h-11 items-center justify-center gap-1.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" /></svg><span x-text="$store.literatureSearch.isSavedResult($store.literatureSearch.selectedResult()) ? 'Saved to shared library' : 'Save to shared library'"></span></button>
                                <a x-show="$store.literatureSearch.selectedResult()?.url" :href="$store.literatureSearch.selectedResult()?.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center gap-1.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-red-800 transition hover:bg-red-50 dark:border-slate-700 dark:bg-slate-950 dark:text-red-200 dark:hover:bg-red-950/30">Read publication<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18m0 0v4.5M18 6l-7.5 7.5M6 7.5v10.125A1.875 1.875 0 0 0 7.875 19.5H18" /></svg></a>
                            </div>
                        </div>
                    </div>

                    <div x-data="{ publicationDetailsOpen: false }" class="mt-5 border-y border-slate-200 py-2 dark:border-slate-800">
                    <button type="button" @click="publicationDetailsOpen = !publicationDetailsOpen" :aria-expanded="publicationDetailsOpen.toString()" aria-controls="literature-publication-details" class="inline-flex min-h-11 items-center gap-2 rounded-lg text-sm font-medium text-slate-600 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-300 dark:hover:text-red-200">Publication details <span aria-hidden="true" x-text="publicationDetailsOpen ? '−' : '+'"></span></button>
                    <dl id="literature-publication-details" x-show="publicationDetailsOpen" x-cloak class="grid grid-cols-2 gap-3 pb-3 pt-2 text-xs">
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Year</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.year || 'Not listed'"></dd></div>
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Citations</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="Number.isInteger($store.literatureSearch.selectedResult()?.citation_count) ? $store.literatureSearch.selectedResult().citation_count : 'Not listed'"></dd></div>
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Relevance</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.relevance_label || 'Potential match'"></dd></div>
                        <div class="col-span-2"><dt class="font-semibold text-gray-400 dark:text-slate-500">Publication</dt><dd class="mt-1 font-bold leading-5 text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.venue || 'Source venue not listed'"></dd></div>
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Type</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.type || 'Not listed'"></dd></div>
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Volume / issue</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="[$store.literatureSearch.selectedResult()?.volume, $store.literatureSearch.selectedResult()?.issue].filter(Boolean).join(' / ') || 'Not listed'"></dd></div>
                        <div><dt class="font-semibold text-gray-400 dark:text-slate-500">Pages</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.pages || 'Not listed'"></dd></div>
                        <div class="col-span-2"><dt class="font-semibold text-gray-400 dark:text-slate-500">Publisher</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.publisher || 'Not listed'"></dd></div>
                    </dl>
                    </div>

                    <p class="mt-3 text-[10px] font-semibold leading-4 text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.selectedResult()?.match_reason"></p>

                    <div x-show="$store.literatureSearch.selectedResult()?.doi" class="mt-5">
                        <p class="text-[10px] font-semibold text-gray-400 dark:text-slate-500">DOI</p>
                        <p class="mt-1 break-all text-[11px] font-semibold text-gray-600 dark:text-slate-300" x-text="$store.literatureSearch.selectedResult()?.doi"></p>
                    </div>

                    <div x-show="$store.literatureSearch.fullTextPreview" x-cloak class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900 dark:bg-emerald-950/20">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[10px] font-semibold text-emerald-800 dark:text-emerald-200">Loaded open-access full-text preview</p>
                            <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-300" x-text="$store.literatureSearch.fullTextContentType"></span>
                        </div>
                        <p class="mt-2 text-xs font-semibold leading-5 text-emerald-900 dark:text-emerald-100" x-text="$store.literatureSearch.fullTextNotice"></p>
                        <div class="mt-3 max-h-[32rem] overflow-y-auto rounded-xl border border-emerald-100 bg-white p-4 dark:border-emerald-900 dark:bg-slate-950">
                            <p class="whitespace-pre-wrap text-sm leading-7 text-slate-700 dark:text-slate-200" x-text="$store.literatureSearch.fullTextPreview"></p>
                        </div>
                        <a x-show="$store.literatureSearch.fullTextSourceUrl" :href="$store.literatureSearch.fullTextSourceUrl" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-semibold text-emerald-800 underline dark:text-emerald-200">Open public source</a>
                    </div>

                </div>
            </aside>
        </div>
    </div>
    </div>

    <template x-teleport="body">
        <div
            x-show="$store.literatureSearch.synthesisReviewOpen"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="$store.literatureSearch.closeSynthesisReview()"
            class="fixed inset-0 z-[105] flex items-center justify-center bg-slate-950/70 p-3 backdrop-blur-sm sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="literature-synthesis-title"
        >
            <button type="button" @click="$store.literatureSearch.closeSynthesisReview()" class="absolute inset-0 cursor-default" aria-label="Close RRL preparation dialog"></button>

            <div class="relative z-10 flex max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:px-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id="literature-synthesis-title" class="text-lg font-semibold text-slate-950 dark:text-white">Review your RRL draft</h2>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200" x-text="$store.literatureSearch.synthesisBasis === 'full_text' ? 'Loaded open-access full text' : 'Indexed abstract only'"></span>
                            <span class="sr-only">Abstract only</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Review the evidence and edit the paragraph before inserting it with its citation and reference. Abstract-based drafts require checking the full paper for methods and limitations.</p>
                    </div>
                    <button type="button" @click="$store.literatureSearch.closeSynthesisReview()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close RRL preparation dialog">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </header>

                <div class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:overflow-hidden">
                    <section class="border-b border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-950/45 lg:overflow-y-auto lg:border-b-0 lg:border-r sm:p-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Source evidence</p>
                        <h3 class="mt-3 text-base font-semibold leading-6 text-slate-950 dark:text-white" x-text="$store.literatureSearch.synthesisSource?.title || 'Selected paper'"></h3>
                        <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            <span x-text="$store.literatureSearch.synthesisSource?.authors || 'Authors not listed'"></span>
                            <span aria-hidden="true"> &middot; </span>
                            <span x-text="$store.literatureSearch.synthesisSource?.year || 'Year not listed'"></span>
                        </p>

                        <div x-show="$store.literatureSearch.fullTextPreview && $store.literatureSearch.fullTextResultKey === $store.literatureSearch.resultKey($store.literatureSearch.synthesisSource)" x-cloak class="mt-4 inline-flex rounded-xl bg-slate-200/80 p-1 dark:bg-slate-800" aria-label="RRL evidence basis">
                            <button type="button" @click="$store.literatureSearch.setSynthesisBasis('abstract')" :class="$store.literatureSearch.synthesisBasis === 'abstract' ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white' : 'text-slate-500 dark:text-slate-400'" class="rounded-lg px-3 py-2 text-[10px] font-semibold">Use abstract</button>
                            <button type="button" @click="$store.literatureSearch.setSynthesisBasis('full_text')" :class="$store.literatureSearch.synthesisBasis === 'full_text' ? 'bg-white text-emerald-800 shadow-sm dark:bg-slate-950 dark:text-emerald-200' : 'text-slate-500 dark:text-slate-400'" class="rounded-lg px-3 py-2 text-[10px] font-semibold">Use loaded full text</button>
                        </div>

                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100" x-text="$store.literatureSearch.synthesisBasis === 'full_text' ? 'Transient open-access full-text evidence' : 'Indexed abstract evidence'"></p>
                            <p x-show="$store.literatureSearch.hasSynthesisEvidence()" class="mt-3 max-h-[34rem] overflow-y-auto whitespace-pre-wrap text-sm leading-7 text-slate-600 dark:text-slate-300" x-text="$store.literatureSearch.synthesisEvidenceText"></p>
                            <p x-show="!$store.literatureSearch.hasSynthesisEvidence()" class="mt-3 text-sm leading-6 text-amber-800 dark:text-amber-200">No usable abstract was returned by the academic indexes. ATHENA will not generate claims from metadata alone.</p>
                        </div>

                        <div class="mt-4 flex gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-900 dark:border-blue-900 dark:bg-blue-950/35 dark:text-blue-200">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 12 10.5m0 0 .75-.75M12 10.5v4.125m0 6.375a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" /></svg>
                            <p>The DOI and source link stay with the evidence record and reference entry. They will not be inserted into the RRL paragraph.</p>
                        </div>
                    </section>

                    <section class="flex min-h-[28rem] flex-col p-5 dark:bg-slate-900 lg:overflow-y-auto sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <label for="literature-synthesis-draft" class="text-sm font-semibold text-slate-950 dark:text-white">Editable research notes</label>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Revise the wording so it fits your study. Do not treat this as a direct quotation.</p>
                            </div>
                            <button type="button" x-show="$store.literatureSearch.hasSynthesisEvidence()" @click="$store.literatureSearch.generateSynthesis()" :disabled="$store.literatureSearch.isSynthesizing" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3.5 text-xs font-semibold text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                                <svg :class="$store.literatureSearch.isSynthesizing ? 'animate-spin' : ''" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 1 12.8-5.3L19.5 9M19.5 4.5V9H15M19.5 12a7.5 7.5 0 0 1-12.8 5.3L4.5 15M4.5 19.5V15H9" /></svg>
                                <span x-text="$store.literatureSearch.isSynthesizing ? 'Generating...' : ($store.literatureSearch.synthesisDraft ? 'Generate again' : 'Generate draft')"></span>
                            </button>
                        </div>

                        <div x-show="$store.literatureSearch.isSynthesizing && !$store.literatureSearch.synthesisDraft" class="mt-4 flex min-h-56 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-red-200 bg-red-50/60 p-6 text-center dark:border-red-900 dark:bg-red-950/20" role="status">
                            <span class="h-8 w-8 animate-spin rounded-full border-2 border-red-200 border-t-red-700 dark:border-red-950 dark:border-t-red-300"></span>
                            <p class="mt-4 text-sm font-semibold text-red-900 dark:text-red-100">Preparing editable research notes</p>
                            <p class="mt-1 text-xs leading-5 text-red-700 dark:text-red-300">ATHENA is constrained to the evidence shown on the left.</p>
                        </div>

                        <textarea
                            id="literature-synthesis-draft"
                            x-show="!$store.literatureSearch.isSynthesizing || $store.literatureSearch.synthesisDraft"
                            x-model="$store.literatureSearch.synthesisDraft"
                            rows="12"
                            maxlength="5000"
                            placeholder="Generate or write an RRL paragraph from the available evidence, then review it before inserting it with its reference."
                            class="mt-4 min-h-64 w-full flex-1 resize-y rounded-2xl border-slate-300 bg-white p-4 text-sm leading-7 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
                        ></textarea>

                        <p x-show="$store.literatureSearch.synthesisDraft.trim().length < 40" class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400" role="status">Generate a draft or write at least 40 characters to enable adding it to the RRL and references.</p>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <p class="font-medium text-slate-500 dark:text-slate-400"><span x-text="$store.literatureSearch.synthesisWordCount()"></span> words <span aria-hidden="true">&middot;</span> Review required before saving the proposal</p>
                            <span x-show="$store.literatureSearch.synthesisBasis === 'abstract'" class="rounded-full bg-amber-100 px-2.5 py-1 font-semibold text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Abstract-based</span>
                            <span x-show="$store.literatureSearch.synthesisBasis === 'full_text'" class="rounded-full bg-emerald-100 px-2.5 py-1 font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200">Loaded full-text based</span>
                        </div>

                        <p x-show="$store.literatureSearch.synthesisNotice" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs font-semibold leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" x-text="$store.literatureSearch.synthesisNotice" role="status"></p>
                        <p x-show="$store.literatureSearch.synthesisError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="$store.literatureSearch.synthesisError" role="alert"></p>

                        <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:flex-wrap sm:justify-end">
                            <button type="button" @click="$store.literatureSearch.closeSynthesisReview()" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                            <button type="button" @click="$store.literatureSearch.discardSynthesisDraft()" :disabled="$store.literatureSearch.isDiscardingDraft || !$store.literatureSearch.synthesisDraft" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-red-200 px-4 text-sm font-bold text-red-700 transition hover:bg-red-50 disabled:opacity-40 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30">Discard draft</button>
                            <button type="button" @click="$store.literatureSearch.persistSynthesisDraft('draft')" :disabled="$store.literatureSearch.isSavingDraft || $store.literatureSearch.synthesisDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" x-text="$store.literatureSearch.isSavingDraft ? 'Saving...' : 'Save draft'"></button>
                            <button type="button" @click="$store.literatureSearch.confirmSynthesis()" :disabled="$store.literatureSearch.isSynthesizing || $store.literatureSearch.isSavingDraft || $store.literatureSearch.isSavingResult($store.literatureSearch.synthesisSource) || $store.literatureSearch.synthesisDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-red-700 dark:hover:bg-red-600">
                                <span>Insert reviewed RRL</span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </template>
</section>
