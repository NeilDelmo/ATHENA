<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ Auth::user()->isUsingWorkspace('research_head') ? route('topics.head-uploads.index', $topic) : route('topics.show', $topic) }}" class="text-xs font-bold text-red-600 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">&larr; {{ Auth::user()->isUsingWorkspace('research_head') ? 'Review and upload files' : 'Revision details' }}</a>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">PDF Revision Annotations</h2>
                <p class="mt-1 text-xs text-gray-500">{{ $file->label() }} · {{ $file->original_filename }} · Version {{ $version->version_number }}</p>
            </div>
            <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-xs font-black {{ $canAnnotate ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-700' }}">{{ $canAnnotate ? 'Annotation mode' : 'Read-only annotations' }}</span>
        </div>
    </x-slot>

    <div
        x-data="pdfAnnotationWorkspace"
        data-pdf-annotation-config='@json($annotationConfiguration)'
        class="mx-auto max-w-[1600px] space-y-5 px-4 py-6 sm:px-6 lg:px-8"
    >
        @unless ($canAnnotate)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-900">
                These comments are read-only. The submitted faculty PDF remains unchanged; the highlights are stored as ATHENA revision records.
            </div>
        @endunless

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canAnnotate)
                        <button type="button" @click="setMode('text')" :class="mode === 'text' ? 'bg-amber-500 text-amber-950' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-xs font-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">Select text</button>
                        <button type="button" @click="setMode('area')" :class="mode === 'area' ? 'bg-amber-500 text-amber-950' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-xs font-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">Draw area</button>
                    @endif
                    <p class="text-xs text-gray-500" x-text="modeInstruction"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="changeZoom(-0.15)" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 bg-white text-sm font-black text-gray-700 hover:bg-gray-100" aria-label="Zoom out">−</button>
                    <span class="min-w-[64px] text-center text-xs font-black text-gray-700" x-text="`${Math.round(scale * 100)}%`"></span>
                    <button type="button" @click="changeZoom(0.15)" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 bg-white text-sm font-black text-gray-700 hover:bg-gray-100" aria-label="Zoom in">+</button>
                </div>
            </div>

            <div class="grid min-h-[70vh] lg:grid-cols-[minmax(0,1fr)_360px]">
                <main class="min-w-0 bg-slate-200/70">
                    <div x-show="loading" class="flex min-h-[60vh] items-center justify-center p-8 text-center"><div><p class="text-sm font-black text-gray-800">Loading submitted PDF…</p><p class="mt-1 text-xs text-gray-500">Preparing selectable text and annotation layers.</p></div></div>
                    <div x-show="loadError" x-cloak class="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" x-text="loadError"></div>
                    <div x-ref="viewer" @mouseup="captureTextSelection" :class="mode === 'area' ? 'pdf-annotation-area-mode' : ''" class="pdf-annotation-viewer flex flex-col items-center gap-5 overflow-auto p-4 sm:p-6"></div>

                    <div x-ref="selectionToolbar" x-show="selectionToolbarVisible" x-cloak class="fixed z-50 flex -translate-x-1/2 gap-2 rounded-xl border border-amber-300 bg-white p-2 shadow-xl">
                        <button type="button" @click="beginTextComment" class="rounded-lg bg-amber-500 px-3 py-2 text-xs font-black text-amber-950 hover:bg-amber-400">Highlight &amp; comment</button>
                        <button type="button" @click="cancelPendingSelection" class="rounded-lg px-3 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    </div>
                </main>

                <aside class="border-t border-gray-200 bg-white lg:border-l lg:border-t-0">
                    <div class="space-y-5 p-4 sm:p-5 lg:sticky lg:top-4">
                        @if ($canAnnotate)
                            <section x-show="draftSelection" x-cloak class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div><p class="text-[10px] font-black uppercase tracking-wider text-amber-800">New revision comment</p><p class="mt-1 text-xs font-bold text-amber-950">Page <span x-text="draftSelection?.pageNumber"></span> · <span x-text="draftSelection?.type === 'text' ? 'Text highlight' : 'Area highlight'"></span></p></div>
                                    <button type="button" @click="cancelDraft" class="text-xs font-black text-amber-800">Cancel</button>
                                </div>
                                <blockquote x-show="draftSelection?.selectedText" class="mt-3 max-h-24 overflow-auto rounded-xl bg-white/80 p-3 text-xs leading-5 text-gray-700" x-text="draftSelection?.selectedText"></blockquote>
                                <label class="mt-3 block text-[11px] font-black text-amber-900">What should the faculty revise?
                                    <textarea x-ref="commentInput" x-model="draftComment" rows="4" maxlength="5000" class="mt-1 block w-full rounded-xl border-amber-300 text-sm" placeholder="Explain the required change clearly."></textarea>
                                </label>
                                <p x-show="saveError" class="mt-2 text-xs font-semibold text-red-700" x-text="saveError"></p>
                                <button type="button" @click="saveAnnotation" :disabled="saving || !draftComment.trim()" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-xs font-black text-amber-950 hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"><span x-text="saving ? 'Saving…' : 'Save highlight'"></span></button>
                            </section>
                        @endif

                        <section aria-labelledby="annotation-comments-heading">
                            <div class="flex items-center justify-between gap-3"><div><h3 id="annotation-comments-heading" class="text-sm font-black text-gray-900">Revision comments</h3><p class="mt-1 text-xs text-gray-500"><span x-text="annotations.length"></span> highlight(s) on this file</p></div></div>
                            <div class="mt-3 max-h-[44vh] space-y-3 overflow-auto pr-1">
                                <template x-for="annotation in annotations" :key="annotation.id">
                                    <article @click="jumpToAnnotation(annotation)" :class="selectedAnnotationId === annotation.id ? 'border-amber-400 ring-2 ring-amber-200' : 'border-gray-200'" class="cursor-pointer rounded-xl border bg-white p-3 transition hover:border-amber-300">
                                        <div class="flex items-start justify-between gap-3">
                                            <div><p class="text-[10px] font-black uppercase tracking-wider text-gray-500">Page <span x-text="annotation.pageNumber"></span> · <span x-text="annotation.type === 'text' ? 'Text' : 'Area'"></span></p><span :class="annotation.state === 'resolved' ? 'bg-green-100 text-green-800' : (annotation.state === 'requested' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')" class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase" x-text="annotationStateLabel(annotation)"></span></div>
                                            <button x-show="canAnnotate && annotation.state === 'draft'" type="button" @click.stop="deleteAnnotation(annotation)" class="text-[10px] font-black text-red-600 hover:text-red-700">Delete</button>
                                        </div>
                                        <blockquote x-show="annotation.selectedText" class="mt-2 line-clamp-3 rounded-lg bg-amber-50 px-2 py-1.5 text-[11px] italic leading-4 text-gray-600" x-text="annotation.selectedText"></blockquote>
                                        <p class="mt-2 whitespace-pre-line text-xs leading-5 text-gray-800" x-text="annotation.comment"></p>
                                        <p class="mt-2 text-[10px] text-gray-400"><span x-text="annotation.reviewer"></span> · <span x-text="annotation.createdAt"></span></p>
                                    </article>
                                </template>
                                <p x-show="annotations.length === 0" class="rounded-xl bg-gray-50 p-4 text-center text-xs text-gray-500">No highlighted revision comments yet.</p>
                            </div>
                        </section>

                        @if ($canAnnotate)
                            <section x-show="revisionCandidates.length > 0" x-cloak class="rounded-2xl border border-blue-200 bg-blue-50 p-4">
                                <h3 class="text-sm font-black text-blue-900">Send revision request</h3>
                                <p class="mt-1 text-xs leading-5 text-blue-800">This will publish every draft annotation on <span x-text="revisionCandidates.length"></span> paper(s) and require the faculty to replace those files.</p>
                                <div class="mt-3 space-y-2">
                                    <template x-for="candidate in revisionCandidates" :key="candidate.fileId">
                                        <div class="rounded-xl bg-white px-3 py-2 text-xs text-gray-700"><span class="font-black" x-text="candidate.label"></span><span class="ml-1 text-gray-500">· <span x-text="candidate.annotationCount"></span> comment(s)</span></div>
                                    </template>
                                </div>
                                <form :action="requestRevisionUrl" method="POST" class="mt-3 space-y-3" data-proposal-confirm data-confirm-title="Send highlighted revision request?" data-confirm-text="The faculty will see these annotations and must replace every listed paper." data-confirm-button="Send revision request" data-confirm-icon="question">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="revision_requested">
                                    <input type="hidden" name="redirect_to" value="topic">
                                    <template x-for="candidate in revisionCandidates" :key="`revision-${candidate.fileId}`">
                                        <span>
                                            <input type="hidden" name="revision_file_ids[]" :value="candidate.fileId">
                                            <input type="hidden" :name="`revision_file_notes[${candidate.fileId}]`" :value="`See ${candidate.annotationCount} highlighted revision comment(s) in ATHENA.`">
                                        </span>
                                    </template>
                                    <label class="block text-[11px] font-black text-blue-900">Overall message
                                        <textarea name="comment" rows="3" required maxlength="5000" class="mt-1 block w-full rounded-xl border-blue-300 text-xs" placeholder="Summarize what the faculty should address."></textarea>
                                    </label>
                                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-800">Request revision</button>
                                </form>
                            </section>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
