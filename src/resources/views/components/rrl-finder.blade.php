@props([
    'proposalDrafts' => collect(),
    'literatureCollections' => collect(),
    'sharedLiteratureSources' => collect(),
])

<section
    id="rrl-finder"
    x-data="{ workspaceMode: 'find', saveOptionsOpen: false }"
    x-init="$store.literatureSearch.initializeLibrary($el)"
    class="athena-readable mb-5 scroll-mt-36 overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white shadow-[0_24px_70px_-36px_rgba(15,23,42,0.35)] ring-1 ring-slate-950/[0.025] dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/30 dark:ring-white/5"
    aria-label="RRL Finder"
    data-rrl-workspace
    data-literature-library-save-url="{{ route('research-support.literature-library.store') }}"
    data-literature-synthesis-url="{{ route('research-support.literature-synthesis') }}"
    data-literature-collection-save-url="{{ route('research-support.literature-collections.store') }}"
    data-literature-attach-url-template="{{ route('faculty.proposal-drafts.literature-sources.store', ['proposalDraft' => '__proposal__', 'literatureSource' => '__source__']) }}"
    data-detailed-proposal-url-template="{{ route('faculty.proposal-drafts.detailed-proposal.edit', ['proposalDraft' => '__proposal__']) }}"
    data-library-sources="{{ $sharedLiteratureSources->toJson() }}"
    data-library-collections="{{ $literatureCollections->map->only(['id', 'name', 'slug', 'sources_count'])->values()->toJson() }}"
>
    <div class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/55 sm:px-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex w-full rounded-xl bg-slate-200/70 p-1 dark:bg-slate-800 sm:w-auto" role="tablist" aria-label="Literature workspace">
            <button type="button" @click="workspaceMode = 'find'" :aria-selected="workspaceMode === 'find'" :class="workspaceMode === 'find' ? 'bg-white text-[#7A0019] shadow-sm dark:bg-slate-950 dark:text-red-300' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'" class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-lg px-4 text-[11px] font-black transition sm:flex-none" role="tab" aria-controls="literature-find-workspace">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                Find new papers
            </button>
            <button type="button" @click="workspaceMode = 'library'; saveOptionsOpen = false" :aria-selected="workspaceMode === 'library'" :class="workspaceMode === 'library' ? 'bg-white text-[#7A0019] shadow-sm dark:bg-slate-950 dark:text-red-300' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'" class="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-lg px-4 text-[11px] font-black transition sm:flex-none" role="tab" aria-controls="shared-literature-library">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9h7.5M8.25 12h7.5M8.25 15h4.5" /></svg>
                Saved library
                <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-500 dark:bg-slate-800 dark:text-slate-400" x-text="$store.literatureSearch.sharedSources.length"></span>
            </button>
            </div>
            <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500" x-text="workspaceMode === 'find' ? 'Start here to discover academic records.' : 'Reuse papers already saved by faculty.'"></p>
        </div>
    </div>

    <div id="literature-save-options" x-show="workspaceMode === 'find' && saveOptionsOpen" x-cloak x-transition class="border-b border-slate-200 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/55 sm:px-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#7A0019] ring-1 ring-red-100 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900/60">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75A2.25 2.25 0 0 1 6.75 4.5h10.5a2.25 2.25 0 0 1 2.25 2.25v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25V6.75Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9h7.5M8.25 12h7.5M8.25 15h4.5" /></svg>
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-black text-slate-900 dark:text-white">Save the selected paper</p>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-slate-500 dark:bg-slate-800 dark:text-slate-400"><span x-text="$store.literatureSearch.sharedSources.length"></span> papers</span>
                        </div>
                        <p class="mt-1 max-w-xl text-[11px] leading-5 text-slate-500 dark:text-slate-400">Choose a collection or proposal only when needed. Saving to the library keeps one reusable shared record; proposal links remain independent.</p>
                    </div>
                </div>

                <div class="grid w-full gap-3 sm:grid-cols-2 lg:max-w-2xl">
                    <div>
                        <label for="literature-collection" class="mb-1.5 block text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Save new results to</label>
                        <select id="literature-collection" x-model="$store.literatureSearch.selectedCollectionId" class="block h-11 w-full rounded-xl border-slate-200 bg-white text-xs font-bold text-slate-800 shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">Shared library only</option>
                            <template x-for="collection in $store.literatureSearch.collections" :key="collection.id">
                                <option :value="String(collection.id)" x-text="collection.name"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label for="literature-proposal-draft" class="mb-1.5 block text-[9px] font-black uppercase tracking-[0.15em] text-slate-400 dark:text-slate-500">Use with proposal <span class="normal-case tracking-normal">(optional)</span></label>
                        <select id="literature-proposal-draft" x-model="$store.literatureSearch.selectedProposalId" @change="$store.literatureSearch.saveNotice = ''; $store.literatureSearch.saveError = ''" class="block h-11 w-full rounded-xl border-slate-200 bg-white text-xs font-bold text-slate-800 shadow-sm focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white" @disabled($proposalDrafts->isEmpty())>
                            @if ($proposalDrafts->isEmpty())
                                <option value="">No editable proposal</option>
                            @else
                                <option value="">Choose when needed</option>
                                @foreach ($proposalDrafts as $proposalDraft)
                                    <option value="{{ $proposalDraft->id }}">{{ Str::limit($proposalDraft->project_title, 72) }}@if (! $proposalDraft->isOwnedBy(Auth::user())) · {{ $proposalDraft->owner?->name }}@endif</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row sm:items-center">
                <label for="new-literature-collection" class="sr-only">New shared collection name</label>
                <input id="new-literature-collection" type="text" maxlength="120" x-model="$store.literatureSearch.collectionName" @keydown.enter.prevent="$store.literatureSearch.createCollection()" placeholder="Create a collection, e.g. Environment" class="block h-10 min-w-0 flex-1 rounded-xl border-slate-200 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
                <button type="button" @click="$store.literatureSearch.createCollection()" :disabled="$store.literatureSearch.isCreatingCollection || !$store.literatureSearch.collectionName.trim()" class="inline-flex h-10 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-4 text-[10px] font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                    <span x-text="$store.literatureSearch.isCreatingCollection ? 'Creating…' : 'Create shared collection'"></span>
                </button>
            </div>
            <p x-show="$store.literatureSearch.collectionError" x-cloak class="mt-2 text-[11px] font-semibold text-red-600 dark:text-red-300" x-text="$store.literatureSearch.collectionError"></p>
        </div>
    </div>

    <div id="literature-find-workspace" x-show="workspaceMode === 'find'" class="border-b border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:p-6" role="tabpanel">
        <div class="mb-3 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
            <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#7A0019] text-[9px] font-black text-white dark:bg-red-700">1</span>
            <p><span class="font-black text-slate-700 dark:text-slate-200">Enter a topic or research question.</span> You can choose where to save a paper after the results appear.</p>
        </div>
        <form @submit.prevent="$store.literatureSearch.search()" class="space-y-4">
            <div class="rounded-2xl border border-slate-300 bg-white p-2 shadow-[0_10px_30px_-20px_rgba(15,23,42,0.45)] transition focus-within:border-red-500 focus-within:ring-4 focus-within:ring-red-100/70 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-red-500 dark:focus-within:ring-red-950/40">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 items-center gap-3 px-2 sm:px-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg></span>
                        <div class="min-w-0 flex-1">
                            <label for="literature-search-query" class="block text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Research question, topic, or working title</label>
                            <input
                                id="literature-search-query"
                                type="search"
                                x-model="$store.literatureSearch.query"
                                maxlength="180"
                                placeholder="e.g. How does community participation affect mangrove restoration?"
                                class="block w-full border-0 bg-transparent px-0 py-1.5 text-sm font-semibold text-slate-900 shadow-none placeholder:font-normal placeholder:text-slate-400 focus:border-0 focus:ring-0 dark:text-white dark:placeholder:text-slate-500"
                            >
                        </div>
                    </div>
                    <button
                        type="submit"
                        :disabled="$store.literatureSearch.isLoading || $store.literatureSearch.query.trim().length < 3"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#7A0019] px-6 text-xs font-black text-white shadow-lg shadow-red-950/10 transition hover:-translate-y-0.5 hover:bg-[#920021] hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none dark:bg-red-700 dark:hover:bg-red-600 dark:focus:ring-offset-slate-950"
                    >
                        <svg x-show="!$store.literatureSearch.isLoading" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        <svg x-show="$store.literatureSearch.isLoading" x-cloak class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"></path></svg>
                        <span x-text="$store.literatureSearch.isLoading ? 'Searching' : 'Search papers'"></span>
                    </button>
                </div>
            </div>

            <div id="literature-search-filters" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950/60">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10m-7 6h4" /></svg>
                        <p class="text-xs font-black text-slate-800 dark:text-slate-100">Search filters</p>
                        <span x-show="$store.literatureSearch.activeFilterCount()" x-cloak class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-black text-white" x-text="$store.literatureSearch.activeFilterCount()"></span>
                    </div>
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Optional — refine the results before searching</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="literature-search-year-from" class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-300">Published from</label>
                    <input id="literature-search-year-from" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_from" placeholder="Any year" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-year-to" class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-300">Published until</label>
                    <input id="literature-search-year-to" type="number" min="1900" max="{{ now()->year }}" x-model.number="$store.literatureSearch.filters.year_to" placeholder="{{ now()->year }}" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <div>
                    <label for="literature-search-min-citations" class="mb-1.5 block text-xs font-bold text-slate-600 dark:text-slate-300">Minimum citations</label>
                    <input id="literature-search-min-citations" type="number" min="0" max="1000000" x-model.number="$store.literatureSearch.filters.min_citations" placeholder="No minimum" class="block h-11 w-full rounded-xl border-gray-200 bg-white text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                </div>
                <label class="flex min-h-11 items-center gap-3 self-end rounded-xl border border-slate-200 bg-white px-3.5 text-sm font-bold text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <input type="checkbox" x-model="$store.literatureSearch.filters.open_access" class="h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500 dark:border-slate-600">
                    <span>Open-access papers only</span>
                </label>
                </div>

                <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400"><svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>Up to 50 deduplicated, evidence-ranked records</p>
            </div>
        </form>
    </div>

    <section id="shared-literature-library" x-show="workspaceMode === 'library'" x-cloak x-transition class="border-b border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-950/35 sm:p-6" aria-labelledby="shared-literature-heading" role="tabpanel">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h3 id="shared-literature-heading" class="text-sm font-black text-slate-900 dark:text-white">Browse the shared library</h3>
                    <span class="rounded-full bg-white px-2 py-1 text-[9px] font-black uppercase tracking-wider text-slate-500 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-400 dark:ring-slate-700" x-text="`${$store.literatureSearch.filteredLibrarySources().length} available`"></span>
                </div>
                <p class="mt-1 text-[11px] leading-5 text-slate-500 dark:text-slate-400">Reuse papers saved by other faculty. The same paper can be linked to multiple proposals without duplicating the shared record.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:w-[48rem] lg:grid-cols-3">
                <label class="relative block">
                    <span class="sr-only">Search the shared literature library</span>
                    <svg class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                    <input type="search" x-model.debounce.200ms="$store.literatureSearch.libraryQuery" placeholder="Search saved papers" class="block h-10 w-full rounded-xl border-slate-200 bg-white pl-9 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
                </label>
                <select x-model="$store.literatureSearch.libraryCollectionFilter" aria-label="Filter shared papers by collection" class="block h-10 w-full rounded-xl border-slate-200 bg-white text-xs font-bold text-slate-700 shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <option value="">All collections</option>
                    <template x-for="collection in $store.literatureSearch.collections" :key="`filter-${collection.id}`">
                        <option :value="String(collection.id)" x-text="collection.name"></option>
                    </template>
                </select>
                <select x-model="$store.literatureSearch.selectedProposalId" aria-label="Proposal to use shared papers with" class="block h-10 w-full rounded-xl border-slate-200 bg-white text-xs font-bold text-slate-700 shadow-sm focus:border-red-500 focus:ring-red-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200" @disabled($proposalDrafts->isEmpty())>
                    @if ($proposalDrafts->isEmpty())
                        <option value="">No editable proposal</option>
                    @else
                        <option value="">Choose proposal to use a paper</option>
                        @foreach ($proposalDrafts as $proposalDraft)
                            <option value="{{ $proposalDraft->id }}">{{ Str::limit($proposalDraft->project_title, 72) }}@if (! $proposalDraft->isOwnedBy(Auth::user())) Â· {{ $proposalDraft->owner?->name }}@endif</option>
                        @endforeach
                    @endif
                </select>
            </div>
        </div>

        <div x-show="$store.literatureSearch.filteredLibrarySources().length === 0" class="mt-4 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-black text-slate-800 dark:text-slate-100" x-text="$store.literatureSearch.sharedSources.length ? 'No shared papers match these filters' : 'The shared library is empty' "></p>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="$store.literatureSearch.sharedSources.length ? 'Try a different title, author, or collection.' : 'Find a paper first, then save it here for everyone to reuse.'"></p>
            <button x-show="!$store.literatureSearch.sharedSources.length" type="button" @click="workspaceMode = 'find'" class="mt-3 rounded-lg bg-[#7A0019] px-3 py-2 text-[10px] font-black text-white transition hover:bg-[#920021] dark:bg-red-700 dark:hover:bg-red-600">Find new papers</button>
        </div>

        <div x-show="$store.literatureSearch.filteredLibrarySources().length" x-cloak class="mt-4 grid gap-3 lg:grid-cols-2">
            <template x-for="source in $store.literatureSearch.filteredLibrarySources().slice(0, 12)" :key="`library-${source.id}`">
                <article class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-black leading-5 text-slate-900 dark:text-white" x-text="source.title"></p>
                            <p class="mt-1 line-clamp-1 text-[10px] text-slate-500 dark:text-slate-400" x-text="source.authors || 'Authors not listed'"></p>
                        </div>
                        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-[9px] font-black text-slate-600 dark:bg-slate-800 dark:text-slate-300" x-text="source.year || 'n.d.'"></span>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <template x-for="collection in source.collections || []" :key="`${source.id}-${collection.id}`">
                            <span class="rounded-full bg-red-50 px-2 py-1 text-[9px] font-bold text-red-700 dark:bg-red-950/30 dark:text-red-200" x-text="collection.name"></span>
                        </template>
                        <span x-show="!source.collections?.length" class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">Unfiled</span>
                    </div>
                    <p class="mt-3 text-[10px] font-semibold text-slate-400 dark:text-slate-500" x-text="`Added by ${source.added_by_name || 'a faculty member'}`"></p>
                    <div class="mt-auto grid grid-cols-3 gap-2 pt-4">
                        <button type="button" @click="$store.literatureSearch.useLibrarySource(source, 'rrl')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingLibrarySource(source)" class="rounded-lg border border-slate-200 px-2 py-2 text-[9px] font-black text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/30">Use in RRL</button>
                        <button type="button" @click="$store.literatureSearch.useLibrarySource(source, 'reference')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingLibrarySource(source)" class="rounded-lg border border-slate-200 px-2 py-2 text-[9px] font-black text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/30">Reference</button>
                        <button type="button" @click="$store.literatureSearch.useLibrarySource(source, 'both')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingLibrarySource(source)" class="rounded-lg bg-red-700 px-2 py-2 text-[9px] font-black text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-red-700 dark:hover:bg-red-600">Use both</button>
                    </div>
                </article>
            </template>
        </div>
    </section>

    <div x-show="workspaceMode === 'find'">
    <div x-show="$store.literatureSearch.saveNotice" x-cloak class="mx-4 mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-xs font-semibold text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200 sm:mx-5" x-text="$store.literatureSearch.saveNotice" role="status"></div>
    <div x-show="$store.literatureSearch.saveError" x-cloak class="mx-4 mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:mx-5" x-text="$store.literatureSearch.saveError" role="alert"></div>

    <div x-show="$store.literatureSearch.error" x-cloak class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 sm:m-5">
        <p class="font-black">Search unavailable</p>
        <p class="mt-1" x-text="$store.literatureSearch.error"></p>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.providerNotice && $store.literatureSearch.results.length" x-cloak class="m-4 flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-300 sm:m-5">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25 12 10.5m0 6.75h.008v.008H12v-.008Zm9-5.25a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        <p><span class="font-black text-slate-700 dark:text-slate-200">Source coverage:</span> <span x-text="$store.literatureSearch.providerNotice"></span></p>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && $store.literatureSearch.queryGuidance?.is_broad && !$store.literatureSearch.isLoading" x-cloak class="m-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100 sm:m-5">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-200"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 4.5h.008v.008H12V16.5Z" /></svg></span>
        <div class="min-w-0">
            <p class="text-xs font-black">Add research context for more precise results</p>
            <p class="mt-1 text-[11px] leading-5" x-text="$store.literatureSearch.queryGuidance?.message"></p>
            <p class="mt-1 text-[11px] font-bold leading-5" x-text="$store.literatureSearch.queryGuidance?.suggestion"></p>
        </div>
    </div>

    <div x-show="$store.literatureSearch.isLoading" x-cloak class="p-4 sm:p-5" role="status" aria-label="Searching academic sources">
        <div class="overflow-hidden rounded-2xl border border-red-100 bg-gradient-to-br from-red-50 via-white to-amber-50 shadow-sm dark:border-red-950 dark:from-red-950/30 dark:via-slate-900 dark:to-amber-950/20">
            <div class="p-5 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-11 w-11 items-center justify-center rounded-2xl bg-red-600 text-white shadow-lg shadow-red-200 dark:shadow-none">
                            <span class="absolute inset-0 animate-ping rounded-2xl bg-red-400 opacity-20"></span>
                            <svg class="relative h-5 w-5 animate-pulse" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
                        </span>
                        <div>
                            <p class="text-sm font-black text-gray-950 dark:text-white">Searching three academic indexes</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.loadingStage()"></p>
                        </div>
                    </div>
                    <p class="text-[11px] font-black tabular-nums text-red-700 dark:text-red-300"><span x-text="$store.literatureSearch.elapsedSeconds"></span>s elapsed</p>
                </div>

                <div class="mt-5 grid gap-2 sm:grid-cols-3">
                    <template x-for="provider in ['Semantic Scholar', 'Crossref', 'OpenAlex']" :key="provider">
                        <div class="flex items-center gap-2 rounded-xl border border-white/80 bg-white/80 px-3 py-2.5 text-[11px] font-bold text-gray-700 shadow-sm dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-200">
                            <span class="h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                            <span x-text="provider"></span>
                        </div>
                    </template>
                </div>
            </div>
            <div class="h-1.5 overflow-hidden bg-red-100 dark:bg-red-950">
                <div class="h-full w-1/2 animate-[pulse_1.2s_ease-in-out_infinite] rounded-full bg-gradient-to-r from-red-500 to-amber-500"></div>
            </div>
            <div class="grid gap-px bg-gray-200/70 dark:bg-slate-800 sm:grid-cols-3">
                <template x-for="cell in 3" :key="`loading-card-${cell}`"><div class="bg-white/90 p-4 dark:bg-slate-900"><div class="h-2.5 animate-pulse rounded bg-gray-100 dark:bg-slate-800"></div><div class="mt-3 h-2 w-2/3 animate-pulse rounded bg-gray-100 dark:bg-slate-800"></div><div class="mt-5 h-12 animate-pulse rounded-lg bg-gray-50 dark:bg-slate-950"></div></div></template>
            </div>
        </div>
    </div>

    <div x-show="!$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading" class="bg-slate-50/60 px-4 py-3 dark:bg-slate-950/30 sm:px-6">
        <div class="flex items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[#7A0019] dark:bg-red-950/40 dark:text-red-200">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" /></svg>
            </span>
            <div class="min-w-0">
                <p class="text-xs font-black text-slate-800 dark:text-slate-100">Your first action is to search</p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500 dark:text-slate-400">Enter a focused topic above, select a result, then decide whether to save it to the shared library or use it in a proposal.</p>
            </div>
        </div>
    </div>

    <div x-show="$store.literatureSearch.hasSearched && !$store.literatureSearch.isLoading && !$store.literatureSearch.results.length && !$store.literatureSearch.error" x-cloak class="p-4 sm:p-5">
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center dark:border-slate-800 dark:bg-slate-950/40">
            <p class="text-sm font-black text-gray-800 dark:text-slate-100" x-text="$store.literatureSearch.queryGuidance?.is_broad ? 'A more specific research query is needed' : 'No literature records found'"></p>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.queryGuidance?.is_broad ? 'Add at least one variable, population, method, or setting before searching the academic indexes.' : 'Try a broader phrase, a key variable, or a shorter version of the working title.'"></p>
        </div>
    </div>

    <div x-show="$store.literatureSearch.results.length && !$store.literatureSearch.isLoading" x-cloak data-rrl-results-workspace>
        <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-black text-gray-900 dark:text-white"><span x-text="$store.literatureSearch.results.length"></span> papers found</p>
                <span class="rounded-md bg-gray-100 px-2 py-1 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:bg-slate-800 dark:text-slate-400">Evidence-ranked</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" @click="saveOptionsOpen = true; $nextTick(() => document.getElementById('literature-save-options')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900 dark:hover:bg-red-950/30">Proposal &amp; collection</button>
                <button type="button" @click="$store.literatureSearch.clear()" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-500 transition hover:bg-gray-50 hover:text-red-600 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-red-300">Clear</button>
                <button type="button" @click="$store.literatureSearch.askAthena()" :disabled="$store.researchAssistant.isLoading" class="rounded-lg bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Ask Athena about results</button>
            </div>
        </div>

        <div class="grid min-h-[520px] xl:grid-cols-[minmax(0,1fr)_24rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
            <div class="min-w-0 overflow-x-auto border-b border-gray-200 dark:border-slate-800 xl:border-b-0 xl:border-r" data-rrl-results-table>
                <table class="min-w-[920px] table-fixed border-collapse text-left">
                    <caption class="sr-only">Related literature comparison results</caption>
                    <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-slate-950">
                        <tr class="border-b border-gray-200 dark:border-slate-800">
                            <th scope="col" class="w-14 px-3 py-3 text-center text-[9px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">#</th>
                            <th scope="col" class="w-72 border-l border-gray-200 px-4 py-3 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Paper</th>
                            <th scope="col" class="w-96 border-l border-gray-200 px-4 py-3 text-[9px] font-black uppercase tracking-wider text-gray-500 dark:border-slate-800 dark:text-slate-400">Abstract excerpt</th>
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
                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-black text-amber-700 dark:bg-amber-950/40 dark:text-amber-300" x-text="result.relevance_label || 'Potential match'"></span>
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

            <aside class="bg-gray-50/70 p-5 dark:bg-slate-950/40 xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] xl:self-start xl:overflow-y-auto" aria-label="Selected paper details" data-rrl-paper-details>
                <div x-show="$store.literatureSearch.selectedResult()" x-cloak>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-gray-400 dark:text-slate-500">Paper details</p>
                        <span class="rounded-md bg-red-50 px-2 py-1 text-[9px] font-black text-red-700 dark:bg-red-950/40 dark:text-red-300" x-text="$store.literatureSearch.selectedResult()?.source"></span>
                    </div>
                    <h4 class="mt-4 text-base font-black leading-6 text-gray-950 dark:text-white" x-text="$store.literatureSearch.selectedResult()?.title"></h4>
                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.selectedResult()?.authors"></p>

                    <div class="sticky top-0 z-10 mt-4 rounded-2xl border border-red-100 bg-white/95 p-4 shadow-md shadow-slate-900/5 backdrop-blur dark:border-red-950 dark:bg-slate-900/95 dark:shadow-black/20">
                        <div>
                            <p class="text-sm font-black text-slate-900 dark:text-white">Add to a Detailed Proposal</p>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Choose the draft and the section where this paper should be staged.</p>
                        </div>

                        @if ($proposalDrafts->isNotEmpty())
                            <label for="detail-literature-proposal" class="mt-4 block text-xs font-bold text-slate-700 dark:text-slate-200">Proposal</label>
                            <select id="detail-literature-proposal" x-model="$store.literatureSearch.selectedProposalId" class="mt-1.5 block h-11 w-full rounded-xl border-slate-200 bg-white text-sm font-semibold text-slate-800 shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option value="">Choose a proposal</option>
                                @foreach ($proposalDrafts as $proposalDraft)
                                    <option value="{{ $proposalDraft->id }}">{{ Str::limit($proposalDraft->project_title, 58) }}@if (! $proposalDraft->isOwnedBy(Auth::user())) Â· {{ $proposalDraft->owner?->name }}@endif</option>
                                @endforeach
                            </select>
                            <div class="mt-3 grid gap-2">
                                <button type="button" @click="$store.literatureSearch.prepareSynthesis($store.literatureSearch.selectedResult(), 'rrl')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="flex min-h-11 items-center justify-between gap-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-left text-xs font-black text-red-800 transition hover:border-red-300 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200 dark:hover:bg-red-950/50"><span>Prepare for RRL</span><span class="font-semibold text-red-600 dark:text-red-300">Review first →</span></button>
                                <button type="button" @click="$store.literatureSearch.saveResult($store.literatureSearch.selectedResult(), 'reference')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="flex min-h-11 items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left text-xs font-black text-slate-800 transition hover:border-red-200 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:hover:border-red-900 dark:hover:bg-red-950/30"><span>Add reference</span><span class="font-semibold text-slate-500 dark:text-slate-400">Section XVI →</span></button>
                                <button type="button" @click="$store.literatureSearch.prepareSynthesis($store.literatureSearch.selectedResult(), 'both')" :disabled="!$store.literatureSearch.selectedProposalId || $store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="min-h-11 rounded-xl bg-red-700 px-3.5 py-2.5 text-xs font-black text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-red-700 dark:hover:bg-red-600">Prepare RRL + reference</button>
                            </div>
                        @endif

                        <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200">Keep for later</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <button type="button" @click="$store.literatureSearch.saveResult($store.literatureSearch.selectedResult())" :disabled="$store.literatureSearch.isSavingResult($store.literatureSearch.selectedResult())" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" /></svg><span x-text="$store.literatureSearch.isSavedResult($store.literatureSearch.selectedResult()) ? 'Saved' : 'Save to library'"></span></button>
                                <a x-show="$store.literatureSearch.selectedResult()?.url" :href="$store.literatureSearch.selectedResult()?.url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-black dark:border-white dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">Open record<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H18m0 0v4.5M18 6l-7.5 7.5M6 7.5v10.125A1.875 1.875 0 0 0 7.875 19.5H18" /></svg></a>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-3 border-y border-gray-200 py-4 text-[11px] dark:border-slate-800">
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Year</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.year || 'Not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Citations</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="Number.isInteger($store.literatureSearch.selectedResult()?.citation_count) ? $store.literatureSearch.selectedResult().citation_count : 'Not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Relevance</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.relevance_label || 'Potential match'"></dd></div>
                        <div class="col-span-2"><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Publication</dt><dd class="mt-1 font-bold leading-5 text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.venue || 'Source venue not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Type</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.type || 'Not listed'"></dd></div>
                        <div><dt class="font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Access</dt><dd class="mt-1 font-bold text-gray-800 dark:text-slate-200" x-text="$store.literatureSearch.selectedResult()?.is_open_access ? 'Open access' : 'Source record'"></dd></div>
                    </dl>

                    <p class="mt-3 text-[10px] font-semibold leading-4 text-gray-500 dark:text-slate-400" x-text="$store.literatureSearch.selectedResult()?.match_reason"></p>

                    <div class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">Abstract excerpt</p>
                        <p class="mt-2 text-xs leading-6 text-gray-600 dark:text-slate-300" x-text="$store.literatureSearch.selectedResult()?.description"></p>
                    </div>

                    <div x-show="$store.literatureSearch.selectedResult()?.doi" class="mt-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-slate-500">DOI</p>
                        <p class="mt-1 break-all text-[11px] font-semibold text-gray-600 dark:text-slate-300" x-text="$store.literatureSearch.selectedResult()?.doi"></p>
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
                            <h2 id="literature-synthesis-title" class="text-lg font-black text-slate-950 dark:text-white">Prepare the RRL paragraph</h2>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Abstract only</span>
                        </div>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Compare the evidence with the editable wording before anything is inserted into the proposal.</p>
                    </div>
                    <button type="button" @click="$store.literatureSearch.closeSynthesisReview()" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Close RRL preparation dialog">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </header>

                <div class="grid min-h-0 flex-1 overflow-y-auto lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] lg:overflow-hidden">
                    <section class="border-b border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-950/45 lg:overflow-y-auto lg:border-b-0 lg:border-r sm:p-6">
                        <p class="text-[11px] font-black uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Source evidence</p>
                        <h3 class="mt-3 text-base font-black leading-6 text-slate-950 dark:text-white" x-text="$store.literatureSearch.synthesisSource?.title || 'Selected paper'"></h3>
                        <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">
                            <span x-text="$store.literatureSearch.synthesisSource?.authors || 'Authors not listed'"></span>
                            <span aria-hidden="true"> &middot; </span>
                            <span x-text="$store.literatureSearch.synthesisSource?.year || 'Year not listed'"></span>
                        </p>

                        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
                            <p class="text-xs font-black text-slate-800 dark:text-slate-100">Indexed abstract</p>
                            <p x-show="$store.literatureSearch.hasSynthesisEvidence()" class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-600 dark:text-slate-300" x-text="$store.literatureSearch.synthesisSource?.description"></p>
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
                                <label for="literature-synthesis-draft" class="text-sm font-black text-slate-950 dark:text-white">Editable RRL paragraph</label>
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">Revise the wording so it fits your study. Do not treat this as a direct quotation.</p>
                            </div>
                            <button type="button" x-show="$store.literatureSearch.hasSynthesisEvidence()" @click="$store.literatureSearch.generateSynthesis()" :disabled="$store.literatureSearch.isSynthesizing" class="inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-slate-200 px-3.5 text-xs font-black text-slate-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:border-red-900 dark:hover:bg-red-950/30">
                                <svg :class="$store.literatureSearch.isSynthesizing ? 'animate-spin' : ''" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 1 12.8-5.3L19.5 9M19.5 4.5V9H15M19.5 12a7.5 7.5 0 0 1-12.8 5.3L4.5 15M4.5 19.5V15H9" /></svg>
                                <span x-text="$store.literatureSearch.isSynthesizing ? 'Generating...' : ($store.literatureSearch.synthesisDraft ? 'Generate again' : 'Generate draft')"></span>
                            </button>
                        </div>

                        <div x-show="$store.literatureSearch.isSynthesizing && !$store.literatureSearch.synthesisDraft" class="mt-4 flex min-h-56 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-red-200 bg-red-50/60 p-6 text-center dark:border-red-900 dark:bg-red-950/20" role="status">
                            <span class="h-8 w-8 animate-spin rounded-full border-2 border-red-200 border-t-red-700 dark:border-red-950 dark:border-t-red-300"></span>
                            <p class="mt-4 text-sm font-black text-red-900 dark:text-red-100">Preparing an abstract-based draft</p>
                            <p class="mt-1 text-xs leading-5 text-red-700 dark:text-red-300">ATHENA is constrained to the evidence shown on the left.</p>
                        </div>

                        <textarea
                            id="literature-synthesis-draft"
                            x-show="!$store.literatureSearch.isSynthesizing || $store.literatureSearch.synthesisDraft"
                            x-model="$store.literatureSearch.synthesisDraft"
                            rows="12"
                            maxlength="5000"
                            placeholder="Generate an abstract-based draft, or write your own synthesis after reviewing the paper."
                            class="mt-4 min-h-64 w-full flex-1 resize-y rounded-2xl border-slate-300 bg-white p-4 text-sm leading-7 text-slate-900 shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500"
                        ></textarea>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                            <p class="font-medium text-slate-500 dark:text-slate-400"><span x-text="$store.literatureSearch.synthesisWordCount()"></span> words <span aria-hidden="true">&middot;</span> Review required before saving the proposal</p>
                            <span x-show="$store.literatureSearch.synthesisBasis === 'abstract'" class="rounded-full bg-amber-100 px-2.5 py-1 font-black text-amber-800 dark:bg-amber-950/50 dark:text-amber-200">Abstract-based</span>
                        </div>

                        <p x-show="$store.literatureSearch.synthesisNotice" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 text-xs font-semibold leading-5 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200" x-text="$store.literatureSearch.synthesisNotice" role="status"></p>
                        <p x-show="$store.literatureSearch.synthesisError" x-cloak class="mt-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs font-semibold leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200" x-text="$store.literatureSearch.synthesisError" role="alert"></p>

                        <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 dark:border-slate-700 sm:flex-row sm:justify-end">
                            <button type="button" @click="$store.literatureSearch.closeSynthesisReview()" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</button>
                            <button type="button" @click="$store.literatureSearch.confirmSynthesis()" :disabled="$store.literatureSearch.isSynthesizing || $store.literatureSearch.isSavingResult($store.literatureSearch.synthesisSource) || $store.literatureSearch.synthesisDraft.trim().length < 40" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-red-700 px-5 text-sm font-black text-white shadow-sm transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-red-700 dark:hover:bg-red-600">
                                <span x-text="$store.literatureSearch.synthesisApplyTo === 'both' ? 'Insert RRL + add reference' : 'Insert into Section XI'"></span>
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </template>
</section>
