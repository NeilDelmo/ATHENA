@props(['topic', 'library'])

@php
    $projectDocumentErrors = $errors->getBag('projectDocuments');
    $initialOpen = (bool) session('project_documents_open') || $projectDocumentErrors->any();
    $categories = $library['categories'];
    $documents = $library['documents'];
    $canUpload = $library['canUpload'];
@endphp

<div
    x-data="projectDocumentDrawer({ initialOpen: @js($initialOpen), uploadOpen: @js($projectDocumentErrors->any()) })"
    @open-project-documents.window="openDrawer()"
    @keydown.escape.window="if (open) closeDrawer()"
>
    <button
        x-ref="trigger"
        type="button"
        @click="openDrawer()"
        class="fixed bottom-5 right-4 z-40 inline-flex min-h-12 items-center gap-2 rounded-full bg-red-700 px-3.5 py-2.5 text-sm font-black text-white shadow-lg shadow-red-950/20 transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 motion-reduce:transition-none sm:bottom-auto sm:right-5 sm:top-28"
        aria-label="Open project files"
        title="Open project files"
        data-project-documents-floating-trigger
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" />
        </svg>
        <span class="hidden sm:inline">Files</span>
        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-white/20 px-1.5 py-0.5 text-[10px] tabular-nums">{{ $library['total'] }}</span>
    </button>

    <div x-cloak x-show="open" class="fixed inset-0 z-[70]" role="presentation" data-project-document-drawer>
        <div
            x-show="open"
            x-transition:enter="transition-opacity duration-200 ease-out motion-reduce:transition-none"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-150 ease-in motion-reduce:transition-none"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-gray-950/45 backdrop-blur-[1px]"
            @click="closeDrawer()"
            aria-hidden="true"
        ></div>

        <aside
            x-show="open"
            x-transition:enter="transition-transform duration-200 ease-out motion-reduce:transition-none"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform duration-150 ease-in motion-reduce:transition-none"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="absolute inset-y-0 right-0 flex w-full max-w-xl flex-col border-l border-gray-200 bg-gray-50 shadow-2xl dark:border-gray-800 dark:bg-gray-950"
            role="dialog"
            aria-modal="true"
            aria-labelledby="project-files-heading"
        >
            <header class="shrink-0 border-b border-gray-200 bg-white px-5 py-5 dark:border-gray-800 dark:bg-gray-950 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-black uppercase tracking-[0.16em] text-red-600 dark:text-red-400">Project document folder</p>
                            <h2 id="project-files-heading" class="mt-1 truncate text-xl font-black tracking-tight text-gray-950 dark:text-white">Project files</h2>
                            <p class="mt-1 text-sm leading-5 text-gray-500 dark:text-gray-400">{{ $library['total'] }} {{ \Illuminate\Support\Str::plural('file', $library['total']) }} collected across this project.</p>
                        </div>
                    </div>
                    <button x-ref="closeButton" type="button" @click="closeDrawer()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Close project files">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                @if ($categories->isNotEmpty())
                    <div class="mt-4 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Project file categories">
                        <button type="button" @click="activeCategory = 'all'" :class="activeCategory === 'all' ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-transparent px-3 py-1.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">All <span class="tabular-nums opacity-70">{{ $library['total'] }}</span></button>
                        @foreach ($categories as $category)
                            <button type="button" @click="activeCategory = '{{ $category['key'] }}'" :class="activeCategory === '{{ $category['key'] }}' ? 'bg-gray-950 text-white dark:bg-white dark:text-gray-950' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'" class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-transparent px-3 py-1.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700">{{ $category['label'] }} <span class="tabular-nums opacity-70">{{ $category['count'] }}</span></button>
                        @endforeach
                    </div>
                @endif
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6">
                @if ($canUpload)
                    <section class="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900" aria-labelledby="add-project-files-heading">
                        <button type="button" @click="uploadOpen = !uploadOpen" :aria-expanded="uploadOpen" aria-controls="project-file-upload" class="flex min-h-14 w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-700 dark:hover:bg-gray-800 sm:px-5">
                            <span>
                                <span id="add-project-files-heading" class="block text-sm font-black text-gray-950 dark:text-white">Add PDF files</span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">For corrected forms, supporting papers, and project records.</span>
                            </span>
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                                <svg :class="uploadOpen ? 'rotate-45' : ''" class="h-4 w-4 transition-transform motion-reduce:transition-none" fill="none" stroke="currentColor" stroke-width="2.25" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                            </span>
                        </button>

                        <form id="project-file-upload" x-show="uploadOpen" x-cloak action="{{ route('topics.documents.store', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-4 border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                            @csrf
                            <div>
                                <label for="project_document_category" class="text-xs font-bold text-gray-700 dark:text-gray-200">Category</label>
                                <select id="project_document_category" name="category" required class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                                    @foreach ($library['uploadCategories'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category', \App\Models\ProjectDocument::CATEGORY_SUPPORTING_FILES) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('category', 'projectDocuments')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <label
                                class="block cursor-pointer rounded-2xl border-2 border-dashed p-5 text-center transition focus-within:ring-2 focus-within:ring-red-700 focus-within:ring-offset-2"
                                :class="isDragging ? 'border-red-600 bg-red-50 dark:bg-red-950/30' : 'border-gray-300 bg-gray-50 hover:border-red-300 hover:bg-red-50/40 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-red-800'"
                                @dragenter.prevent="isDragging = true"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="dropFiles($event)"
                            >
                                <input x-ref="fileInput" type="file" name="documents[]" accept="application/pdf,.pdf" multiple required class="sr-only" @change="chooseFiles($event)">
                                <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-red-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-red-300 dark:ring-gray-700" aria-hidden="true">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V6.75m0 0-3.75 3.75M12 6.75l3.75 3.75M6.75 18.75h10.5A2.25 2.25 0 0 0 19.5 16.5v-9a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                </span>
                                <span class="mt-3 block text-sm font-black text-gray-900 dark:text-white">Drop PDFs here or browse</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Up to 10 PDFs, 25 MB each</span>
                            </label>
                            @error('documents', 'projectDocuments')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            @error('documents.*', 'projectDocuments')<p class="text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            <p x-show="fileError" x-text="fileError" class="text-xs font-semibold text-red-600" role="alert"></p>

                            <div x-show="selectedFiles.length > 0" x-cloak class="space-y-2">
                                <template x-for="(file, index) in selectedFiles" :key="`${file.name}-${file.size}-${index}`">
                                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-950">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-50 text-[10px] font-black text-red-700 dark:bg-red-950/50 dark:text-red-300">PDF</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-xs font-bold text-gray-900 dark:text-white" x-text="file.name"></span>
                                            <span class="block text-[11px] text-gray-500" x-text="formatSize(file.size)"></span>
                                        </span>
                                        <button type="button" @click="removeFile(index)" class="rounded-lg p-2 text-gray-400 transition hover:bg-white hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:hover:bg-gray-800" :aria-label="`Remove ${file.name}`">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            <div>
                                <label for="project_document_note" class="text-xs font-bold text-gray-700 dark:text-gray-200">Note <span class="font-normal text-gray-400">(optional)</span></label>
                                <textarea id="project_document_note" name="note" rows="2" maxlength="1000" class="mt-1.5 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Why these files are being kept with the project">{{ old('note') }}</textarea>
                                @error('note', 'projectDocuments')<p class="mt-1 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <button type="submit" :disabled="selectedFiles.length === 0" class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300 dark:disabled:bg-gray-700" x-text="uploadButtonLabel()"></button>
                        </form>
                    </section>
                @endif

                @if ($documents->isEmpty())
                    <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <p class="mt-4 text-sm font-black text-gray-900 dark:text-white">No project PDFs yet</p>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Generated and released PDFs will appear here automatically.</p>
                    </div>
                @else
                    <div class="space-y-6">
                        @foreach ($categories as $category)
                            <section x-show="activeCategory === 'all' || activeCategory === '{{ $category['key'] }}'" aria-labelledby="project-file-category-{{ $category['key'] }}">
                                <div class="mb-2 flex items-end justify-between gap-3 px-1">
                                    <div>
                                        <h3 id="project-file-category-{{ $category['key'] }}" class="text-sm font-black text-gray-950 dark:text-white">{{ $category['label'] }}</h3>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $category['description'] }}</p>
                                    </div>
                                    <span class="shrink-0 text-xs font-bold tabular-nums text-gray-400">{{ $category['count'] }}</span>
                                </div>

                                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($documents->where('category', $category['key']) as $document)
                                            <article class="p-4 sm:p-5">
                                                <div class="flex items-start gap-3">
                                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-[10px] font-black text-red-700 dark:bg-red-950/50 dark:text-red-300">PDF</span>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h4 class="min-w-0 break-words text-sm font-black leading-5 text-gray-950 dark:text-white">{{ $document['title'] }}</h4>
                                                            @if ($document['official'])
                                                                <span class="rounded-full bg-gray-950 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white dark:bg-white dark:text-gray-950">Official</span>
                                                            @endif
                                                        </div>
                                                        <p class="mt-1 truncate text-xs font-semibold text-gray-500 dark:text-gray-400" title="{{ $document['filename'] }}">{{ $document['filename'] }}</p>
                                                        <p class="mt-1 text-[11px] leading-4 text-gray-400">
                                                            {{ $document['source'] }}
                                                            @if ($document['uploaded_by']) &middot; {{ $document['uploaded_by'] }} @endif
                                                            @if ($document['uploaded_at']) &middot; {{ $document['uploaded_at']->format('M j, Y') }} @endif
                                                            @if ($document['file_size']) &middot; {{ \Illuminate\Support\Number::fileSize($document['file_size']) }} @endif
                                                        </p>
                                                        @if ($document['note'])
                                                            <p class="mt-2 rounded-lg bg-gray-50 px-3 py-2 text-xs leading-5 text-gray-600 dark:bg-gray-950 dark:text-gray-300">{{ $document['note'] }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="mt-3 flex justify-end gap-2">
                                                    @if ($document['view_url'])
                                                        <a href="{{ $document['view_url'] }}" target="_blank" rel="noopener" class="inline-flex min-h-10 items-center justify-center rounded-xl border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-gray-800">View</a>
                                                    @endif
                                                    <a href="{{ $document['download_url'] }}" class="inline-flex min-h-10 items-center justify-center rounded-xl bg-gray-950 px-3 py-2 text-xs font-bold text-white transition hover:bg-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-900 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">Download</a>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
