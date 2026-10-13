@props(['projects', 'preferences', 'completedOnly' => false])

<section
    x-data="projectWorkspace(@js(['projects' => $projects, 'workspace' => $preferences, 'saveUrl' => route('research_head.projects.workspace.update')]))"
    @keydown.escape.window="endDrag(); closePreview()"
    x-cloak
    data-project-workspace
    aria-labelledby="workspace-heading"
    class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
>
    <svg class="hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <symbol id="workspace-folder-art" viewBox="0 0 80 64">
                <path d="M5 13a5 5 0 0 1 5-5h20l7 7h33a5 5 0 0 1 5 5v32a5 5 0 0 1-5 5H10a5 5 0 0 1-5-5Z" fill="#D99417" />
                <path d="M9 23h62v28H9Z" fill="#FFF4C7" />
                <path d="M4 25a4 4 0 0 1 4-4h64a4 4 0 0 1 4 4l-4 29a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4Z" fill="#F5C451" stroke="#D99417" stroke-width="1.5" />
                <path d="M9 27h61" stroke="#FFE8A0" stroke-width="2" />
            </symbol>
            <symbol id="workspace-file-art" viewBox="0 0 56 64">
                <path d="M8 2h27l13 13v45a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="#FFF" stroke="#94A3B8" stroke-width="1.5" />
                <path d="M35 2v13h13M15 24h25M15 30h25M15 36h18" fill="none" stroke="#CBD5E1" stroke-width="2" />
            </symbol>
        </defs>
    </svg>

    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
        <div>
            <h2 id="workspace-heading" class="text-xl font-bold text-slate-950 dark:text-white">{{ $completedOnly ? 'Completed project records' : 'Project library' }}</h2>
            <p class="mt-1 text-base text-slate-500 dark:text-slate-400">Browse year folders, open a project, then explore its documents.</p>
        </div>
        <span class="text-sm text-slate-500 dark:text-slate-400" role="status" x-text="saving ? 'Saving…' : 'Your personal workspace'"></span>
    </header>

    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-700" aria-label="File actions">
        <button x-show="!activeProject" type="button" class="workspace-primary" :disabled="saving || stale || !!activeProject" @click="openCreate(); $dispatch('open-modal', 'project-workspace-dialog')">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7V4h6l2 3h10v13H3V7Zm9 4v6m-3-3h6" /></svg>
            New folder
        </button>
        <button x-show="canOpenSelection" type="button" class="workspace-secondary" :disabled="!canOpenSelection" @click="openSelection(); if (previewDocument) $dispatch('open-modal', 'project-workspace-preview')">Open</button>
        <button x-show="canMoveSelection" type="button" class="workspace-secondary" :disabled="saving || stale || !canMoveSelection" @click="openMove(); $dispatch('open-modal', 'project-workspace-dialog')">Move to…</button>
        <button x-show="selectedFolder" type="button" class="workspace-secondary" :disabled="saving || stale || !selectedFolder" @click="openRename(selectedFolder); $dispatch('open-modal', 'project-workspace-dialog')">Rename</button>
        <button x-show="selectedFolder" type="button" class="workspace-secondary" :disabled="saving || stale || !selectedFolder" @click="openDelete(selectedFolder); $dispatch('open-modal', 'project-workspace-dialog')">Remove folder</button>
        <button x-show="undoWorkspace" type="button" class="workspace-icon" :disabled="!canUndo" @click="undo()" aria-label="Undo" title="Undo last change">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m8 5-5 5 5 5M3 10h10a7 7 0 0 1 7 7" /></svg>
        </button>
        <div class="ml-auto flex items-center gap-1" aria-label="View">
            <button type="button" class="workspace-view" :aria-pressed="view === 'grid'" :disabled="saving || stale" @click="setPreference('view', 'grid')">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z" /></svg>Icons
            </button>
            <button type="button" class="workspace-view" :aria-pressed="view === 'list'" :disabled="saving || stale" @click="setPreference('view', 'list')">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M8 5h13M8 12h13M8 19h13M3 5h1M3 12h1M3 19h1" /></svg>Details
            </button>
        </div>
    </div>

    <div class="flex min-w-0 flex-wrap items-center gap-2 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
        <div class="flex shrink-0 gap-1">
            <button type="button" class="workspace-icon" :disabled="!canGoBack" @click="back()" aria-label="Back" title="Back"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m12 5-7 7 7 7M5 12h15" /></svg></button>
            <button type="button" class="workspace-icon" :disabled="!canGoUp" @click="up()" aria-label="Up" title="Up one folder"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m5 12 7-7 7 7M12 5v15" /></svg></button>
        </div>
        <nav aria-label="Folder path" class="flex min-h-11 min-w-0 flex-1 items-center gap-2 overflow-x-auto rounded-lg border border-slate-200 px-3 text-base dark:border-slate-700">
            <button type="button" @click="openLocation('all')" class="shrink-0 font-medium text-slate-700 dark:text-slate-200">Project library</button>
            <template x-for="crumb in breadcrumbs" :key="crumb.id">
                <span class="flex min-w-0 shrink-0 items-center gap-2"><span class="text-slate-400" aria-hidden="true">›</span><button type="button" @click="openLocation(crumb.id)" class="max-w-[230px] truncate text-slate-700 dark:text-slate-200" :title="crumb.name" x-text="crumb.name"></button></span>
            </template>
        </nav>
        <label class="relative w-full shrink-0 sm:w-60">
            <svg class="pointer-events-none absolute left-3 top-3 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></svg>
            <input type="search" x-model="query" @input="page = 1" aria-label="Search this location" :placeholder="activeProject ? 'Search project documents' : 'Project title or leader'" class="workspace-input w-full pl-10">
        </label>
    </div>

    <div class="grid min-w-0 lg:grid-cols-[200px_minmax(0,1fr)]">
        <nav aria-label="Project folders" class="hidden max-h-[640px] overflow-y-auto border-r border-slate-200 bg-slate-50/60 p-3 dark:border-slate-700 dark:bg-slate-950/30 lg:block">
            <button type="button" class="workspace-nav" :class="{'workspace-drop-target': dropTarget === 'root'}" :aria-current="location === 'all' ? 'location' : null" @click="openLocation('all')" @dragover.stop="dragOver($event, null)" @dragleave.stop="dragLeave($event, null)" @drop.stop="drop($event, null)">
                <svg class="h-5 w-6 shrink-0" aria-hidden="true"><use href="#workspace-folder-art" /></svg><span class="truncate">Project library</span>
            </button>
            <button type="button" class="workspace-nav" :aria-current="location === 'unfiled' ? 'location' : null" @click="openLocation('unfiled')" @dragover.stop="dragOver($event, null)" @drop.stop="drop($event, null)"><span class="flex-1">Unfiled projects</span><span class="workspace-count" x-text="unfiledCount"></span></button>
            <details class="mt-4" open>
                <summary class="disclosure-summary min-h-11 cursor-pointer px-3 py-2 text-base font-semibold text-slate-700 dark:text-slate-200">Years</summary>
                <template x-for="item in years" :key="item.year">
                    <button type="button" class="workspace-nav" :aria-current="location === 'year:' + item.year ? 'location' : null" @click="openLocation('year:' + item.year)"><svg class="h-5 w-6 shrink-0" aria-hidden="true"><use href="#workspace-folder-art" /></svg><span x-text="item.year"></span><span class="workspace-count" x-text="item.count"></span></button>
                </template>
            </details>
            <details class="mt-4" open>
                <summary class="disclosure-summary min-h-11 cursor-pointer px-3 py-2 text-base font-semibold text-slate-700 dark:text-slate-200">My folders</summary>
                <p x-show="!folders.length" class="px-3 py-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Use New folder to start organizing.</p>
                <template x-for="folder in folderTree" :key="folder.id">
                    <button type="button" class="workspace-nav" :class="{'workspace-drop-target': dropTarget === folder.id}" :style="{paddingLeft: (12 + Math.min(folder.depth, 5) * 12) + 'px'}" :aria-current="location === folder.id ? 'location' : null" :aria-label="'Open folder ' + folder.name" :title="folderPath(folder.id).map(item => item.name).join(' / ')" @click="openLocation(folder.id)" @dragover.stop="dragOver($event, folder.id)" @dragleave.stop="dragLeave($event, folder.id)" @drop.stop="drop($event, folder.id)"><svg class="h-5 w-6 shrink-0" aria-hidden="true"><use href="#workspace-folder-art" /></svg><span class="truncate" x-text="folder.name"></span><span class="workspace-count" x-text="folderCount(folder.id)"></span></button>
                </template>
            </details>
        </nav>

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                <label class="flex w-full min-w-0 flex-none items-center gap-2 lg:hidden"><span class="shrink-0 text-sm text-slate-500">Go to</span><select :value="location" x-effect="const selectedLocation = location; $nextTick(() => { $el.value = selectedLocation })" @change="openLocation($event.target.value)" aria-label="Go to folder" class="workspace-input min-w-0 flex-1"><option value="all">Project library</option><option value="unfiled">Unfiled projects</option><template x-for="item in years" :key="item.year"><option :value="'year:' + item.year" x-text="item.year"></option></template><template x-for="folder in folderTree" :key="folder.id"><option :value="folder.id" x-text="'— '.repeat(folder.depth) + folder.name"></option></template><template x-if="activeProject"><option :value="location" x-text="activeProject.title"></option></template></select></label>
                <span class="hidden min-w-0 flex-1 truncate text-base font-semibold text-slate-800 dark:text-slate-100 lg:block" :title="locationLabel" x-text="locationLabel"></span>
                <label x-show="location === 'all'" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">Browse<select :value="grouping" @change="setPreference('grouping', $event.target.value)" :disabled="saving || stale" aria-label="Browse projects" class="workspace-input max-w-[180px]"><option value="year">Year folders</option><option value="none">All projects</option></select></label>
                <label x-show="!activeProject" class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">Sort<select :value="sort" @change="setPreference('sort', $event.target.value)" :disabled="saving || stale" aria-label="Sort items" class="workspace-input max-w-[180px]"><option value="attention">Needs attention</option><option value="title">Name</option><option value="newest">Newest first</option><option value="oldest">Oldest first</option></select></label>
            </div>

            <div x-show="error || notice" class="flex flex-wrap items-center gap-3 border-b border-slate-100 px-5 py-3 text-base dark:border-slate-800" :class="error ? 'text-red-700 dark:text-red-300' : 'text-slate-600 dark:text-slate-300'" role="status"><span class="min-w-0 flex-1" x-text="error || notice"></span><button x-show="stale" type="button" class="workspace-secondary" @click="window.location.reload()">Reload workspace</button></div>

            <template x-if="activeProject">
                <div data-project-summary class="border-b border-slate-200 bg-slate-50/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-950/30">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 flex-1"><h3 class="text-lg font-semibold leading-7 text-slate-950 dark:text-white" x-text="activeProject.title"></h3><p class="mt-1 text-base leading-6 text-slate-500 dark:text-slate-400" x-text="activeProject.researcher"></p></div><a class="workspace-primary" :href="activeProject.url" :aria-label="activeProject.action_label + ': ' + activeProject.title" x-text="activeProject.action_label"></a></div>
                    <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-base text-slate-600 dark:text-slate-300"><span class="workspace-status" :class="{'workspace-completed': activeProject.status === 'completed', 'workspace-delayed': activeProject.status === 'delayed', 'workspace-pending': activeProject.status === 'completion_pending'}" x-text="activeProject.status_label"></span><span x-text="activeProject.progress == null ? 'No progress report yet' : activeProject.progress + '% progress'"></span><span x-text="activeProject.reports + (activeProject.reports === 1 ? ' report' : ' reports')"></span><span :class="{'font-semibold text-red-700 dark:text-red-300': activeProject.pending > 0}" x-text="activeProject.pending + ' awaiting review'"></span></div>
                    <p class="mt-2 text-base leading-6 text-slate-500 dark:text-slate-400" x-text="'Project secretary: ' + (activeProject.secretary || 'Awaiting selection') + ' · ' + activeProject.latest_label"></p>
                </div>
            </template>

            <div data-workspace-items class="min-h-[400px] max-h-[640px] overflow-y-auto px-4 py-5 sm:px-5" :class="{'workspace-drop-target': !activeProject && dropTarget === (currentFolder ? currentFolder.id : 'root')}" @click="if (!$event.target.closest('[data-workspace-item]')) clearSelection()" @dragover.stop="if (!activeProject && !location.startsWith('year:') && !$event.target.closest('[data-workspace-item]')) dragOver($event, currentFolder?.id || null)" @dragleave="dragLeave($event, currentFolder?.id || null)" @drop.stop="if (!activeProject && !location.startsWith('year:') && !$event.target.closest('[data-workspace-item]')) drop($event, currentFolder?.id || null)">
                <template x-if="!activeProject">
                    <div>
                        <div :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'">
                            <template x-if="location === 'all' && grouping === 'year' && !query && statusFilter === 'all'">
                                <div class="contents">
                                    <template x-for="item in years" :key="item.year">
                                        <div role="button" tabindex="0" class="workspace-item" data-workspace-item data-workspace-year data-item-type="year" :data-item-id="item.year" :class="{'workspace-selected': selectedYear === item.year}" :aria-pressed="selectedYear === item.year" :aria-label="'Select year ' + item.year" @click.stop="selectYear(item.year, $event)" @dblclick.stop="openLocation('year:' + item.year)" @keydown="handleItemKeydown($event, 'year', item.year)">
                                            <svg class="workspace-item-graphic" aria-hidden="true"><use href="#workspace-folder-art" /></svg><span class="workspace-item-name" x-text="item.year"></span><span class="workspace-item-meta" x-text="item.count + (item.count === 1 ? ' project' : ' projects')"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-for="folder in childFolders" :key="folder.id">
                                <div role="button" tabindex="0" class="workspace-item" data-workspace-item data-workspace-folder data-item-type="folder" :data-item-id="folder.id" :class="{'workspace-selected': selectedFolderId === folder.id, 'workspace-drop-target': dropTarget === folder.id}" :aria-pressed="selectedFolderId === folder.id" :aria-label="'Select folder ' + folder.name" :draggable="!saving && !stale" @click.stop="selectFolder(folder.id, $event)" @dblclick.stop="openLocation(folder.id)" @keydown="handleItemKeydown($event, 'folder', folder.id)" @dragstart.stop="startFolderDrag($event, folder.id)" @dragend.stop="endDrag()" @dragover.stop="dragOver($event, folder.id)" @dragleave.stop="dragLeave($event, folder.id)" @drop.stop="drop($event, folder.id)">
                                    <svg class="workspace-item-graphic" aria-hidden="true"><use href="#workspace-folder-art" /></svg><span class="workspace-item-name" :title="folder.name" x-text="folder.name"></span><span class="workspace-item-meta" x-text="folderCount(folder.id) + ' projects'"></span>
                                </div>
                            </template>
                            <template x-if="location !== 'all' || grouping !== 'year' || query || statusFilter !== 'all'">
                                <div class="contents">
                                    <template x-for="project in pagedProjects" :key="project.id">
                                        <div x-data="{posterFailed: false}" role="button" tabindex="0" class="workspace-item" data-workspace-item data-workspace-project data-item-type="project" :data-item-id="project.id" :class="{'workspace-selected': selectedIds.includes(project.id)}" :aria-pressed="selectedIds.includes(project.id)" :aria-label="'Select project ' + project.title" :title="project.title" :draggable="!saving && !stale" @click.stop="selectProject(project.id, $event)" @dblclick.stop="activateProject(project.id, $event)" @keydown="handleItemKeydown($event, 'project', project.id)" @dragstart.stop="startProjectDrag($event, project.id)" @dragend.stop="endDrag()" @dragover.stop @drop.stop.prevent="endDrag()">
                                            <span class="workspace-project-cover" aria-hidden="true" data-workspace-project-cover><svg class="workspace-item-graphic"><use href="#workspace-folder-art" /></svg><template x-if="project.poster_url && !posterFailed"><img :src="project.poster_url" alt="" loading="lazy" draggable="false" x-on:error="posterFailed = true"></template></span><span class="workspace-item-name" x-text="project.title"></span><span class="workspace-item-meta" :class="{'!text-emerald-700 dark:!text-emerald-300': project.status === 'completed'}" x-text="project.status_label"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div x-show="!filteredProjects.length && (query || (!childFolders.length && !(location === 'all' && grouping === 'year' && years.length)))" class="flex min-h-[260px] flex-col items-center justify-center gap-3 text-center"><svg class="h-16 w-20 opacity-60" aria-hidden="true"><use href="#workspace-folder-art" /></svg><p class="text-lg font-semibold text-slate-700 dark:text-slate-200" x-text="query ? 'No matching projects' : 'This folder is empty'"></p><p class="max-w-sm text-base leading-6 text-slate-500 dark:text-slate-400" x-text="query ? 'Try another project title or leader name, or search from Project library.' : 'Drag a project folder here, or select a project and use Move to.'"></p></div>
                        <div x-show="pageCount > 1 && (location !== 'all' || grouping !== 'year' || query)" class="mt-5 flex items-center justify-end gap-3"><button type="button" class="workspace-secondary" :disabled="page <= 1" @click="setPage(page - 1)">Previous</button><span class="text-base text-slate-500" x-text="page + ' / ' + pageCount"></span><button type="button" class="workspace-secondary" :disabled="page >= pageCount" @click="setPage(page + 1)">Next</button></div>
                    </div>
                </template>

                <template x-if="activeProject">
                    <div>
                        <p x-show="documentsLoading" role="status" class="py-20 text-center text-base text-slate-500 dark:text-slate-400">Loading project documents…</p>
                        <div x-show="documentsError" role="alert" class="rounded-lg bg-red-50 p-5 text-base text-red-800 dark:bg-red-950/30 dark:text-red-300"><p x-text="documentsError"></p><button type="button" class="workspace-secondary mt-3" @click="retryDocuments()">Try again</button></div>
                        <template x-for="group in documentGroups" :key="group.key">
                            <section class="mb-7 last:mb-0">
                                <h3 class="mb-3 border-b border-slate-100 pb-2 text-base font-semibold text-slate-700 dark:border-slate-800 dark:text-slate-200" x-text="group.label"></h3>
                                <div :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'">
                                    <template x-for="document in group.documents" :key="document.key">
                                        <div x-data="{thumbnailFailed: false}" role="button" tabindex="0" class="workspace-item" data-workspace-item data-workspace-document data-item-type="document" :data-item-id="document.key" :class="{'workspace-selected': selectedDocument?.key === document.key}" :aria-pressed="selectedDocument?.key === document.key" :aria-label="'Select document ' + document.filename" :title="document.filename" @click.stop="selectDocument(document.key, $event)" @dblclick.stop="openDocument(document.key); $dispatch('open-modal', 'project-workspace-preview')" @keydown="handleItemKeydown($event, 'document', document.key); if ($event.key === 'Enter' && previewDocument) $dispatch('open-modal', 'project-workspace-preview')">
                                            <template x-if="document.preview_kind === 'image' && !thumbnailFailed"><span class="workspace-image-graphic" aria-hidden="true"><img :src="document.thumbnail_url || document.view_url" alt="" loading="lazy" draggable="false" x-on:error="thumbnailFailed = true"></span></template>
                                            <template x-if="document.preview_kind !== 'image' || thumbnailFailed"><span class="workspace-file-graphic" aria-hidden="true"><svg><use href="#workspace-file-art" /></svg><span class="workspace-file-type" :class="{'!bg-blue-700': document.filename?.toLowerCase().endsWith('.docx'), '!bg-emerald-700': document.preview_kind === 'image'}" x-text="document.preview_kind === 'image' ? document.filename.split('.').pop().toUpperCase() : (document.filename?.toLowerCase().endsWith('.docx') ? 'DOCX' : 'PDF')"></span></span></template>
                                            <span class="workspace-item-name" x-text="document.filename"></span><span class="workspace-item-meta" x-text="document.title"></span>
                                        </div>
                                    </template>
                                </div>
                            </section>
                        </template>
                        <div x-show="!documentsLoading && !documentsError && !documentGroups.length" class="flex min-h-[260px] flex-col items-center justify-center gap-3 text-center"><svg class="h-16 w-14 opacity-60" aria-hidden="true"><use href="#workspace-file-art" /></svg><p class="text-lg font-semibold text-slate-700 dark:text-slate-200" x-text="query ? 'No matching documents' : 'No documents available'"></p><p class="text-base text-slate-500 dark:text-slate-400">Available project papers and reports appear here.</p></div>
                    </div>
                </template>
            </div>

            <div x-show="selectedProject || selectedDocument || selectedFolder || selectedYear" class="border-t border-slate-200 bg-slate-50/60 px-5 py-4 dark:border-slate-700 dark:bg-slate-950/30" aria-label="Item details">
                <template x-if="selectedDocument">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div class="min-w-0 flex-1"><p class="break-words text-base font-semibold text-slate-900 dark:text-white" x-text="selectedDocument.filename"></p><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="selectedDocument.source + (selectedDocument.uploaded_by ? ' · ' + selectedDocument.uploaded_by : '')"></p></div><button type="button" class="workspace-secondary" @click="openDocument(selectedDocument.key); $dispatch('open-modal', 'project-workspace-preview')">Preview</button><a class="workspace-secondary" :href="selectedDocument.download_url" :download="selectedDocument.generated_comment_response ? selectedDocument.filename : null">Download</a></div>
                </template>
                <template x-if="!selectedDocument && selectedProject && !activeProject">
                    <div>
                        <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 flex-1"><p class="text-base font-semibold leading-6 text-slate-950 dark:text-white" x-text="(selectedProject || activeProject).title"></p><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="(selectedProject || activeProject).researcher"></p></div><a class="workspace-primary" :href="(selectedProject || activeProject).url" :aria-label="(selectedProject || activeProject).action_label + ': ' + (selectedProject || activeProject).title" x-text="(selectedProject || activeProject).action_label"></a></div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-600 dark:text-slate-300"><span class="workspace-status" :class="{'workspace-completed': (selectedProject || activeProject).status === 'completed', 'workspace-delayed': (selectedProject || activeProject).status === 'delayed', 'workspace-pending': (selectedProject || activeProject).status === 'completion_pending'}" x-text="(selectedProject || activeProject).status_label"></span><span x-text="(selectedProject || activeProject).progress == null ? 'No progress report yet' : (selectedProject || activeProject).progress + '% progress'"></span><span x-text="selectedProject.reports + (selectedProject.reports === 1 ? ' report' : ' reports')"></span><span x-text="(selectedProject || activeProject).pending + ' awaiting review'"></span></div>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="'Project secretary: ' + ((selectedProject || activeProject).secretary || 'Awaiting selection') + ' · ' + (selectedProject || activeProject).latest_label"></p>
                    </div>
                </template>
                <p x-show="!selectedDocument && !selectedProject && !activeProject" class="text-base text-slate-600 dark:text-slate-300" x-text="selectedFolder ? selectedFolder.name + ' · Personal folder' : selectedYear + ' · Implementation year'"></p>
            </div>
            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-5 py-3 text-sm leading-6 text-slate-500 dark:border-slate-800 dark:text-slate-400"><span x-text="selectedCount ? selectedCount + ' selected' : (activeProject ? documentGroups.reduce((count, group) => count + group.documents.length, 0) + ' documents' : (location === 'all' && grouping === 'year' && !query ? years.length + childFolders.length + ' folders' : filteredProjects.length + ' projects'))"></span><span>Select to see details. Double-click or use Open. Ctrl / ⌘ selects multiple projects.</span></footer>
        </div>
    </div>

    <x-modal name="project-workspace-dialog" maxWidth="md" focusable>
        <form @submit.prevent="submitDialog().then(() => { if (!dialog) { $dispatch('close-modal', 'project-workspace-dialog'); $nextTick(() => { if (trigger && !trigger.offsetParent) $el.closest('[data-project-workspace]').querySelector('[aria-label=&quot;File actions&quot;] button').focus() }) } })" :tabindex="saving ? 0 : null" class="p-6" role="dialog" aria-modal="true" aria-labelledby="workspace-dialog-heading">
            <h2 id="workspace-dialog-heading" class="text-xl font-bold text-slate-950 dark:text-white" x-text="({create: 'New folder', rename: 'Rename folder', move: 'Move to a folder', delete: 'Remove folder'})[dialog]"></h2>
            <div x-show="dialog === 'create' || dialog === 'rename'" class="mt-5"><p x-show="dialog === 'create'" class="mb-3 text-sm text-slate-500 dark:text-slate-400" x-text="'In: ' + (currentFolder ? currentFolder.name : 'Project library')"></p><label for="workspace-folder-name" class="block text-base font-medium text-slate-700 dark:text-slate-300">Folder name</label><input id="workspace-folder-name" x-model="folderName" maxlength="80" type="text" :disabled="saving || !(dialog === 'create' || dialog === 'rename')" :required="dialog === 'create' || dialog === 'rename'" class="workspace-input mt-2 w-full" autocomplete="off"></div>
            <div x-show="dialog === 'move'" class="mt-5"><label for="workspace-destination" class="block text-base font-medium text-slate-700 dark:text-slate-300">Destination</label><select id="workspace-destination" x-model="destinationId" :disabled="saving || dialog !== 'move'" class="workspace-input mt-2 w-full"><option value="">Unfiled / top level</option><template x-for="folder in destinationFolders" :key="folder.id"><option :value="folder.id" x-text="'— '.repeat(folder.depth) + folder.name"></option></template></select></div>
            <p x-show="dialog === 'delete'" class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">Remove this personal folder and its subfolders? Projects return to Unfiled. Their records and documents are kept.</p>
            <p x-show="error" role="alert" class="mt-3 text-base text-red-700 dark:text-red-300" x-text="error"></p>
            <div class="mt-6 flex justify-end gap-3"><button type="button" class="workspace-secondary" :disabled="saving" @click="closeDialog(); $dispatch('close-modal', 'project-workspace-dialog')">Cancel</button><button type="submit" class="workspace-primary" :disabled="saving || stale" x-text="saving ? 'Saving…' : ({create: 'Create folder', rename: 'Save name', move: 'Move', delete: 'Remove folder'})[dialog]"></button></div>
        </form>
    </x-modal>

    <x-modal name="project-workspace-preview" maxWidth="6xl" focusable>
        <template x-if="show && previewDocument">
            <section x-data="{imageFailed: false}" role="dialog" aria-modal="true" aria-labelledby="workspace-preview-heading">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700"><div class="min-w-0 flex-1"><h2 id="workspace-preview-heading" class="break-words text-lg font-bold text-slate-950 dark:text-white" x-text="previewDocument.filename"></h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400" x-text="previewDocument.source"></p></div><a class="workspace-secondary" :href="previewDocument.download_url" :download="previewDocument.generated_comment_response ? previewDocument.filename : null">Download</a><button type="button" class="workspace-secondary" @click="$dispatch('close'); closePreview()">Close</button></header>
                <template x-if="previewDocument.view_url && previewDocument.preview_kind === 'image'">
                    <div class="bg-slate-50 p-4 dark:bg-slate-950" data-workspace-image-preview><img x-show="!imageFailed" :src="previewDocument.view_url" :alt="previewDocument.note || previewDocument.title" class="mx-auto max-h-[75dvh] max-w-full object-contain" x-on:error="imageFailed = true"><p x-show="imageFailed" role="alert" class="p-5 text-base text-red-700 dark:text-red-300">The poster could not be loaded. Try opening it again or use Download.</p><p x-show="previewDocument.note" class="mt-3 text-center text-sm leading-6 text-slate-500 dark:text-slate-400" x-text="previewDocument.note"></p></div>
                </template>
                <template x-if="previewDocument.view_url && previewDocument.preview_kind !== 'image'">
                    <x-proposal-revision-pdf :configuration="[]" configuration-expression="{pdfUrl: previewDocument.view_url, annotations: [], canAnnotate: false, fitWidth: true}" loading-label="Loading document…" viewer-label="Project document preview" class="!h-[75dvh]" />
                </template>
                <p x-show="!previewDocument.view_url" class="p-8 text-base leading-7 text-slate-600 dark:text-slate-300">This document is available to download. Use Download to open the original file.</p>
            </section>
        </template>
    </x-modal>
</section>
