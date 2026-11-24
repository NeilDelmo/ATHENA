@props(['topic', 'library', 'floating' => true, 'inline' => false])

@php
    $categories = $library['categories'];
    $documents = $library['documents'];
@endphp

<div
    x-data="projectDocumentDrawer({ topicId: @js($topic->id), documents: @js($documents->map(fn (array $document) => \Illuminate\Support\Arr::only($document, ['key', 'filename', 'view_url', 'download_url']))->values()), inline: @js($inline), displayStorageKey: @js('athena-report-library:'.auth()->id()) })"
    data-workspace-sizing data-size="medium" :data-size="size"
    @open-project-documents.window="openDrawer($event.detail?.category || 'all')"
    @keydown.escape.window="if (open && !document.querySelector('[data-project-document-preview-content]')) closeDrawer()"
>
    @unless ($inline)
    <button
        x-ref="trigger"
        type="button"
        @click="openDrawer()"
        class="{{ $floating ? 'fixed top-[7.625rem] right-4 z-40 rounded-full shadow-lg shadow-red-950/20 sm:right-5' : 'rounded-xl whitespace-nowrap' }} inline-flex min-h-12 items-center gap-2 bg-red-700 px-3.5 py-2.5 text-base font-black text-white transition hover:bg-red-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 motion-reduce:transition-none"
        aria-label="Open project files"
        title="Open project files"
        @if ($floating) data-project-documents-floating-trigger @else data-project-documents-inline-trigger @endif
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" />
        </svg>
        <span class="{{ $floating ? 'hidden sm:inline' : '' }}">{{ $floating ? 'Folders' : 'Open project folders' }}</span>
        <span class="inline-flex min-w-5 items-center justify-center rounded-full bg-white/20 px-1.5 py-0.5 text-base tabular-nums">{{ $categories->count() }}</span>
    </button>
    @endunless

    <div x-cloak x-show="open" class="{{ $inline ? 'overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' : 'fixed inset-0 z-[70]' }}" @unless ($inline) role="presentation" @endunless data-project-document-drawer data-project-document-browser>
        @unless ($inline)
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
        @endunless

        <aside
            x-show="open"
            @unless ($inline)
            x-transition:enter="transition-transform duration-200 ease-out motion-reduce:transition-none"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform duration-150 ease-in motion-reduce:transition-none"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            @endunless
            class="{{ $inline ? 'flex w-full flex-col' : 'absolute inset-y-0 right-0 flex w-full max-w-4xl flex-col border-l border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-slate-900' }}"
            @unless ($inline)
            role="dialog"
            aria-modal="true"
            @endunless
            aria-labelledby="project-files-heading"
        >
            <header class="shrink-0 border-b border-slate-200 bg-white px-5 py-5 dark:border-slate-700 dark:bg-slate-900 sm:px-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-700 dark:bg-red-950/50 dark:text-red-300" aria-hidden="true">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 id="project-files-heading" class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Project folders</h2>
                            <p class="mt-2 max-w-[65ch] text-base leading-7 text-slate-600 dark:text-slate-300">{{ $categories->count() }} {{ \Illuminate\Support\Str::plural('folder', $categories->count()) }} · {{ $library['total'] }} {{ \Illuminate\Support\Str::plural('document', $library['total']) }}</p>
                        </div>
                    </div>
                    @unless ($inline)
                    <button x-ref="closeButton" type="button" @click="closeDrawer()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800" aria-label="Close project files">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                    @endunless
                </div>

                <nav class="mt-4 flex min-w-0 flex-wrap items-center gap-2" aria-label="Project file location">
                    <button type="button" class="workspace-secondary" :disabled="activeCategory === 'all'" @click="openCategory()" aria-label="Up to project folders">↑ Up</button>
                    <button type="button" class="workspace-secondary" @click="openCategory()">Project folders</button>
                    <span x-show="activeCategory !== 'all'" class="min-w-0 text-base text-slate-600 dark:text-slate-300" x-text="' / ' + ((@js($categories->pluck('label', 'key')))[activeCategory] || '')"></span>
                </nav>
                <x-report-library-view-controls class="mt-4" />
            </header>

            <div @class(['px-4 py-5 sm:px-6', 'min-h-0 flex-1 overflow-y-auto overscroll-contain' => ! $inline])>
                @if ($documents->isEmpty())
                    <div x-ref="browserHeading" data-library-view-surface tabindex="0" aria-label="Project folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300" aria-hidden="true">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75A2.25 2.25 0 0 1 6 4.5h3.19c.597 0 1.17.237 1.591.659l1.06 1.06c.422.422.994.659 1.591.659H18A2.25 2.25 0 0 1 20.25 9.13v7.62A2.25 2.25 0 0 1 18 19H6a2.25 2.25 0 0 1-2.25-2.25v-10Z" /></svg>
                        </span>
                        <p class="mt-4 text-base font-black text-gray-900 dark:text-white">No project files yet</p>
                        <p class="mt-1 text-base leading-5 text-gray-500 dark:text-gray-400">Generated and released documents and terminal report posters will appear here automatically.</p>
                    </div>
                @else
                    <svg class="hidden" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <symbol id="project-files-folder-{{ $topic->id }}" viewBox="0 0 80 64">
                                <path d="M5 13a5 5 0 0 1 5-5h20l7 7h33a5 5 0 0 1 5 5v32a5 5 0 0 1-5 5H10a5 5 0 0 1-5-5Z" fill="#D99417" />
                                <path d="M9 23h62v28H9Z" fill="#FFF4C7" />
                                <path d="M4 25a4 4 0 0 1 4-4h64a4 4 0 0 1 4 4l-4 29a4 4 0 0 1-4 4H12a4 4 0 0 1-4-4Z" fill="#F5C451" stroke="#D99417" stroke-width="1.5" />
                                <path d="M9 27h61" stroke="#FFE8A0" stroke-width="2" />
                            </symbol>
                            <symbol id="project-files-file-{{ $topic->id }}" viewBox="0 0 56 64">
                                <path d="M8 2h27l13 13v45a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z" fill="#FFF" stroke="#94A3B8" stroke-width="1.5" />
                                <path d="M35 2v13h13M15 24h25M15 30h25M15 36h18" fill="none" stroke="#CBD5E1" stroke-width="2" />
                            </symbol>
                        </defs>
                    </svg>
                    <div x-ref="browserHeading" data-library-view-surface tabindex="0" aria-label="Project folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="outline-none">
                        <div x-show="activeCategory === 'all'" :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'" aria-label="Project file categories">
                            @foreach ($categories as $category)
                                <button type="button" data-project-file-category="{{ $category['key'] }}" aria-label="Select folder {{ $category['label'] }}" :aria-pressed="selectedItem === 'category:{{ $category['key'] }}'" class="workspace-item" :class="selectedItem === 'category:{{ $category['key'] }}' && 'workspace-selected'" @click="selectItem('category:{{ $category['key'] }}')" @dblclick="openCategory('{{ $category['key'] }}')" @keydown.enter.prevent="openCategory('{{ $category['key'] }}')">
                                    <svg class="workspace-item-graphic" aria-hidden="true"><use href="#project-files-folder-{{ $topic->id }}" /></svg>
                                    <span class="workspace-item-name">{{ $category['label'] }}</span>
                                    <span class="workspace-item-meta">{{ $category['count'] }} {{ \Illuminate\Support\Str::plural('file', $category['count']) }}</span>
                                </button>
                            @endforeach
                        </div>
                        @foreach ($categories as $category)
                            <section x-show="activeCategory === '{{ $category['key'] }}'" aria-labelledby="project-file-category-{{ $category['key'] }}">
                                <h3 id="project-file-category-{{ $category['key'] }}" class="text-lg font-semibold text-slate-950 dark:text-white">{{ $category['label'] }}</h3>
                                <p class="mb-4 mt-1 text-base text-slate-500 dark:text-slate-400">{{ $category['description'] }}</p>
                                <div :class="view === 'grid' ? 'workspace-items-grid' : 'workspace-items-list'">
                                    @foreach ($documents->where('category', $category['key']) as $document)
                                        @php($isDocx = strtolower(pathinfo($document['filename'], PATHINFO_EXTENSION)) === 'docx')
                                        <article x-data="{ thumbnailFailed: false }" data-project-document-key="{{ $document['key'] }}" role="button" tabindex="0" aria-label="Select file {{ $document['title'] }}" :aria-pressed="selectedItem === '{{ $document['key'] }}'" class="workspace-item" :class="selectedItem === '{{ $document['key'] }}' && 'workspace-selected'" @click="selectItem('{{ $document['key'] }}')" @keydown.space.self.prevent="selectItem('{{ $document['key'] }}')"
                                            @if ($document['view_url'])
                                                @dblclick="$dispatch('open-modal', 'project-{{ $topic->id }}-{{ $document['key'] }}')" @keydown.enter.self.prevent="$dispatch('open-modal', 'project-{{ $topic->id }}-{{ $document['key'] }}')"
                                            @else
                                                @dblclick="window.location.assign(@js($document['download_url']))" @keydown.enter.self.prevent="window.location.assign(@js($document['download_url']))"
                                            @endif
                                        >
                                            @if (($document['preview_kind'] ?? null) === 'image')
                                                <span x-show="!thumbnailFailed" class="workspace-image-graphic"><img src="{{ $document['thumbnail_url'] }}" alt="" loading="lazy" draggable="false" x-on:error="thumbnailFailed = true"></span>
                                                <span x-show="thumbnailFailed" class="workspace-file-graphic"><svg aria-hidden="true"><use href="#project-files-file-{{ $topic->id }}" /></svg><span class="workspace-file-type">IMG</span></span>
                                            @else
                                                <span class="workspace-file-graphic"><svg aria-hidden="true"><use href="#project-files-file-{{ $topic->id }}" /></svg><span class="workspace-file-type {{ $isDocx ? '!bg-blue-700' : '' }}">{{ $isDocx ? 'DOCX' : 'PDF' }}</span></span>
                                            @endif
                                            <h4 class="workspace-item-name" title="{{ $document['filename'] }}">{{ $document['filename'] }}</h4>
                                            <span class="workspace-item-meta">{{ $document['title'] }}</span>
                                            <span class="workspace-item-meta">{{ $document['category'] === \App\Models\ProjectDocument::CATEGORY_SIGNED_PAPERS ? 'Signed' : ($document['official'] ? 'Official' : 'Project file') }}</span>
                                            @if ($document['generated_comment_response'] ?? false)
                                                <span class="col-start-2 text-base font-medium text-red-700 dark:text-red-300">{{ $document['draft'] ? 'Draft · Research Head only' : 'Generated' }}</span>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                        @foreach ($categories as $category)
                            <section x-show="selectedItem === 'category:{{ $category['key'] }}'" class="mt-5 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
                                <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $category['label'] }}</h3>
                                <p class="text-base text-slate-600 dark:text-slate-300">{{ $category['description'] }}</p>
                                <button type="button" class="workspace-primary" @click="openCategory('{{ $category['key'] }}')">Open folder</button>
                            </section>
                        @endforeach
                        @foreach ($documents as $document)
                            <section x-show="selectedItem === '{{ $document['key'] }}'" data-project-file-selection="{{ $document['key'] }}" class="mt-5 min-w-0 space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left dark:border-slate-700 dark:bg-slate-950">
                                <h3 class="break-words text-base font-semibold text-slate-950 dark:text-white">{{ $document['filename'] }}</h3>
                                <p class="text-base text-slate-700 dark:text-slate-200">{{ $document['title'] }}</p>
                                <p class="text-base leading-6 text-slate-500 dark:text-slate-400">{{ $document['source'] }} @if ($document['uploaded_by']) · {{ $document['uploaded_by'] }} @endif @if ($document['uploaded_at']) · {{ $document['uploaded_at']->format(($document['generated_comment_response'] ?? false) ? 'M j, Y g:i A' : 'M j, Y') }} @endif @if ($document['file_size']) · {{ \Illuminate\Support\Number::fileSize($document['file_size']) }} @endif</p>
                                @if ($document['note']) <p class="text-base leading-6 text-slate-600 dark:text-slate-300">{{ $document['note'] }}</p> @endif
                                <div class="flex flex-wrap gap-2">
                                    @if ($document['view_url'])
                                        <button type="button" data-project-document-preview-button aria-haspopup="dialog" @click="$dispatch('open-modal', 'project-{{ $topic->id }}-{{ $document['key'] }}')" class="workspace-secondary">Preview</button>
                                    @endif
                                    @if ($document['editable_docx_url'] ?? null)
                                        <a href="{{ $document['editable_docx_url'] }}" class="workspace-secondary">Editable DOCX</a>
                                    @endif
                                    <a href="{{ $document['download_url'] }}" @if ($document['generated_comment_response'] ?? false) download="{{ $document['filename'] }}" @endif class="workspace-primary">Download</a>
                                </div>
                            </section>
                        @endforeach
                        <p class="mt-5 text-base leading-6 text-slate-500 dark:text-slate-400">Click to select. Double-click or press Enter to open a folder or document.</p>
                    </div>
                @endif
            </div>
        </aside>
    </div>
    @foreach ($documents->whereNotNull('view_url') as $document)
        <x-modal name="project-{{ $topic->id }}-{{ $document['key'] }}" maxWidth="4xl" focusable class="!z-[140]" data-project-document-preview-modal>
            <template x-if="show">
                <section data-project-document-preview-content role="dialog" aria-modal="true" aria-labelledby="project-{{ $topic->id }}-{{ $document['key'] }}-heading">
                    <header class="flex items-center justify-between gap-4 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <h3 id="project-{{ $topic->id }}-{{ $document['key'] }}-heading" class="break-words text-lg font-bold text-gray-950 dark:text-white">{{ $document['title'] }}</h3>
                            <p class="mt-1 break-words text-base text-gray-700 dark:text-gray-200">{{ $document['filename'] }}</p>
                            <p class="mt-1 text-base text-gray-600 dark:text-gray-300">{{ $document['source'] }}</p>
                        </div>
                        <button type="button" @click="$dispatch('close')" class="inline-flex min-h-11 shrink-0 items-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-base font-semibold text-gray-800 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:hover:bg-gray-800">Close</button>
                    </header>
                    @if (($document['preview_kind'] ?? null) === 'image')
                        <div x-data="{ imageFailed: false }" class="flex min-h-64 items-center justify-center bg-gray-100 p-4 dark:bg-gray-950">
                            <img x-show="!imageFailed" x-on:error="imageFailed = true" src="{{ $document['view_url'] }}" alt="{{ $document['note'] ?: $document['title'] }}" class="max-h-[75dvh] max-w-full object-contain" data-project-document-image-preview>
                            <p x-show="imageFailed" role="alert" class="text-base text-slate-600 dark:text-slate-300">The image could not be loaded. Close the preview and use Download to open the original file.</p>
                        </div>
                        @if ($document['note']) <p class="border-t border-slate-200 px-4 py-3 text-base text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $document['note'] }}</p> @endif
                    @else
                        <x-proposal-revision-pdf :configuration="['pdfUrl' => $document['view_url'], 'annotations' => [], 'canAnnotate' => false]" loading-label="Loading PDF…" :viewer-label="$document['title']" class="!h-[75dvh]" />
                    @endif
                </section>
            </template>
        </x-modal>
    @endforeach
</div>
