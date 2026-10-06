<div
    x-show="sourceWorkspaceOpen"
    x-cloak
    @keydown.escape.window="sourceWorkspaceOpen && closeSourceWorkspace()"
    @keydown="trapSourceWorkspaceFocus($event)"
    {{ $attributes->merge(['class' => 'fixed inset-0 z-[65] flex justify-end']) }}
    role="dialog"
    aria-modal="true"
    aria-labelledby="proposal-sources-heading"
    data-source-workspace
>
    <button type="button" @click="closeSourceWorkspace()" tabindex="-1" aria-label="Close Sources workspace" class="absolute inset-0 bg-slate-950/50"></button>
    <section x-ref="sourceWorkspacePanel" class="relative flex h-full w-full flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-950 sm:w-[min(92vw,72rem)]">
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 dark:border-slate-700 sm:px-6">
            <div class="min-w-0">
                <div class="flex items-center gap-3">
                    <button x-show="sourceWorkspaceView !== 'list'" type="button" @click="sourceWorkspaceView = 'list'; sourceWorkspaceError = ''" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-800 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-200 dark:hover:bg-red-950/40">Back to sources</button>
                    <h2 id="proposal-sources-heading" class="text-lg font-semibold text-slate-950 dark:text-white">Sources</h2>
                </div>
                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="sourceWorkspaceCitationMode ? 'Choose a source for the claim or cursor position you selected.' : 'Keep your references, read source evidence, and cite while you write.'"></p>
            </div>
            <button x-ref="sourceWorkspaceClose" type="button" @click="closeSourceWorkspace()" :disabled="sourceWorkspaceSaving || sourceWorkspaceAiLoading" aria-label="Close Sources" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:text-slate-300 dark:hover:bg-slate-800">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M6 18 18 6" /></svg>
            </button>
        </header>

        <nav x-show="sourceWorkspaceView === 'list'" class="flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 px-3 dark:border-slate-700 sm:px-5" aria-label="Source collections">
            <template x-for="tab in [{ id: 'paper', label: 'This paper' }, { id: 'library', label: 'Saved library' }, { id: 'search', label: 'Academic search' }, { id: 'add', label: 'Add source' }]" :key="tab.id">
                <button type="button" @click="setSourceWorkspaceTab(tab.id)" :disabled="sourceWorkspaceSaving || sourceWorkspaceAiLoading" :aria-current="sourceWorkspaceTab === tab.id ? 'page' : false" :class="sourceWorkspaceTab === tab.id ? 'border-red-700 text-red-800 dark:text-red-200' : 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white'" class="min-h-12 shrink-0 border-b-2 px-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600 disabled:opacity-40 sm:px-3" x-text="tab.label"></button>
            </template>
        </nav>
        <div x-show="sourceWorkspaceCanCite()" class="flex shrink-0 flex-col gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
            <p x-show="sourceWorkspaceCitationMode && sourceWorkspaceSelectedClaim()" class="line-clamp-2 text-xs leading-5 text-slate-600 dark:text-slate-300"><span class="font-semibold">Selected claim: </span><span x-text="sourceWorkspaceSelectedClaim()"></span></p>
            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Page or section (optional)<input type="text" x-model="citationPickerLocator" maxlength="100" placeholder="e.g. p. 14 or Results, paragraph 2" class="h-10 w-full rounded-lg border-slate-300 bg-white text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
            <div x-show="sourceWorkspaceError" role="alert" class="border-b border-red-200 bg-red-50 px-4 py-3 text-xs leading-5 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200 sm:px-6" x-text="sourceWorkspaceError"></div>
            <p x-show="sourceWorkspaceNotice" role="status" class="border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:px-6" x-text="sourceWorkspaceNotice"></p>

            <div class="grid min-h-full lg:grid-cols-[minmax(0,1fr)_18rem]">
                <main class="min-w-0">
                    <div x-show="sourceWorkspaceView === 'list' && sourceWorkspaceTab !== 'add'">
                        <form x-show="sourceWorkspaceTab === 'library' || sourceWorkspaceTab === 'search'" @submit.prevent="sourceWorkspaceTab === 'library' ? searchSourceWorkspaceLibrary() : searchSourceWorkspaceAcademic()" class="flex gap-2 border-b border-slate-200 px-4 py-4 dark:border-slate-700 sm:px-6">
                            <label class="min-w-0 flex-1">
                                <span class="sr-only" x-text="sourceWorkspaceTab === 'library' ? 'Search saved library' : 'Search academic publications'"></span>
                                <input type="search" x-model="sourceWorkspaceQuery" maxlength="500" :placeholder="sourceWorkspaceTab === 'library' ? 'Title, author, or DOI' : 'Research topic, method, or keywords'" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                            </label>
                            <button type="submit" :disabled="sourceWorkspaceBusy() || (sourceWorkspaceTab === 'search' && sourceWorkspaceQuery.trim().length < 3)" class="rounded-lg bg-red-700 px-4 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-40 dark:focus:ring-offset-slate-950">Search</button>
                        </form>
                        <div x-show="sourceWorkspaceTab === 'search'" class="flex flex-wrap items-end gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700 sm:px-6">
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">From year<input type="number" min="1900" max="{{ now()->year }}" x-model="sourceWorkspaceSearchFilters.year_from" placeholder="Any" class="mt-1 block h-9 w-24 rounded-lg border-slate-300 bg-white text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">To year<input type="number" min="1900" max="{{ now()->year }}" x-model="sourceWorkspaceSearchFilters.year_to" placeholder="Any" class="mt-1 block h-9 w-24 rounded-lg border-slate-300 bg-white text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <label class="flex min-h-9 items-center gap-2 text-xs text-slate-600 dark:text-slate-300"><input type="checkbox" x-model="sourceWorkspaceSearchFilters.open_access" class="rounded border-slate-300 text-red-700 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900">Open access only</label>
                        </div>
                        <p x-show="sourceWorkspaceLoading" role="status" class="px-4 py-6 text-sm text-slate-500 dark:text-slate-400 sm:px-6">Loading sources…</p>
                        <div x-show="!sourceWorkspaceLoading" class="divide-y divide-slate-200 dark:divide-slate-700">
                            <template x-for="source in sourceWorkspaceSources()" :key="sourceWorkspaceSourceKey(source)">
                                <article class="flex flex-col gap-3 px-4 py-4 sm:px-6">
                                    <div class="min-w-0">
                                        <h3 class="break-words text-sm font-semibold leading-6 text-slate-950 dark:text-white" x-text="source.title"></h3>
                                        <p class="mt-1 break-words text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="sourceWorkspaceMetadata(source)"></p>
                                        <p x-show="source.venue" class="mt-1 break-words text-xs text-slate-500 dark:text-slate-400" x-text="source.venue"></p>
                                        <p x-show="source.match_reason" class="mt-2 text-xs leading-5 text-slate-600 dark:text-slate-300" x-text="source.match_reason"></p>
                                    </div>
                                    <details x-show="source.description" class="text-xs leading-5 text-slate-600 dark:text-slate-300"><summary class="cursor-pointer rounded font-semibold focus:outline-none focus:ring-2 focus:ring-red-600">Read available abstract</summary><p class="mt-2 whitespace-pre-wrap break-words" x-text="source.description"></p></details>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span x-show="sourceWorkspaceLinkedSource(source)" class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">In this paper’s library</span>
                                        <button x-show="!sourceWorkspaceLinkedSource(source)" type="button" @click="saveSourceWorkspaceSource(source)" :disabled="sourceWorkspaceBusy()" class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Add to paper</button>
                                        <button type="button" @click="readSourceWorkspaceSource(source)" :disabled="sourceWorkspaceBusy()" class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800">Read &amp; take notes</button>
                                        <button type="button" @click="citeSourceWorkspaceSource(source)" :disabled="sourceWorkspaceBusy() || !sourceWorkspaceCanCite()" class="min-h-9 rounded-lg bg-red-700 px-3 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40">Insert citation</button>
                                        <a x-show="sourceWorkspaceUrl(source.url)" :href="sourceWorkspaceUrl(source.url)" target="_blank" rel="noopener noreferrer" class="min-h-9 rounded-lg px-2 py-2 text-xs font-semibold text-red-800 hover:underline focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-red-200">Publication</a>
                                    </div>
                                </article>
                            </template>
                            <div x-show="!sourceWorkspaceSources().length" class="flex flex-col items-start gap-3 px-4 py-8 sm:px-6">
                                <p class="text-sm font-semibold text-slate-800 dark:text-white" x-text="sourceWorkspaceTab === 'paper' ? 'No sources saved to this paper yet' : (sourceWorkspaceTab === 'search' && !sourceWorkspaceSearchPerformed ? 'Find publications related to your research' : 'No matching sources')"></p>
                                <p class="text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="sourceWorkspaceTab === 'paper' ? 'Choose a source from your saved library, search academic publications, or add its details.' : 'Use a specific title, author, or research term.'"></p>
                                <button x-show="sourceWorkspaceTab === 'paper'" type="button" @click="setSourceWorkspaceTab('add')" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:text-slate-200">Add a source</button>
                            </div>
                        </div>
                        <p x-show="!sourceWorkspaceCanCite() && sourceWorkspaceSources().length" class="border-t border-slate-200 px-4 py-3 text-xs leading-5 text-slate-500 dark:border-slate-700 dark:text-slate-400 sm:px-6">Place the cursor or select a supported claim in the paper, then open Sources to insert a citation.</p>
                    </div>

                    <div x-show="sourceWorkspaceView === 'list' && sourceWorkspaceTab === 'add'" class="flex flex-col gap-5 px-4 py-5 sm:px-6">
                        <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Add source using
                            <select :value="sourceWorkspaceAddMode" @change="setSourceWorkspaceAddMode($event.target.value)" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                                <option value="identifier">DOI or publication URL</option><option value="manual">Manual details</option><option value="bibtex">BibTeX</option><option value="ris">RIS</option><option value="pdf">Upload PDF</option>
                            </select>
                        </label>
                        <div x-show="sourceWorkspaceAddMode === 'identifier'" class="flex flex-col gap-2">
                            <label class="text-xs font-semibold text-slate-700 dark:text-slate-200">DOI or publication URL<input x-model="sourceWorkspaceIdentifier" type="text" maxlength="2048" placeholder="10.1234/example or https://…" class="mt-2 h-11 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <button type="button" @click="lookupSourceWorkspaceIdentifier()" :disabled="sourceWorkspaceBusy() || !sourceWorkspaceIdentifier.trim()" class="self-start rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200" x-text="sourceWorkspaceLoading ? 'Looking up DOI…' : 'Review publication details'"></button>
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">DOIs use publisher metadata. For other URLs, enter the publication details yourself.</p>
                        </div>
                        <div x-show="sourceWorkspaceAddMode === 'bibtex' || sourceWorkspaceAddMode === 'ris'" class="flex flex-col gap-3">
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Bibliography import<textarea x-model="sourceWorkspaceImportText" rows="5" maxlength="1000000" class="rounded-lg border-slate-300 bg-white text-xs font-normal leading-5 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                            <label class="flex flex-col gap-2 text-xs text-slate-500 dark:text-slate-400">Or choose a text file<input type="file" accept=".bib,.ris,text/plain" @change="readSourceWorkspaceImportFile($event)" class="max-w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-200"></label>
                            <button type="button" @click="parseSourceWorkspaceImport()" :disabled="sourceWorkspaceBusy() || !sourceWorkspaceImportText.trim()" class="self-start rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200">Read import</button>
                            <label x-show="sourceWorkspaceImports.length" class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Entry to review<select x-model.number="sourceWorkspaceImportIndex" @change="selectSourceWorkspaceImport()" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><template x-for="(entry, index) in sourceWorkspaceImports" :key="index"><option :value="index" x-text="`${entry._saved ? 'Saved: ' : ''}${entry.title}`"></option></template></select></label>
                        </div>
                        <div x-show="sourceWorkspaceAddMode === 'pdf'" class="flex flex-col gap-2">
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Source PDF<input type="file" accept="application/pdf,.pdf" @change="chooseSourceWorkspacePdf($event)" class="max-w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-200"></label>
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Attach the original PDF after reviewing its publication details. Extracted text may cover only part of the document.</p>
                        </div>
                        <form @submit.prevent="saveSourceWorkspaceMetadata()" class="flex flex-col gap-4 border-t border-slate-200 pt-5 dark:border-slate-700">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Review source details</h3>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Publication title<input type="text" x-model="sourceWorkspaceSourceForm.title" required maxlength="500" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Authors<input type="text" x-model="sourceWorkspaceSourceForm.authors" maxlength="2000" placeholder="Authors as shown on the publication" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <div class="grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Year<input type="number" x-model="sourceWorkspaceSourceForm.year" min="1900" max="{{ now()->year }}" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                                <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Journal or venue<input type="text" x-model="sourceWorkspaceSourceForm.venue" maxlength="500" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            </div>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">DOI<input type="text" x-model="sourceWorkspaceSourceForm.doi" maxlength="255" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Publication URL<input type="url" x-model="sourceWorkspaceSourceForm.url" maxlength="2048" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <details class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                                <summary class="cursor-pointer rounded text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-200">Reference details</summary>
                                <div class="mt-4 flex flex-col gap-4">
                                    <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Publication type<select aria-label="Publication type" x-model="sourceWorkspaceSourceForm.type" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="article">Journal article</option><option value="conference-paper">Conference paper</option><option value="book">Book</option><option value="thesis">Thesis</option><option value="report">Report</option><option value="website">Website</option><option value="other">Other</option></select></label>
                                    <div class="grid gap-4 sm:grid-cols-3">
                                        <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Volume<input x-model="sourceWorkspaceSourceForm.volume" type="text" maxlength="100" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                                        <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Issue<input x-model="sourceWorkspaceSourceForm.issue" type="text" maxlength="100" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                                        <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Pages<input x-model="sourceWorkspaceSourceForm.pages" type="text" maxlength="100" placeholder="e.g. 14–28" class="h-11 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                                    </div>
                                    <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Publisher<input x-model="sourceWorkspaceSourceForm.publisher" type="text" maxlength="500" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                                </div>
                            </details>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Abstract, if available<textarea x-model="sourceWorkspaceSourceForm.description" rows="3" maxlength="12000" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-6 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                            <button type="submit" :disabled="sourceWorkspaceBusy() || !sourceWorkspaceSourceForm.title.trim() || (sourceWorkspaceAddMode === 'pdf' && !sourceWorkspacePendingFile)" class="self-start rounded-lg bg-red-700 px-4 py-3 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-40 dark:focus:ring-offset-slate-950" x-text="sourceWorkspaceSaving ? 'Saving source…' : (sourceWorkspacePendingFile ? 'Save source and attach PDF' : 'Save source to paper')"></button>
                        </form>
                    </div>

                    <div x-show="sourceWorkspaceView === 'reader'" class="flex flex-col gap-5 px-4 py-5 sm:px-6">
                        <div>
                            <h3 class="break-words text-sm font-semibold leading-6 text-slate-950 dark:text-white" x-text="sourceWorkspaceActiveSource()?.title"></h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="sourceWorkspaceMetadata(sourceWorkspaceActiveSource())"></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <a x-show="sourceWorkspaceUrl(sourceWorkspaceActiveSource()?.full_text_url)" :href="sourceWorkspaceUrl(sourceWorkspaceActiveSource()?.full_text_url)" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:text-slate-200">Open available full text</a>
                            <a x-show="sourceWorkspaceEvidence().document?.url" :href="sourceWorkspaceEvidence().document?.url" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-600 dark:text-slate-200">Open original PDF</a>
                            <label class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><span x-text="sourceWorkspaceEvidence().document ? 'Replace attached PDF' : 'Attach source PDF'"></span><input type="file" accept="application/pdf,.pdf" @change="uploadSourceWorkspaceReaderPdf($event)" :disabled="sourceWorkspaceBusy()" class="max-w-full text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-slate-700 disabled:opacity-40 dark:file:bg-slate-800 dark:file:text-slate-200"></label>
                        </div>
                        <div x-show="sourceWorkspaceEvidence().document" class="flex flex-col gap-3">
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="sourceWorkspaceCoverageLabel()"></p>
                            <p x-show="sourceWorkspaceEvidence().document?.notice" class="text-xs leading-5 text-amber-800 dark:text-amber-200" x-text="sourceWorkspaceEvidence().document?.notice"></p>
                            <label class="flex items-center gap-3 text-xs font-semibold text-slate-700 dark:text-slate-200">Extracted page<select x-model.number="sourceWorkspacePage" @change="sourceWorkspaceQuote = ''; sourceWorkspacePassagePage = null" class="h-10 rounded-lg border-slate-300 bg-white text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><template x-for="(page, index) in sourceWorkspaceEvidence().document?.pages || []" :key="page.number"><option :value="index" x-text="`Page ${page.number}`"></option></template></select></label>
                            <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Select an exact excerpt from the page text to save it with its page number.</p>
                            <div data-source-page-text tabindex="0" @mouseup="captureSourceWorkspaceSelection($event)" @keyup="captureSourceWorkspaceSelection($event)" class="max-h-[26rem] min-h-32 overflow-y-auto whitespace-pre-wrap break-words rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm leading-7 text-slate-800 focus:outline-none focus:ring-2 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100" x-text="sourceWorkspaceCurrentPage()?.text || 'No extractable text is available on this page. Use the original PDF and save a reading note.'"></div>
                        </div>
                        <p x-show="!sourceWorkspaceEvidence().document" class="text-xs leading-5 text-slate-500 dark:text-slate-400">Attach a PDF to read its extracted text. You can also save a researcher supplied excerpt or reading note below.</p>
                        <form @submit.prevent="saveSourceWorkspacePassage()" class="flex flex-col gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200"><span x-text="sourceWorkspaceHasPdfText() ? 'Selected PDF excerpt' : 'Researcher supplied excerpt'"></span><textarea x-model="sourceWorkspaceQuote" :readonly="sourceWorkspaceHasPdfText()" rows="3" maxlength="3000" :placeholder="sourceWorkspaceHasPdfText() ? 'Select text from the extracted page above' : 'Copy an exact excerpt from the source'" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-6 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                            <label x-show="!sourceWorkspaceHasPdfText() && sourceWorkspaceQuote" class="flex items-center gap-3 text-xs font-semibold text-slate-700 dark:text-slate-200">Page, if known<input type="number" min="1" x-model.number="sourceWorkspacePassagePage" class="h-10 w-24 rounded-lg border-slate-300 bg-white text-xs focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></label>
                            <p x-show="sourceWorkspaceHasPdfText() && sourceWorkspaceQuote" class="text-xs text-slate-500 dark:text-slate-400" x-text="`Selected from page ${sourceWorkspacePassagePage}`"></p>
                            <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Reading note<textarea x-model="sourceWorkspaceNote" rows="2" maxlength="2000" placeholder="How this relates to your research, or what to check next" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-6 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                            <button type="submit" :disabled="!sourceWorkspaceCanSavePassage()" class="self-start rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40">Save excerpt or note</button>
                        </form>
                        <section class="flex flex-col gap-3 border-t border-slate-200 pt-4 dark:border-slate-700" aria-label="Saved source excerpts and notes">
                            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Saved excerpts &amp; notes</h4>
                            <p x-show="!sourceWorkspaceEvidence().passages.length" class="text-xs text-slate-500 dark:text-slate-400">Save an excerpt or note to start collecting evidence.</p>
                            <template x-for="passage in sourceWorkspaceEvidence().passages" :key="passage.id">
                                <article class="flex gap-3 border-b border-slate-200 py-3 dark:border-slate-700">
                                    <input type="checkbox" :checked="sourceWorkspacePassageSelected(sourceWorkspaceActiveSourceId, passage.id)" @change="toggleSourceWorkspacePassage(sourceWorkspaceActiveSourceId, passage)" :disabled="sourceWorkspaceBusy()" :aria-label="`Use ${passage.kind === 'note' ? 'reading note' : 'excerpt'} ${passage.id} for AI help`" class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-red-700 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900">
                                    <div class="flex min-w-0 flex-1 flex-col gap-2">
                                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400" x-text="sourceWorkspacePassageLabel(passage)"></p>
                                        <blockquote x-show="passage.quote" class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700 dark:text-slate-200" x-text="passage.quote"></blockquote>
                                        <p x-show="passage.note" class="whitespace-pre-wrap break-words text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="passage.note"></p>
                                        <button type="button" @click="deleteSourceWorkspacePassage(passage)" :disabled="sourceWorkspaceBusy()" class="self-start rounded px-1 py-1 text-xs text-slate-500 hover:text-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:text-slate-400 dark:hover:text-red-200">Delete excerpt or note</button>
                                    </div>
                                </article>
                            </template>
                        </section>
                    </div>

                    <div x-show="sourceWorkspaceView === 'draft'" class="flex flex-col gap-5 px-4 py-5 sm:px-6">
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white" x-text="sourceWorkspaceDraftMode === 'synthesize' ? 'Review the synthesis draft' : 'Review the evidence response'"></h3>
                        <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">AI response<textarea x-model="sourceWorkspaceDraft.paragraph" rows="9" maxlength="5000" :disabled="sourceWorkspaceBusy() || sourceWorkspaceDraftInserted" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-7 focus:border-red-600 focus:ring-red-600 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                        <section class="flex flex-col gap-3" aria-label="Evidence used in this response">
                            <h4 class="text-xs font-semibold text-slate-800 dark:text-slate-200">Evidence used</h4>
                            <template x-for="(evidence, index) in sourceWorkspaceDraft?.evidence || []" :key="`${evidence.source_link_id}-${evidence.passage_id}-${index}`">
                                <article class="flex flex-col gap-2 border-l-2 border-slate-300 pl-3 dark:border-slate-600">
                                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200" x-text="evidence.title"></p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="sourceWorkspacePassageLabel(evidence)"></p>
                                    <blockquote class="whitespace-pre-wrap break-words text-xs leading-5 text-slate-600 dark:text-slate-300" x-text="evidence.quote || evidence.note"></blockquote>
                                </article>
                            </template>
                        </section>
                        <div x-show="sourceWorkspaceDraftMode === 'synthesize'" class="flex flex-col items-start gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                            <label class="flex w-full flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Add reviewed text to<select aria-label="Add reviewed text to" x-model="sourceWorkspaceDestination" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="related-literature">Review of Related Literature</option><option value="introduction">RRL opening paragraphs</option><option value="rationale">Rationale</option><option value="methodology-research_design">Research Design</option><option value="methodology-data_analysis">Data Analysis</option></select></label>
                            <p x-show="!sourceWorkspaceDraft?.can_insert" class="text-xs leading-5 text-amber-800 dark:text-amber-200">The selected evidence does not support an insertable synthesis. Review the response and choose stronger source excerpts.</p>
                            <button type="button" @click="insertReviewedSourceWorkspaceDraft()" :disabled="!sourceWorkspaceCanInsertDraft()" class="rounded-lg bg-red-700 px-4 py-3 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-40 dark:focus:ring-offset-slate-950" x-text="sourceWorkspaceDraftInserted ? 'Reviewed draft inserted' : 'Insert reviewed draft'"></button>
                        </div>
                        <p x-show="sourceWorkspaceDraftMode !== 'synthesize'" class="text-xs leading-5 text-slate-500 dark:text-slate-400">This response helps you review the evidence. Choose “Draft synthesis” to prepare text for the paper.</p>
                    </div>
                </main>

                <aside class="flex flex-col gap-4 border-t border-slate-200 bg-slate-50 px-4 py-5 dark:border-slate-700 dark:bg-slate-900/40 sm:px-6 lg:border-l lg:border-t-0 lg:px-4" aria-label="Selected evidence and AI help">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Selected evidence</h3>
                        <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="`${sourceWorkspaceSelectedPassages.length} of 12 passages selected`"></p>
                    </div>
                    <p x-show="!sourceWorkspaceSelectedPassages.length" class="text-xs leading-5 text-slate-500 dark:text-slate-400">Open a source, save an excerpt or note, then select it for AI help. You can combine evidence from up to 5 sources.</p>
                    <div class="flex flex-col gap-3">
                        <template x-for="passage in sourceWorkspaceSelectedPassages" :key="`${passage.source_link_id}-${passage.passage_id}`">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1"><p class="break-words text-xs font-semibold leading-5 text-slate-700 dark:text-slate-200" x-text="passage.title"></p><p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400" x-text="sourceWorkspacePassageLabel(passage)"></p></div>
                                <button type="button" @click="removeSourceWorkspaceSelectedPassage(passage.source_link_id, passage.passage_id)" :disabled="sourceWorkspaceBusy()" :aria-label="`Remove selected passage from ${passage.title}`" class="rounded px-1 text-slate-500 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:opacity-40 dark:text-slate-400">×</button>
                            </div>
                        </template>
                    </div>
                    <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">AI help<select aria-label="AI help" x-model="sourceWorkspaceAiMode" @change="invalidateSourceWorkspaceDraft()" class="h-11 rounded-lg border-slate-300 bg-white text-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"><option value="explain">Explain excerpts</option><option value="support">Check support for a claim</option><option value="synthesize">Draft synthesis</option></select></label>
                    <label x-show="sourceWorkspaceAiMode === 'support'" class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">Claim to check<textarea x-model="sourceWorkspaceClaim" @input="invalidateSourceWorkspaceDraft()" rows="3" maxlength="3000" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-6 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                    <label class="flex flex-col gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200">What should Athena focus on?<textarea x-model="sourceWorkspaceInstruction" @input="invalidateSourceWorkspaceDraft()" rows="3" maxlength="2000" placeholder="Optional focus or question" class="rounded-lg border-slate-300 bg-white text-sm font-normal leading-6 focus:border-red-600 focus:ring-red-600 dark:border-slate-600 dark:bg-slate-900 dark:text-white"></textarea></label>
                    <button type="button" @click="prepareSourceWorkspaceAssistance()" :disabled="!sourceWorkspaceCanAssist()" class="rounded-lg bg-red-700 px-3 py-3 text-xs font-semibold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-40 dark:focus:ring-offset-slate-950" x-text="sourceWorkspaceAiLoading ? 'Preparing evidence response…' : (sourceWorkspaceAiMode === 'support' ? 'Check support' : (sourceWorkspaceAiMode === 'synthesize' ? 'Draft synthesis' : 'Explain excerpts'))"></button>
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Athena uses your selected excerpts and notes. Review the evidence and wording before adding a synthesis to the paper.</p>
                </aside>
            </div>
        </div>
    </section>
</div>
