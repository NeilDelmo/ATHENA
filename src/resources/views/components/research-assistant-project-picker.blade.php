@props(['id'])

<div
    x-data="{ open: false, query: '', get projects() { return $store.researchAssistant.contextOptions.filter(project => [project.label, project.meta].join(' ').toLowerCase().includes(this.query.trim().toLowerCase())); } }"
    x-show="$store.researchAssistant.hasContextOptions()"
    x-cloak
    @click.outside="open = false"
    @keydown.escape.stop.prevent="open = false; $refs.trigger.focus()"
    x-effect="if (!$store.researchAssistant.{{ $id === 'drawer' ? 'drawerOpen' : 'workspaceOpen' }}) open = false"
    class="relative min-w-0 max-w-full"
    data-assistant-project-picker
>
    <div class="flex min-w-0 items-center rounded-full bg-gray-100 dark:bg-slate-800">
        <button
            x-ref="trigger"
            type="button"
            @click="open = !open; query = ''; $store.researchAssistant.closeDocumentPicker(); if (open) $nextTick(() => $refs.search.focus())"
            :aria-expanded="open"
            aria-controls="assistant-projects-{{ $id }}"
            :aria-label="$store.researchAssistant.contextEnabled ? `Change project: ${$store.researchAssistant.selectedContext()?.label || ''}` : 'Add project context'"
            :title="$store.researchAssistant.contextEnabled ? $store.researchAssistant.selectedContext()?.label : 'Use a project’s saved records in this chat'"
            class="inline-flex min-h-11 min-w-0 items-center gap-2 rounded-full px-3 text-xs font-medium text-gray-700 transition hover:bg-gray-200/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-200 dark:hover:bg-slate-700"
        >
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z" /></svg>
            <span class="max-w-[8rem] truncate sm:max-w-[14rem]" x-text="$store.researchAssistant.contextEnabled ? $store.researchAssistant.selectedContext()?.label : 'Add project'"></span>
            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
        </button>
        <button x-show="$store.researchAssistant.contextEnabled" type="button" @click="$store.researchAssistant.selectProjectContext(null); open = false; $refs.trigger.focus()" class="inline-flex h-11 w-9 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-200/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 dark:text-slate-400 dark:hover:bg-slate-700" aria-label="Remove project context" title="Remove project context">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
    </div>

    <div id="assistant-projects-{{ $id }}" x-show="open" x-cloak x-transition.opacity.duration.150ms class="absolute bottom-full left-0 z-20 mb-3 flex max-h-[min(32rem,calc(100dvh-18rem))] w-[28rem] max-w-[calc(100vw-5rem)] flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
        <div class="shrink-0 border-b border-gray-100 p-4 dark:border-slate-800">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">Choose a project</p>
                <span class="text-xs text-gray-500 dark:text-slate-400" x-text="`${projects.length} ${projects.length === 1 ? 'project' : 'projects'}`"></span>
            </div>
            <div class="flex items-center gap-2 rounded-xl bg-gray-100 px-3 ring-1 ring-transparent transition focus-within:ring-gray-400 dark:bg-slate-800 dark:focus-within:ring-slate-500">
                <svg class="h-4 w-4 shrink-0 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                <label for="assistant-project-search-{{ $id }}" class="sr-only">Search projects</label>
                <input x-ref="search" id="assistant-project-search-{{ $id }}" type="search" x-model="query" placeholder="Search your projects…" autocomplete="off" class="min-w-0 w-full border-0 bg-transparent px-0 py-2.5 text-sm text-gray-900 placeholder:text-gray-500 focus:ring-0 dark:text-white dark:placeholder:text-slate-400">
            </div>
            <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-slate-400">All accessible projects · Search by title, category, or year.</p>
        </div>
        <div class="min-h-0 max-h-[min(24rem,38dvh)] space-y-1.5 overflow-y-auto overscroll-contain p-2">
            <template x-for="project in projects" :key="project.id">
                <button data-assistant-project-option type="button" @click="$store.researchAssistant.selectProjectContext(project.id); open = false; $refs.trigger.focus()" :aria-pressed="$store.researchAssistant.contextEnabled && Number($store.researchAssistant.selectedContextId) === Number(project.id)" class="group flex w-full items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/60 p-3 text-left transition hover:border-gray-300 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-500 aria-pressed:border-red-200 aria-pressed:bg-red-50 dark:border-slate-800 dark:bg-slate-800/30 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:aria-pressed:border-red-800 dark:aria-pressed:bg-red-950/40">
                    <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 sm:inline-flex" aria-hidden="true">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5"/></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span data-assistant-project-title class="block whitespace-normal break-words text-sm font-semibold leading-6 text-gray-900 dark:text-slate-100" x-text="project.label"></span>
                        <span x-show="project.meta" class="mt-1.5 block whitespace-normal break-words text-xs leading-5 text-gray-500 dark:text-slate-400" x-text="project.meta"></span>
                        <span class="mt-2 flex flex-wrap items-center gap-2">
                            <span x-show="project.status" class="inline-flex rounded-md bg-gray-200/70 px-2 py-1 text-xs font-medium capitalize leading-4 text-gray-600 dark:bg-slate-700 dark:text-slate-200" x-text="project.status"></span>
                            <span x-show="$store.researchAssistant.contextEnabled && Number($store.researchAssistant.selectedContextId) === Number(project.id)" class="inline-flex items-center gap-1 text-xs font-medium text-red-700 dark:text-red-300">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                                Selected
                            </span>
                        </span>
                    </span>
                </button>
            </template>
            <p x-show="!projects.length" role="status" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-slate-400">No matching projects.</p>
        </div>
    </div>
</div>
